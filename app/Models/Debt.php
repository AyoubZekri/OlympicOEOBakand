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
        'paid_amount',
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
        'paid_amount' => 'float',
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

    /** What has been paid: the paid_amount column */
    public function repaid(): float
    {
        return round((float) $this->paid_amount, 2);
    }

    public function remaining(): float
    {
        return max(0, round((float) $this->amount - (float) $this->paid_amount, 2));
    }

    /**
     * paid_amount = the sum of the repayments. Called after every change; also corrects the column when a
     * repayment disappeared with its expense (an expense deleted from the payments page).
     */
    public function syncPaid(): void
    {
        $paid = round((float) DebtRepayment::where('debt_id', $this->id)->sum('amount'), 2);
        if (abs($paid - (float) $this->paid_amount) > 0.001) {
            $this->forceFill(['paid_amount' => $paid])->saveQuietly();
        }
    }
}
