<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    use HasFactory;

    protected $table = 'department_meetings';

    protected $fillable = [
        'topic', 'date', 'time', 'location', 'attendees', 'points'
    ];

    public function meetingAttendees()
    {
        return $this->hasMany(MeetingAttendee::class, 'meeting_id');
    }

    protected $casts = [
        'attendees' => 'array',
        'points' => 'array',
    ];
}


