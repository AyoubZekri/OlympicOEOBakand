<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

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
                'notes' => $payment->notes,
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
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
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
        ]);

        return response()->json([
            'id' => (string) $payment->id,
            'memberId' => (string) $payment->individuals_id,
            'amount' => (float) $payment->amount,
            'paymentMethod' => $payment->payment_method,
            'paymentDate' => $payment->Payments_data,
            'amountNature' => $payment->amount_Nature,
            'postal_check' => $payment->postal_check,
            'notes' => $payment->notes,
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
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $payment = PaymentExpense::find($request->id);
        
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
        if ($request->has('notes')) {
            $payment->notes = $request->notes;
        }
        
        $payment->save();

        return response()->json([
            'id' => (string) $payment->id,
            'memberId' => (string) $payment->individuals_id,
            'amount' => (float) $payment->amount,
            'paymentMethod' => $payment->payment_method,
            'paymentDate' => $payment->Payments_data,
            'amountNature' => $payment->amount_Nature,
            'postal_check' => $payment->postal_check,
            'notes' => $payment->notes,
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
}
