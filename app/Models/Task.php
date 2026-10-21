<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A task given to one member, or an appointment (is_template) that creates tasks: on an event, or periodically */
class Task extends Model
{
    public const KINDS = ['normal', 'urgent', 'scheduled'];
    public const NATURES = ['internal', 'external', 'call'];
    public const STATUSES = ['new', 'in_progress', 'on_hold', 'done', 'cancelled'];
    public const EVENTS = ['match', 'training', 'travel', 'meeting'];

    protected $fillable = [
        'title', 'details', 'kind', 'nature', 'assignee_id', 'created_by',
        'is_template', 'schedule', 'event_type', 'repeat', 'repeat_day', 'alert_offset', 'due_offset', 'last_run_on',
        'template_id', 'source_ref', 'event_label', 'alert_at', 'due_at', 'status', 'done_at',
        'assignee_seen_at', 'assignee_read_at', 'managers_read_at',
    ];

    protected $casts = [
        'is_template' => 'boolean',
        'repeat_day' => 'integer',
        'alert_offset' => 'integer',
        'due_offset' => 'integer',
        'last_run_on' => 'date',
        'alert_at' => 'datetime',
        'due_at' => 'datetime',
        'done_at' => 'datetime',
        'assignee_seen_at' => 'datetime',
        'assignee_read_at' => 'datetime',
        'managers_read_at' => 'datetime',
    ];

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function template()
    {
        return $this->belongsTo(Task::class, 'template_id');
    }

    /** The tasks an appointment created */
    public function children()
    {
        return $this->hasMany(Task::class, 'template_id');
    }

    public function messages()
    {
        return $this->hasMany(TaskMessage::class);
    }

    public function lastMessage()
    {
        return $this->hasOne(TaskMessage::class)->latestOfMany();
    }

    /** The member it is given to */
    public function isAssignee(?User $user): bool
    {
        return $user !== null && $this->assignee_id !== null && (int) $this->assignee_id === (int) $user->id;
    }

    /** Still to do: not done, not cancelled */
    public function isOpen(): bool
    {
        return in_array($this->status, ['new', 'in_progress', 'on_hold'], true);
    }
}
