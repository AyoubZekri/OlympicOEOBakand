<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    public function index()
    {
        return response()->json(Meeting::orderBy('date', 'desc')->orderBy('time', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'topic' => 'required|string',
            'date' => 'required|date',
            'time' => 'required',
            'location' => 'required|string',
            'attendees' => 'nullable|array',
            'points' => 'nullable|array',
        ]);

        $meeting = Meeting::create($validated);
        return response()->json($meeting, 201);
    }

    public function show(Meeting $meeting)
    {
        return response()->json($meeting);
    }

    public function update(Request $request, Meeting $meeting)
    {
        $validated = $request->validate([
            'topic' => 'sometimes|string',
            'date' => 'sometimes|date',
            'time' => 'sometimes',
            'location' => 'sometimes|string',
            'attendees' => 'nullable|array',
            'points' => 'nullable|array',
        ]);

        $meeting->update($validated);
        return response()->json($meeting);
    }

    public function destroy(Meeting $meeting)
    {
        $meeting->delete();
        return response()->json(null, 204);
    }
}
