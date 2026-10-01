<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fund;
use App\Models\FundTransaction;
use App\Models\PaymentExpense;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchases on credit, kept in the payments & expenses table.
 * The purchase is a row with is_credit = 1: nothing is paid and no fund moves when it is recorded.
 * Paying it (all of it or any part, as many times as needed) adds a normal expense row linked to it (credit_id),
 * taken from the chosen fund like any expense. What is paid = the sum of those rows.
 * Answers in the debts' shape (kind "purchase") so the page shows them with the same cards and dialogs.
 */
class CreditPurchaseController extends Controller
{
    private const MESSAGES = [
        'creditor.required' => 'اكتب اسم البائع أو المحل',
        'amount.required' => 'اكتب المبلغ',
        'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر',
        'debt_date.required' => 'حدد تاريخ الشراء',
        'paid_on.required' => 'حدد تاريخ الدفع',
        'fund_id.exists' => 'الصندوق غير موجود',
        'due_date.after_or_equal' => 'آخر أجل يجب أن يكون بعد تاريخ الشراء',
    ];

    public function index()
    {
        $credits = PaymentExpense::where('is_credit', true)
            ->with(['creditPayments.fund:id,name'])
            ->orderByDesc('Payments_data')
            ->orderByDesc('id')
            ->get();

        return response()->json(['status' => 'success', 'data' => $credits->map(fn (PaymentExpense $p) => $this->present($p))]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $credit = PaymentExpense::create($this->columns($data) + [
            'individuals_id' => null,
            'payment_method' => 'بالدين',
            'transaction_type' => 'مصروف',
            'fund_id' => null,
            'Number_of_months' => 1,
            'is_credit' => true,
        ]);

        return response()->json(['status' => 'success', 'data' => $this->presentOne($credit)], 201);
    }

    public function update(Request $request)
    {
        $credit = $this->find($request);
        $data = $this->validated($request);
        $paid = $credit->creditPaid();
        if ($data['amount'] + 0.005 < $paid) {
            throw ValidationException::withMessages(['amount' => 'المبلغ أقل مما تم دفعه (' . $this->money($paid) . ')']);
        }
        $credit->update($this->columns($data));

        return response()->json(['status' => 'success', 'data' => $this->presentOne($credit)]);
    }

    /** Deleting the purchase keeps the payments already made: they stay expenses */
    public function destroy(Request $request)
    {
        $this->find($request)->delete();

        return response()->json(['status' => 'success']);
    }

    /** Pay all or part of the purchase: a normal expense, taken from the chosen fund */
    public function pay(Request $request)
    {
        $data = $request->validate([
            'debt_id' => 'required|integer',
            'amount' => 'required|numeric|min:0.01',
            'paid_on' => 'required|date',
            'fund_id' => 'nullable|exists:funds,id',
            'payment_method' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
        ], self::MESSAGES);

        $credit = PaymentExpense::where('is_credit', true)->findOrFail($data['debt_id']);

        DB::transaction(function () use ($credit, $data) {
            PaymentExpense::whereKey($credit->id)->lockForUpdate()->first();
            $left = $credit->creditRemaining();
            if ($left <= 0) {
                throw ValidationException::withMessages(['amount' => 'هذا الشراء مدفوع بالكامل']);
            }
            if ($data['amount'] > $left + 0.005) {
                throw ValidationException::withMessages(['amount' => 'المبلغ أكبر من الباقي (' . $this->money($left) . ')']);
            }

            $amount = round((float) $data['amount'], 2);
            $fundId = $data['fund_id'] ?? null;
            $payment = PaymentExpense::create([
                'individuals_id' => null,
                'amount' => $amount,
                'payment_method' => $data['payment_method'] ?? 'نقدا',
                'Payments_data' => CarbonImmutable::parse($data['paid_on'])->format('Y-m-d'),
                'amount_Nature' => $credit->amount_Nature,
                'transaction_type' => 'مصروف',
                'Occasion_Reason_numper' => 'دفع شراء بالدين - ' . $credit->creditor . ($credit->Occasion_Reason_numper ? ' (' . $credit->Occasion_Reason_numper . ')' : ''),
                'notes' => $data['notes'] ?? null,
                'fund_id' => $fundId,
                'Number_of_months' => 1,
                'credit_id' => $credit->id,
            ]);

            if ($fundId) {
                Fund::whereKey($fundId)->decrement('current_balance', $amount);
                // Same description as the payments page writes for an expense
                FundTransaction::create([
                    'fund_id' => $fundId,
                    'payment_expenses_id' => $payment->id,
                    'type' => 'سحب',
                    'amount' => $amount,
                    'transaction_date' => $payment->Payments_data,
                    'description' => 'دفع/مصروف (' . $payment->amount_Nature . ') - ' . $credit->creditor,
                    'created_by' => auth()->id(),
                ]);
            }
        });

        return response()->json(['status' => 'success', 'data' => $this->presentOne($credit)], 201);
    }

    /** Cancel a payment made on a purchase: the money goes back to its fund and the expense is removed */
    public function destroyPayment(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        $payment = PaymentExpense::whereNotNull('credit_id')->findOrFail($request->input('id'));
        $credit = PaymentExpense::findOrFail($payment->credit_id);

        DB::transaction(function () use ($payment) {
            $tx = FundTransaction::where('payment_expenses_id', $payment->id)->where('type', 'سحب')->first();
            if ($payment->fund_id) {
                Fund::whereKey($payment->fund_id)->increment('current_balance', (float) $payment->amount);
            }
            $tx?->delete();
            $payment->delete();
        });

        return response()->json(['status' => 'success', 'data' => $this->presentOne($credit)]);
    }

    /* ── Helpers ── */

    private function find(Request $request): PaymentExpense
    {
        $request->validate(['id' => 'required|integer']);

        return PaymentExpense::where('is_credit', true)->findOrFail($request->input('id'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'creditor' => 'required|string|max:255',
            'creditor_phone' => 'nullable|string|max:50',
            'title' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'debt_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:debt_date',
            'expense_nature' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
        ], self::MESSAGES);
        $data['amount'] = round((float) $data['amount'], 2);

        return $data;
    }

    /** Request fields → payment_expenses columns */
    private function columns(array $data): array
    {
        return [
            'creditor' => trim($data['creditor']),
            'creditor_phone' => $data['creditor_phone'] ?? null,
            'Occasion_Reason_numper' => $data['title'] ?? null,
            'amount' => $data['amount'],
            'Payments_data' => CarbonImmutable::parse($data['debt_date'])->format('Y-m-d'),
            'due_date' => $data['due_date'] ?? null,
            'amount_Nature' => trim((string) ($data['expense_nature'] ?? '')) ?: 'اخرى',
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, ',', '.') . ' د.ج';
    }

    private function presentOne(PaymentExpense $credit): array
    {
        return $this->present($credit->fresh()->load(['creditPayments.fund:id,name']));
    }

    /** The purchase in the debts' shape */
    private function present(PaymentExpense $p): array
    {
        $paid = round((float) $p->creditPayments->sum('amount'), 2);
        $remaining = max(0, round((float) $p->amount - $paid, 2));
        $status = $remaining <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'open');
        $due = $p->due_date ? CarbonImmutable::parse($p->due_date) : null;

        return [
            'id' => $p->id,
            'kind' => 'purchase',
            'creditor' => $p->creditor ?: '—',
            'creditor_phone' => $p->creditor_phone,
            'title' => $p->Occasion_Reason_numper,
            'amount' => (float) $p->amount,
            'paid_amount' => $paid,
            'debt_date' => $p->Payments_data ? substr((string) $p->Payments_data, 0, 10) : null,
            'due_date' => $due?->format('Y-m-d'),
            'fund_id' => null,
            'fund_name' => null,
            'expense_nature' => $p->amount_Nature,
            'notes' => $p->notes,
            'repaid' => $paid,
            'remaining' => $remaining,
            'status' => $status,
            'overdue' => $status !== 'paid' && $due && $due->lt(CarbonImmutable::today()),
            'created_at' => $p->created_at?->format('Y-m-d H:i'),
            'repayments' => $p->creditPayments->map(fn (PaymentExpense $x) => [
                'id' => $x->id,
                'amount' => (float) $x->amount,
                'paid_on' => $x->Payments_data ? substr((string) $x->Payments_data, 0, 10) : null,
                'fund_id' => $x->fund_id,
                'fund_name' => $x->fund?->name,
                'payment_method' => $x->payment_method,
                'notes' => $x->notes,
            ])->values(),
        ];
    }
}
