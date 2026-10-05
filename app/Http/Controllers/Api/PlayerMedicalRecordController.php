<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PlayerMedicalRecord;
use App\Models\MedicalNotice;
use Illuminate\Support\Facades\Validator;
use Exception;

class PlayerMedicalRecordController extends Controller
{
    public function index()
    {
        try {
            $records = PlayerMedicalRecord::with(['playerId', 'doctorId'])->orderBy('created_at', 'desc')->get();
            return response()->json([
                'status' => 'success',
                'data' => $records
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch medical records',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'player_id' => 'required|exists:individuals,id',
            'doctor_id' => 'required|exists:individuals,id',
            'injury_date' => 'required|date',
            'incident_location' => 'nullable|string|max:255',
            'injury_nature' => 'nullable|string|max:255',
            'diagnosis' => 'nullable|string',
            'initial_recommendation' => 'nullable|string|max:255',
            'last_exam_date' => 'nullable|date',
            'medical_decision' => 'nullable|string|max:255',
            'restrictions' => 'nullable|string',
            'next_exam_date' => 'nullable|date',
            'record_status' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $record = PlayerMedicalRecord::create($request->all());
            return response()->json([
                'status' => 'success',
                'message' => 'Medical record created successfully',
                'data' => $record->load(['playerId', 'doctorId'])
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create medical record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'player_id' => 'sometimes|exists:individuals,id',
            'doctor_id' => 'sometimes|exists:individuals,id',
            'injury_date' => 'sometimes|date',
            'incident_location' => 'nullable|string|max:255',
            'injury_nature' => 'nullable|string|max:255',
            'diagnosis' => 'nullable|string',
            'initial_recommendation' => 'nullable|string|max:255',
            'last_exam_date' => 'nullable|date',
            'medical_decision' => 'nullable|string|max:255',
            'restrictions' => 'nullable|string',
            'next_exam_date' => 'nullable|date',
            'record_status' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $record = PlayerMedicalRecord::find($id);
            if (!$record) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Medical record not found'
                ], 404);
            }

            $before = MedicalNotice::snapshot($record);
            $oldPlayer = $record->getAttributes()['player_id'] ?? null;
            $oldStatus = $record->record_status;
            $record->update($request->all());
            $this->announceChange($record->fresh(), $before, $oldPlayer, $oldStatus);
            return response()->json([
                'status' => 'success',
                'message' => 'Medical record updated successfully',
                'data' => $record->load(['playerId', 'doctorId'])
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update medical record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $record = PlayerMedicalRecord::find($id);
            if (!$record) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Medical record not found'
                ], 404);
            }

            MedicalNotice::record($record, 'deleted', $record->getAttributes()['player_id'] ?? null);
            $record->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Medical record deleted successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete medical record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Personal space: my medical files (the members linked to my account), newest first.
     * The diagnosis is marked confidential: it stays with the medical staff and is not sent here.
     */
    public function mine(Request $request)
    {
        $memberIds = \App\Models\Individual::where('user_id', $request->user()->id)->pluck('id');
        $records = PlayerMedicalRecord::with(['playerId:id,first_name,last_name,photo', 'doctorId:id,first_name,last_name'])
            ->whereIn('player_id', $memberIds)
            ->orderByDesc('injury_date')
            ->orderByDesc('id')
            ->get();

        $name = fn ($p) => $p ? trim($p->first_name . ' ' . $p->last_name) : null;
        $data = $records->map(fn (PlayerMedicalRecord $r) => [
            'id' => $r->id,
            'player_id' => $r->getAttributes()['player_id'] ?? null,
            'doctor_id' => $r->getAttributes()['doctor_id'] ?? null,
            'player' => $r->playerId ? ['id' => $r->playerId->id, 'name' => $name($r->playerId), 'photo' => $r->playerId->photo] : null,
            'doctor' => $r->doctorId ? ['id' => $r->doctorId->id, 'name' => $name($r->doctorId)] : null,
            'injury_date' => $r->injury_date,
            'incident_location' => $r->incident_location,
            'injury_nature' => $r->injury_nature,
            'diagnosis' => null,
            'initial_recommendation' => $r->initial_recommendation,
            'last_exam_date' => $r->last_exam_date,
            'medical_decision' => $r->medical_decision,
            'absence_from' => $r->absence_from,
            'absence_to' => $r->absence_to,
            'restrictions' => $r->restrictions,
            'next_exam_date' => $r->next_exam_date,
            'record_status' => $r->record_status,
            'created_at' => $r->created_at?->toIso8601String(),
            'updated_at' => $r->updated_at?->toIso8601String(),
        ])->values();

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /**
     * Tells the member what changed in their file: a new stage (the status changed), or a date / the doctor / a detail.
     * A file moved to another member is announced as deleted to the first one.
     */
    private function announceChange(PlayerMedicalRecord $record, array $before, $oldPlayer, ?string $oldStatus): void
    {
        $player = $record->getAttributes()['player_id'] ?? null;
        if ((int) $oldPlayer !== (int) $player) {
            MedicalNotice::record($record, 'deleted', $oldPlayer);
            return;
        }
        if ($record->record_status !== $oldStatus) {
            MedicalNotice::record($record, 'stage', $player, [['field' => 'record_status', 'label' => 'الحالة', 'from' => $oldStatus, 'to' => $record->record_status]]);
            return;
        }
        $changes = MedicalNotice::diff($before, MedicalNotice::snapshot($record));
        if ($changes) {
            MedicalNotice::record($record, 'updated', $player, $changes);
        }
    }

    /** Personal space: what happened to my medical files in the last 14 days, newest first */
    public function myNotices(Request $request)
    {
        $memberIds = \App\Models\Individual::where('user_id', $request->user()->id)->pluck('id');
        $notices = MedicalNotice::whereIn('player_id', $memberIds)
            ->where('created_at', '>=', now()->subDays(14))
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return response()->json($notices->map(fn (MedicalNotice $n) => [
            'id' => $n->id,
            'record_id' => $n->record_id,
            'kind' => $n->kind,
            'injury_nature' => $n->injury_nature,
            'changes' => $n->changes ?? [],
            'created_at' => $n->created_at?->toIso8601String(),
        ])->values());
    }
}
