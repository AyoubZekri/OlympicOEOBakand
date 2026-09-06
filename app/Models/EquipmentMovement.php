<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipmentMovement extends Model
{
    use HasFactory;

    protected $table = 'equipment_movements';
    public $timestamps = false;
    protected $guarded = ['id'];

    public function operation()
    {
        return $this->belongsTo(EquipmentOperation::class, 'operation_id');
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }
}

