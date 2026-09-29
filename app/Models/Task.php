<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    public const STATUSES = ['assigned', 'in_progress', 'blocked', 'in_review', 'approved', 'returned'];
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];
    public const BLOCK_REASONS = ['waiting_decision', 'financial', 'missing_resources', 'external', 'other'];

    protected $fillable = [
        'reference', 'title', 'description', 'assignee_id', 'reviewer_id', 'created_by', 'priority',
        'starts_at', 'due_at', 'requires_approval', 'requires_proof', 'status', 'block_reason', 'block_note',
        'return_reason', 'source_type', 'source_ref', 'template_id', 'completed_at', 'approved_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
        'requires_approval' => 'boolean',
        'requires_proof' => 'boolean',
    ];

    protected $appends = ['is_overdue'];

    /** Overdue is never stored: past the deadline and not approved yet */
    public function getIsOverdueAttribute(): bool
    {
        return $this->due_at !== null && $this->status !== 'approved' && $this->due_at->isPast();
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments()
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function history()
    {
        return $this->hasMany(TaskStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function template()
    {
        return $this->belongsTo(TaskTemplate::class);
    }
}
