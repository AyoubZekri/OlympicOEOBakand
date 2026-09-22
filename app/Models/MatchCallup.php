<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchCallup extends Model
{
    use HasFactory;

    protected $table = 'match_callups';
    protected $guarded = ['id'];

    public function matchId()
    {
        return $this->belongsTo('App\Models\Matchs', 'match_id');
    }

    public function playerId()
    {
        return $this->belongsTo(Individual::class, 'player_id');
    }

    public function individual()
    {
        return $this->belongsTo(Individual::class, 'player_id');
    }

    public function replacedBy()
    {
        return $this->belongsTo(Individual::class, 'replaced_by_id');
    }
}
