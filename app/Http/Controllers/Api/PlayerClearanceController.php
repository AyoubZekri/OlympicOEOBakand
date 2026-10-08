<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Individual;
use App\Models\PlayerClearance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * The clearance card ("إخلاء الطرف") of a member leaving the club, in three steps:
 * 1. the departure is started (date, reason): the member is "leaving";
 * 2. each department clears the member; the app shows what is still pending in each one
 *    (equipment not returned, advances, open medical record, open disciplinary action, running contract);
 * 3. once every department signed, the player acknowledges and the file is closed: the member becomes inactive.
 */
class PlayerClearanceController extends Controller
{
    /** department => [status, signer, cleared at, notes] columns */
    private const DEPARTMENTS = [
        'admin' => ['admin_status', 'admin_id', 'admin_cleared_at', 'admin_notes'],
        'sporting' => ['sporting_status', 'sporting_director_id', 'sporting_cleared_at', 'sporting_notes'],
        'medical' => ['medical_status', 'medical_staff_id', 'medical_cleared_at', 'medical_notes'],
        'financial' => ['financial_status', 'finance_manager_id', 'finance_cleared_at', 'financial_notes'],
        'equipment' => ['equipment_status', 'equipment_manager_id', 'equipment_cleared_at', 'equipment_notes'],
    ];

    private const DONE = 'مكتمل';

    /** The card, what each department still has pending, and the signers' names */
    public function show($player_id)
    {
        $clearance = PlayerClearance::where('player_id', $player_id)->first();

        return response()->json([
            'status' => 'success',
            'data' => $clearance,
            'checks' => $this->checks((int) $player_id, $clearance?->exit_date),
            'signers' => $clearance ? $this->signerNames($clearance) : (object) [],
        ]);
    }

    /** The cards of every member (the members list shows who is leaving) */
    public function index()
    {
        $data = PlayerClearance::query()->get()->map(fn (PlayerClearance $c) => [
            'player_id' => $c->player_id,
            'exit_date' => $c->exit_date ? date('Y-m-d', strtotime($c->exit_date)) : null,
            'exit_reason' => $c->exit_reason,
            'signed' => collect(self::DEPARTMENTS)->filter(fn ($cols) => $c->{$cols[0]} === self::DONE)->count(),
            'total' => count(self::DEPARTMENTS),
            'closed' => (bool) $c->player_signature,
        ]);

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /** Step 1: start the departure, or change its date, reason and notes */
    public function updateOrCreate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'player_id' => 'required|exists:individuals,id',
            'exit_date' => 'required|date',
            'exit_reason' => 'required|string|max:255',
            'general_notes' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors(), 'message' => 'الرجاء تعبئة تاريخ وسبب المغادرة'], 422);
        }

        $clearance = PlayerClearance::firstOrNew(['player_id' => $request->player_id]);
        $clearance->fill($request->only(['exit_date', 'exit_reason', 'general_notes']));
        if (!$clearance->exists) {
            $clearance->started_by = $request->user()?->id;
        }
        $clearance->save();

        return response()->json(['status' => 'success', 'data' => $clearance->fresh()]);
    }

    /**
     * Step 2: a department clears the member (signed by the signed-in user).
     * Something still pending there: the signature needs a note saying why.
     */
    public function sign(Request $request, $player_id)
    {
        $request->validate([
            'department' => 'required|in:' . implode(',', array_keys(self::DEPARTMENTS)),
            'note' => 'nullable|string|max:2000',
        ]);
        $clearance = PlayerClearance::where('player_id', $player_id)->first();
        if (!$clearance) {
            return response()->json(['status' => 'error', 'message' => 'ابدأ إجراءات المغادرة أولاً'], 422);
        }
        if ($clearance->player_signature) {
            return response()->json(['status' => 'error', 'message' => 'الملف مغلق'], 422);
        }

        $department = $request->department;
        $pending = $this->checks((int) $player_id, $clearance->exit_date)[$department] ?? [];
        if ($pending && !trim((string) $request->note)) {
            return response()->json(['status' => 'error', 'message' => 'توجد أمور عالقة في هذا القسم: اكتب ملاحظة لتبرئة الذمة رغمها'], 422);
        }

        [$status, $signer, $at, $notes] = self::DEPARTMENTS[$department];
        $clearance->{$status} = self::DONE;
        $clearance->{$signer} = $request->user()?->id;
        $clearance->{$at} = now();
        $clearance->{$notes} = $request->note ?: null;
        $clearance->save();

        return $this->show($player_id);
    }

    /** Step 2: a department's signature is withdrawn (the file still open) */
    public function unsign(Request $request, $player_id)
    {
        $request->validate(['department' => 'required|in:' . implode(',', array_keys(self::DEPARTMENTS))]);
        $clearance = PlayerClearance::where('player_id', $player_id)->firstOrFail();
        if ($clearance->player_signature) {
            return response()->json(['status' => 'error', 'message' => 'الملف مغلق'], 422);
        }

        foreach (self::DEPARTMENTS[$request->department] as $column) {
            $clearance->{$column} = null;
        }
        $clearance->save();

        return $this->show($player_id);
    }

    /** Step 3: every department signed, the player acknowledges: the file is closed and the member becomes inactive */
    public function close($player_id)
    {
        $clearance = PlayerClearance::where('player_id', $player_id)->firstOrFail();
        $missing = collect(self::DEPARTMENTS)->filter(fn ($cols) => $clearance->{$cols[0]} !== self::DONE)->keys();
        if ($missing->isNotEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'لم توقّع كل الأقسام بعد'], 422);
        }

        DB::transaction(function () use ($clearance, $player_id) {
            $clearance->player_signature = true;
            $clearance->player_signed_at = now();
            $clearance->save();
            Individual::whereKey($player_id)->update(['status' => 'inactive']);
        });

        return $this->show($player_id);
    }

    /** The card is deleted: the member is active again */
    public function destroy($player_id)
    {
        $clearance = PlayerClearance::where('player_id', $player_id)->first();
        if (!$clearance) {
            return response()->json(['status' => 'error', 'message' => 'Player clearance not found'], 404);
        }
        DB::transaction(function () use ($clearance, $player_id) {
            $clearance->delete();
            Individual::whereKey($player_id)->update(['status' => 'active']);
        });

        return response()->json(['status' => 'success', 'message' => 'Player clearance deleted successfully']);
    }

    /** What is still pending in each department, read from the app's own records */
    private function checks(int $playerId, $exitDate): array
    {
        $day = fn ($date) => $date ? date('d/m/Y', strtotime($date)) : '';

        // Equipment handed over and not returned
        $equipment = DB::table('equipment_movements as m')
            ->join('equipment_operations as o', 'o.id', '=', 'm.operation_id')
            ->leftJoin('equipments as e', 'e.id', '=', 'm.equipment_id')
            ->where('o.member_id', $playerId)
            ->whereNull('m.return_date')
            ->get(['e.name', 'm.quantity'])
            ->map(fn ($m) => ['text' => trim(($m->name ?: 'معدات') . ' ×' . ($m->quantity ?: 1))])
            ->values()->all();

        // Advances not paid back, and contract dues not paid to the player
        $payments = DB::table('payment_expenses')->where('individuals_id', $playerId)->get(['amount_Nature', 'amount', 'contract_id']);
        $advances = $payments->where('amount_Nature', 'سلفة')->sum('amount') - $payments->where('amount_Nature', 'إرجاع سلفة')->sum('amount');
        $financial = [];
        if ($advances > 0) {
            $financial[] = ['text' => 'سلفة غير مسترجعة', 'amount' => round($advances, 2)];
        }
        $contracts = DB::table('contracts')->where('individuals_id', $playerId)->get(['id', 'Contract_value', 'end_date', 'status']);
        $dues = $contracts->sum(fn ($c) => (float) $c->Contract_value
            - $payments->where('amount_Nature', 'رقم دفعة')->where('contract_id', $c->id)->sum('amount'));
        if ($dues > 0) {
            $financial[] = ['text' => 'مستحقات عقد لم تُدفع للاعب', 'amount' => round($dues, 2)];
        }

        // Medical records not closed
        $medical = DB::table('player_medical_records')
            ->where('player_id', $playerId)
            ->where(fn ($q) => $q->whereNull('record_status')->orWhere('record_status', '!=', 'مغلق/متعافي'))
            ->get(['injury_nature', 'record_status'])
            ->map(fn ($r) => ['text' => trim(($r->injury_nature ?: 'ملف طبي') . ' — ' . ($r->record_status ?: 'مفتوح'))])
            ->values()->all();

        // Disciplinary actions still open
        $sporting = DB::table('disciplinary_cases')
            ->where('individuals_id', $playerId)
            ->whereIn('case_status', ['مفتوح', 'متأخر'])
            ->get(['description', 'incident_date'])
            ->map(fn ($c) => ['text' => 'إجراء تأديبي مفتوح: ' . ($c->description ?: '—') . ($c->incident_date ? ' (' . $day($c->incident_date) . ')' : '')])
            ->values()->all();

        // A contract still running after the departure
        $leaving = $exitDate ? strtotime($exitDate) : time();
        $admin = $contracts
            ->filter(fn ($c) => in_array($c->status, [null, '', 'active', 'نشط'], true) && (!$c->end_date || strtotime($c->end_date) > $leaving))
            ->map(fn ($c) => ['text' => $c->end_date ? 'عقد ساري حتى ' . $day($c->end_date) : 'عقد ساري دون تاريخ نهاية'])
            ->values()->all();

        return compact('admin', 'sporting', 'medical', 'financial', 'equipment');
    }

    /** department => the name of whoever signed it */
    private function signerNames(PlayerClearance $clearance): array
    {
        $ids = collect(self::DEPARTMENTS)->map(fn ($cols) => $clearance->{$cols[1]})->filter();
        $users = User::whereIn('id', $ids->unique()->values())->pluck('name', 'id');

        return $ids->map(fn ($id) => $users[$id] ?? null)->filter()->all();
    }
}
