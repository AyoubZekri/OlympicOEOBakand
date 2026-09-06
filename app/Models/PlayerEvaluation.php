<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerEvaluation extends Model
{
    use HasFactory;

    protected $table = 'player_evaluations';
    protected $guarded = ['id'];

    public function playerId()
    {
        return $this->belongsTo(Individual::class, 'player_id');
    }

    public function coachId()
    {
        return $this->belongsTo(Individual::class, 'coach_id');
    }

    public function sportingDirectorId()
    {
        return $this->belongsTo(Individual::class, 'sporting_director_id');
    }
}
