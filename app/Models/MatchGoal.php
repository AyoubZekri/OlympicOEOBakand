<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchGoal extends Model
{
    use HasFactory;

    protected $table = 'match_goals';

    protected $fillable = [
        'match_id',
        'scorer_id',
        'assist_id',
        'minute',
    ];

    public function match()
    {
        return $this->belongsTo(Matchs::class, 'match_id');
    }

    public function scorer()
    {
        return $this->belongsTo(Individual::class, 'scorer_id');
    }

    public function assist()
    {
        return $this->belongsTo(Individual::class, 'assist_id');
    }
}
