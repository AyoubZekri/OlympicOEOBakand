<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\TaskTemplate;
use Carbon\CarbonImmutable;

/**
 * Creates tasks from templates:
 *  - periodic templates: one task per RRULE occurrence, created LEAD_DAYS days before its date
 *    (run by the tasks:generate-periodic command, and right after a template is saved),
 *  - event templates: tasks for a system event (a match, a training session, a meeting is created).
 * Each occurrence / event creates its task only once.
 */
class TaskGenerator
{
    /** Most missed occurrences created per template in one run (after the server was down) */
    private const MAX_PER_RUN = 31;

    /** A periodic task is created this many days before its date, so it shows up in advance */
    public const LEAD_DAYS = 3;

    /** Create the periodic tasks whose date is within the next LEAD_DAYS days; returns how many were created */
    public static function runPeriodic(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $created = 0;
        TaskTemplate::where('kind', 'periodic')->where('active', true)->whereNotNull('rrule')->get()
            ->each(function (TaskTemplate $template) use ($now, &$created) {
                $created += self::runTemplate($template, $now);
            });
        return $created;
    }

    /** One periodic template: create its occurrences up to now + LEAD_DAYS and move next_run_at forward */
    public static function runTemplate(TaskTemplate $template, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        if ($template->kind !== 'periodic' || !$template->active || !$template->rrule) {
            return 0;
        }

        $anchor = CarbonImmutable::parse($template->starts_on ?? $template->created_at);
        try {
            $rule = new RecurrenceRule($template->rrule, $anchor);
        } catch (\Throwable) {
            return 0; // an invalid rule never blocks the other templates
        }

        $next = $template->next_run_at
            ? CarbonImmutable::parse($template->next_run_at)
            : $rule->nextAfter($anchor->subSecond());
        $horizon = $now->addDays(self::LEAD_DAYS);

        $created = 0;
        $count = 0;
        while ($next && $next->lessThanOrEqualTo($horizon) && $count < self::MAX_PER_RUN) {
            if (self::createFromTemplate($template, $next, 'periodic', null)) {
                $created++;
            }
            $count++;
            $next = $rule->nextAfter($next);
        }

        $template->next_run_at = $next;
        if (!$next) {
            $template->active = false; // the rule has ended (UNTIL)
        }
        $template->save();

        return $created;
    }

    /**
     * Tasks for a system event.
     * $trigger: match.created | training.created | meeting.created, $sourceRef: e.g. "match:12",
     * $eventAt: when the event happens, $label: replaces {event} in the template title / description.
     */
    public static function fromEvent(string $trigger, string $sourceRef, ?CarbonImmutable $eventAt, string $label): int
    {
        $eventAt ??= CarbonImmutable::now();
        $created = 0;

        TaskTemplate::where('kind', 'event')->where('trigger', $trigger)->where('active', true)->get()
            ->each(function (TaskTemplate $template) use ($sourceRef, $eventAt, $label, &$created) {
                $due = $eventAt->addMinutes($template->offset_minutes);
                if (self::createFromTemplate($template, $due, $sourceRef, $label, true)) {
                    $created++;
                }
            });

        return $created;
    }

    /**
     * One task from a template. For periodic templates $at is the start (due = start + duration);
     * for event templates $at is the deadline (start = deadline − duration, never before now).
     */
    private static function createFromTemplate(TaskTemplate $template, CarbonImmutable $at, string $sourceRef, ?string $label, bool $atIsDeadline = false): ?Task
    {
        if ($atIsDeadline) {
            $due = $at;
            $start = $at->subMinutes($template->duration_minutes);
            if ($start->isPast()) {
                $start = CarbonImmutable::now()->startOfMinute();
            }
            $exists = Task::withTrashed()->where('template_id', $template->id)->where('source_ref', $sourceRef)->exists();
        } else {
            $start = $at;
            $due = $at->addMinutes($template->duration_minutes);
            $exists = Task::withTrashed()->where('template_id', $template->id)->where('source_ref', $sourceRef)
                ->where('starts_at', $start->toDateTimeString())->exists();
        }
        if ($exists) {
            return null;
        }

        $fill = fn (?string $text) => $text === null ? null : str_replace('{event}', $label ?? '', $text);

        return TaskWorkflow::create([
            'title' => $fill($template->title),
            'description' => $fill($template->description),
            'assignee_id' => $template->assignee_id,
            'created_by' => $template->created_by,
            'priority' => $template->priority,
            'starts_at' => $start,
            'due_at' => $due,
            'requires_approval' => $template->requires_approval,
            'requires_proof' => $template->requires_proof,
            'source_type' => $template->kind,
            'source_ref' => $sourceRef,
            'template_id' => $template->id,
        ], null, $template->kind === 'periodic' ? 'مهمة دورية' : "مهمة مرتبطة بحدث: {$label}");
    }
}
