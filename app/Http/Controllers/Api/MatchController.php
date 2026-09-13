<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Matchs;
use Illuminate\Support\Facades\Validator;
use Exception;

class MatchController extends Controller
{
    public function index()
    {
        try {
            $matches = Matchs::with(['coachId', 'adminId', 'team'])->orderBy('created_at', 'desc')->get();
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
            'opponent' => 'required|string|max:255',
            'match_title' => 'required|string|max:255',
            'match_date' => 'required|date',
            'location' => 'required|string|max:255',
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
                'data' => $match->load(['coachId', 'adminId', 'team'])
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
            'opponent' => 'required|string|max:255',
            'match_title' => 'required|string|max:255',
            'match_date' => 'required|date',
            'location' => 'required|string|max:255',
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
            $match = Matchs::findOrFail($request->id);
            $match->update($request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Match updated successfully',
                'data' => $match->load(['coachId', 'adminId', 'team'])
            ]);
        } catch (Exception $e) {
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
}
