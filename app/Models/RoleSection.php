<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A section of the roles page: a name, and the roles put in it */
class RoleSection extends Model
{
    protected $fillable = ['name'];

    public function roles()
    {
        return $this->hasMany(Role::class, 'section_id');
    }
}
