<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskStatusHistory extends Model
{
    protected $table = 'task_status_history';

    // Only created_at: history rows are never edited
    public const UPDATED_AT = null;

    protected $fillable = ['task_id', 'action', 'from_status', 'to_status', 'note', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
