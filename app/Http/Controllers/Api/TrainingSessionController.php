<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppAbsence;
use App\Models\Individual;
use App\Models\TrainingSession;
use Illuminate\Http\Request;

class TrainingSessionController extends Controller
{
    public function index(Request $request)
    {
        $query = TrainingSession::with('teamId')->orderBy('session_date', 'desc')->orderBy('start_time', 'desc');

        if ($request->has('team_id') && $request->team_id) {
            $query->where('team_id', $request->team_id);
        }

        $sessions = $query->get();

        $data = $sessions->map(function ($session) {
            return [
                'id' => $session->id,
                'team_id' => (string)$session->team_id,
                'team_name' => $session->teamId ? $session->teamId->name : 'غير محدد',
                'date' => $session->session_date,
                'location' => $session->location,
                'start' => $session->start_time,
                'end' => $session->end_time,
                'status' => $session->status,
            ];
        });

        return response()->json($data);
    }

    /**
     * Personal space: the training sessions of my category (the teams of the members linked to my account),
     * with my own attendance for each one (my_absence: the absence/late record, null when none).
     */
    public function mine(Request $request)
    {
        $members = Individual::where('user_id', $request->user()->id)->get(['id', 'team_id']);
        $teamIds = $members->pluck('team_id')->filter()->unique()->values();

        $sessions = TrainingSession::with('teamId')
            ->whereIn('team_id', $teamIds)
            ->orderBy('session_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get();

        $absences = AppAbsence::whereIn('training_session_id', $sessions->pluck('id'))
            ->whereIn('player_id', $members->pluck('id'))
            ->get()
            ->keyBy('training_session_id');

        $data = $sessions->map(function ($session) use ($absences) {
            $absence = $absences->get($session->id);

            return [
                'id' => $session->id,
                'team_id' => (string) $session->team_id,
                'team_name' => $session->teamId ? $session->teamId->name : 'غير محدد',
                'date' => $session->session_date,
                'location' => $session->location,
                'start' => $session->start_time,
                'end' => $session->end_time,
                'status' => $session->status,
                'my_absence' => $absence ? $absence->absence_type : null,
                'my_absence_note' => $absence ? ($absence->reason ?? '') : '',
            ];
        })->values();

        return response()->json($data);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'team_id' => 'required|exists:teams,id',
            'date' => 'required|date',
            'location' => 'required|string',
            'start' => 'required|string',
            'end' => 'required|string',
            'status' => 'required|string',
        ]);

        try {
            $session = TrainingSession::create([
                'team_id' => $validated['team_id'],
                'session_date' => $validated['date'],
                'location' => $validated['location'],
                'start_time' => $validated['start'],
                'end_time' => $validated['end'],
                'status' => $validated['status'],
            ]);

            return response()->json([
                'message' => 'تم إضافة الحصة التدريبية بنجاح',
                'session' => $session
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:training_sessions,id',
            'team_id' => 'required|exists:teams,id',
            'date' => 'required|date',
            'location' => 'required|string',
            'start' => 'required|string',
            'end' => 'required|string',
            'status' => 'required|string',
        ]);

        try {
            $session = TrainingSession::findOrFail($validated['id']);
            $session->update([
                'team_id' => $validated['team_id'],
                'session_date' => $validated['date'],
                'location' => $validated['location'],
                'start_time' => $validated['start'],
                'end_time' => $validated['end'],
                'status' => $validated['status'],
            ]);

            return response()->json(['message' => 'تم تحديث الحصة التدريبية بنجاح'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:training_sessions,id',
        ]);

        try {
            $session = TrainingSession::findOrFail($validated['id']);
            $session->delete();

            return response()->json(['message' => 'تم حذف الحصة التدريبية بنجاح'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function updateStatus(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:training_sessions,id',
            'status' => 'required|string',
        ]);

        try {
            $session = TrainingSession::findOrFail($validated['id']);
            $session->update(['status' => $validated['status']]);

            return response()->json(['message' => 'تم تحديث حالة الحصة بنجاح'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
