<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerActivityLog extends Model
{
    use HasFactory;

    protected $table = 'player_activity_logs';
    protected $guarded = ['id'];

    public function playerId()
    {
        return $this->belongsTo(Individual::class, 'player_id');
    }
}
