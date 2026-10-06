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

    /** Saving an action (reply, decision, document) marks its case updated: the alerts see the change at once */
    protected $touches = ['caseId'];

    public function caseId()
    {
        return $this->belongsTo(DisciplinaryCase::class, 'case_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
