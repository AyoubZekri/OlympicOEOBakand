<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Decision extends Model
{
    use HasFactory;

    protected $table = 'meeting_decisions';

    protected $fillable = [
        'meeting_id', 'category', 'type', 'checklist_items', 'decision_text', 'assignee_ids', 'deadline', 'progress', 'execution_status', 'execution_notes'
    ];

    protected $casts = [
        'checklist_items' => 'array',
        'assignee_ids' => 'array',
    ];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }
}
