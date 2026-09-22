<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MatchCallup;
use App\Models\Matchs;
use Illuminate\Support\Facades\DB;

class MatchCallupController extends Controller
{
    public function index($match_id)
    {
        try {
            $callups = MatchCallup::with('playerId')
                ->where('match_id', $match_id)
                ->orderBy('created_at', 'asc')
                ->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $callups
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'match_id' => 'required|exists:matches,id',
            'players' => 'required|array',
        ]);

        $match_id = $request->match_id;

        DB::beginTransaction();
        try {
            $currentPlayerIds = collect($request->players)->pluck('player_id')->toArray();
            
            MatchCallup::where('match_id', $match_id)
                ->whereNotIn('player_id', $currentPlayerIds)
                ->delete();

            foreach ($request->players as $player) {
                MatchCallup::updateOrCreate(
                    [
                        'match_id' => $match_id,
                        'player_id' => $player['player_id']
                    ],
                    [
                        'notes' => $player['notes'] ?? null
                    ]
                );
            }

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'تم حفظ الاستدعاءات بنجاح'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء حفظ الاستدعاءات: ' . $e->getMessage()
            ], 500);
        }
    }

    public function saveLineup(Request $request)
    {
        $request->validate([
            'match_id' => 'required|exists:matches,id',
            'formation' => 'nullable|string',
            'players' => 'required|array',
        ]);

        $match_id = $request->match_id;

        DB::beginTransaction();
        try {
            // Update formation on match
            $match = Matchs::find($match_id);
            if ($match) {
                $match->formation = $request->formation;
                $match->save();
            }

            // Reset all players in this match to not starters
            MatchCallup::where('match_id', $match_id)->update([
                'is_starter' => false,
                'position_x' => null,
                'position_y' => null
            ]);

            // Set the new starters
            foreach ($request->players as $player) {
                MatchCallup::where('match_id', $match_id)
                    ->where('player_id', $player['player_id'])
                    ->update([
                        'is_starter' => $player['is_starter'] ?? false,
                        'position_x' => $player['position_x'] ?? null,
                        'position_y' => $player['position_y'] ?? null
                    ]);
            }

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'تم حفظ التشكيلة بنجاح'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء حفظ التشكيلة: ' . $e->getMessage()
            ], 500);
        }
    }

    
    public function saveStats(Request $request)
    {
        $request->validate([
            'match_id' => 'required|exists:matches,id',
            'players' => 'required|array',
        ]);

        $match_id = $request->match_id;

        DB::beginTransaction();
        try {
            foreach ($request->players as $player) {
                MatchCallup::where('match_id', $match_id)
                    ->where('player_id', $player['player_id'])
                    ->update([
                        'yellow_cards' => $player['yellow_cards'] ?? 0,
                        'yellow_card_minute' => $player['yellow_card_minute'] ?? null,
                        'yellow_card_2_minute' => $player['yellow_card_2_minute'] ?? null,
                        'red_cards' => $player['red_cards'] ?? 0,
                        'red_card_minute' => $player['red_card_minute'] ?? null,
                        'red_card_type' => $player['red_card_type'] ?? null,
                        'rating' => $player['rating'] ?? null
                    ]);
            }
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'تم حفظ التقييمات والبطاقات بنجاح'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء الحفظ: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:match_callups,id'
        ]);

        try {
            MatchCallup::destroy($request->id);
            return response()->json([
                'status' => 'success',
                'message' => 'تم حذف الاستدعاء بنجاح'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء حذف الاستدعاء: ' . $e->getMessage()
            ], 500);
        }
    }
}


