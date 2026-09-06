<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DisciplinaryCase extends Model
{
    use HasFactory;

    protected $table = 'disciplinary_cases';
    protected $guarded = ['id'];

    public function individualsId()
    {
        return $this->belongsTo(Individual::class, 'individuals_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
