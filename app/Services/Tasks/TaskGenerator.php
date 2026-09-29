<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\TaskTemplate;
use Carbon\CarbonImmutable;

/**
 * Creates tasks from templates:
 *  - periodic templates: one task per RRULE occurrence (run by the tasks:generate-periodic command),
 *  - event templates: tasks for a system event (a match, a training session, a meeting is created).
 * Each occurrence / event creates its task only once.
 */
class TaskGenerator
{
    /** Most missed occurrences created per template in one run (after the server was down) */
    private const MAX_PER_RUN = 31;

    /** Create the periodic tasks that are due; returns how many were created */
    public static function runPeriodic(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $created = 0;

        TaskTemplate::where('kind', 'periodic')->where('active', true)->whereNotNull('rrule')->get()
            ->each(function (TaskTemplate $template) use ($now, &$created) {
                $anchor = CarbonImmutable::parse($template->starts_on ?? $template->created_at);
                try {
                    $rule = new RecurrenceRule($template->rrule, $anchor);
                } catch (\Throwable) {
                    return; // an invalid rule never blocks the other templates
                }

                $next = $template->next_run_at
                    ? CarbonImmutable::parse($template->next_run_at)
                    : $rule->nextAfter($anchor->subSecond());

                $count = 0;
                while ($next && $next->lessThanOrEqualTo($now) && $count < self::MAX_PER_RUN) {
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
            });

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
