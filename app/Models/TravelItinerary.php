<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TravelItinerary extends Model
{
    use HasFactory;

    protected $table = 'travel_itineraries';
    protected $guarded = ['id'];

    public function matchId()
    {
        return $this->belongsTo('App\Models\Match', 'match_id');
    }

    public function headOfDelegationId()
    {
        return $this->belongsTo(Individual::class, 'head_of_delegation_id');
    }
}
