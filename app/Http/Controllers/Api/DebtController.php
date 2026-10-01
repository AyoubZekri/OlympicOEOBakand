<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Debt;
use App\Models\DebtRepayment;
use App\Models\Fund;
use App\Models\FundTransaction;
use App\Models\PaymentExpense;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The club's debts.
 * - loan: money borrowed from someone, put into a fund (a "استلاف" transaction raises its balance);
 *   a repayment lowers the chosen fund's balance directly: it is not a fund operation.
 *   What has been paid is kept in the debt's paid_amount column.
 * - purchase: something bought and not paid yet; nothing moves until it is paid. A repayment is a real expense:
 *   it is recorded in the payments & expenses table (and withdrawn from the fund it is paid from).
 * Any part of a debt can be paid, as many times as needed, up to what is left.
 */
class DebtController extends Controller
{
    /** Fund transaction types written by the debts */
    public const TX_LOAN = 'استلاف';
    public const TX_REPAY = 'تسديد دين';

    private const MESSAGES = [
        'creditor.required' => 'اكتب اسم الدائن',
        'amount.required' => 'اكتب المبلغ',
        'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر',
        'fund_id.required' => 'اختر الصندوق',
        'fund_id.exists' => 'الصندوق غير موجود',
        'debt_date.required' => 'حدد تاريخ الدين',
        'paid_on.required' => 'حدد تاريخ التسديد',
        'due_date.after_or_equal' => 'تاريخ الاستحقاق يجب أن يكون بعد تاريخ الدين',
    ];

    public function index()
    {
        $debts = Debt::with(['fund:id,name', 'repayments.fund:id,name'])
            ->orderByDesc('debt_date')
            ->orderByDesc('id')
            ->get();
        $debts->each(fn (Debt $d) => $d->syncPaid());

        return response()->json(['status' => 'success', 'data' => $debts->map(fn (Debt $d) => $this->present($d))]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $debt = DB::transaction(function () use ($data) {
            $debt = Debt::create($data + ['created_by' => auth()->id()]);
            if ($debt->isLoan()) {
                $this->moveFund($debt->fund_id, $debt->amount);
                $debt->update(['fund_transaction_id' => $this->loanTransaction($debt)->id]);
            }

            return $debt;
        });

        return response()->json(['status' => 'success', 'data' => $this->presentOne($debt)], 201);
    }

    public function update(Request $request)
    {
        $request->validate(['id' => 'required|exists:debts,id']);
        $debt = Debt::findOrFail($request->input('id'));
        $data = $this->validated($request, $debt);

        $debt->syncPaid();
        $repaid = $debt->repaid();
        if ($data['amount'] + 0.005 < $repaid) {
            throw ValidationException::withMessages(['amount' => 'المبلغ أقل مما تم تسديده (' . number_format($repaid, 2, ',', '.') . ' د.ج)']);
        }

        DB::transaction(function () use ($debt, $data) {
            $oldFund = $debt->fund_id;
            $oldAmount = (float) $debt->amount;
            $debt->update($data);

            if ($debt->isLoan()) {
                // The borrowed money moves with the debt: out of the old fund, into the new one
                $this->moveFund($oldFund, -$oldAmount);
                $this->moveFund($debt->fund_id, (float) $debt->amount);
                $tx = $debt->fundTransaction;
                if ($tx) {
                    $tx->update([
                        'fund_id' => $debt->fund_id,
                        'amount' => $debt->amount,
                        'transaction_date' => $debt->debt_date,
                        'description' => $this->loanDescription($debt),
                    ]);
                } else {
                    $debt->update(['fund_transaction_id' => $this->loanTransaction($debt)->id]);
                }
            }
        });

        return response()->json(['status' => 'success', 'data' => $this->presentOne($debt->fresh())]);
    }

    /** Deleting a debt cancels everything it did: its repayments, and the borrowed money in the fund */
    public function destroy(Request $request)
    {
        $request->validate(['id' => 'required|exists:debts,id']);
        $debt = Debt::findOrFail($request->input('id'));

        DB::transaction(function () use ($debt) {
            $debt->repayments->each(fn (DebtRepayment $r) => $this->cancelRepayment($r));
            if ($debt->isLoan()) {
                $this->moveFund($debt->fund_id, -(float) $debt->amount);
                $tx = $debt->fundTransaction;
                $debt->update(['fund_transaction_id' => null]);
                $tx?->delete();
            }
            $debt->delete();
        });

        return response()->json(['status' => 'success']);
    }

    /** Pay all or part of a debt */
    public function repay(Request $request)
    {
        $data = $request->validate([
            'debt_id' => 'required|exists:debts,id',
            'amount' => 'required|numeric|min:0.01',
            'paid_on' => 'required|date',
            'fund_id' => 'nullable|exists:funds,id',
            'payment_method' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
        ], self::MESSAGES);

        $debt = Debt::findOrFail($data['debt_id']);
        $debt->syncPaid();

        DB::transaction(function () use ($debt, $data) {
            $debt = Debt::whereKey($debt->id)->lockForUpdate()->first();
            $left = $debt->remaining();
            if ($left <= 0) {
                throw ValidationException::withMessages(['amount' => 'هذا الدين مسدد بالكامل']);
            }
            if ($data['amount'] > $left + 0.005) {
                throw ValidationException::withMessages(['amount' => 'المبلغ أكبر من الباقي (' . number_format($left, 2, ',', '.') . ' د.ج)']);
            }

            $amount = round((float) $data['amount'], 2);
            $fundId = $data['fund_id'] ?? null;
            $repayment = new DebtRepayment([
                'debt_id' => $debt->id,
                'amount' => $amount,
                'paid_on' => $data['paid_on'],
                'fund_id' => $fundId,
                'payment_method' => $data['payment_method'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            if ($debt->isLoan()) {
                // Paying back the lender: the money leaves the fund, without a fund operation
                $this->moveFund($fundId, -$amount);
            } else {
                // A purchase paid: an expense in the payments & expenses table
                $nature = $debt->expense_nature ?: 'اخرى';
                $payment = PaymentExpense::create([
                    'individuals_id' => null,
                    'amount' => $amount,
                    'payment_method' => $data['payment_method'] ?? 'نقدا',
                    'Payments_data' => CarbonImmutable::parse($data['paid_on'])->format('Y-m-d'),
                    'amount_Nature' => $nature,
                    'transaction_type' => 'مصروف',
                    'Occasion_Reason_numper' => 'تسديد دين - ' . $debt->creditor . ($debt->title ? ' (' . $debt->title . ')' : ''),
                    'notes' => $data['notes'] ?? null,
                    'fund_id' => $fundId,
                    'Number_of_months' => 1,
                ]);
                $repayment->payment_expense_id = $payment->id;

                if ($fundId) {
                    $this->moveFund($fundId, -$amount);
                    // Same description as the payments page writes, so the operations list shows it the same way
                    $repayment->fund_transaction_id = FundTransaction::create([
                        'fund_id' => $fundId,
                        'payment_expenses_id' => $payment->id,
                        'type' => 'سحب',
                        'amount' => $amount,
                        'transaction_date' => $data['paid_on'],
                        'description' => 'دفع/مصروف (' . $nature . ') - تسديد دين: ' . $debt->creditor,
                        'created_by' => auth()->id(),
                    ])->id;
                }
            }

            $repayment->save();
            $debt->increment('paid_amount', $amount);
        });

        return response()->json(['status' => 'success', 'data' => $this->presentOne($debt->fresh())], 201);
    }

    /** Cancel a repayment: the money goes back to its fund, its expense is removed */
    public function destroyRepayment(Request $request)
    {
        $request->validate(['id' => 'required|exists:debt_repayments,id']);
        $repayment = DebtRepayment::findOrFail($request->input('id'));
        $debt = $repayment->debt;

        DB::transaction(function () use ($repayment, $debt) {
            $this->cancelRepayment($repayment);
            $debt->syncPaid();
        });

        return response()->json(['status' => 'success', 'data' => $this->presentOne($debt->fresh())]);
    }

    /* ── Helpers ── */

    /** $debt: the debt being edited (its kind does not change) */
    private function validated(Request $request, ?Debt $debt = null): array
    {
        $kind = $debt ? $debt->kind : $request->input('kind');

        $data = $request->validate([
            'kind' => $debt ? 'nullable' : 'required|in:loan,purchase',
            'creditor' => 'required|string|max:255',
            'creditor_phone' => 'nullable|string|max:50',
            'title' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'debt_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:debt_date',
            'fund_id' => $kind === Debt::LOAN ? 'required|exists:funds,id' : 'nullable',
            'expense_nature' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
        ], self::MESSAGES + ['kind.required' => 'اختر نوع الدين']);

        $data['kind'] = $kind;
        $data['amount'] = round((float) $data['amount'], 2);
        if ($kind === Debt::LOAN) {
            $data['expense_nature'] = null;
        } else {
            $data['fund_id'] = null;
            $data['expense_nature'] = trim((string) ($data['expense_nature'] ?? '')) ?: 'اخرى';
        }

        return $data;
    }

    private function cancelRepayment(DebtRepayment $r): void
    {
        $tx = $r->fundTransaction;
        $payment = $r->paymentExpense;
        if ($tx) {
            $this->moveFund($tx->fund_id, (float) $tx->amount);
        } elseif ($r->fund_id && $r->debt?->isLoan()) {
            // A loan repayment lowered its fund directly: put the money back
            $this->moveFund($r->fund_id, (float) $r->amount);
        }
        $r->delete();
        $tx?->delete();
        $payment?->delete();
    }

    private function moveFund($fundId, float $delta): void
    {
        if (! $fundId || ! $delta) {
            return;
        }
        // One UPDATE on the column, so nothing else can overwrite it in between
        $query = Fund::whereKey($fundId);
        $delta > 0 ? $query->increment('current_balance', $delta) : $query->decrement('current_balance', -$delta);
    }

    private function loanDescription(Debt $debt): string
    {
        return 'استلاف من ' . $debt->creditor . ($debt->title ? ' (' . $debt->title . ')' : '');
    }

    private function loanTransaction(Debt $debt): FundTransaction
    {
        return FundTransaction::create([
            'fund_id' => $debt->fund_id,
            'type' => self::TX_LOAN,
            'amount' => $debt->amount,
            'transaction_date' => $debt->debt_date,
            'description' => $this->loanDescription($debt),
            'created_by' => auth()->id(),
        ]);
    }

    private function presentOne(Debt $debt): array
    {
        return $this->present($debt->load(['fund:id,name', 'repayments.fund:id,name']));
    }

    private function present(Debt $d): array
    {
        $repaid = $d->repaid();
        $remaining = $d->remaining();
        $status = $remaining <= 0 ? 'paid' : ($repaid > 0 ? 'partial' : 'open');

        return [
            'id' => $d->id,
            'kind' => $d->kind,
            'creditor' => $d->creditor,
            'creditor_phone' => $d->creditor_phone,
            'title' => $d->title,
            'amount' => (float) $d->amount,
            'paid_amount' => $repaid,
            'debt_date' => $d->debt_date?->format('Y-m-d'),
            'due_date' => $d->due_date?->format('Y-m-d'),
            'fund_id' => $d->fund_id,
            'fund_name' => $d->fund?->name,
            'expense_nature' => $d->expense_nature,
            'notes' => $d->notes,
            'repaid' => $repaid,
            'remaining' => $remaining,
            'status' => $status,
            'overdue' => $status !== 'paid' && $d->due_date && $d->due_date->lt(CarbonImmutable::today()),
            'created_at' => $d->created_at?->format('Y-m-d H:i'),
            'repayments' => $d->repayments->map(fn (DebtRepayment $r) => [
                'id' => $r->id,
                'amount' => (float) $r->amount,
                'paid_on' => $r->paid_on?->format('Y-m-d'),
                'fund_id' => $r->fund_id,
                'fund_name' => $r->fund?->name,
                'payment_method' => $r->payment_method,
                'notes' => $r->notes,
            ])->values(),
        ];
    }
}
