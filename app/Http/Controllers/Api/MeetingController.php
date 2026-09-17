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
        
        if (isset($validated['attendees']) && is_array($validated['attendees'])) {
            $memberIds = [];
            foreach ($validated['attendees'] as $attendee) {
                if (isset($attendee['id'])) {
                    $memberIds[] = $attendee['id'];
                }
            }
            if (count($memberIds) > 0) {
                $records = array_map(function($id) use ($meeting) {
                    return ['meeting_id' => $meeting->id, 'member_id' => $id];
                }, $memberIds);
                \DB::table('meeting_attendees')->insert($records);
            }
        }

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

        if (array_key_exists('attendees', $validated) && is_array($validated['attendees'])) {
            $memberIds = [];
            foreach ($validated['attendees'] as $attendee) {
                if (isset($attendee['id'])) {
                    $memberIds[] = $attendee['id'];
                }
            }
            
            \DB::table('meeting_attendees')->where('meeting_id', $meeting->id)->delete();
            
            if (count($memberIds) > 0) {
                $records = array_map(function($id) use ($meeting) {
                    return ['meeting_id' => $meeting->id, 'member_id' => $id];
                }, $memberIds);
                \DB::table('meeting_attendees')->insert($records);
            }
        }

        return response()->json($meeting);
    }

    public function destroy(Meeting $meeting)
    {
        \DB::table('meeting_attendees')->where('meeting_id', $meeting->id)->delete();
        $meeting->delete();
        return response()->json(null, 204);
    }
}

