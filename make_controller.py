import os

file_path = 'app/Http/Controllers/Api/AdministrativeMatchReportController.php'
content = '''<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AdministrativeMatchReport;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Auth;

class AdministrativeMatchReportController extends Controller
{
    public function show()
    {
        try {
             = AdministrativeMatchReport::where('match_id', )
                ->with('adminId')
                ->first();

            return response()->json([
                'status' => 'success',
                'data' => 
            ]);
        } catch (Exception ) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch report',
                'error' => ->getMessage()
            ], 500);
        }
    }

    public function save(Request )
    {
         = Validator::make(->all(), [
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

        if (->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => ->errors()
            ], 422);
        }

        try {
             = ->except(['match_id']);
            ['report_date'] = now();
            if (!isset(['admin_id'])) {
                ['admin_id'] = Auth::id();
            }

             = AdministrativeMatchReport::updateOrCreate(
                ['match_id' => ->match_id],
                
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Report saved successfully',
                'data' => ->load('adminId')
            ]);
        } catch (Exception ) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save report',
                'error' => ->getMessage()
            ], 500);
        }
    }
}
'''
with open(file_path, 'w', encoding='utf-8', newline='\\n') as f:
    f.write(content)
print("Done")
