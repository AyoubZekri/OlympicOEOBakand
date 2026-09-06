<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppAbsence extends Model
{
    use HasFactory;

    protected $table = 'app_absences';
    protected $guarded = ['id'];

    public function playerId()
    {
        return $this->belongsTo(Individual::class, 'player_id');
    }

    public function trainingSessionId()
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function decisionBy()
    {
        return $this->belongsTo(Individual::class, 'decision_by');
    }
}
