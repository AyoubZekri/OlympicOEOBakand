<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Matchs;
use App\Models\MatchNotice;
use App\Models\Individual;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Exception;

class MatchController extends Controller
{
    public function index()
    {
        try {
            // The counts tell the managers what is still to do: call-up, lineup, ratings, report
            $matches = Matchs::with(['coachId', 'adminId', 'team', 'opponentClub'])
                ->withCount([
                    'callups',
                    'callups as starters_count' => fn ($q) => $q->where('is_starter', true),
                    'callups as rated_count' => fn ($q) => $q->whereNotNull('rating'),
                    'administrativeReports as reports_count',
                ])
                ->orderBy('created_at', 'desc')
                ->get();
            return response()->json([
                'status' => 'success',
                'data' => $matches
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch matches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'competition' => 'required|string|max:255',
            'opponent' => 'nullable|string|max:255',
            'opponent_club_id' => 'nullable|exists:clubs,id',
            'match_title' => 'nullable|string|max:255',
            'match_date' => 'required|date',
            'location' => 'required|string|max:255',
            'team_score' => 'nullable|integer',
            'opponent_score' => 'nullable|integer',
            'match_status' => 'nullable|string|max:255',
            'gathering_time' => 'nullable|date',
            'gathering_location' => 'nullable|string|max:255',
            'coach_id' => 'required|exists:individuals,id',
            'admin_id' => 'required|exists:users,id',
            'team_id' => 'required|exists:teams,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $match = Matchs::create($request->all());
            if ($match->team_id) {
                MatchNotice::record($match, $this->statusKind($match->match_status) ?? 'created');
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Match created successfully',
                'data' => $match->load(['coachId', 'adminId', 'team', 'opponentClub'])
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create match',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:matches,id',
            'competition' => 'required|string|max:255',
            'opponent' => 'nullable|string|max:255',
            'opponent_club_id' => 'nullable|exists:clubs,id',
            'match_title' => 'nullable|string|max:255',
            'match_date' => 'required|date',
            'location' => 'required|string|max:255',
            'team_score' => 'nullable|integer',
            'opponent_score' => 'nullable|integer',
            'match_status' => 'nullable|string|max:255',
            'gathering_time' => 'nullable|date',
            'gathering_location' => 'nullable|string|max:255',
            'coach_id' => 'required|exists:individuals,id',
            'admin_id' => 'required|exists:users,id',
            'team_id' => 'required|exists:teams,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $match = Matchs::findOrFail($request->id);
            $before = $this->snapshot($match);

            $updateData = $request->except(['goals', 'substitutions']);
            $match->update($updateData);
            $this->announceChange($match, $before);

            // Process Goals
            if ($request->has('goals') && is_array($request->goals)) {
                DB::table('match_goals')->where('match_id', $match->id)->delete();
                $goalsData = [];
                foreach ($request->goals as $goal) {
                    if (!empty($goal['scorer_id']) && !empty($goal['minute'])) {
                        $goalsData[] = [
                            'match_id' => $match->id,
                            'scorer_id' => $goal['scorer_id'],
                            'assist_id' => !empty($goal['assist_id']) ? $goal['assist_id'] : null,
                            'minute' => $goal['minute'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
                if (!empty($goalsData)) {
                    DB::table('match_goals')->insert($goalsData);
                }
            }

            // Process Substitutions
            if ($request->has('substitutions') && is_array($request->substitutions)) {
                foreach ($request->substitutions as $sub) {
                    if (!empty($sub['player_out_id']) && !empty($sub['player_in_id'])) {
                        // Because some records might use `individual_id` depending on the migration, we use the standard from frontend which is player_id
                        // But wait! What is the column name in `match_callups`? It could be `individual_id`. I'll use a callback to match either.
                        $outQuery = DB::table('match_callups')->where('match_id', $match->id);
                        if (\Schema::hasColumn('match_callups', 'player_id')) {
                            $outQuery->where('player_id', $sub['player_out_id']);
                        } else {
                            $outQuery->where('individual_id', $sub['player_out_id']);
                        }
                        $outQuery->update([
                            'subbed_out_minute' => $sub['minute'],
                            'replaced_by_id' => $sub['player_in_id']
                        ]);

                        $inQuery = DB::table('match_callups')->where('match_id', $match->id);
                        if (\Schema::hasColumn('match_callups', 'player_id')) {
                            $inQuery->where('player_id', $sub['player_in_id']);
                        } else {
                            $inQuery->where('individual_id', $sub['player_in_id']);
                        }
                        $inQuery->update([
                            'subbed_in_minute' => $sub['minute']
                        ]);
                    }
                }
            }

            // Goals and substitutions are saved apart: the match is marked updated, the alerts see them at once
            $match->touch();

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Match updated successfully',
                'data' => $match->load(['coachId', 'adminId', 'team', 'opponentClub'])
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update match',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:matches,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $match = Matchs::findOrFail($request->id);
            \App\Models\MatchCallup::where('match_id', $match->id)->delete();
            \App\Models\AdministrativeMatchReport::where('match_id', $match->id)->delete();
            \App\Models\MatchBonuse::where('match_id', $match->id)->delete();
            \App\Models\TravelItinerary::where('match_id', $match->id)->delete();
            $match->delete();
            // Announced as cancelled (it will not take place); not for a match already played
            if ($match->team_id && !$this->isPast($match)) {
                MatchNotice::record($match, 'deleted');
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Match deleted successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete match',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getMatchEvents($id)
    {
        try {
            $match = Matchs::with(['coachId', 'adminId', 'team', 'opponentClub'])->findOrFail($id);
            
            $goals = DB::table('match_goals')
                ->join('individuals as scorer', 'match_goals.scorer_id', '=', 'scorer.id')
                ->leftJoin('individuals as assist', 'match_goals.assist_id', '=', 'assist.id')
                ->where('match_goals.match_id', $id)
                ->orderBy('match_goals.minute')
                ->select('match_goals.*', 'scorer.first_name as scorer_first_name', 'scorer.last_name as scorer_last_name', 'assist.first_name as assist_first_name', 'assist.last_name as assist_last_name')
                ->get();
            
            $callups = \App\Models\MatchCallup::with(['individual', 'replacedBy'])
                ->where('match_id', $id)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'match' => $match,
                    'goals' => $goals,
                    'callups' => $callups
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch match events',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Personal space: the matches of my category (the teams of the members linked to my account), with,
     * for me: called up or not (starter, rating, cards, goals) and my attendance record.
     */
    public function mine(Request $request)
    {
        $members = Individual::where('user_id', $request->user()->id)->get(['id', 'team_id']);
        $memberIds = $members->pluck('id');
        $teamIds = $members->pluck('team_id')->filter()->unique()->values();

        $matches = Matchs::with(['team', 'opponentClub'])
            ->whereIn('team_id', $teamIds)
            ->orderByDesc('match_date')
            ->get();
        $matchIds = $matches->pluck('id');

        $callups = DB::table('match_callups')->whereIn('match_id', $matchIds)->whereIn('player_id', $memberIds)->get()->keyBy('match_id');
        $absences = DB::table('app_absences')->whereIn('match_id', $matchIds)->whereIn('player_id', $memberIds)->get()->keyBy('match_id');
        $goals = DB::table('match_goals')->whereIn('match_id', $matchIds)->whereIn('scorer_id', $memberIds)
            ->selectRaw('match_id, count(*) as c')->groupBy('match_id')->pluck('c', 'match_id');

        $data = $matches->map(function (Matchs $match) use ($callups, $absences, $goals) {
            $callup = $callups->get($match->id);
            $absence = $absences->get($match->id);

            return array_merge($match->toArray(), [
                'my_callup' => $callup ? [
                    'is_starter' => (bool) ($callup->is_starter ?? false),
                    'rating' => $callup->rating ?? null,
                    'yellow_cards' => (int) ($callup->yellow_cards ?? 0),
                    'red_cards' => (int) ($callup->red_cards ?? 0),
                    'goals' => (int) ($goals[$match->id] ?? 0),
                ] : null,
                'my_absence' => $absence ? $absence->absence_type : null,
                'my_absence_note' => $absence ? ($absence->reason ?? '') : '',
            ]);
        })->values();

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /**
     * Personal space: what happened lately to the matches of my category (scheduled, changed, postponed, cancelled, deleted),
     * the latest per match, for the matches not played yet and announced in the last 14 days.
     */
    public function myNotices(Request $request)
    {
        $teamIds = Individual::where('user_id', $request->user()->id)->pluck('team_id')->filter()->unique()->values();
        $today = now('Africa/Algiers')->toDateString();

        $notices = MatchNotice::with('team')
            ->whereIn('team_id', $teamIds)
            ->where('created_at', '>=', now()->subDays(14))
            ->orderByDesc('id')
            ->get()
            ->unique(fn ($n) => $n->match_id . ':' . $n->team_id)
            ->filter(fn ($n) => !$n->match_date || substr($n->match_date, 0, 10) >= $today)
            ->values();

        return response()->json($notices->map(fn (MatchNotice $n) => [
            'id' => $n->id,
            'match_id' => $n->match_id,
            'kind' => $n->kind,
            'team_name' => $n->team ? $n->team->name : '',
            'match_date' => $n->match_date ?? '',
            'location' => $n->location ?? '',
            'opponent' => $n->opponent ?? '',
            'competition' => $n->competition ?? '',
            'previous' => $n->previous,
        ])->values());
    }

    /** Postponed / cancelled status → its notice; any other status: none */
    private function statusKind(?string $status): ?string
    {
        return match ($status) {
            'ملغاة' => 'cancelled',
            'مؤجلة' => 'postponed',
            default => null,
        };
    }

    /** The date, place, opponent, category and status of a match, to compare before / after a change */
    private function snapshot(Matchs $match): array
    {
        return [
            'team_id' => (int) $match->team_id,
            'match_date' => MatchNotice::minute($match->match_date),
            'location' => (string) $match->location,
            'opponent' => MatchNotice::opponentOf($match),
            'status' => (string) $match->match_status,
        ];
    }

    private function isPast(Matchs $match): bool
    {
        $date = MatchNotice::minute($match->match_date);

        return $date && substr($date, 0, 10) < now('Africa/Algiers')->toDateString();
    }

    /**
     * Tells the category's members what changed: postponed, cancelled, back on (restored, maybe on a new date),
     * or a new date / place / opponent. A move to another category: the old one is told it is cancelled,
     * the new one that it is scheduled. A result or a lineup is not announced here.
     */
    private function announceChange(Matchs $match, array $before): void
    {
        $match->refresh();
        $after = $this->snapshot($match);
        if (!$after['team_id']) return;

        if ($before['team_id'] && $before['team_id'] !== $after['team_id']) {
            MatchNotice::record($match, 'cancelled', $before['team_id']);
            MatchNotice::record($match, $this->statusKind($after['status']) ?? 'created');
            return;
        }

        $wasKind = $this->statusKind($before['status']);
        $isKind = $this->statusKind($after['status']);
        if ($isKind && $isKind !== $wasKind) {
            MatchNotice::record($match, $isKind);
            return;
        }
        if ($wasKind && !$isKind) {
            MatchNotice::record($match, 'restored');
            return;
        }
        if ($isKind) return;

        $keys = ['match_date', 'location', 'opponent'];
        $changed = array_filter($keys, fn ($k) => $before[$k] !== $after[$k]);
        if ($changed) {
            MatchNotice::record($match, 'updated', null, array_intersect_key($before, array_flip($keys)));
        }
    }
}
