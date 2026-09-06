<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DisciplinaryAction extends Model
{
    use HasFactory;

    protected $table = 'disciplinary_actions';
    protected $guarded = ['id'];

    public $timestamps = false;

    public function caseId()
    {
        return $this->belongsTo('App\Models\Case', 'case_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
