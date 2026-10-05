<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/** What happened to a trip, announced to the members concerned */
class TravelNotice extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'member_ids' => 'array',
        'previous' => 'array',
    ];

    private static function minute($value): ?string
    {
        return $value ? CarbonImmutable::parse($value)->format('Y-m-d H:i') : null;
    }

    /** The trip's departure / return / destination / meeting point / transport, to compare before / after a change */
    public static function snapshot(TravelItinerary $t): array
    {
        return [
            'destination' => (string) $t->destination,
            'departure_time' => self::minute($t->departure_time),
            'return_time' => self::minute($t->return_time),
            'departure_location' => (string) $t->departure_location,
            'transport_method' => (string) $t->transport_method,
        ];
    }

    /** Everyone on the trip: head of the delegation, staff, players (member ids) */
    public static function membersOf(TravelItinerary $t): array
    {
        return array_values(array_unique(array_filter(array_map('intval', array_merge(
            [$t->head_of_delegation_id],
            $t->staff_ids ?? [],
            $t->player_ids ?? [],
        )))));
    }

    /** Records what happened to the trip, for these members */
    public static function record(TravelItinerary $t, string $kind, array $memberIds, ?array $previous = null): ?self
    {
        $memberIds = array_values(array_unique(array_filter(array_map('intval', $memberIds))));
        if (!$memberIds) {
            return null;
        }

        return self::create(array_merge(self::snapshot($t), [
            'travel_id' => $t->id,
            'kind' => $kind,
            'member_ids' => $memberIds,
            'previous' => $previous,
        ]));
    }
}
