<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

use App\Models\Fund;
use App\Models\FundTransaction;

class PaymentController extends Controller
{
    public function index()
    {
        $payments = PaymentExpense::orderBy('created_at', 'desc')->get();
        
        $formatted = $payments->map(function($payment) {
            return [
                'id' => (string) $payment->id,
                'memberId' => (string) $payment->individuals_id,
                'amount' => (float) $payment->amount,
                'paymentMethod' => $payment->payment_method,
                'paymentDate' => $payment->Payments_data,
                'checkNumber' => $payment->Occasion_Reason_numper, // we mapped conditionally
                'amountNature' => $payment->amount_Nature,
                'dateFrom' => $payment->start_date ? Carbon::parse($payment->start_date)->format('Y-m-d') : null,
                'dateTo' => $payment->end_date ? Carbon::parse($payment->end_date)->format('Y-m-d') : null,
                'postal_check' => $payment->postal_check,
                'receipt_file' => $payment->receipt_file,
                'notes' => $payment->notes,
                'fund_id' => (string) $payment->fund_id,
                // other conditionals can be stored in notes or other fields if there is no dedicated column
            ];
        });
        
        return response()->json($formatted);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'memberId' => 'nullable|exists:individuals,id',
            'amount' => 'required|numeric',
            'paymentMethod' => 'required|string',
            'paymentDate' => 'required|string',
            'amountNature' => 'required|string',
            'receipt_file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'fund_id' => 'nullable|exists:funds,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $receiptPath = null;
        if ($request->hasFile('receipt_file')) {
            $receiptPath = $request->file('receipt_file')->store('receipts', 'public');
        }

        $payment = PaymentExpense::create([
            'individuals_id' => $request->memberId,
            'amount' => $request->amount,
            'payment_method' => $request->paymentMethod,
            'Payments_data' => $request->paymentDate,
            'amount_Nature' => $request->amountNature,
            'Occasion_Reason_numper' => $request->checkNumber ?? $request->installmentNumber ?? $request->occasion ?? null,
            'postal_check' => $request->postal_check ?? null,
            'start_date' => $request->dateFrom,
            'end_date' => $request->dateTo,
            'notes' => $request->notes,
            'receipt_file' => $receiptPath,
            'fund_id' => $request->fund_id,
        ]);

        if ($payment->fund_id) {
            $fund = Fund::find($payment->fund_id);
            if ($fund) {
                $isDeposit = ($payment->amount_Nature === 'إرجاع سلفة');
                if ($isDeposit) {
                    $fund->current_balance += $payment->amount;
                } else {
                    $fund->current_balance -= $payment->amount;
                }
                $fund->save();

                $individual = \App\Models\Individual::find($payment->individuals_id);
                $memberName = $individual ? ($individual->first_name . ' ' . $individual->last_name) : 'مصروف عام';
                
                FundTransaction::create([
                    'fund_id' => $fund->id,
                    'type' => $isDeposit ? 'إيداع' : 'سحب',
                    'amount' => $payment->amount,
                    'transaction_date' => $payment->Payments_data ?? now(),
                    'description' => ($isDeposit ? 'إرجاع سلفة - ' : 'دفع/مصروف (' . $payment->amount_Nature . ') - ') . $memberName,
                    'created_by' => auth()->id() ?? null,
                ]);
            }
        }

        return response()->json([
            'id' => (string) $payment->id,
            'memberId' => (string) $payment->individuals_id,
            'amount' => (float) $payment->amount,
            'paymentMethod' => $payment->payment_method,
            'paymentDate' => $payment->Payments_data,
            'amountNature' => $payment->amount_Nature,
            'postal_check' => $payment->postal_check,
            'receipt_file' => $payment->receipt_file,
            'notes' => $payment->notes,
            'fund_id' => (string) $payment->fund_id,
        ], 201);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:payment_expenses,id',
            'memberId' => 'nullable|exists:individuals,id',
            'amount' => 'sometimes|numeric',
            'paymentMethod' => 'sometimes|string',
            'paymentDate' => 'sometimes|string',
            'amountNature' => 'sometimes|string',
            'receipt_file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'fund_id' => 'nullable|exists:funds,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $payment = PaymentExpense::find($request->id);
        $old_amount = (float) $payment->amount;
        $old_fund_id = $payment->fund_id;
        $old_isDeposit = ($payment->amount_Nature === 'إرجاع سلفة');
        
        if ($request->has('memberId')) {
            $payment->individuals_id = $request->memberId;
        }
        if ($request->has('amount')) {
            $payment->amount = $request->amount;
        }
        if ($request->has('paymentMethod')) {
            $payment->payment_method = $request->paymentMethod;
        }
        if ($request->has('paymentDate')) {
            $payment->Payments_data = $request->paymentDate;
        }
        if ($request->has('amountNature')) {
            $payment->amount_Nature = $request->amountNature;
        }
        if ($request->has('dateFrom')) {
            $payment->start_date = $request->dateFrom;
        }
        if ($request->has('dateTo')) {
            $payment->end_date = $request->dateTo;
        }
        // If they send checkNumber, it could be occasion or installment based on legacy
        if ($request->has('checkNumber') || $request->has('installmentNumber') || $request->has('occasion')) {
            $payment->Occasion_Reason_numper = $request->checkNumber ?? $request->installmentNumber ?? $request->occasion ?? $payment->Occasion_Reason_numper;
        }
        if ($request->has('postal_check')) {
            $payment->postal_check = $request->postal_check;
        }
        if ($request->hasFile('receipt_file')) {
            $path = $request->file('receipt_file')->store('receipts', 'public');
            $payment->receipt_file = $path;
        }
        if ($request->has('notes')) {
            $payment->notes = $request->notes;
        }
        if ($request->has('fund_id')) {
            $payment->fund_id = $request->fund_id;
        }
        
        $payment->save();

        $new_amount = (float) $payment->amount;
        $new_fund_id = $payment->fund_id;
        $new_isDeposit = ($payment->amount_Nature === 'إرجاع سلفة');

        if ($old_fund_id == $new_fund_id && $old_isDeposit == $new_isDeposit && $new_fund_id != null) {
            if ($old_amount != $new_amount) {
                $fund = Fund::find($new_fund_id);
                if ($fund) {
                    $diff = $new_amount - $old_amount; 
                    if ($diff > 0) {
                        if ($new_isDeposit) {
                            $fund->current_balance += $diff;
                            $type = 'إيداع';
                        } else {
                            $fund->current_balance -= $diff;
                            $type = 'سحب';
                        }
                        $fund->save();
                        FundTransaction::create([
                            'fund_id' => $fund->id,
                            'type' => $type,
                            'amount' => $diff,
                            'transaction_date' => $payment->Payments_data ?? now(),
                            'description' => 'تعديل دفعة - الفارق المستحق',
                            'created_by' => auth()->id() ?? null,
                        ]);
                    } else if ($diff < 0) {
                        $diff = abs($diff); 
                        if ($new_isDeposit) {
                            $fund->current_balance -= $diff;
                            $type = 'سحب';
                        } else {
                            $fund->current_balance += $diff;
                            $type = 'إرجاع';
                        }
                        $fund->save();
                        FundTransaction::create([
                            'fund_id' => $fund->id,
                            'type' => $type,
                            'amount' => $diff,
                            'transaction_date' => $payment->Payments_data ?? now(),
                            'description' => 'تعديل دفعة - إرجاع الفارق',
                            'created_by' => auth()->id() ?? null,
                        ]);
                    }
                }
            }
        } else {
            // Complex case where fund or nature changed. 
            if ($old_fund_id) {
                $old_fund = Fund::find($old_fund_id);
                if ($old_fund) {
                    if ($old_isDeposit) {
                        $old_fund->current_balance -= $old_amount;
                    } else {
                        $old_fund->current_balance += $old_amount;
                    }
                    $old_fund->save();
                    FundTransaction::create([
                        'fund_id' => $old_fund->id,
                        'type' => 'إرجاع',
                        'amount' => $old_amount,
                        'transaction_date' => now(),
                        'description' => 'إلغاء لارتباط الصندوق القديم',
                        'created_by' => auth()->id() ?? null,
                    ]);
                }
            }
            if ($new_fund_id) {
                $new_fund = Fund::find($new_fund_id);
                if ($new_fund) {
                    if ($new_isDeposit) {
                        $new_fund->current_balance += $new_amount;
                    } else {
                        $new_fund->current_balance -= $new_amount;
                    }
                    $new_fund->save();
                    $individual = \App\Models\Individual::find($payment->individuals_id);
                    $memberName = $individual ? ($individual->first_name . ' ' . $individual->last_name) : 'مصروف عام';
                    FundTransaction::create([
                        'fund_id' => $new_fund->id,
                        'type' => $new_isDeposit ? 'إيداع' : 'سحب',
                        'amount' => $new_amount,
                        'transaction_date' => $payment->Payments_data ?? now(),
                        'description' => ($new_isDeposit ? 'إرجاع سلفة - ' : 'دفع/مصروف (' . $payment->amount_Nature . ') - ') . $memberName,
                        'created_by' => auth()->id() ?? null,
                    ]);
                }
            }
        }

        return response()->json([
            'id' => (string) $payment->id,
            'memberId' => (string) $payment->individuals_id,
            'amount' => (float) $payment->amount,
            'paymentMethod' => $payment->payment_method,
            'paymentDate' => $payment->Payments_data,
            'amountNature' => $payment->amount_Nature,
            'postal_check' => $payment->postal_check,
            'receipt_file' => $payment->receipt_file,
            'notes' => $payment->notes,
            'fund_id' => (string) $payment->fund_id,
        ]);
    }

    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:payment_expenses,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        PaymentExpense::destroy($request->id);
        return response()->json(['success' => true]);
    }

    public function returnPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:payment_expenses,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $payment = PaymentExpense::find($request->id);

        if ($payment->fund_id) {
            $fund = Fund::find($payment->fund_id);
            if ($fund) {
                // If the original payment was a return advance (deposit), we subtract. Otherwise, we add.
                $isDeposit = ($payment->amount_Nature === 'إرجاع سلفة');
                
                if ($isDeposit) {
                    $fund->current_balance -= $payment->amount;
                } else {
                    $fund->current_balance += $payment->amount;
                }
                $fund->save();

                FundTransaction::create([
                    'fund_id' => $fund->id,
                    'type' => 'إرجاع',
                    'amount' => $payment->amount,
                    'transaction_date' => now(),
                    'description' => 'إسترجاع مبلغ الدفعة/المصروف الملغاة',
                    'created_by' => auth()->id() ?? null,
                ]);
            }
        }

        // Delete the payment record
        $payment->delete();

        return response()->json(['success' => true]);
    }
}
