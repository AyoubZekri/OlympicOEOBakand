<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchBonuse extends Model
{
    use HasFactory;

    protected $table = 'match_bonuses';
    protected $guarded = ['id'];

    public function matchId()
    {
        return $this->belongsTo('App\Models\Match', 'match_id');
    }

    public function preparedBy()
    {
        return $this->belongsTo(Individual::class, 'prepared_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(Individual::class, 'reviewed_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(Individual::class, 'approved_by');
    }
}
