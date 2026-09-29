<?php

namespace App\Services\Tasks;

use App\Models\Matchs;
use App\Models\TrainingSession;
use Carbon\CarbonImmutable;

/**
 * Matches and training sessions a task can be linked to ("match:12", "training:4"):
 * the upcoming ones for the task form, and a short description for the task details.
 */
class TaskEvents
{
    public const TYPES = ['match', 'training'];

    /** Upcoming events of a type (from the start of today), soonest first */
    public static function upcoming(string $type, int $limit = 60): array
    {
        $today = CarbonImmutable::today();

        if ($type === 'match') {
            return Matchs::with(['team:id,name', 'opponentClub:id,name'])
                ->where('match_date', '>=', $today)
                ->orderBy('match_date')
                ->limit($limit)
                ->get()
                ->map(fn (Matchs $m) => self::match($m))
                ->all();
        }

        return TrainingSession::with('teamId:id,name')
            ->whereDate('session_date', '>=', $today)
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'ملغاة'))
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->limit($limit)
            ->get()
            ->map(fn (TrainingSession $s) => self::training($s))
            ->all();
    }

    /** "match:12" → the event, or null when it no longer exists */
    public static function find(?string $sourceRef): ?array
    {
        if (!$sourceRef || !str_contains($sourceRef, ':')) {
            return null;
        }
        [$type, $id] = explode(':', $sourceRef, 2);
        return match ($type) {
            'match' => ($m = Matchs::with(['team:id,name', 'opponentClub:id,name'])->find($id)) ? self::match($m) : null,
            'training' => ($s = TrainingSession::with('teamId:id,name')->find($id)) ? self::training($s) : null,
            default => null,
        };
    }

    public static function exists(string $type, int $id): bool
    {
        return $type === 'match' ? Matchs::whereKey($id)->exists() : TrainingSession::whereKey($id)->exists();
    }

    private static function match(Matchs $m): array
    {
        $opponent = $m->opponentClub?->name ?? $m->opponent;
        return [
            'type' => 'match',
            'id' => $m->id,
            'title' => $opponent ? "مباراة ضد {$opponent}" : ($m->match_title ?: 'مباراة'),
            'at' => $m->match_date ? CarbonImmutable::parse($m->match_date)->format('Y-m-d H:i') : null,
            'team' => $m->team?->name,
            'place' => $m->location,
            'detail' => $m->competition,
        ];
    }

    private static function training(TrainingSession $s): array
    {
        $at = null;
        if ($s->session_date) {
            $day = CarbonImmutable::parse($s->session_date);
            if ($s->start_time) {
                [$h, $i] = array_map('intval', array_pad(explode(':', (string) $s->start_time), 2, 0));
                $day = $day->setTime($h, $i);
            }
            $at = $day->format('Y-m-d H:i');
        }
        return [
            'type' => 'training',
            'id' => $s->id,
            'title' => 'حصة تدريبية' . ($s->teamId?->name ? " - {$s->teamId->name}" : ''),
            'at' => $at,
            'team' => $s->teamId?->name,
            'place' => $s->location,
            'detail' => $s->shift,
        ];
    }
}
