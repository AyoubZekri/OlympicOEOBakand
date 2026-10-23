<?php

namespace App\Services;

use App\Models\Matchs;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\TaskMessage;
use App\Models\TrainingSession;
use App\Models\TravelItinerary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The tasks the appointments create:
 * - linked to an event: every new match / training session / trip / meeting creates the task (its alert and deadline
 *   counted from the event's time); the event moved → the dates follow; the event deleted → the task is cancelled;
 * - periodic: one task each week (a weekday) or each month (a day), created on that day;
 * - automatic (no appointment to set up): every match / training session / meeting / trip, and every active member's
 *   birthday, has its own appointment task — its alert a day before, its deadline the event's time. It shows on the
 *   home page from its alert on, and stays until its status changes (then the archive).
 * Times are the club's local time (Algeria), as the events store them.
 */
class TaskSchedule
{
    /** The club's clock, as a plain local time (the way the dates are stored) */
    public static function localNow(): Carbon
    {
        return Carbon::parse(now('Africa/Algiers')->format('Y-m-d H:i:s'));
    }

    /** The automatic appointments: their alert (a day before the event) and each event's nature */
    private const AUTO_ALERT = -1440;
    private const AUTO_NATURE = ['match' => 'external', 'training' => 'internal', 'meeting' => 'internal', 'travel' => 'external', 'birthday' => 'call'];

    /** Each event: its model, its time and what it is («مباراة ضد …», «تنقل إلى …»), the fields that change them, its date column */
    private static function events(): array
    {
        return [
            'match' => [Matchs::class, fn (Matchs $m) => [self::at($m->match_date), 'مباراة ضد ' . ($m->opponentClub?->name ?? $m->opponent ?? '—')], ['match_date', 'opponent', 'opponent_club_id'], 'match_date'],
            'training' => [TrainingSession::class, fn (TrainingSession $s) => [self::at($s->session_date, $s->start_time), 'حصة تدريب' . ($s->teamId?->name ? ' ' . $s->teamId->name : '')], ['session_date', 'start_time', 'team_id'], 'session_date'],
            'meeting' => [Meeting::class, fn (Meeting $m) => [self::at($m->date, $m->time), 'اجتماع: ' . ($m->topic ?: '—')], ['date', 'time', 'topic'], 'date'],
            'travel' => [TravelItinerary::class, fn (TravelItinerary $t) => [self::at($t->departure_time), 'تنقل إلى ' . ($t->destination ?: '—')], ['departure_time', 'destination'], 'departure_time'],
        ];
    }

    public static function register(): void
    {
        $label = fn (string $what, ?Carbon $at) => $what . ($at ? ' · ' . $at->format('Y/m/d H:i') : '');

        foreach (self::events() as $type => [$model, $describe, $watched]) {
            $model::created(fn ($e) => self::safely(function () use ($type, $e, $describe, $label) {
                [$at, $what] = $describe($e);
                self::onEvent($type, $e->id, $at, $label($what, $at));
                self::spawnAuto($type, "{$type}:{$e->id}", $at, $what);
            }));
            $model::updated(function ($e) use ($type, $describe, $watched, $label) {
                if ($e->wasChanged($watched)) {
                    self::safely(function () use ($type, $e, $describe, $label) {
                        [$at, $what] = $describe($e);
                        self::moveEvent($type, $e->id, $at, $label($what, $at), $what);
                    });
                }
            });
            $model::deleted(fn ($e) => self::safely(fn () => self::cancelEvent($type, $e->id)));
        }
    }

    /** An event's automatic appointment task (once): its alert a day before, its deadline the event's time, given to no one yet */
    private static function spawnAuto(string $type, string $ref, ?Carbon $at, string $what, ?Carbon $due = null): void
    {
        if (!$at) {
            return;
        }
        Task::firstOrCreate(['template_id' => null, 'source_ref' => $ref, 'is_template' => false], [
            'title' => $what,
            'kind' => 'scheduled',
            'nature' => self::AUTO_NATURE[$type],
            'event_label' => $at->format($type === 'birthday' ? 'Y/m/d' : 'Y/m/d H:i'),
            'alert_at' => self::offset($at, self::AUTO_ALERT),
            'due_at' => ($due ?? $at)->format('Y-m-d H:i:s'),
            'status' => 'new',
        ]);
    }

    /** The automatic tasks of the events already saved, coming within two days (a new event creates its own at once) */
    private static function generateAutoEvents(Carbon $now): void
    {
        $from = $now->copy()->startOfDay();
        $until = $now->copy()->addDays(2)->endOfDay();
        foreach (self::events() as $type => [$model, $describe, , $column]) {
            $model::whereBetween($column, [$from->format('Y-m-d H:i:s'), $until->format('Y-m-d H:i:s')])->get()
                ->each(function ($e) use ($type, $describe, $now) {
                    [$at, $what] = $describe($e);
                    if ($at && $at->gte($now)) {
                        self::spawnAuto($type, "{$type}:{$e->id}", $at, $what);
                    }
                });
        }
    }

    /** Each active member's birthday: its task from the day before (its alert, at midnight) to the day's end; not once it is past */
    private static function generateAutoBirthdays(Carbon $now): void
    {
        $today = $now->copy()->startOfDay();
        \App\Models\Individual::whereNotNull('birth_date')->where(fn ($q) => $q->whereNull('status')->orWhere('status', 'active'))
            ->get(['id', 'first_name', 'last_name', 'birth_date'])
            ->each(function ($member) use ($now, $today) {
                $born = Carbon::parse($member->birth_date);
                foreach ([$today->year, $today->year + 1] as $year) {
                    // 29 February: the 28th in the other years
                    $day = Carbon::create($year, $born->month, min($born->day, Carbon::create($year, $born->month, 1)->daysInMonth));
                    if ($day->lt($today) || $day->copy()->addMinutes(self::AUTO_ALERT)->gt($now)) {
                        continue;
                    }
                    $name = trim($member->first_name . ' ' . $member->last_name);
                    self::spawnAuto('birthday', "birthday:{$member->id}:{$year}", $day, 'عيد ميلاد ' . $name, $day->copy()->setTime(23, 59));
                }
            });
    }

    /** A new event: the active appointments of its type create their task */
    public static function onEvent(string $type, int $id, ?Carbon $at, string $label): void
    {
        Task::where('is_template', true)->where('schedule', 'event')->where('event_type', $type)->where('status', 'active')
            ->get()
            ->each(fn (Task $template) => self::spawn($template, "{$type}:{$id}", $at, $label));
    }

    /** The event moved (or renamed): its open tasks follow (the automatic one: its title, alert and deadline too) */
    public static function moveEvent(string $type, int $id, ?Carbon $at, string $label, ?string $what = null): void
    {
        Task::with('template')->where('source_ref', "{$type}:{$id}")->where('is_template', false)->whereIn('status', ['new', 'in_progress', 'on_hold'])->get()
            ->each(function (Task $task) use ($at, $label, $what) {
                if (!$task->template_id) {
                    $task->update([
                        'title' => $what ?? $task->title,
                        'event_label' => $at?->format('Y/m/d H:i'),
                        'alert_at' => self::offset($at, self::AUTO_ALERT),
                        'due_at' => self::offset($at, 0),
                    ]);

                    return;
                }
                $template = $task->template;
                $task->update([
                    'event_label' => $label,
                    'alert_at' => $template ? self::offset($at, $template->alert_offset) : $task->alert_at,
                    'due_at' => $template ? self::offset($at, $template->due_offset) : $task->due_at,
                ]);
            });
    }

    /** The event deleted: its open tasks are cancelled (said in their conversation) */
    public static function cancelEvent(string $type, int $id): void
    {
        Task::where('source_ref', "{$type}:{$id}")->whereIn('status', ['new', 'in_progress', 'on_hold'])->get()
            ->each(function (Task $task) {
                $task->update(['status' => 'cancelled', 'done_at' => null]);
                TaskMessage::create(['task_id' => $task->id, 'status' => 'cancelled', 'body' => 'حُذف الحدث المرتبط بالمهمة']);
            });
    }

    /**
     * The periodic appointments' tasks that are due (today's, and the days missed in the last month). An alert before
     * its day (a negative offset from the day's start): the task is created those days ahead, so it shows at its alert.
     * Asked by the tasks' lists: at most once a minute, unless forced.
     */
    public static function generateDue(bool $force = false): void
    {
        if (!$force && !Cache::store('file')->add('tasks.periodic.checked', 1, 60)) {
            return;
        }

        $today = self::localNow()->startOfDay();
        Task::where('is_template', true)->where('schedule', 'periodic')->where('status', 'active')->get()
            ->each(function (Task $template) use ($today) {
                $from = Carbon::parse($template->created_at->copy()->setTimezone('Africa/Algiers')->toDateString());
                if ($template->last_run_on) {
                    $from = $from->max($template->last_run_on->copy()->addDay());
                }
                $from = $from->max($today->copy()->subDays(31));
                $until = $today->copy()->addDays((int) ceil(max(0, -(int) $template->alert_offset) / 1440));

                for ($day = $from->copy(); $day->lte($until); $day->addDay()) {
                    if (self::isRepeatDay($template, $day)) {
                        self::spawn($template, 'day:' . $day->toDateString(), $day->copy(), $day->format('Y/m/d'));
                    }
                }
                $template->update(['last_run_on' => $until->toDateString()]);
            });

        self::generateBirthdays($today);
        self::generateAutoEvents(self::localNow());
        self::generateAutoBirthdays(self::localNow());
    }

    /**
     * Birthday appointments: each member's birthday creates its task once its alert time comes
     * (this year's, or next year's when the alert is before the new year). Not for birthdays already past
     * by more than a week, nor before the appointment existed.
     */
    private static function generateBirthdays(Carbon $today): void
    {
        $templates = Task::where('is_template', true)->where('schedule', 'event')->where('event_type', 'birthday')->where('status', 'active')->get();
        if ($templates->isEmpty()) {
            return;
        }
        $now = self::localNow();
        $members = \App\Models\Individual::whereNotNull('birth_date')->get(['id', 'first_name', 'last_name', 'birth_date']);

        foreach ($templates as $template) {
            $since = Carbon::parse($template->created_at->copy()->setTimezone('Africa/Algiers')->toDateString());
            foreach ($members as $member) {
                $born = Carbon::parse($member->birth_date);
                foreach ([$today->year, $today->year + 1] as $year) {
                    // 29 February: the 28th in the other years
                    $day = Carbon::create($year, $born->month, min($born->day, Carbon::create($year, $born->month, 1)->daysInMonth));
                    $alertAt = $day->copy()->addMinutes(min((int) $template->alert_offset, 0));
                    if ($day->lt($since) || $day->lt($today->copy()->subDays(7)) || $alertAt->gt($now)) {
                        continue;
                    }
                    $name = trim($member->first_name . ' ' . $member->last_name);
                    self::spawn($template, "birthday:{$member->id}:{$year}", $day, 'عيد ميلاد ' . $name . ' · ' . $day->format('Y/m/d'));
                }
            }
        }
    }

    /** weekly: 1 = Monday … 7 = Sunday; monthly: the day, or the month's last day when it is shorter */
    public static function isRepeatDay(Task $template, Carbon $day): bool
    {
        if ($template->repeat === 'weekly') {
            return $day->dayOfWeekIso === (int) $template->repeat_day;
        }

        return $day->day === min((int) $template->repeat_day, $day->daysInMonth);
    }

    /** One task per appointment and event / day */
    private static function spawn(Task $template, string $ref, ?Carbon $anchor, string $label): void
    {
        Task::firstOrCreate(['template_id' => $template->id, 'source_ref' => $ref], [
            'title' => $template->title,
            'details' => $template->details,
            'kind' => 'scheduled',
            'nature' => $template->nature,
            'assignee_id' => $template->assignee_id,
            'created_by' => $template->created_by,
            'event_label' => $label,
            'alert_at' => self::offset($anchor, $template->alert_offset),
            'due_at' => self::offset($anchor, $template->due_offset),
            'status' => 'new',
        ]);
    }

    private static function offset(?Carbon $anchor, ?int $minutes): ?string
    {
        return $anchor && $minutes !== null ? $anchor->copy()->addMinutes($minutes)->format('Y-m-d H:i:s') : null;
    }

    /** A problem with the tasks never stops the event itself from being saved */
    private static function safely(callable $work): void
    {
        try {
            $work();
        } catch (\Throwable $e) {
            Log::warning('Tasks from an event failed: ' . $e->getMessage());
        }
    }

    /** An event's time from a datetime, or a date + a time */
    private static function at($date, $time = null): ?Carbon
    {
        if (!$date) {
            return null;
        }
        try {
            $day = Carbon::parse(is_object($date) ? $date->format('Y-m-d H:i:s') : (string) $date);
            if ($time) {
                [$h, $m] = array_map('intval', array_pad(explode(':', (string) $time), 2, 0));
                $day = $day->setTime($h, $m);
            }

            return $day;
        } catch (\Throwable) {
            return null;
        }
    }
}
