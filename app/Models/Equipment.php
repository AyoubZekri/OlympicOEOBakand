<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    use HasFactory;

    protected $table = 'equipments';
    protected $guarded = ['id'];

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function movements()
    {
        return $this->hasMany(EquipmentMovement::class, 'equipment_id');
    }
}

