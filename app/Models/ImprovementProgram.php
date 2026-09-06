<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImprovementProgram extends Model
{
    use HasFactory;

    protected $table = 'improvement_programs';
    protected $guarded = ['id'];

    public function evaluationId()
    {
        return $this->belongsTo(Evaluation::class, 'evaluation_id');
    }

    public function playerId()
    {
        return $this->belongsTo(Individual::class, 'player_id');
    }
}
