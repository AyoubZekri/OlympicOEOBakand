<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipmentOperation extends Model
{
    use HasFactory;

    protected $table = 'equipment_operations';
    public $timestamps = false;
    protected $guarded = ['id'];

    public function member()
    {
        return $this->belongsTo(Individual::class, 'member_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function movements()
    {
        return $this->hasMany(EquipmentMovement::class, 'operation_id');
    }
}


