<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerClearance extends Model
{
    use HasFactory;

    protected $table = 'player_clearances';
    protected $guarded = ['id'];

    public function playerId()
    {
        return $this->belongsTo(Individual::class, 'player_id');
    }

    public function equipmentManagerId()
    {
        return $this->belongsTo(Individual::class, 'equipment_manager_id');
    }

    public function adminId()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function sportingDirectorId()
    {
        return $this->belongsTo(Individual::class, 'sporting_director_id');
    }

    public function financeManagerId()
    {
        return $this->belongsTo(Individual::class, 'finance_manager_id');
    }

    public function medicalStaffId()
    {
        return $this->belongsTo(Individual::class, 'medical_staff_id');
    }
}
