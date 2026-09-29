<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskTemplate extends Model
{
    public const TRIGGERS = ['match.created', 'training.created', 'meeting.created'];

    protected $fillable = [
        'title', 'description', 'kind', 'assignee_id', 'created_by', 'priority',
        'requires_approval', 'requires_proof', 'rrule', 'starts_on', 'next_run_at', 'trigger',
        'offset_minutes', 'duration_minutes', 'active',
    ];

    protected $casts = [
        'starts_on' => 'datetime',
        'next_run_at' => 'datetime',
        'requires_approval' => 'boolean',
        'requires_proof' => 'boolean',
        'active' => 'boolean',
        'offset_minutes' => 'integer',
        'duration_minutes' => 'integer',
    ];

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'template_id');
    }
}
