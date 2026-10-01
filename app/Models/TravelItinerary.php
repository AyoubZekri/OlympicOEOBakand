<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TravelItinerary extends Model
{
    use HasFactory;

    protected $table = 'travel_itineraries';
    protected $guarded = ['id'];

    protected $casts = [
        'staff_ids' => 'array',
        'player_ids' => 'array',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function matchId()
    {
        return $this->belongsTo('App\Models\Matchs', 'match_id');
    }

    public function headOfDelegationId()
    {
        return $this->belongsTo(Individual::class, 'head_of_delegation_id');
    }
}
