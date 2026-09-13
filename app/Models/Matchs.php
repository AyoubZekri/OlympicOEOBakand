<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Matchs extends Model
{
    use HasFactory;

    protected $table = 'matches';
    protected $guarded = ['id'];

    public function coachId()
    {
        return $this->belongsTo(Individual::class, 'coach_id');
    }

    public function adminId()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
