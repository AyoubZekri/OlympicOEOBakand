<?php

namespace App\Services\Tasks;

use App\Models\Matchs;
use App\Models\Meeting;
use App\Models\TrainingSession;
use App\Models\TravelItinerary;
use Carbon\CarbonImmutable;

/**
 * Events a task can be linked to: matches, training sessions, meetings and travels ("match:12", "meeting:3"…):
 * the upcoming ones for the task form, and a short description for the task details.
 */
class TaskEvents
{
    public const TYPES = ['match', 'training', 'meeting', 'travel'];

    /** Upcoming events of a type (from the start of today), soonest first */
    public static function upcoming(string $type, int $limit = 60): array
    {
        $today = CarbonImmutable::today();

        return match ($type) {
            'match' => Matchs::with(['team:id,name', 'opponentClub:id,name'])
                ->where('match_date', '>=', $today)
                ->orderBy('match_date')
                ->limit($limit)
                ->get()
                ->map(fn (Matchs $m) => self::match($m))
                ->all(),

            'training' => TrainingSession::with('teamId:id,name')
                ->whereDate('session_date', '>=', $today)
                ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'ملغاة'))
                ->orderBy('session_date')
                ->orderBy('start_time')
                ->limit($limit)
                ->get()
                ->map(fn (TrainingSession $s) => self::training($s))
                ->all(),

            'meeting' => Meeting::whereDate('date', '>=', $today)
                ->orderBy('date')
                ->orderBy('time')
                ->limit($limit)
                ->get()
                ->map(fn (Meeting $m) => self::meeting($m))
                ->all(),

            'travel' => TravelItinerary::with('team:id,name')
                ->where('departure_time', '>=', $today)
                ->orderBy('departure_time')
                ->limit($limit)
                ->get()
                ->map(fn (TravelItinerary $t) => self::travel($t))
                ->all(),

            default => [],
        };
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
            'meeting' => ($m = Meeting::find($id)) ? self::meeting($m) : null,
            'travel' => ($t = TravelItinerary::with('team:id,name')->find($id)) ? self::travel($t) : null,
            default => null,
        };
    }

    public static function exists(string $type, int $id): bool
    {
        return match ($type) {
            'match' => Matchs::whereKey($id)->exists(),
            'training' => TrainingSession::whereKey($id)->exists(),
            'meeting' => Meeting::whereKey($id)->exists(),
            'travel' => TravelItinerary::whereKey($id)->exists(),
            default => false,
        };
    }

    /** A date plus an optional "HH:MM[:SS]" time → "Y-m-d H:i" */
    private static function at($date, $time = null): ?string
    {
        if (!$date) {
            return null;
        }
        $day = CarbonImmutable::parse($date);
        if ($time) {
            [$h, $i] = array_map('intval', array_pad(explode(':', (string) $time), 2, 0));
            $day = $day->setTime($h, $i);
        }
        return $day->format('Y-m-d H:i');
    }

    private static function match(Matchs $m): array
    {
        $opponent = $m->opponentClub?->name ?? $m->opponent;
        return [
            'type' => 'match',
            'id' => $m->id,
            'title' => $opponent ? "مباراة ضد {$opponent}" : ($m->match_title ?: 'مباراة'),
            'at' => self::at($m->match_date),
            'team' => $m->team?->name,
            'place' => $m->location,
            'detail' => $m->competition,
        ];
    }

    private static function training(TrainingSession $s): array
    {
        return [
            'type' => 'training',
            'id' => $s->id,
            'title' => 'حصة تدريبية' . ($s->teamId?->name ? " - {$s->teamId->name}" : ''),
            'at' => self::at($s->session_date, $s->start_time),
            'team' => $s->teamId?->name,
            'place' => $s->location,
            'detail' => $s->shift,
        ];
    }

    private static function meeting(Meeting $m): array
    {
        return [
            'type' => 'meeting',
            'id' => $m->id,
            'title' => 'اجتماع: ' . ($m->topic ?: 'بدون موضوع'),
            'at' => self::at($m->date, $m->time),
            'team' => null,
            'place' => $m->location,
            'detail' => null,
        ];
    }

    private static function travel(TravelItinerary $t): array
    {
        return [
            'type' => 'travel',
            'id' => $t->id,
            'title' => 'تنقل إلى ' . ($t->destination ?: '—'),
            'at' => self::at($t->departure_time),
            'team' => $t->team?->name,
            'place' => $t->departure_location,
            'detail' => $t->travel_reason,
        ];
    }
}
