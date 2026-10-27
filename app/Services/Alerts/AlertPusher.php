<?php

namespace App\Services\Alerts;

use App\Models\User;
use App\Services\Push;
use App\Services\RolePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The app's alerts as Firebase notifications (php artisan alerts:push, every few minutes): for each user signed in
 * on a phone, the same lists the app asks for (as that user: same rights, same data), the same rules (AlertRules),
 * and each alert not sent yet goes to the phone once (alert_pushes keeps what was sent). A user's first time: what
 * is already there is only noted, so the phone is not flooded. A few at a time; more: one «N تنبيهات جديدة».
 */
class AlertPusher
{
    /** At most this many notifications per user per run (the others in one) */
    private const PER_RUN = 4;

    public static function run(): int
    {
        $sent = 0;
        User::whereNotNull('fcm_token')->where('fcm_token', '!=', '')->with('role')->get()
            ->each(function (User $user) use (&$sent) {
                try {
                    $sent += self::forUser($user);
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        // What was sent long ago (alerts that ended): forgotten
        DB::table('alert_pushes')->where('created_at', '<', now()->subDays(60))->delete();

        return $sent;
    }

    public static function forUser(User $user): int
    {
        $keys = DB::table('alert_pushes')->where('user_id', $user->id)->pluck('key')->flip();
        // The user's first run: marked, so from then on every new alert is sent
        $first = $keys->isEmpty();
        $fresh = array_values(array_filter(self::alerts($user), fn ($a) => !isset($keys[$a['key']])));
        $now = now();
        DB::table('alert_pushes')->insertOrIgnore(array_map(
            fn ($key) => ['user_id' => $user->id, 'key' => $key, 'created_at' => $now],
            [...($first ? ['__start__'] : []), ...array_column($fresh, 'key')],
        ));
        if (!$fresh) {
            return 0;
        }
        if ($first) {
            return 0;
        }

        $shown = array_slice($fresh, 0, self::PER_RUN);
        foreach ($shown as $a) {
            Push::send($user->id, $a['title'], $a['heading'] . ($a['detail'] ? ' · ' . $a['detail'] : ''), ['link' => $a['link'], 'space' => $a['space']]);
        }
        $more = count($fresh) - count($shown);
        if ($more > 0) {
            Push::send($user->id, 'تنبيهات جديدة', "و{$more} تنبيهات أخرى في التطبيق", ['link' => '/']);
        }

        return count($shown) + ($more > 0 ? 1 : 0);
    }

    /** The user's alerts now, by the app's rules and rights */
    public static function alerts(User $user): array
    {
        $can = fn (string $module, string $action = 'view') => RolePermissions::can($user, $module, $action);
        $manage = self::canManage($user);
        $get = fn (string $path) => self::fetch($user, $path);
        $list = function ($res, ?string $field = null) {
            if (!is_array($res)) {
                return [];
            }
            $v = $field ? ($res[$field] ?? null) : $res;
            if ($field === null && isset($res['data']) && is_array($res['data']) && array_is_list($res['data'])) {
                $v = $res['data'];
            }

            return is_array($v) && array_is_list($v) ? $v : [];
        };
        $r = new AlertRules();
        $me = $user->id;

        $matchCan = ['callups' => $can('matches', 'callups'), 'lineup' => $can('matches', 'lineup'), 'attendance' => $can('matches', 'attendance'), 'report' => $can('matches', 'report')];
        $managesMatches = in_array(true, $matchCan, true);
        $meetCan = ['meetings' => $can('meetings'), 'attendance' => $can('meetings', 'attendance'), 'addDecisions' => $can('decisions', 'add'), 'decisions' => $can('decisions')];

        $myMeetings = $meetCan['meetings'] || $meetCan['decisions'] ? $list($get('/meetings/mine')) : [];
        $myTravels = $can('travels') ? $list($get('/travels/mine'), 'data') : [];

        // Each section on its own: one that fails (unexpected data) does not stop the others
        $safe = function (callable $section) {
            try {
                return $section();
            } catch (\Throwable $e) {
                report($e);

                return [];
            }
        };

        return array_merge(
            $safe(fn () => $can('disciplinary') ? $r->disciplinary($list($get('/disciplinary/mine'), 'data')) : []),
            $safe(fn () => $manage && $can('disciplinary') ? $r->managerDisciplinary($list($get('/disciplinary'))) : []),
            $safe(fn () => $manage ? $r->officerHearings($list($get('/disciplinary/officiating'), 'data')) : []),
            $safe(fn () => $r->myTasks($list($get('/tasks/mine'), 'data'))),
            $safe(fn () => $manage && $can('tasks') ? $r->managerTasks($list($get('/tasks'), 'data'), $me) : []),
            $safe(fn () => $r->feedback($list($get('/feedback/mine'), 'data'))),
            $safe(fn () => $manage && $can('feedback') ? $r->managerFeedback($list($get('/feedback'), 'data')) : []),
            $safe(fn () => $manage && $can('debts') ? $r->debts($list($get('/debts'), 'data')) : []),
            $safe(fn () => $manage && $can('payments') ? $r->debts($list($get('/payments/credit'), 'data')) : []),
            $safe(fn () => $can('medical') ? $r->medical($list($get('/medical-records/mine'), 'data')) : []),
            $safe(fn () => $manage && $can('medical') ? $r->managerMedical($list($get('/medical-records'), 'data')) : []),
            $safe(fn () => $can('medical') ? $r->medicalNotices($list($get('/medical-records/mine/notices'))) : []),
            $safe(fn () => $can('travels') ? $r->travelNotices($list($get('/travels/mine/notices'))) : []),
            $safe(fn () => $r->travels($myTravels)),
            $safe(fn () => $manage && $can('travels') ? $r->managerTravels($list($get('/travels'), 'data'), array_column($myTravels, 'id')) : []),
            $safe(fn () => $meetCan['meetings'] ? $r->meetingNotices($list($get('/meetings/mine/notices'))) : []),
            $safe(fn () => $r->meetings($myMeetings, $meetCan['meetings'], $meetCan['decisions'])),
            $safe(fn () => $manage && ($meetCan['meetings'] || $meetCan['attendance'] || $meetCan['addDecisions'] || $meetCan['decisions'])
                ? $r->managerMeetings(
                    $meetCan['meetings'] || $meetCan['attendance'] || $meetCan['addDecisions'] ? $list($get('/meetings')) : [],
                    $meetCan['decisions'] || $meetCan['addDecisions'] ? $list($get('/decisions')) : [],
                    $meetCan, $me, array_column($myMeetings, 'id'),
                ) : []),
            $safe(fn () => $can('absences') ? $r->absences($list($get('/absences/mine'))) : []),
            $safe(fn () => $manage && $can('absences') ? $r->managerAbsences($list($get('/absences')), $can('absences', 'justify')) : []),
            $safe(fn () => $can('matches') ? $r->matchNotices($list($get('/matches/mine/notices'))) : []),
            $safe(fn () => $can('matches') ? $r->matches($list($get('/matches/mine'), 'data'), 'personal') : []),
            $safe(fn () => $manage && $managesMatches ? $r->matches($list($get('/matches'), 'data'), 'management', $matchCan) : []),
            $safe(fn () => $can('trainingSessions') ? $r->trainingNotices($list($get('/training-sessions/mine/notices'))) : []),
            $safe(fn () => $can('trainingSessions') ? $r->training($list($get('/training-sessions/mine')), 'personal') : []),
            $safe(fn () => $manage && $can('trainingSessions', 'attendance') ? $r->training($list($get('/training-sessions')), 'management', true) : []),
        );
    }

    /** Has the management space: not a player, and a full role or one that sees some section */
    private static function canManage(User $user): bool
    {
        if (($user->individual?->type ?? null) === 'player' || !$user->role) {
            return false;
        }
        if (strtolower((string) $user->role->type) === 'full') {
            return true;
        }
        $permissions = is_string($user->role->permissions) ? json_decode($user->role->permissions, true) : (array) $user->role->permissions;

        return is_array($permissions) && collect($permissions)->contains(fn ($p) => is_array($p) && ($p['view'] ?? false) === true);
    }

    /** One of the app's lists, asked as this user (the same route, rights and data) */
    private static function fetch(User $user, string $path): mixed
    {
        $previous = app()->bound('request') ? app('request') : null;
        try {
            Auth::forgetGuards();
            Auth::guard('web')->setUser($user);
            $sub = Request::create('/api' . $path, 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
            $sub->setUserResolver(fn () => $user);
            $response = app('router')->dispatch($sub);

            return $response->getStatusCode() === 200 ? json_decode($response->getContent(), true) : null;
        } catch (\Throwable) {
            return null;
        } finally {
            if ($previous) {
                app()->instance('request', $previous);
            }
        }
    }
}
