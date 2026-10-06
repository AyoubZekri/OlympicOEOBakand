<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeetingAttendee extends Model
{
    use HasFactory;

    protected $table = 'meeting_attendees';
    protected $guarded = ['id'];

    public $timestamps = false;

    /** Saving (or deleting) one marks its meeting updated: the alerts see the change at once */
    protected $touches = ['meetingId'];

    public function meetingId()
    {
        return $this->belongsTo(DepartmentMeeting::class, 'meeting_id');
    }

    public function memberId()
    {
        return $this->belongsTo(Individual::class, 'member_id');
    }
}
