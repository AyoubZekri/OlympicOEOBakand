<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One payment made on a purchase on credit (the purchase's row keeps the total in paid_amount) */
class CreditPayment extends Model
{
    protected $fillable = [
        'payment_expense_id',
        'amount',
        'paid_on',
        'fund_id',
        'payment_method',
        'notes',
        'fund_transaction_id',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'paid_on' => 'date',
    ];

    public function purchase()
    {
        return $this->belongsTo(PaymentExpense::class, 'payment_expense_id');
    }

    public function fund()
    {
        return $this->belongsTo(Fund::class);
    }

    public function fundTransaction()
    {
        return $this->belongsTo(FundTransaction::class);
    }
}
