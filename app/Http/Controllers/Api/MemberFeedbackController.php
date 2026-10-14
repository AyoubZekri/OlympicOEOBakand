<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Individual;
use App\Models\MemberFeedback;
use App\Services\RolePermissions;
use Illuminate\Http\Request;

/**
 * Members' opinions.
 * Personal space: a member sends a suggestion, a complaint or a note, and reads the reply.
 * Management (feedback.view / reply / delete): reads them, replies to each one, and rates a suggestion (1 → 5 stars).
 */
class MemberFeedbackController extends Controller
{
    private const MEMBER_TYPES = ['player' => 'لاعب', 'coach' => 'مدرب', 'employee' => 'موظف'];

    // ── Personal space ──

    public function mine(Request $request)
    {
        $items = MemberFeedback::with('replier:id,name')
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->get();

        return response()->json(['data' => $items->map(fn ($f) => $this->present($f))]);
    }

    public function storeMine(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:' . implode(',', MemberFeedback::TYPES),
            'subject' => 'required|string|max:200',
            'body' => 'nullable|string|max:5000',
        ], [
            'type.required' => 'اختر النوع',
            'subject.required' => 'اكتب الموضوع',
        ]);

        $feedback = MemberFeedback::create($data + [
            'user_id' => $request->user()->id,
            'individual_id' => Individual::where('user_id', $request->user()->id)->value('id'),
        ]);

        return response()->json(['data' => $this->present($feedback)], 201);
    }

    /** Taken back by the member, only while it has no reply */
    public function destroyMine(Request $request)
    {
        $data = $request->validate(['id' => 'required|integer']);
        $feedback = MemberFeedback::where('user_id', $request->user()->id)->findOrFail($data['id']);
        if ($feedback->replied_at) {
            return response()->json(['message' => 'لا يمكن حذف رأي تم الرد عليه'], 422);
        }
        $feedback->delete();

        return response()->json(['message' => 'تم الحذف']);
    }

    /** The member has read their replies */
    public function seenMine(Request $request)
    {
        MemberFeedback::where('user_id', $request->user()->id)
            ->whereNotNull('replied_at')
            ->whereNull('reply_seen_at')
            ->update(['reply_seen_at' => now()]);

        return response()->json(['message' => 'ok']);
    }

    // ── Management ──

    public function index(Request $request)
    {
        $this->allow($request, 'view');
        $items = MemberFeedback::with(['individual:id,first_name,last_name,type', 'user:id,name', 'replier:id,name'])
            ->latest('id')
            ->get();

        return response()->json(['data' => $items->map(fn ($f) => $this->present($f, true))]);
    }

    /** Opened by the management: no longer "new" */
    public function markRead(Request $request)
    {
        $this->allow($request, 'view');
        $data = $request->validate(['id' => 'required|integer|exists:member_feedback,id']);
        MemberFeedback::whereKey($data['id'])->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'ok']);
    }

    /** The reply (every type) and the rating (a suggestion: required, 1 → 5); replying again replaces them */
    public function reply(Request $request)
    {
        $this->allow($request, 'reply');
        $data = $request->validate([
            'id' => 'required|integer|exists:member_feedback,id',
            'reply' => 'required|string|max:5000',
            'rating' => 'nullable|integer|min:1|max:5',
        ], [
            'reply.required' => 'اكتب الرد',
            'rating.min' => 'التقييم من نجمة إلى خمس نجوم',
            'rating.max' => 'التقييم من نجمة إلى خمس نجوم',
        ]);

        $feedback = MemberFeedback::findOrFail($data['id']);
        $isSuggestion = $feedback->type === 'suggestion';
        if ($isSuggestion && empty($data['rating'])) {
            return response()->json(['message' => 'قيّم الاقتراح بالنجوم', 'errors' => ['rating' => ['قيّم الاقتراح بالنجوم']]], 422);
        }

        $feedback->update([
            'reply' => $data['reply'],
            'rating' => $isSuggestion ? (int) $data['rating'] : null,
            'replied_by' => $request->user()->id,
            'replied_at' => now(),
            // The member is told again
            'reply_seen_at' => null,
            'read_at' => $feedback->read_at ?? now(),
        ]);

        return response()->json(['data' => $this->present($feedback->fresh(['individual', 'user', 'replier']), true)]);
    }

    public function destroy(Request $request)
    {
        $this->allow($request, 'delete');
        $data = $request->validate(['id' => 'required|integer|exists:member_feedback,id']);
        MemberFeedback::whereKey($data['id'])->delete();

        return response()->json(['message' => 'تم الحذف']);
    }

    private function allow(Request $request, string $action): void
    {
        abort_unless(RolePermissions::can($request->user(), 'feedback', $action), 403, 'ليست لديك صلاحية');
    }

    private function present(MemberFeedback $f, bool $withMember = false): array
    {
        $out = [
            'id' => $f->id,
            'type' => $f->type,
            'subject' => $f->subject,
            'body' => $f->body,
            'reply' => $f->reply,
            'rating' => $f->rating,
            'replied_by' => $f->replier?->name,
            'replied_at' => $f->replied_at?->toDateTimeString(),
            'reply_seen' => $f->reply_seen_at !== null,
            'read' => $f->read_at !== null,
            'created_at' => $f->created_at?->toDateTimeString(),
        ];
        if ($withMember) {
            $member = $f->individual;
            $out['member_name'] = $member ? trim($member->first_name . ' ' . $member->last_name) : ($f->user?->name ?? 'عضو');
            $out['member_role'] = $member ? (self::MEMBER_TYPES[$member->type] ?? null) : null;
        }

        return $out;
    }
}
