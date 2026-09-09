<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImprovementProgram extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_id',
        'player_id',
        'program_start',
        'program_end',
        'areas_to_improve',
        'specific_goals',
        'actions_required',
        'next_evaluation_date',
        'is_acknowledged',
    ];
}
