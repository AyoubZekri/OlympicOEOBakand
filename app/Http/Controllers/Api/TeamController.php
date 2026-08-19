<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index()
    {
        $teams = Team::all();
        return response()->json($teams);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
        ]);

        $team = Team::create($validated);

        return response()->json(['message' => 'Team created successfully', 'team' => $team], 201);
    }

    public function show(Request $request)
    {
        $request->validate(['id' => 'required|exists:teams,id']);
        
        $team = Team::findOrFail($request->id);
        return response()->json($team);
    }

    public function update(Request $request)
    {
        $request->validate(['id' => 'required|exists:teams,id']);
        $team = Team::findOrFail($request->id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'category' => 'sometimes|string|max:255',
        ]);

        $team->update($validated);

        return response()->json(['message' => 'Team updated successfully', 'team' => $team]);
    }

    public function destroy(Request $request)
    {
        $request->validate(['id' => 'required|exists:teams,id']);
        
        $team = Team::findOrFail($request->id);
        $team->delete();

        return response()->json(['message' => 'Team deleted successfully']);
    }
}
