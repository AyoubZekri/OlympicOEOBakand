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

    /**
     * The match's report: a picture or a PDF of the paper report (jpg / png / webp / pdf, 5 MB at most), kept in
     * public/uploads/match_reports (its report made if it has none yet). Sent again: it replaces it.
     */
    public function saveImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'match_id' => 'required|exists:matches,id',
            'image' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ], [
            'match_id.required' => 'المباراة غير محددة',
            'match_id.exists' => 'المباراة غير موجودة',
            'image.required' => 'أرفق صورة أو ملف التقرير',
            'image.mimes' => 'التقرير يجب أن يكون صورة أو ملف PDF',
            'image.max' => 'حجم الملف أكبر من 5 ميغا',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $file = $request->file('image');
        $name = 'match_report_' . $request->match_id . '_' . time() . '.' . strtolower($file->getClientOriginalExtension());
        $folder = public_path('uploads/match_reports');
        if (!file_exists($folder)) {
            mkdir($folder, 0755, true);
        }
        $file->move($folder, $name);

        $report = AdministrativeMatchReport::firstOrNew(['match_id' => $request->match_id]);
        $report->image_path = asset('uploads/match_reports/' . $name);
        if (!$report->exists) {
            $report->admin_id = Auth::id();
            $report->report_date = now();
        }
        $report->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Report image saved successfully',
            'data' => $report->load('adminId')
        ]);
    }
}
