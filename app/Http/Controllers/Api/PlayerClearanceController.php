<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PlayerClearance;
use Illuminate\Support\Facades\Validator;
use Exception;

class PlayerClearanceController extends Controller
{
    public function show($player_id)
    {
        try {
            $clearance = PlayerClearance::where('player_id', $player_id)->first();
            return response()->json([
                'status' => 'success',
                'data' => $clearance
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch player clearance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateOrCreate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'player_id' => 'required|exists:individuals,id',
            'exit_date' => 'nullable|date',
            'exit_reason' => 'nullable|string|max:255',
            
            'equipment_status' => 'nullable|string|max:255',
            'equipment_notes' => 'nullable|string',
            'equipment_manager_id' => 'nullable|exists:users,id',
            'equipment_cleared_at' => 'nullable|date',
            
            'admin_status' => 'nullable|string|max:255',
            'admin_id' => 'nullable|exists:users,id',
            'admin_cleared_at' => 'nullable|date',
            
            'sporting_status' => 'nullable|string|max:255',
            'sporting_director_id' => 'nullable|exists:users,id',
            'sporting_cleared_at' => 'nullable|date',
            
            'financial_status' => 'nullable|string|max:255',
            'finance_manager_id' => 'nullable|exists:users,id',
            'finance_cleared_at' => 'nullable|date',
            
            'medical_status' => 'nullable|string|max:255',
            'medical_staff_id' => 'nullable|exists:users,id',
            'medical_cleared_at' => 'nullable|date',
            
            'general_notes' => 'nullable|string',
            'player_signature' => 'nullable|boolean',
            'player_signed_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $clearance = PlayerClearance::updateOrCreate(
                ['player_id' => $request->player_id],
                $request->all()
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Player clearance updated successfully',
                'data' => $clearance
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update player clearance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($player_id)
    {
        try {
            $clearance = PlayerClearance::where('player_id', $player_id)->first();
            if (!$clearance) {
                return response()->json(['status' => 'error', 'message' => 'Player clearance not found'], 404);
            }
            $clearance->delete();
            return response()->json(['status' => 'success', 'message' => 'Player clearance deleted successfully']);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to delete player clearance', 'error' => $e->getMessage()], 500);
        }
    }
}
