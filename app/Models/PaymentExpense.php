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
        'Number_of_months',
        'start_date',
        'end_date',
        'Occasion_Reason_numper',
        'postal_check',
        'amount',
        'notes',
        'receipt_file',
        'fund_id',
        'contract_id',
    ];

    public function individual()
    {
        return $this->belongsTo(Individual::class, 'individuals_id');
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function fundTransaction()
    {
        return $this->hasOne(FundTransaction::class, 'payment_expenses_id');
    }

    public function fund()
    {
        return $this->belongsTo(Fund::class, 'fund_id');
    }
}

