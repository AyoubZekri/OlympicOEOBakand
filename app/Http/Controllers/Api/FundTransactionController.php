<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FundTransaction;
use App\Models\Fund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class FundTransactionController extends Controller
{
    public function index()
    {
        $transactions = FundTransaction::orderBy('transaction_date', 'desc')->get();
        
        $formatted = $transactions->map(function($transaction) {
            return [
                'id' => (string) $transaction->id,
                'fundId' => (string) $transaction->fund_id,
                'type' => $transaction->type, // إيداع, سحب, تحويل
                'amount' => (float) $transaction->amount,
                'date' => $transaction->transaction_date ? Carbon::parse($transaction->transaction_date)->format('Y-m-d') : null,
                'description' => $transaction->description,
                'toFundId' => $transaction->to_fund_id ? (string) $transaction->to_fund_id : null,
            ];
        });
        
        return response()->json($formatted);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fundId' => 'required|exists:funds,id',
            'type' => 'required|string', // 'إيداع', 'سحب', 'تحويل'
            'amount' => 'required|numeric',
            'date' => 'required|date',
            'description' => 'nullable|string',
            'toFundId' => 'nullable|exists:funds,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $fund = Fund::find($request->fundId);

        // Check for sufficient balance for withdrawal and transfer
        if (($request->type === 'سحب' || $request->type === 'تحويل') && $request->amount > $fund->current_balance) {
            return response()->json(['error' => 'الرصيد غير كافٍ لإتمام العملية.'], 400);
        }

        $transaction = FundTransaction::create([
            'fund_id' => $request->fundId,
            'type' => $request->type,
            'amount' => $request->amount,
            'transaction_date' => $request->date,
            'description' => $request->description ?? '',
            'to_fund_id' => $request->toFundId,
            'created_by' => auth()->id() ?? null,
        ]);

        // Update Fund Balances
        if ($request->type === 'إيداع') {
            $fund->current_balance += $request->amount;
        } elseif ($request->type === 'سحب' || $request->type === 'تحويل') {
            $fund->current_balance -= $request->amount;
        }
        $fund->save();

        if ($request->type === 'تحويل' && $request->toFundId) {
            $toFund = Fund::find($request->toFundId);
            $toFund->current_balance += $request->amount;
            $toFund->save();
        }

        return response()->json([
            'id' => (string) $transaction->id,
            'fundId' => (string) $transaction->fund_id,
            'type' => $transaction->type,
            'amount' => (float) $transaction->amount,
            'date' => Carbon::parse($transaction->transaction_date)->format('Y-m-d'),
            'description' => $transaction->description,
            'toFundId' => $transaction->to_fund_id ? (string) $transaction->to_fund_id : null,
        ], 201);
    }

    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:fund_transactions,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $transaction = FundTransaction::find($request->id);
        
        // Revert Balances
        if ($transaction) {
            $fund = Fund::find($transaction->fund_id);
            if ($fund) {
                if ($transaction->type === 'إيداع') {
                    $fund->current_balance -= $transaction->amount;
                } elseif ($transaction->type === 'سحب' || $transaction->type === 'تحويل') {
                    $fund->current_balance += $transaction->amount;
                }
                $fund->save();
            }

            if ($transaction->type === 'تحويل' && $transaction->to_fund_id) {
                $toFund = Fund::find($transaction->to_fund_id);
                if ($toFund) {
                    $toFund->current_balance -= $transaction->amount;
                    $toFund->save();
                }
            }
            $transaction->delete();
        }
        
        return response()->json(['success' => true]);
    }
}
