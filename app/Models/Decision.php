<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Decision extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id', 'category', 'type', 'checklist_items', 'text', 'assignee_ids', 'deadline', 'progress'
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
