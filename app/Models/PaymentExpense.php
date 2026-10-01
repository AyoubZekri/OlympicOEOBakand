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
        'transaction_type',
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
        // Purchase on credit (is_credit) and the payments made on it (credit_id)
        'is_credit',
        'credit_id',
        'creditor',
        'creditor_phone',
        'due_date',
    ];

    protected $casts = [
        'is_credit' => 'boolean',
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

    /** A purchase on credit: the expenses that paid it */
    public function creditPayments()
    {
        return $this->hasMany(PaymentExpense::class, 'credit_id')->orderByDesc('Payments_data')->orderByDesc('id');
    }

    public function creditPaid(): float
    {
        return round((float) PaymentExpense::where('credit_id', $this->id)->sum('amount'), 2);
    }

    public function creditRemaining(): float
    {
        return max(0, round((float) $this->amount - $this->creditPaid(), 2));
    }
}


