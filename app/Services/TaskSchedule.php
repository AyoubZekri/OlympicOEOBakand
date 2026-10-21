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
 * - periodic: one task each week (a weekday) or each month (a day), created on that day.
 * Times are the club's local time (Algeria), as the events store them.
 */
class TaskSchedule
{
    /** The club's clock, as a plain local time (the way the dates are stored) */
    public static function localNow(): Carbon
    {
        return Carbon::parse(now('Africa/Algiers')->format('Y-m-d H:i:s'));
    }

    public static function register(): void
    {
        $label = fn (string $what, ?Carbon $at) => $what . ($at ? ' · ' . $at->format('Y/m/d H:i') : '');

        $match = fn (Matchs $m) => [self::at($m->match_date), $label('مباراة ضد ' . ($m->opponentClub?->name ?? $m->opponent ?? '—'), self::at($m->match_date))];
        $training = fn (TrainingSession $s) => [self::at($s->session_date, $s->start_time), $label('حصة تدريب' . ($s->teamId?->name ? ' ' . $s->teamId->name : ''), self::at($s->session_date, $s->start_time))];
        $meeting = fn (Meeting $m) => [self::at($m->date, $m->time), $label('اجتماع: ' . ($m->topic ?: '—'), self::at($m->date, $m->time))];
        $travel = fn (TravelItinerary $t) => [self::at($t->departure_time), $label('تنقل إلى ' . ($t->destination ?: '—'), self::at($t->departure_time))];

        foreach ([
            ['match', Matchs::class, $match, ['match_date', 'opponent', 'opponent_club_id']],
            ['training', TrainingSession::class, $training, ['session_date', 'start_time', 'team_id']],
            ['meeting', Meeting::class, $meeting, ['date', 'time', 'topic']],
            ['travel', TravelItinerary::class, $travel, ['departure_time', 'destination']],
        ] as [$type, $model, $describe, $watched]) {
            $model::created(fn ($e) => self::safely(fn () => self::onEvent($type, $e->id, ...$describe($e))));
            $model::updated(function ($e) use ($type, $describe, $watched) {
                if ($e->wasChanged($watched)) {
                    self::safely(fn () => self::moveEvent($type, $e->id, ...$describe($e)));
                }
            });
            $model::deleted(fn ($e) => self::safely(fn () => self::cancelEvent($type, $e->id)));
        }
    }

    /** A new event: the active appointments of its type create their task */
    public static function onEvent(string $type, int $id, ?Carbon $at, string $label): void
    {
        Task::where('is_template', true)->where('schedule', 'event')->where('event_type', $type)->where('status', 'active')
            ->get()
            ->each(fn (Task $template) => self::spawn($template, "{$type}:{$id}", $at, $label));
    }

    /** The event moved (or renamed): its open tasks follow */
    public static function moveEvent(string $type, int $id, ?Carbon $at, string $label): void
    {
        Task::with('template')->where('source_ref', "{$type}:{$id}")->whereIn('status', ['new', 'in_progress', 'on_hold'])->get()
            ->each(function (Task $task) use ($at, $label) {
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
     * The periodic appointments' tasks that are due (today's, and the days missed in the last month).
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

                for ($day = $from->copy(); $day->lte($today); $day->addDay()) {
                    if (self::isRepeatDay($template, $day)) {
                        self::spawn($template, 'day:' . $day->toDateString(), $day->copy(), $day->format('Y/m/d'));
                    }
                }
                $template->update(['last_run_on' => $today->toDateString()]);
            });
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
