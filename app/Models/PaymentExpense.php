<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentExpense extends Model
{
    protected $fillable = [
        'individuals_id',
        'first_name',
        'last_name',
        'Payments_data',
        'payment_method',
        'amount_Nature',
        'start_date',
        'end_date',
        'Occasion_Reason_numper',
        'amount',
        'notes',
    ];

    public function individual()
    {
        return $this->belongsTo(Individual::class, 'individuals_id');
    }

    public function fundTransaction()
    {
        return $this->hasOne(FundTransaction::class, 'payment_expenses_id');
    }
}
