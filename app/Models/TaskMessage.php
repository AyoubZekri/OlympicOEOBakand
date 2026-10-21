<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A message in a task's conversation, or a status change written in it (status set) */
class TaskMessage extends Model
{
    protected $fillable = ['task_id', 'user_id', 'body', 'status'];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
