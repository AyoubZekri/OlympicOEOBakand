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

    protected $casts = [
        'attendees' => 'array',
        'points' => 'array',
    ];
}

