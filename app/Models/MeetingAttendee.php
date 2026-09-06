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

    public function meetingId()
    {
        return $this->belongsTo(DepartmentMeeting::class, 'meeting_id');
    }

    public function memberId()
    {
        return $this->belongsTo(Individual::class, 'member_id');
    }
}
