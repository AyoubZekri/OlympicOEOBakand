<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One of a member's other documents: its name and its file (in storage, «documents/») */
class MemberDocument extends Model
{
    protected $fillable = ['individual_id', 'name', 'file'];

    public function individual()
    {
        return $this->belongsTo(Individual::class);
    }
}
