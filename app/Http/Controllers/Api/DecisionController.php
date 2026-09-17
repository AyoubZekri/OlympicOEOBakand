<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Decision;
use Illuminate\Http\Request;

class DecisionController extends Controller
{
    public function index()
    {
        return response()->json(Decision::orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'meeting_id' => 'nullable|exists:meetings,id',
            'category' => 'nullable|string',
            'type' => 'nullable|string',
            'checklist_items' => 'nullable|array',
            'text' => 'required|string',
            'assignee_ids' => 'nullable|array',
            'deadline' => 'nullable|date',
            'progress' => 'integer|min:0|max:100',
        ]);

        $decision = Decision::create($validated);
        return response()->json($decision, 201);
    }

    public function show(Decision $decision)
    {
        return response()->json($decision);
    }

    public function update(Request $request, Decision $decision)
    {
        $validated = $request->validate([
            'meeting_id' => 'nullable|exists:meetings,id',
            'category' => 'nullable|string',
            'type' => 'nullable|string',
            'checklist_items' => 'nullable|array',
            'text' => 'sometimes|string',
            'assignee_ids' => 'nullable|array',
            'deadline' => 'nullable|date',
            'progress' => 'integer|min:0|max:100',
        ]);

        $decision->update($validated);
        return response()->json($decision);
    }

    public function destroy(Decision $decision)
    {
        $decision->delete();
        return response()->json(null, 204);
    }
}
