<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepartmentMeeting extends Model
{
    use HasFactory;

    protected $table = 'department_meetings';
    protected $guarded = ['id'];

    public function createdBy()
    {
        return $this->belongsTo(Individual::class, 'created_by');
    }
}
