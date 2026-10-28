<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A general goal set by the management for one user and one season */
class GeneralGoal extends Model
{
    protected $fillable = ['goal', 'user_id', 'season', 'created_by'];

    /** The user responsible for it (المكلّف) */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
