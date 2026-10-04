<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppAbsence;
use App\Models\Individual;
use Illuminate\Http\Request;

class AbsenceController extends Controller
{
    /** A member justifies a record logged on them within this many hours */
    public const JUSTIFY_HOURS = 24;

    public function index(Request $request)
    {
        $query = AppAbsence::with([
            'playerId',
            'playerId.team',
            'trainingSessionId',
            'meeting'
        ])->orderByDesc('event_date');

        if ($request->filled('team_id')) {
            $query->whereHas('playerId', fn($q) => $q->where('team_id', $request->team_id));
        }

        if ($request->filled('event_category')) {
            $query->where('event_category', $request->event_category);
        }

        if ($request->filled('justification_status')) {
            $query->where('justification_status', $request->justification_status);
        }

        $absences = $query->get()->map(fn ($rec) => $this->present($rec));

        return response()->json($absences);
    }

    /** One record as the pages read it */
    private function present(AppAbsence $rec): array
    {
        $player = $rec->playerId;
        return [
            'id'                   => $rec->id,
            'player_id'            => $rec->player_id,
            'player_name'          => $player ? $player->first_name . ' ' . $player->last_name : 'غير معروف',
            'shirt_number'         => $player?->Shirt_number,
            'team_name'            => $player?->team?->name ?? 'غير محدد',
            'absence_type'         => $rec->absence_type,
            'event_category'       => $rec->event_category,
            'event_date'           => $rec->event_date,
            'session_id'           => $rec->training_session_id,
            'session_date'         => $rec->trainingSessionId?->session_date,
            'location'             => $rec->trainingSessionId?->location,
            'meeting_id'           => $rec->meeting_id,
            'meeting_topic'        => $rec->meeting_topic ?? $rec->meeting?->topic,
            'duration'             => $rec->duration,
            'reason'               => $rec->reason ?? '',
            'is_justified'         => (bool) $rec->is_justified,
            'justification_status' => $rec->justification_status ?? 'none',
            'record_source'        => $rec->record_source ?? 'يدوي',
            'created_at'           => $rec->created_at?->toIso8601String(),
            'attachment_url'       => $rec->attachment_path,
            'justify_until'        => $this->justifyUntil($rec)?->toIso8601String(),
            'decision_date'        => $rec->decision_date ? \Illuminate\Support\Carbon::parse($rec->decision_date)->toIso8601String() : null,
        ];
    }

    public function updateJustification(Request $request)
    {
        $validated = $request->validate([
            'id'                   => 'required|exists:app_absences,id',
            'justification_status' => 'required|in:none,pending,accepted,rejected',
            'justification_text'   => 'nullable|string',
        ]);

        try {
            $absence = AppAbsence::findOrFail($validated['id']);
            $absence->justification_status = $validated['justification_status'];
            $absence->is_justified         = $validated['justification_status'] === 'accepted';
            $absence->reason               = $validated['justification_text'] ?? $absence->reason;

            // If accepted upgrade absence_type to مبرر
            if ($validated['justification_status'] === 'accepted') {
                $absence->absence_type = 'غائب مبرر';
            }

            $absence->decision_date = now();
            $absence->save();

            return response()->json(['message' => 'تم تحديث حالة التبرير بنجاح'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'player_id'      => 'required|exists:individuals,id',
            'absence_type'   => 'required|string',
            'event_category' => 'nullable|string',
            'event_date'     => 'required|date',
            'duration'       => 'nullable|string',
            'reason'         => 'nullable|string',
            'record_source'  => 'nullable|string',
            'meeting_id'     => 'nullable|integer',
            'meeting_topic'  => 'nullable|string',
            'justification_status' => 'nullable|string',
            'is_justified'   => 'nullable|boolean',
        ]);

        try {
            $absence = AppAbsence::create(array_merge($validated, [
                'is_justified'         => $validated['is_justified'] ?? false,
                'justification_status' => $validated['justification_status'] ?? 'none',
            ]));

            return response()->json(['message' => 'تم تسجيل الغياب بنجاح', 'id' => $absence->id], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:app_absences,id',
        ]);

        try {
            AppAbsence::findOrFail($validated['id'])->delete();
            return response()->json(['message' => 'تم حذف السجل بنجاح'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /** The members linked to the signed-in account */
    private function myMemberIds(Request $request)
    {
        return Individual::where('user_id', $request->user()->id)->pluck('id');
    }

    /** Personal space: my records (absences, late, leaves, holiday requests), newest first */
    public function mine(Request $request)
    {
        $records = AppAbsence::with(['playerId', 'playerId.team', 'trainingSessionId', 'meeting'])
            ->whereIn('player_id', $this->myMemberIds($request))
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($rec) => $this->present($rec))
            ->values();

        return response()->json($records);
    }

    /**
     * Until when the member may justify a record: 24 hours after it was logged by the administration.
     * None for a holiday request or an absence the member announced (they are decided as they are).
     */
    private function justifyUntil(AppAbsence $rec): ?\Illuminate\Support\Carbon
    {
        if (!$rec->created_at || $rec->record_source === 'طلب العضو' || $rec->absence_type === 'طلب عطلة') {
            return null;
        }

        return $rec->created_at->copy()->addHours(self::JUSTIFY_HOURS);
    }

    /**
     * Personal space: I justify one of my records, with a text, a document (PDF / picture), or both,
     * within 24 hours after it was logged. It then waits for the administration's decision;
     * after a refusal I may justify again while the 24 hours last.
     */
    public function justifyMine(Request $request)
    {
        $validated = $request->validate([
            'id'       => 'required|integer',
            'text'     => 'nullable|string|max:2000',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:5120',
        ], [
            'document.mimes' => 'الوثيقة يجب أن تكون PDF أو صورة',
            'document.max'   => 'حجم الوثيقة أكبر من 5 ميغا',
        ]);
        $text = trim((string) ($validated['text'] ?? ''));
        if ($text === '' && !$request->hasFile('document')) {
            return response()->json(['message' => 'أرفق وثيقة أو اكتب نص التبرير'], 422);
        }

        $absence = AppAbsence::where('id', $validated['id'])->whereIn('player_id', $this->myMemberIds($request))->first();
        if (!$absence) {
            return response()->json(['message' => 'هذا السجل غير موجود في سجلك'], 404);
        }
        $status = $absence->justification_status;
        if (in_array($status, ['accepted', 'مقبول'], true)) {
            return response()->json(['message' => 'تم قبول تبرير هذا السجل من قبل'], 422);
        }
        if (in_array($status, ['pending', 'قيد_الدراسة'], true)) {
            return response()->json(['message' => 'تبريرك قيد الدراسة، انتظر قرار الإدارة'], 422);
        }
        $until = $this->justifyUntil($absence);
        if (!$until) {
            return response()->json(['message' => 'هذا السجل لا يحتاج إلى تبرير'], 422);
        }
        if (now()->greaterThan($until)) {
            return response()->json(['message' => 'انتهت مهلة تقديم التبرير (24 ساعة بعد تسجيل الغياب)'], 422);
        }

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $name = 'absence_' . $absence->id . '_' . time() . '.' . strtolower($file->getClientOriginalExtension());
            $folder = public_path('uploads/absences');
            if (!file_exists($folder)) {
                mkdir($folder, 0755, true);
            }
            $file->move($folder, $name);
            $absence->attachment_path = asset('uploads/absences/' . $name);
        }

        $absence->reason = $text;
        $absence->justification_status = 'pending';
        $absence->is_justified = false;
        $absence->decision_date = null;
        $absence->save();

        return response()->json(['message' => 'تم إرسال التبرير، وهو قيد الدراسة']);
    }

    /**
     * Personal space: I ask for a holiday, or announce an absence in advance.
     * Recorded on my member, waiting for the administration's decision.
     */
    public function requestMine(Request $request)
    {
        $validated = $request->validate([
            'kind'           => 'required|in:leave,absence',
            'event_date'     => 'required|date',
            'end_date'       => 'nullable|date|after_or_equal:event_date',
            'event_category' => 'nullable|in:تدريب,مباراة,اجتماع,أخرى',
            'reason'         => 'required|string|max:2000',
        ], [
            'reason.required' => 'اكتب السبب',
            'event_date.required' => 'حدد التاريخ',
            'end_date.after_or_equal' => 'تاريخ النهاية قبل تاريخ البداية',
        ]);

        $memberId = $this->myMemberIds($request)->first();
        if (!$memberId) {
            return response()->json(['message' => 'حسابك غير مرتبط بعضو في النادي'], 422);
        }

        $days = null;
        if (!empty($validated['end_date'])) {
            $n = \Illuminate\Support\Carbon::parse($validated['event_date'])->diffInDays(\Illuminate\Support\Carbon::parse($validated['end_date'])) + 1;
            $days = $n === 1 ? 'يوم واحد' : ($n === 2 ? 'يومان' : $n . ' أيام') . ' (حتى ' . substr($validated['end_date'], 0, 10) . ')';
        }

        $absence = AppAbsence::create([
            'player_id'            => $memberId,
            'absence_type'         => $validated['kind'] === 'leave' ? 'طلب عطلة' : 'غياب',
            'event_category'       => $validated['event_category'] ?? ($validated['kind'] === 'leave' ? null : 'أخرى'),
            'event_date'           => substr($validated['event_date'], 0, 10),
            'duration'             => $days,
            'reason'               => trim($validated['reason']),
            'record_source'        => 'طلب العضو',
            'is_justified'         => false,
            'justification_status' => 'pending',
        ]);

        return response()->json(['message' => 'تم إرسال الطلب، وهو قيد الدراسة', 'id' => $absence->id], 201);
    }
}
