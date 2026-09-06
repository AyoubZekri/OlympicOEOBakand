<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingSession extends Model
{
    use HasFactory;

    protected $table = 'training_sessions';
    protected $guarded = ['id'];

    public function supervisorId()
    {
        return $this->belongsTo(Individual::class, 'supervisor_id');
    }
}
