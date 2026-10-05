<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingNotice;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    public function index()
    {
        $meetings = Meeting::with('meetingAttendees.memberId')->orderBy('date', 'desc')->orderBy('time', 'desc')->get();
        $meetings->transform(function ($meeting) {
            $originalAttendees = is_array($meeting->attendees) ? collect($meeting->attendees)->keyBy('id') : collect();
            
            $attendees = $meeting->meetingAttendees->map(function ($ma) use ($originalAttendees) {
                $idStr = (string) $ma->member_id;
                $orig = $originalAttendees->get($idStr) ?? $originalAttendees->get((int)$idStr);
                
                return [
                    'id' => $idStr,
                    'name' => $ma->memberId ? $ma->memberId->first_name . ' ' . $ma->memberId->last_name : ($orig['name'] ?? 'ÚÖæ'),
                    'status' => $orig['status'] ?? 'pending',
                    'reason' => $orig['reason'] ?? ''
                ];
            });
            unset($meeting->meetingAttendees);
            $meeting->attendees = $attendees;
            return $meeting;
        });
        return response()->json($meetings);
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

        // Set default status to pending for new attendees
        if (isset($validated['attendees']) && is_array($validated['attendees'])) {
            foreach ($validated['attendees'] as &$att) {
                if (!isset($att['status'])) {
                    $att['status'] = 'pending';
                }
            }
        }

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
        $meeting->load('meetingAttendees.memberId');
        $originalAttendees = is_array($meeting->attendees) ? collect($meeting->attendees)->keyBy('id') : collect();
        
        $attendees = $meeting->meetingAttendees->map(function ($ma) use ($originalAttendees) {
            $idStr = (string) $ma->member_id;
            $orig = $originalAttendees->get($idStr) ?? $originalAttendees->get((int)$idStr);
            
            return [
                'id' => $idStr,
                'name' => $ma->memberId ? $ma->memberId->first_name . ' ' . $ma->memberId->last_name : ($orig['name'] ?? 'ÚÖæ'),
                'status' => $orig['status'] ?? 'pending',
                'reason' => $orig['reason'] ?? ''
            ];
        });
        unset($meeting->meetingAttendees);
        $meeting->attendees = $attendees;
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

        $before = MeetingNotice::snapshot($meeting);
        $invitedBefore = $this->invitedIds($meeting);

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

        $this->announceChange($meeting->fresh(), $before, $invitedBefore);

        return response()->json($meeting);
    }

    public function destroy(Meeting $meeting)
    {
        // The invited members are told it will not take place (not for a meeting already held)
        if (($meeting->date ? substr((string) $meeting->date, 0, 10) : '') >= now('Africa/Algiers')->toDateString()) {
            MeetingNotice::record($meeting, 'deleted', $this->invitedIds($meeting));
        }
        \DB::table('meeting_attendees')->where('meeting_id', $meeting->id)->delete();
        $meeting->delete();
        return response()->json(null, 204);
    }

    /** The member ids invited to the meeting */
    private function invitedIds(Meeting $meeting): array
    {
        return \DB::table('meeting_attendees')->where('meeting_id', $meeting->id)->pluck('member_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Tells the members concerned what changed: the date, time, place or topic (to those still invited),
     * and to those taken off the list that they are no longer invited. An attendance or a point is not announced here.
     */
    private function announceChange(Meeting $meeting, array $before, array $invitedBefore): void
    {
        $after = MeetingNotice::snapshot($meeting);
        $invitedAfter = $this->invitedIds($meeting);

        $changed = array_filter(array_keys($after), fn ($k) => $before[$k] !== $after[$k]);
        if ($changed) {
            // Those just invited get "invited" from their meetings list: only the ones already invited are told of the change
            MeetingNotice::record($meeting, 'updated', array_intersect($invitedAfter, $invitedBefore), $before);
        }

        $removed = array_diff($invitedBefore, $invitedAfter);
        if ($removed && ($after['date'] ?? '') >= now('Africa/Algiers')->toDateString()) {
            MeetingNotice::record($meeting, 'uninvited', $removed);
        }
    }
}
