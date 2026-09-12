<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppAbsence;
use App\Models\TrainingSession;
use App\Models\Individual;
use Illuminate\Http\Request;

class TrainingAttendanceController extends Controller
{
    /**
     * Get the attendance sheet for a session.
     * Returns all players in the session's team.
     * Checks app_absences for existing records (غائب/متأخر).
     * If no record found → player is considered حاضر by default.
     */
    public function getSessionAttendance($sessionId)
    {
        $session = TrainingSession::with('teamId')->findOrFail($sessionId);

        $players = Individual::where('type', 'player')
            ->where('team_id', $session->team_id)
            ->orderBy('first_name')
            ->get();

        // Get existing absence records for this session from app_absences
        $existingRecords = AppAbsence::where('training_session_id', $sessionId)
            ->get()
            ->keyBy('player_id');

        $data = $players->map(function ($player) use ($existingRecords) {
            $record = $existingRecords->get($player->id);
            return [
                'id'           => $player->id,
                'name'         => $player->first_name . ' ' . $player->last_name,
                'shirt_number' => $player->Shirt_number,
                'photo'        => $player->photo,
                // If no absence record → player was present
                'status'       => $record ? $record->absence_type : 'حاضر',
                'note'         => $record ? ($record->reason ?? '') : '',
            ];
        });

        return response()->json([
            'session_id'   => $session->id,
            'session_date' => $session->session_date,
            'location'     => $session->location,
            'start_time'   => $session->start_time,
            'end_time'     => $session->end_time,
            'team_name'    => $session->teamId ? $session->teamId->name : 'غير محدد',
            'players'      => $data,
        ]);
    }

    /**
     * Save attendance for all players in a session.
     * - حاضر  → delete any existing absence record for this session/player
     * - غائب/متأخر → upsert into app_absences
     */
    public function saveAttendance(Request $request)
    {
        $validated = $request->validate([
            'session_id'          => 'required|exists:training_sessions,id',
            'records'             => 'required|array',
            'records.*.player_id' => 'required|exists:individuals,id',
            'records.*.status'    => 'required|in:حاضر,متأخر,غائب مبرر,غائب غير مبرر',
            'records.*.note'      => 'nullable|string',
        ]);

        try {
            $session = TrainingSession::findOrFail($validated['session_id']);

            foreach ($validated['records'] as $rec) {
                if ($rec['status'] === 'حاضر') {
                    // Player present → remove any absence record
                    AppAbsence::where('training_session_id', $validated['session_id'])
                        ->where('player_id', $rec['player_id'])
                        ->delete();
                } else {
                    // Player absent/late → save to app_absences
                    $data = [
                        'absence_type'         => $rec['status'],
                        'event_category'       => 'تدريب',
                        'event_date'           => $session->session_date,
                        'record_source'        => 'حصة تدريبية',
                        'reason'               => $rec['note'] ?? null,
                        'is_justified'         => $rec['status'] === 'غائب مبرر',
                        'justification_status' => $rec['status'] === 'غائب مبرر' ? 'accepted' : 'none',
                    ];

                    $existing = AppAbsence::where('training_session_id', $validated['session_id'])
                        ->where('player_id', $rec['player_id'])
                        ->first();

                    if ($existing) {
                        $existing->update($data);
                    } else {
                        AppAbsence::create(array_merge($data, [
                            'training_session_id' => $validated['session_id'],
                            'player_id'           => $rec['player_id'],
                        ]));
                    }
                }
            }

            // Mark session as completed if it was ongoing
            if ($session->status === 'جارية') {
                $session->update(['status' => 'مكتملة']);
            }

            return response()->json(['message' => 'تم حفظ كشف الحضور بنجاح'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}


