<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What happened to a meeting, announced to the members concerned */
class MeetingNotice extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'member_ids' => 'array',
        'previous' => 'array',
    ];

    /** The date / time / place / topic of a meeting, to compare before / after a change */
    public static function snapshot(Meeting $meeting): array
    {
        return [
            'topic' => (string) $meeting->topic,
            'date' => $meeting->date ? substr((string) $meeting->date, 0, 10) : null,
            'time' => $meeting->time ? substr((string) $meeting->time, 0, 5) : null,
            'location' => (string) $meeting->location,
        ];
    }

    /** Records what happened to the meeting, for these members */
    public static function record(Meeting $meeting, string $kind, array $memberIds, ?array $previous = null): ?self
    {
        $memberIds = array_values(array_unique(array_map('intval', array_filter($memberIds))));
        if (!$memberIds) {
            return null;
        }

        return self::create(array_merge(self::snapshot($meeting), [
            'meeting_id' => $meeting->id,
            'kind' => $kind,
            'member_ids' => $memberIds,
            'previous' => $previous,
        ]));
    }
}
