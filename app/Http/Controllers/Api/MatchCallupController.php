<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MatchCallup;
use Illuminate\Support\Facades\DB;

class MatchCallupController extends Controller
{
    /**
     * Display a listing of callups for a specific match.
     */
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

    /**
     * Store or update a newly created callup in storage.
     * We will accept an array of callups to sync.
     */
    public function store(Request $request)
    {
        $request->validate([
            'match_id' => 'required|exists:matches,id',
            'players' => 'required|array', // array of {player_id, notes}
        ]);

        $match_id = $request->match_id;

        DB::beginTransaction();
        try {
            // Get all current callups to remove the ones not in the request
            $currentPlayerIds = collect($request->players)->pluck('player_id')->toArray();
            
            // Delete those not in the new list
            MatchCallup::where('match_id', $match_id)
                ->whereNotIn('player_id', $currentPlayerIds)
                ->delete();

            // Update or Create the ones in the list
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

    /**
     * Remove a single callup.
     */
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
