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
    public function getMatchAttendance()
    {
        \ = Matchs::with('team')->findOrFail(\);

        // Get called up players
        \ = MatchCallup::with('playerId')
            ->where('match_id', \)
            ->get();

        if (\->isNotEmpty()) {
            \ = \->map(function (\) {
                return \->playerId;
            })->filter()->values();
        } else {
            // Fallback to all team players if no callups exist
            \ = Individual::where('type', 'player')
                ->where('team_id', \->team_id)
                ->orderBy('first_name')
                ->get();
        }

        // Get existing absence records for this match from app_absences
        \ = AppAbsence::where('match_id', \)
            ->get()
            ->keyBy('player_id');

        \ = \->map(function (\) use (\) {
            \ = \->get(\->id);
            return [
                'id'           => \->id,
                'name'         => \->first_name . ' ' . \->last_name,
                'shirt_number' => \->Shirt_number,
                'photo'        => \->photo,
                'status'       => \ ? \->absence_type : 'حاضر',
                'note'         => \ ? (\->reason ?? '') : '',
            ];
        });

        return response()->json([
            'match_id'     => \->id,
            'match_date'   => \->match_date,
            'location'     => \->location,
            'start_time'   => \->gathering_time ?? '00:00',
            'end_time'     => \->start_time ?? '00:00',
            'team_name'    => \->team ? \->team->name : 'غير محدد',
            'players'      => \,
        ]);
    }

    /**
     * Save attendance for all players in a match.
     * - حاضر  → delete any existing absence record for this match/player
     * - غائب/متأخر → upsert into app_absences
     */
    public function saveAttendance(Request \)
    {
        \ = \->validate([
            'match_id'            => 'required|exists:matches,id',
            'records'             => 'required|array',
            'records.*.player_id' => 'required|exists:individuals,id',
            'records.*.status'    => 'required|in:حاضر,متأخر,غائب مبرر,غائب غير مبرر',
            'records.*.note'      => 'nullable|string',
        ]);

        try {
            \ = Matchs::findOrFail(\['match_id']);

            foreach (\['records'] as \) {
                if (\['status'] === 'حاضر') {
                    // Player present → remove any absence record
                    AppAbsence::where('match_id', \['match_id'])
                        ->where('player_id', \['player_id'])
                        ->delete();
                } else {
                    // Player absent/late → save to app_absences
                    \ = [
                        'absence_type'         => \['status'],
                        'event_category'       => 'مباراة',
                        'event_date'           => \->match_date,
                        'record_source'        => 'مباراة',
                        'reason'               => \['note'] ?? null,
                        'is_justified'         => \['status'] === 'غائب مبرر',
                        'justification_status' => \['status'] === 'غائب مبرر' ? 'accepted' : 'none',
                    ];

                    \ = AppAbsence::where('match_id', \['match_id'])
                        ->where('player_id', \['player_id'])
                        ->first();

                    if (\) {
                        \->update(\);
                    } else {
                        AppAbsence::create(array_merge(\, [
                            'match_id'  => \['match_id'],
                            'player_id' => \['player_id'],
                        ]));
                    }
                }
            }

            return response()->json(['message' => 'تم حفظ كشف الحضور بنجاح'], 200);
        } catch (\Exception \) {
            return response()->json(['error' => \->getMessage()], 400);
        }
    }
}
