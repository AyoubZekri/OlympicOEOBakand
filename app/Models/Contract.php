<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $fillable = [
        'individuals_id',
        'numper',
        'start_date',
        'end_date',
        'Contract_value',
        'Number_payments',
        'Monthly_Salary',
        'Winning_Bonus',
        'Goals_Bonus',
        'nots',
        'status',
    ];

    public function individual()
    {
        return $this->belongsTo(Individual::class, 'individuals_id');
    }
}
