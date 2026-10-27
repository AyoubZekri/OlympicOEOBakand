<?php

namespace App\Services\Alerts;

use App\Models\User;
use App\Services\Push;
use App\Services\RolePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

    /** What a save touched (its API path's first part) → the alerts' sections to look at again */
    private const SECTIONS = [
        'absences' => ['absences'],
        'disciplinary' => ['disciplinary'],
        'tasks' => ['tasks'],
        'feedback' => ['feedback'],
        'debts' => ['debts'],
        'payments' => ['debts'],
        'medical-records' => ['medical'],
        'travels' => ['travels'],
        'meetings' => ['meetings', 'absences'],
        'decisions' => ['meetings'],
        'matches' => ['matches', 'absences'],
        'match-callups' => ['matches'],
        'training-sessions' => ['training', 'absences'],
        'training-attendance' => ['training', 'absences'],
        'match-attendance' => ['matches', 'absences'],
    ];

    /** Saves that tell their own news (a chat message, a phone's token), or nothing anyone is alerted of */
    private const QUIET = ['me/push-token', 'tasks/messages/send', 'tasks/seen'];

    /**
     * A save went through: the alerts it causes (an absence logged, a status changed, a reply…) are sent right after
     * its answer — the save itself does not wait — and only for what it touched (an absence: the absences' alerts),
     * so it is quick. The cron sends what the clock brings (near, late…).
     */
    public static function afterSave(Request $request): void
    {
        if (!config('services.fcm.after_save', true)) {
            return;
        }
        $path = (string) preg_replace('#^api/#', '', $request->path());
        if (in_array($path, self::QUIET, true)) {
            return;
        }
        $sections = self::SECTIONS[explode('/', $path)[0]] ?? [];
        if (!$sections) {
            return;
        }
        defer(fn () => self::run('save: ' . implode(',', $sections), $sections));
    }

    /** Every phone user's new alerts, sent now (its time and count in the log: storage/logs/laravel.log) */
    /** $only: these sections only (a save's); none: all of them (the cron) */
    public static function run(string $why = 'cron', ?array $only = null): int
    {
        $started = microtime(true);
        $sent = 0;
        $users = User::whereNotNull('fcm_token')->where('fcm_token', '!=', '')->with('role')->get();
        // Sent here and now: this may already run after a request's answer, where a new deferral would never run
        Push::$immediate = true;
        try {
            $users->each(function (User $user) use (&$sent, $only) {
                try {
                    $sent += self::forUser($user, $only);
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        } finally {
            Push::$immediate = false;
        }
        // What was sent long ago (alerts that ended): forgotten
        DB::table('alert_pushes')->where('created_at', '<', now()->subDays(60))->delete();
        Log::info("alerts:push ({$why}): {$users->count()} phones, {$sent} sent, " . round(microtime(true) - $started, 1) . 's');

        return $sent;
    }

    public static function forUser(User $user, ?array $only = null): int
    {
        $keys = DB::table('alert_pushes')->where('user_id', $user->id)->pluck('key')->flip();
        // The user's first run: marked, so from then on every new alert is sent
        $first = $keys->isEmpty();
        $fresh = array_values(array_filter(self::alerts($user, $only), fn ($a) => !isset($keys[$a['key']])));
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

    /** The user's alerts now, by the app's rules and rights ($only: these sections only) */
    public static function alerts(User $user, ?array $only = null): array
    {
        $wants = fn (string $section) => $only === null || in_array($section, $only, true);
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

        $myMeetings = $wants('meetings') && ($meetCan['meetings'] || $meetCan['decisions']) ? $list($get('/meetings/mine')) : [];
        $myTravels = $wants('travels') && $can('travels') ? $list($get('/travels/mine'), 'data') : [];

        // Each section on its own: one that fails (unexpected data) does not stop the others
        $safe = function (string $name, callable $section) use ($wants) {
            if (!$wants($name)) {
                return [];
            }
            try {
                return $section();
            } catch (\Throwable $e) {
                report($e);

                return [];
            }
        };

        return array_merge(
            $safe('disciplinary', fn () => $can('disciplinary') ? $r->disciplinary($list($get('/disciplinary/mine'), 'data')) : []),
            $safe('disciplinary', fn () => $manage && $can('disciplinary') ? $r->managerDisciplinary($list($get('/disciplinary'))) : []),
            $safe('disciplinary', fn () => $manage ? $r->officerHearings($list($get('/disciplinary/officiating'), 'data')) : []),
            $safe('tasks', fn () => $r->myTasks($list($get('/tasks/mine'), 'data'))),
            $safe('tasks', fn () => $manage && $can('tasks') ? $r->managerTasks($list($get('/tasks'), 'data'), $me, $user->name) : []),
            $safe('feedback', fn () => $r->feedback($list($get('/feedback/mine'), 'data'))),
            $safe('feedback', fn () => $manage && $can('feedback') ? $r->managerFeedback($list($get('/feedback'), 'data')) : []),
            $safe('debts', fn () => $manage && $can('debts') ? $r->debts($list($get('/debts'), 'data')) : []),
            $safe('debts', fn () => $manage && $can('payments') ? $r->debts($list($get('/payments/credit'), 'data')) : []),
            $safe('medical', fn () => $can('medical') ? $r->medical($list($get('/medical-records/mine'), 'data')) : []),
            $safe('medical', fn () => $manage && $can('medical') ? $r->managerMedical($list($get('/medical-records'), 'data')) : []),
            $safe('medical', fn () => $can('medical') ? $r->medicalNotices($list($get('/medical-records/mine/notices'))) : []),
            $safe('travels', fn () => $can('travels') ? $r->travelNotices($list($get('/travels/mine/notices'))) : []),
            $safe('travels', fn () => $r->travels($myTravels)),
            $safe('travels', fn () => $manage && $can('travels') ? $r->managerTravels($list($get('/travels'), 'data'), array_column($myTravels, 'id')) : []),
            $safe('meetings', fn () => $meetCan['meetings'] ? $r->meetingNotices($list($get('/meetings/mine/notices'))) : []),
            $safe('meetings', fn () => $r->meetings($myMeetings, $meetCan['meetings'], $meetCan['decisions'])),
            $safe('meetings', fn () => $manage && ($meetCan['meetings'] || $meetCan['attendance'] || $meetCan['addDecisions'] || $meetCan['decisions'])
                ? $r->managerMeetings(
                    $meetCan['meetings'] || $meetCan['attendance'] || $meetCan['addDecisions'] ? $list($get('/meetings')) : [],
                    $meetCan['decisions'] || $meetCan['addDecisions'] ? $list($get('/decisions')) : [],
                    $meetCan, $me, array_column($myMeetings, 'id'),
                ) : []),
            $safe('absences', fn () => $can('absences') ? $r->absences($list($get('/absences/mine'))) : []),
            $safe('absences', fn () => $manage && $can('absences') ? $r->managerAbsences($list($get('/absences')), $can('absences', 'justify')) : []),
            $safe('matches', fn () => $can('matches') ? $r->matchNotices($list($get('/matches/mine/notices'))) : []),
            $safe('matches', fn () => $can('matches') ? $r->matches($list($get('/matches/mine'), 'data'), 'personal') : []),
            $safe('matches', fn () => $manage && $managesMatches ? $r->matches($list($get('/matches'), 'data'), 'management', $matchCan) : []),
            $safe('training', fn () => $can('trainingSessions') ? $r->trainingNotices($list($get('/training-sessions/mine/notices'))) : []),
            $safe('training', fn () => $can('trainingSessions') ? $r->training($list($get('/training-sessions/mine')), 'personal') : []),
            $safe('training', fn () => $manage && $can('trainingSessions', 'attendance') ? $r->training($list($get('/training-sessions')), 'management', true) : []),
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
