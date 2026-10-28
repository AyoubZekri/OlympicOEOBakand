<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A general goal set by the management for one member and one season */
class GeneralGoal extends Model
{
    protected $fillable = ['goal', 'individual_id', 'season', 'created_by'];

    public function individual()
    {
        return $this->belongsTo(Individual::class, 'individual_id');
    }
}
