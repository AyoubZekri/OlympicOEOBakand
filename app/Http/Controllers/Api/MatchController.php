<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Matchs;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Exception;

class MatchController extends Controller
{
    public function index()
    {
        try {
            $matches = Matchs::with(['coachId', 'adminId', 'team', 'opponentClub'])->orderBy('created_at', 'desc')->get();
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
            
            $updateData = $request->except(['goals', 'substitutions']);
            $match->update($updateData);

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
}





