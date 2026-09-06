<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HearingAttendee extends Model
{
    use HasFactory;

    protected $table = 'hearing_attendees';
    protected $guarded = ['id'];

    public $timestamps = false;

    public function actionId()
    {
        return $this->belongsTo(DisciplinaryAction::class, 'action_id');
    }

    public function individualsId()
    {
        return $this->belongsTo(Individual::class, 'individuals_id');
    }
}
