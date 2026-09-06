<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipmentMovement extends Model
{
    use HasFactory;

    protected $table = 'equipment_movements';
    protected $guarded = ['id'];

    public $timestamps = false;

    public function operationId()
    {
        return $this->belongsTo(Operation::class, 'operation_id');
    }

    public function equipmentId()
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }
}
