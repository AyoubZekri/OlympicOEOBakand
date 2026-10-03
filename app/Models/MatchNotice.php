<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What happened to a match, announced to the members of its category */
class MatchNotice extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'previous' => 'array',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /** "Y-m-d H:i" of a match date, or null */
    public static function minute($value): ?string
    {
        return $value ? substr(str_replace('T', ' ', (string) $value), 0, 16) : null;
    }

    /** The opponent's name: typed, or the chosen club's */
    public static function opponentOf(Matchs $match): string
    {
        if ($match->opponent) return $match->opponent;
        $club = $match->opponentClub;

        return $club ? (string) $club->name : '';
    }

    /** Records what happened to the match, with its date, place and opponent at that moment */
    public static function record(Matchs $match, string $kind, ?int $teamId = null, ?array $previous = null): self
    {
        return self::create([
            'match_id' => $match->id,
            'team_id' => $teamId ?? $match->team_id,
            'kind' => $kind,
            'match_date' => self::minute($match->match_date),
            'location' => $match->location,
            'opponent' => self::opponentOf($match),
            'competition' => $match->competition,
            'previous' => $previous,
        ]);
    }
}
