<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FundController extends Controller
{
    public function index()
    {
        $funds = Fund::all();
        
        $formatted = $funds->map(function($fund) {
            return [
                'id' => (string) $fund->id,
                'name' => $fund->name,
                'icon' => $fund->type, // type stores the icon name: bank, mail, wallet
                'initialBalance' => (float) $fund->current_balance,
            ];
        });
        
        return response()->json($formatted);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'icon' => 'required|string',
            'initialBalance' => 'nullable|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $fund = Fund::create([
            'name' => $request->name,
            'type' => $request->icon,
            'current_balance' => $request->initialBalance ?? 0,
            'status' => true,
        ]);

        return response()->json([
            'id' => (string) $fund->id,
            'name' => $fund->name,
            'icon' => $fund->type,
            'initialBalance' => (float) $fund->current_balance,
        ], 201);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:funds,id',
            'name' => 'sometimes|string',
            'icon' => 'sometimes|string',
            'initialBalance' => 'sometimes|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $fund = Fund::find($request->id);
        
        if ($request->has('name')) {
            $fund->name = $request->name;
        }
        if ($request->has('icon')) {
            $fund->type = $request->icon;
        }
        if ($request->has('initialBalance')) {
            $fund->current_balance = $request->initialBalance;
        }
        
        $fund->save();

        return response()->json([
            'id' => (string) $fund->id,
            'name' => $fund->name,
            'icon' => $fund->type,
            'initialBalance' => (float) $fund->current_balance,
        ]);
    }

    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:funds,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        Fund::destroy($request->id);
        return response()->json(['success' => true]);
    }
}
