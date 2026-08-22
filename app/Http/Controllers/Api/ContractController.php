<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function index()
    {
        $contracts = Contract::with(['individual', 'addedBy', 'installments'])->get();
        return response()->json($contracts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'individuals_id' => 'required|exists:individuals,id',
            'numper' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'Contract_value' => 'nullable|numeric|min:0',
            'Number_payments' => 'nullable|integer|min:0',
            'Monthly_Salary' => 'nullable|numeric|min:0',
            'Winning_Bonus' => 'nullable|numeric|min:0',
            'Goals_Bonus' => 'nullable|numeric|min:0',
            'nots' => 'nullable|string',
            'status' => 'nullable|string',
            'added_by' => 'nullable|exists:users,id',
        ]);

        $contract = Contract::create($validated);

        if ($request->has('installments') && is_array($request->installments)) {
            foreach ($request->installments as $inst) {
                $contract->installments()->create([
                    'installment_number' => $inst['installment_number'],
                    'amount' => $inst['amount'],
                ]);
            }
        }

        return response()->json(['message' => 'Contract created successfully', 'contract' => $contract->load('installments')], 201);
    }

    public function show(Request $request)
    {
        $request->validate(['id' => 'required|exists:contracts,id']);
        
        $contract = Contract::with(['individual', 'addedBy', 'installments'])->findOrFail($request->id);
        return response()->json($contract);
    }

    public function update(Request $request)
    {
        $request->validate(['id' => 'required|exists:contracts,id']);
        $contract = Contract::findOrFail($request->id);

        $validated = $request->validate([
            'individuals_id' => 'sometimes|exists:individuals,id',
            'numper' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'Contract_value' => 'nullable|numeric|min:0',
            'Number_payments' => 'nullable|integer|min:0',
            'Monthly_Salary' => 'nullable|numeric|min:0',
            'Winning_Bonus' => 'nullable|numeric|min:0',
            'Goals_Bonus' => 'nullable|numeric|min:0',
            'nots' => 'nullable|string',
            'status' => 'nullable|string',
            'added_by' => 'nullable|exists:users,id',
        ]);

        $contract->update($validated);

        if ($request->has('installments') && is_array($request->installments)) {
            $contract->installments()->delete();
            foreach ($request->installments as $inst) {
                $contract->installments()->create([
                    'installment_number' => $inst['installment_number'],
                    'amount' => $inst['amount'],
                ]);
            }
        }

        return response()->json(['message' => 'Contract updated successfully', 'contract' => $contract->load('installments')]);
    }

    public function destroy(Request $request)
    {
        $request->validate(['id' => 'required|exists:contracts,id']);
        
        $contract = Contract::findOrFail($request->id);
        $contract->delete();

        return response()->json(['message' => 'Contract deleted successfully']);
    }
}
