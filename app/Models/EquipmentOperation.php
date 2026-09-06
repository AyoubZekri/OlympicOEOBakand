<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipmentOperation extends Model
{
    use HasFactory;

    protected $table = 'equipment_operations';
    protected $guarded = ['id'];

    public $timestamps = false;

    public function memberId()
    {
        return $this->belongsTo(Individual::class, 'member_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
