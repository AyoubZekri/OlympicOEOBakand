<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerMedicalRecord extends Model
{
    use HasFactory;

    protected $table = 'player_medical_records';
    protected $guarded = ['id'];

    public function playerId()
    {
        return $this->belongsTo(Individual::class, 'player_id');
    }

    public function doctorId()
    {
        return $this->belongsTo(Individual::class, 'doctor_id');
    }
}
