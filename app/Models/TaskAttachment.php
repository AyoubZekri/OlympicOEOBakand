<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskAttachment extends Model
{
    public const TYPES = ['file', 'image', 'text', 'link'];

    protected $fillable = ['task_id', 'type', 'path', 'original_name', 'url', 'body', 'uploaded_by'];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
