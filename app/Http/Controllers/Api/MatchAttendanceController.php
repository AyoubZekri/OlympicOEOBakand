<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppAbsence;
use App\Models\Matchs;
use App\Models\MatchCallup;
use App\Models\Individual;
use Illuminate\Http\Request;

class MatchAttendanceController extends Controller
{
    /**
     * Get the attendance sheet for a match.
     * Returns called-up players, or all team players if no callups.
     * Checks app_absences for existing records (غائب/متأخر).
     * If no record found → player is considered حاضر by default.
     */
    public function getMatchAttendance($id)
    {
        $match = Matchs::with('team')->findOrFail($id);

        // Get called up players
        $callups = MatchCallup::with('playerId')
            ->where('match_id', $id)
            ->get();

        if ($callups->isNotEmpty()) {
            $players = $callups->map(function ($callup) {
                return $callup->playerId;
            })->filter()->values();
        } else {
            // Fallback to all team players if no callups exist
            $players = Individual::where('type', 'player')
                ->where('team_id', $match->team_id)
                ->orderBy('first_name')
                ->get();
        }

        // Get existing absence records for this match from app_absences
        $absences = AppAbsence::where('match_id', $id)
            ->get()
            ->keyBy('player_id');

        $medicalRecords = \App\Models\PlayerMedicalRecord::whereIn('player_id', $players->pluck('id'))
            ->where('record_status', 'like', '%����%')
            ->get()
            ->keyBy('player_id');

        $formattedPlayers = $players->map(function ($player) use ($absences, $medicalRecords) {
            $absence = $absences->get($player->id);
            $medical = $medicalRecords->get($player->id);
            return [
                'id'           => $player->id,
                'name'         => $player->first_name . ' ' . $player->last_name,
                'shirt_number' => $player->Shirt_number,
                'photo'        => $player->photo,
                'status'       => $absence ? $absence->absence_type : 'حاضر',
                'note'         => $absence ? ($absence->reason ?? '') : '',
            ];
        });

        return response()->json([
            'match_id'     => $match->id,
            'match_date'   => $match->match_date,
            'location'     => $match->location,
            'start_time'   => $match->gathering_time ?? '00:00',
            'end_time'     => $match->start_time ?? '00:00',
            'team_name'    => $match->team ? $match->team->name : 'غير محدد',
            'players'      => $formattedPlayers,
        ]);
    }

    /**
     * Save attendance for all players in a match.
     * - حاضر  → delete any existing absence record for this match/player
     * - غائب/متأخر → upsert into app_absences
     */
    public function saveAttendance(Request $request)
    {
        $validated = $request->validate([
            'match_id'            => 'required|exists:matches,id',
            'records'             => 'required|array',
            'records.*.player_id' => 'required|exists:individuals,id',
            'records.*.status'    => 'required|in:حاضر,متأخر,غائب مبرر,غائب غير مبرر',
            'records.*.note'      => 'nullable|string',
        ]);

        try {
            $match = Matchs::findOrFail($validated['match_id']);

            foreach ($validated['records'] as $record) {
                if ($record['status'] === 'حاضر') {
                    // Player present → remove any absence record
                    AppAbsence::where('match_id', $validated['match_id'])
                        ->where('player_id', $record['player_id'])
                        ->delete();
                } else {
                    // Player absent/late → save to app_absences
                    $data = [
                        'absence_type'         => $record['status'],
                        'event_category'       => 'مباراة',
                        'event_date'           => $match->match_date,
                        'record_source'        => 'مباراة',
                        'reason'               => $record['note'] ?? null,
                        'is_justified'         => $record['status'] === 'غائب مبرر',
                        'justification_status' => $record['status'] === 'غائب مبرر' ? 'accepted' : 'none',
                    ];

                    $existing = AppAbsence::where('match_id', $validated['match_id'])
                        ->where('player_id', $record['player_id'])
                        ->first();

                    if ($existing) {
                        $existing->update($data);
                    } else {
                        AppAbsence::create(array_merge($data, [
                            'match_id'  => $validated['match_id'],
                            'player_id' => $record['player_id'],
                        ]));
                    }
                }
            }

            return response()->json(['message' => 'تم حفظ كشف الحضور بنجاح'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}



