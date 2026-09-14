<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AdministrativeMatchReport;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Auth;

class AdministrativeMatchReportController extends Controller
{
    public function show($match_id)
    {
        try {
            $report = AdministrativeMatchReport::where('match_id', $match_id)
                ->with('adminId')
                ->first();

            return response()->json([
                'status' => 'success',
                'data' => $report
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function save(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'match_id' => 'required|exists:matches,id',
            'travel_as_planned' => 'nullable|boolean',
            'attendance_status' => 'nullable|string|max:255',
            'equipment_status' => 'nullable|string|max:255',
            'equipment_notes' => 'nullable|string',
            'accommodation_catering_notes' => 'nullable|string',
            'organizational_incidents' => 'nullable|string',
            'disciplinary_incidents' => 'nullable|string',
            'refereeing_notes' => 'nullable|string',
            'required_actions' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->except(['match_id']);
            $data['report_date'] = now();
            if (!isset($data['admin_id'])) {
                $data['admin_id'] = Auth::id();
            }

            $report = AdministrativeMatchReport::updateOrCreate(
                ['match_id' => $request->match_id],
                $data
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Report saved successfully',
                'data' => $report->load('adminId')
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
