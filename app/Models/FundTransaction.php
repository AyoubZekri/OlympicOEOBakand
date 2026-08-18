<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundTransaction extends Model
{
    protected $fillable = [
        'fund_id',
        'payment_expenses_id',
        'type',
        'amount',
        'reference_number',
        'description',
        'transaction_date',
        'created_by',
        'to_fund_id',
    ];

    public function fund()
    {
        return $this->belongsTo(Fund::class, 'fund_id');
    }

    public function paymentExpense()
    {
        return $this->belongsTo(PaymentExpense::class, 'payment_expenses_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function toFund()
    {
        return $this->belongsTo(Fund::class, 'to_fund_id');
    }
}
