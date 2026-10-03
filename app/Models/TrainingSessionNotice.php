<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What happened to a training session, announced to the members of its category */
class TrainingSessionNotice extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'previous' => 'array',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /** Records what happened to the session, with its date, times and place at that moment */
    public static function record(TrainingSession $session, string $kind, ?int $teamId = null, ?array $previous = null): self
    {
        return self::create([
            'training_session_id' => $session->id,
            'team_id' => $teamId ?? $session->team_id,
            'kind' => $kind,
            'session_date' => $session->session_date ? substr((string) $session->session_date, 0, 10) : null,
            'start_time' => $session->start_time ? substr((string) $session->start_time, 0, 5) : null,
            'end_time' => $session->end_time ? substr((string) $session->end_time, 0, 5) : null,
            'location' => $session->location,
            'previous' => $previous,
        ]);
    }
}
