<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Decision;
use Illuminate\Http\Request;

class DecisionController extends Controller
{
    public function index()
    {
        $decisions = Decision::orderBy('id', 'desc')->get();
        $decisions->map(function ($decision) {
            $decision->text = $decision->decision_text;
            return $decision;
        });
        return response()->json($decisions);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'meeting_id' => 'nullable|exists:department_meetings,id',
            'category' => 'nullable|string',
            'type' => 'nullable|string',
            'checklist_items' => 'nullable|array',
            'text' => 'required|string',
            'assignee_ids' => 'nullable|array',
            'deadline' => 'nullable|date',
            'progress' => 'integer|min:0|max:100',
        ]);

        $validated['decision_text'] = $validated['text'];
        unset($validated['text']);

        $decision = Decision::create($validated);
        return response()->json($decision, 201);
    }

    public function show(Decision $decision)
    {
        $decision->text = $decision->decision_text;
        return response()->json($decision);
    }

    public function update(Request $request, Decision $decision)
    {
        $validated = $request->validate([
            'meeting_id' => 'nullable|exists:department_meetings,id',
            'category' => 'nullable|string',
            'type' => 'nullable|string',
            'checklist_items' => 'nullable|array',
            'text' => 'sometimes|string',
            'assignee_ids' => 'nullable|array',
            'deadline' => 'nullable|date',
            'progress' => 'integer|min:0|max:100',
        ]);

        if (isset($validated['text'])) {
            $validated['decision_text'] = $validated['text'];
            unset($validated['text']);
        }

        $decision->update($validated);
        return response()->json($decision);
    }

    public function destroy(Decision $decision)
    {
        $decision->delete();
        return response()->json(null, 204);
    }
}
