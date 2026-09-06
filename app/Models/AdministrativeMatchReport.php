<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdministrativeMatchReport extends Model
{
    use HasFactory;

    protected $table = 'administrative_match_reports';
    protected $guarded = ['id'];

    public $timestamps = false;

    public function matchId()
    {
        return $this->belongsTo('App\Models\Match', 'match_id');
    }

    public function adminId()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
