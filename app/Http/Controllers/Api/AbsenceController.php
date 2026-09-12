<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppAbsence;
use Illuminate\Http\Request;

class AbsenceController extends Controller
{
    /**
     * Get all absence records from app_absences table.
     * 
     * Query params:
     *   - team_id         : filter by player's team
     *   - event_category  : 'تدريب' | etc.
     *   - justification_status : 'none' | 'pending' | 'accepted' | 'rejected'
     */
    public function index(Request $request)
    {
        $query = AppAbsence::with([
            'playerId',
            'playerId.team',
            'trainingSessionId',
        ])->orderByDesc('event_date');

        if ($request->filled('team_id')) {
            $query->whereHas('playerId', fn($q) => $q->where('team_id', $request->team_id));
        }

        if ($request->filled('event_category')) {
            $query->where('event_category', $request->event_category);
        }

        if ($request->filled('justification_status')) {
            $query->where('justification_status', $request->justification_status);
        }

        $absences = $query->get()->map(function ($rec) {
            $player = $rec->playerId;
            return [
                'id'                   => $rec->id,
                'player_id'            => $rec->player_id,
                'player_name'          => $player ? $player->first_name . ' ' . $player->last_name : 'غير معروف',
                'shirt_number'         => $player?->Shirt_number,
                'team_name'            => $player?->team?->name ?? 'غير محدد',
                'absence_type'         => $rec->absence_type,
                'event_category'       => $rec->event_category,
                'event_date'           => $rec->event_date,
                'session_id'           => $rec->training_session_id,
                'session_date'         => $rec->trainingSessionId?->session_date,
                'location'             => $rec->trainingSessionId?->location,
                'duration'             => $rec->duration,
                'reason'               => $rec->reason ?? '',
                'is_justified'         => (bool) $rec->is_justified,
                'justification_status' => $rec->justification_status ?? 'none',
                'record_source'        => $rec->record_source ?? 'يدوي',
            ];
        });

        return response()->json($absences);
    }

    /**
     * Update justification status for an absence record.
     */
    public function updateJustification(Request $request)
    {
        $validated = $request->validate([
            'id'                   => 'required|exists:app_absences,id',
            'justification_status' => 'required|in:none,pending,accepted,rejected',
            'justification_text'   => 'nullable|string',
        ]);

        try {
            $absence = AppAbsence::findOrFail($validated['id']);
            $absence->justification_status = $validated['justification_status'];
            $absence->is_justified         = $validated['justification_status'] === 'accepted';
            $absence->reason               = $validated['justification_text'] ?? $absence->reason;

            // If accepted → upgrade absence_type to مبرر
            if ($validated['justification_status'] === 'accepted') {
                $absence->absence_type = 'غائب مبرر';
            }

            $absence->decision_date = now();
            $absence->save();

            return response()->json(['message' => 'تم تحديث حالة التبرير بنجاح'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Add a manual absence record (not from training sessions).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'player_id'      => 'required|exists:individuals,id',
            'absence_type'   => 'required|string',
            'event_category' => 'nullable|string',
            'event_date'     => 'required|date',
            'duration'       => 'nullable|string',
            'reason'         => 'nullable|string',
            'record_source'  => 'nullable|string',
        ]);

        try {
            $absence = AppAbsence::create(array_merge($validated, [
                'is_justified'         => false,
                'justification_status' => 'none',
            ]));

            return response()->json(['message' => 'تم تسجيل الغياب بنجاح', 'id' => $absence->id], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Delete an absence record.
     */
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:app_absences,id',
        ]);

        try {
            AppAbsence::findOrFail($validated['id'])->delete();
            return response()->json(['message' => 'تم حذف السجل بنجاح'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
