<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppAbsence;
use App\Models\Individual;
use App\Models\TrainingSession;
use App\Models\TrainingSessionNotice;
use Illuminate\Support\Facades\DB;
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
                'attendance_taken' => $session->attendance_taken_at !== null,
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
            TrainingSessionNotice::record($session, $session->status === 'ملغاة' ? 'cancelled' : 'created');

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
            $before = $this->snapshot($session);
            $session->update([
                'team_id' => $validated['team_id'],
                'session_date' => $validated['date'],
                'location' => $validated['location'],
                'start_time' => $validated['start'],
                'end_time' => $validated['end'],
                'status' => $validated['status'],
            ]);
            $this->announceChange($session, $before);

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
            DB::transaction(function () use ($session) {
                $session->delete();
                // Announced as cancelled (it will not take place); not for a session already over
                if ($session->team_id && !$this->isPast($session)) {
                    TrainingSessionNotice::record($session, 'deleted');
                }
            });

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
            $before = $this->snapshot($session);
            $session->update(['status' => $validated['status']]);
            $this->announceChange($session, $before);

            return response()->json(['message' => 'تم تحديث حالة الحصة بنجاح'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Personal space: what happened lately to the sessions of my category (created, edited, cancelled, deleted),
     * the latest per session, for the sessions not over yet and announced in the last 14 days.
     */
    public function myNotices(Request $request)
    {
        $teamIds = Individual::where('user_id', $request->user()->id)->pluck('team_id')->filter()->unique()->values();

        $notices = TrainingSessionNotice::with('team')
            ->whereIn('team_id', $teamIds)
            ->where('created_at', '>=', now()->subDays(14))
            ->where(function ($q) {
                $q->whereNull('session_date')->orWhere('session_date', '>=', now()->toDateString());
            })
            ->orderByDesc('id')
            ->get()
            ->unique(fn ($n) => $n->training_session_id . ':' . $n->team_id)
            ->values();

        return response()->json($notices->map(fn (TrainingSessionNotice $n) => [
            'id' => $n->id,
            'session_id' => $n->training_session_id,
            'kind' => $n->kind,
            'team_name' => $n->team ? $n->team->name : '',
            'date' => $n->session_date ? substr((string) $n->session_date, 0, 10) : '',
            'start' => $n->start_time ?? '',
            'end' => $n->end_time ?? '',
            'location' => $n->location ?? '',
            'previous' => $n->previous,
            'at' => $n->created_at?->toIso8601String(),
        ])->values());
    }

    /** The date, times, place, category and status of a session, to compare before / after a change */
    private function snapshot(TrainingSession $session): array
    {
        return [
            'team_id' => (int) $session->team_id,
            'date' => $session->session_date ? substr((string) $session->session_date, 0, 10) : null,
            'start' => $session->start_time ? substr((string) $session->start_time, 0, 5) : null,
            'end' => $session->end_time ? substr((string) $session->end_time, 0, 5) : null,
            'location' => (string) $session->location,
            'status' => (string) $session->status,
        ];
    }

    private function isPast(TrainingSession $session): bool
    {
        return $session->session_date && substr((string) $session->session_date, 0, 10) < now()->toDateString();
    }

    /**
     * Tells the category's members what changed: cancelled, back on (restored), or a new date / time / place.
     * A move to another category: the old one is told it is cancelled, the new one that it is scheduled.
     * Status steps (running, completed) are not announced.
     */
    private function announceChange(TrainingSession $session, array $before): void
    {
        $after = $this->snapshot($session->fresh());
        if (!$after['team_id']) return;

        if ($before['team_id'] && $before['team_id'] !== $after['team_id']) {
            TrainingSessionNotice::record($session, 'cancelled', $before['team_id']);
            TrainingSessionNotice::record($session, $after['status'] === 'ملغاة' ? 'cancelled' : 'created');
            return;
        }

        $wasCancelled = $before['status'] === 'ملغاة';
        $isCancelled = $after['status'] === 'ملغاة';
        if ($isCancelled && !$wasCancelled) {
            TrainingSessionNotice::record($session, 'cancelled');
            return;
        }
        if ($wasCancelled && !$isCancelled) {
            TrainingSessionNotice::record($session, 'restored');
            return;
        }
        if ($isCancelled) return;

        $keys = ['date', 'start', 'end', 'location'];
        $changed = array_filter($keys, fn ($k) => $before[$k] !== $after[$k]);
        if ($changed) {
            TrainingSessionNotice::record($session, 'updated', null, array_intersect_key($before, array_flip($keys)));
        }
    }
}
