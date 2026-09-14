<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PlayerMedicalRecord;
use Illuminate\Support\Facades\Validator;
use Exception;

class PlayerMedicalRecordController extends Controller
{
    public function index()
    {
        try {
            $records = PlayerMedicalRecord::with(['playerId', 'doctorId'])->orderBy('created_at', 'desc')->get();
            return response()->json([
                'status' => 'success',
                'data' => $records
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch medical records',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'player_id' => 'required|exists:individuals,id',
            'doctor_id' => 'required|exists:individuals,id',
            'injury_date' => 'required|date',
            'incident_location' => 'nullable|string|max:255',
            'injury_nature' => 'nullable|string|max:255',
            'diagnosis' => 'nullable|string',
            'initial_recommendation' => 'nullable|string|max:255',
            'last_exam_date' => 'nullable|date',
            'medical_decision' => 'nullable|string|max:255',
            'restrictions' => 'nullable|string',
            'next_exam_date' => 'nullable|date',
            'record_status' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $record = PlayerMedicalRecord::create($request->all());
            return response()->json([
                'status' => 'success',
                'message' => 'Medical record created successfully',
                'data' => $record->load(['playerId', 'doctorId'])
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create medical record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'player_id' => 'sometimes|exists:individuals,id',
            'doctor_id' => 'sometimes|exists:individuals,id',
            'injury_date' => 'sometimes|date',
            'incident_location' => 'nullable|string|max:255',
            'injury_nature' => 'nullable|string|max:255',
            'diagnosis' => 'nullable|string',
            'initial_recommendation' => 'nullable|string|max:255',
            'last_exam_date' => 'nullable|date',
            'medical_decision' => 'nullable|string|max:255',
            'restrictions' => 'nullable|string',
            'next_exam_date' => 'nullable|date',
            'record_status' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $record = PlayerMedicalRecord::find($id);
            if (!$record) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Medical record not found'
                ], 404);
            }

            $record->update($request->all());
            return response()->json([
                'status' => 'success',
                'message' => 'Medical record updated successfully',
                'data' => $record->load(['playerId', 'doctorId'])
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update medical record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $record = PlayerMedicalRecord::find($id);
            if (!$record) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Medical record not found'
                ], 404);
            }

            $record->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Medical record deleted successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete medical record',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
