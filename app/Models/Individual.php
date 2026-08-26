<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Individual extends Model
{
    protected $fillable = [
        'type',
        'first_name',
        'last_name',
        'national_id',
        'phone',
        'place_of_birth',
        'birth_date',
        'Shirt_number',
        'status',
        'is_internal_system_printed',
        'team_id',
        'added_by',
        'photo',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
