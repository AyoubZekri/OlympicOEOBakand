<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Correspondence extends Model
{
    use HasFactory;

    protected $table = 'correspondences';
    protected $guarded = ['id'];

    public function senderId()
    {
        return $this->belongsTo(Individual::class, 'sender_id');
    }

    public function receiverId()
    {
        return $this->belongsTo(Individual::class, 'receiver_id');
    }
}
