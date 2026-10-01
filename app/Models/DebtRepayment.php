<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A payment made on a debt (any part of it) */
class DebtRepayment extends Model
{
    protected $fillable = [
        'debt_id',
        'amount',
        'paid_on',
        'fund_id',
        'payment_method',
        'payment_expense_id',
        'fund_transaction_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'paid_on' => 'date',
    ];

    public function debt()
    {
        return $this->belongsTo(Debt::class);
    }

    public function fund()
    {
        return $this->belongsTo(Fund::class);
    }

    public function paymentExpense()
    {
        return $this->belongsTo(PaymentExpense::class);
    }

    public function fundTransaction()
    {
        return $this->belongsTo(FundTransaction::class);
    }
}
