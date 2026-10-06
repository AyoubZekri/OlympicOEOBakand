<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeetingDecision extends Model
{
    use HasFactory;

    protected $table = 'meeting_decisions';
    protected $guarded = ['id'];

    public $timestamps = false;

    /** Saving (or deleting) one marks its meeting updated: the alerts see the change at once */
    protected $touches = ['meetingId'];

    public function meetingId()
    {
        return $this->belongsTo(DepartmentMeeting::class, 'meeting_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(Individual::class, 'assigned_to');
    }
}
