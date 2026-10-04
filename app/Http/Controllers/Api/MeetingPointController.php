<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Decision;
use App\Models\Individual;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The meetings of the people concerned (personal space), and the discussion points they send.
 * The points stay in the meeting's own list (department_meetings.points): the administration's points are texts,
 * a point sent by a member is kept with who sent it. Any invited member (or a manager of the meetings)
 * adds a point until the meeting starts.
 */
class MeetingPointController extends Controller
{
    /** Full-access roles, roles with meetings.edit, and roles saved before the permissions (the meetings were open to all) */
    private function managesMeetings(?User $user): bool
    {
        $role = $user?->role;
        if (!$role) {
            return false;
        }
        if (strtolower((string) $role->type) === 'full') {
            return true;
        }
        $permissions = is_string($role->permissions) ? json_decode($role->permissions, true) : (array) $role->permissions;
        if (!is_array($permissions) || !isset($permissions['_v'])) {
            return true;
        }

        return ($permissions['meetings']['edit'] ?? false) === true;
    }

    /** The meeting's start (Algeria time, as entered) */
    private function started(Meeting $meeting): bool
    {
        if (!$meeting->date) {
            return false;
        }
        $day = substr((string) $meeting->date, 0, 10);
        $time = substr((string) ($meeting->time ?: '00:00'), 0, 5);

        return now()->greaterThanOrEqualTo(Carbon::parse("$day $time", 'Africa/Algiers'));
    }

    /** The member ids invited to the meeting */
    private function invitedIds(Meeting $meeting)
    {
        return DB::table('meeting_attendees')->where('meeting_id', $meeting->id)->pluck('member_id')->map(fn ($id) => (int) $id);
    }

    /**
     * The meeting's points as the pages read them: a text is the administration's point;
     * a point sent by a member carries its id, who sent it and when.
     */
    private function points(Meeting $meeting, ?int $viewerId): array
    {
        return collect((array) $meeting->points)->map(function ($p) use ($viewerId) {
            if (is_string($p)) {
                return trim($p) === '' ? null : ['id' => null, 'text' => $p, 'author' => null, 'mine' => false, 'created_at' => null];
            }
            if (!is_array($p) || trim((string) ($p['text'] ?? '')) === '') {
                return null;
            }

            return [
                'id' => $p['id'] ?? null,
                'text' => $p['text'],
                'author' => $p['author'] ?? 'عضو',
                'mine' => $viewerId !== null && (int) ($p['user_id'] ?? 0) === $viewerId,
                'created_at' => $p['created_at'] ?? null,
            ];
        })->filter()->values()->all();
    }

    /**
     * Personal space: the meetings I am invited to, newest first, with their points (who sent each one),
     * my attendance status, and the meeting's decisions.
     */
    public function mine(Request $request)
    {
        $user = $request->user();
        $memberIds = Individual::where('user_id', $user->id)->pluck('id')->map(fn ($id) => (int) $id);
        $meetingIds = DB::table('meeting_attendees')->whereIn('member_id', $memberIds)->pluck('meeting_id')->unique();

        $meetings = Meeting::with('meetingAttendees.memberId')->whereIn('id', $meetingIds)
            ->orderByDesc('date')->orderByDesc('time')->get();
        $decisions = Decision::whereIn('meeting_id', $meetings->pluck('id'))->orderBy('id')->get()->groupBy('meeting_id');
        $names = Individual::whereIn('id', $decisions->flatten()->pluck('assignee_ids')->flatten()->filter()->unique())
            ->get()->mapWithKeys(fn ($i) => [(string) $i->id => trim($i->first_name . ' ' . $i->last_name)]);

        $data = $meetings->map(function (Meeting $meeting) use ($memberIds, $decisions, $names, $user) {
            $original = is_array($meeting->attendees) ? collect($meeting->attendees)->keyBy(fn ($a) => (string) ($a['id'] ?? '')) : collect();
            $attendees = $meeting->meetingAttendees->map(function ($ma) use ($original) {
                $orig = $original->get((string) $ma->member_id);

                return [
                    'id' => (string) $ma->member_id,
                    'name' => $ma->memberId ? trim($ma->memberId->first_name . ' ' . $ma->memberId->last_name) : ($orig['name'] ?? 'عضو'),
                    'status' => $orig['status'] ?? 'pending',
                ];
            })->values();
            $me = $attendees->first(fn ($a) => $memberIds->contains((int) $a['id']));

            return [
                'id' => (string) $meeting->id,
                'topic' => $meeting->topic,
                'date' => substr((string) $meeting->date, 0, 10),
                'time' => substr((string) $meeting->time, 0, 5),
                'location' => $meeting->location,
                'attendees' => $attendees,
                'my_status' => $me['status'] ?? 'pending',
                'points' => $this->points($meeting, $user->id),
                'can_propose' => !$this->started($meeting),
                'decisions' => ($decisions->get($meeting->id) ?? collect())->map(fn (Decision $d) => [
                    'id' => $d->id,
                    'text' => $d->decision_text,
                    'category' => $d->category,
                    'type' => $d->type,
                    'deadline' => $d->deadline ? substr((string) $d->deadline, 0, 10) : null,
                    'progress' => (int) ($d->progress ?? 0),
                    'execution_status' => $d->execution_status,
                    'assignees' => collect($d->assignee_ids ?? [])->map(fn ($id) => $names[(string) $id] ?? null)->filter()->values(),
                    'mine' => collect($d->assignee_ids ?? [])->contains(fn ($id) => $memberIds->contains((int) $id)),
                ])->values(),
            ];
        })->values();

        return response()->json($data);
    }

    /** Sends a point to the meeting's list: an invited member, or a manager of the meetings, until the meeting starts */
    public function store(Request $request, int $id)
    {
        $validated = $request->validate(['text' => 'required|string|max:500'], ['text.required' => 'اكتب نقطة النقاش']);
        $user = $request->user();

        return DB::transaction(function () use ($id, $user, $validated) {
            // Locked: two people sending at the same time both keep their point
            $meeting = Meeting::lockForUpdate()->findOrFail($id);
            $member = Individual::where('user_id', $user->id)->whereIn('id', $this->invitedIds($meeting))->first();
            if (!$member && !$this->managesMeetings($user)) {
                return response()->json(['message' => 'لست من المعنيين بهذا الاجتماع'], 403);
            }
            if ($this->started($meeting)) {
                return response()->json(['message' => 'بدأ الاجتماع، لا يمكن إضافة نقاط جديدة'], 422);
            }

            $point = [
                'id' => (string) Str::ulid(),
                'text' => trim($validated['text']),
                'author' => $member ? trim($member->first_name . ' ' . $member->last_name) : ($user->name ?: 'الإدارة'),
                'user_id' => $user->id,
                'member_id' => $member?->id,
                'created_at' => now()->toIso8601String(),
            ];
            $meeting->points = array_values(array_merge((array) $meeting->points, [$point]));
            $meeting->save();

            return response()->json(['message' => 'تمت إضافة النقطة', 'point' => $point], 201);
        });
    }

    /** Removes a sent point: its sender until the meeting starts, or a manager of the meetings */
    public function destroy(Request $request)
    {
        $validated = $request->validate(['meeting_id' => 'required|integer', 'point_id' => 'required|string']);
        $user = $request->user();

        return DB::transaction(function () use ($validated, $user) {
            $meeting = Meeting::lockForUpdate()->find($validated['meeting_id']);
            $points = collect((array) $meeting?->points);
            $point = $points->first(fn ($p) => is_array($p) && ($p['id'] ?? null) === $validated['point_id']);
            if (!$meeting || !$point) {
                return response()->json(['message' => 'النقطة غير موجودة'], 404);
            }
            $manager = $this->managesMeetings($user);
            if (!$manager && (int) ($point['user_id'] ?? 0) !== $user->id) {
                return response()->json(['message' => 'لا يمكنك حذف نقطة أرسلها غيرك'], 403);
            }
            if (!$manager && $this->started($meeting)) {
                return response()->json(['message' => 'بدأ الاجتماع، لا يمكن حذف النقطة'], 422);
            }

            $meeting->points = $points->reject(fn ($p) => is_array($p) && ($p['id'] ?? null) === $validated['point_id'])->values()->all();
            $meeting->save();

            return response()->json(['message' => 'تم حذف النقطة']);
        });
    }
}
