<?php

namespace App\Services\Tasks;

use App\Models\Matchs;
use App\Models\Meeting;
use App\Models\TrainingSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * System events that create tasks (event templates): a match, a training session or a meeting is created.
 * A problem while creating the tasks is logged and never stops the event itself from being saved.
 */
class TaskEventHooks
{
    public static function register(): void
    {
        Matchs::created(function (Matchs $match) {
            $opponent = $match->opponentClub?->name ?? $match->opponent;
            self::fire('match.created', "match:{$match->id}", self::at($match->match_date), $opponent ? "مباراة ضد {$opponent}" : 'مباراة');
        });

        TrainingSession::created(function (TrainingSession $session) {
            $at = self::at($session->session_date, $session->start_time);
            self::fire('training.created', "training:{$session->id}", $at, 'حصة تدريبية' . ($at ? ' ' . $at->format('Y-m-d') : ''));
        });

        Meeting::created(function (Meeting $meeting) {
            self::fire('meeting.created', "meeting:{$meeting->id}", self::at($meeting->date, $meeting->time), 'اجتماع: ' . ($meeting->topic ?: 'بدون موضوع'));
        });
    }

    private static function fire(string $trigger, string $sourceRef, ?CarbonImmutable $at, string $label): void
    {
        try {
            TaskGenerator::fromEvent($trigger, $sourceRef, $at, $label);
        } catch (\Throwable $e) {
            Log::warning("Task templates for {$trigger} ({$sourceRef}) failed: {$e->getMessage()}");
        }
    }

    /** Event time from a datetime, or a date + a time */
    private static function at($date, $time = null): ?CarbonImmutable
    {
        if (!$date) {
            return null;
        }
        try {
            $day = CarbonImmutable::parse($date);
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
