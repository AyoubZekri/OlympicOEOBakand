<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
