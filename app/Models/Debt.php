<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A debt of the club: a loan put into a fund, or a purchase not paid yet */
class Debt extends Model
{
    public const LOAN = 'loan';
    public const PURCHASE = 'purchase';

    protected $fillable = [
        'kind',
        'creditor',
        'creditor_phone',
        'title',
        'amount',
        'debt_date',
        'due_date',
        'fund_id',
        'fund_transaction_id',
        'expense_nature',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'debt_date' => 'date',
        'due_date' => 'date',
    ];

    public function repayments()
    {
        return $this->hasMany(DebtRepayment::class)->orderByDesc('paid_on')->orderByDesc('id');
    }

    public function fund()
    {
        return $this->belongsTo(Fund::class);
    }

    public function fundTransaction()
    {
        return $this->belongsTo(FundTransaction::class);
    }

    public function isLoan(): bool
    {
        return $this->kind === self::LOAN;
    }

    public function repaid(): float
    {
        return round((float) $this->repayments()->sum('amount'), 2);
    }
}
