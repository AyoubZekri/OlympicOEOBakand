<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DisciplinaryCase;
use App\Models\DisciplinaryAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DisciplinaryController extends Controller
{
    public function index()
    {
        // Join cases and actions to match the frontend model
        $cases = DisciplinaryCase::with(['individualsId', 'addedBy'])->orderBy('id', 'desc')->get();
        
        $data = [];
        foreach ($cases as $case) {
            // Find the associated action (taking the latest if multiple exist, but usually 1:1)
            $action = DisciplinaryAction::where('case_id', $case->id)->orderBy('id', 'desc')->first();
            
            $data[] = [
                'id' => (string) $case->id,
                'memberId' => (string) $case->individuals_id,
                'memberName' => $case->individualsId ? $case->individualsId->first_name . ' ' . $case->individualsId->last_name : 'غير معروف',
                'actionType' => $action ? $action->action_type : 'طلب توضيح',
                'incidentDate' => $case->incident_date ? date('Y-m-d', strtotime($case->incident_date)) : '',
                'reason' => $case->description ?? '',
                'status' => $case->case_status ?? 'مفتوح',
                'is_acknowledged' => $action ? (bool) $action->is_acknowledged : false,
                'acknowledged_at' => $action ? $action->acknowledged_at : null,
                'signed_document' => $action ? $action->signed_document : null,
                'actionId' => $action ? (string) $action->id : null,
                'incidentLocation' => $case->incident_location ?? '',
                'violatedRule' => $case->violated_rule ?? '',
                'presentPeople' => $case->present_people ?? '',
                'attachments' => $case->attachments ?? '',
                'deadlineOrHearingDate' => $action && $action->deadline_or_hearing_date ? date('Y-m-d', strtotime($action->deadline_or_hearing_date)) : '',
                'hearingLocation' => $action ? $action->hearing_location ?? '' : '',
                'hearingOfficer' => $action ? $action->hearing_officer ?? '' : '',
                'hearingEndTime' => $action ? $action->hearing_end_time ?? '' : '',
                'hearingTime' => $action && $action->deadline_or_hearing_date && date('H:i', strtotime($action->deadline_or_hearing_date)) !== '00:00' ? date('H:i', strtotime($action->deadline_or_hearing_date)) : '',
                'player_statements' => $action ? $action->player_statements ?? '' : '',
                'admin_notes' => $action ? $action->admin_notes ?? '' : '',
                'decision_outcome' => $action ? $action->decision_outcome ?? '' : '',
                'decision_reasons' => $action ? $action->decision_reasons ?? '' : '',
                'effective_date' => $action && $action->effective_date ? date('Y-m-d', strtotime($action->effective_date)) : '',
            ];
        }

        return response()->json($data);
    }

    /**
     * Personal space: the disciplinary actions of the signed-in user (the member linked to their account),
     * newest first, with the administration's notes and decision (its answer to the member).
     */
    public function mine(Request $request)
    {
        $memberIds = \App\Models\Individual::where('user_id', $request->user()->id)->pluck('id');
        $cases = DisciplinaryCase::whereIn('individuals_id', $memberIds)
            ->orderByDesc('incident_date')
            ->orderByDesc('id')
            ->get();

        $data = $cases->map(function (DisciplinaryCase $case) {
            $action = DisciplinaryAction::where('case_id', $case->id)->orderBy('id', 'desc')->first();

            return [
                'id' => (string) $case->id,
                'actionType' => $action ? $action->action_type : 'طلب توضيح',
                'incidentDate' => $case->incident_date ? date('Y-m-d', strtotime($case->incident_date)) : '',
                'reason' => $case->description ?? '',
                'status' => $case->case_status ?? 'مفتوح',
                'incidentLocation' => $case->incident_location ?? '',
                'violatedRule' => $case->violated_rule ?? '',
                'presentPeople' => $case->present_people ?? '',
                'deadlineOrHearingDate' => $action && $action->deadline_or_hearing_date ? date('Y-m-d', strtotime($action->deadline_or_hearing_date)) : '',
                'hearingLocation' => $action ? $action->hearing_location ?? '' : '',
                'hearingOfficer' => $action ? $action->hearing_officer ?? '' : '',
                'hearingEndTime' => $action ? $action->hearing_end_time ?? '' : '',
                'hearingTime' => $action && $action->deadline_or_hearing_date && date('H:i', strtotime($action->deadline_or_hearing_date)) !== '00:00' ? date('H:i', strtotime($action->deadline_or_hearing_date)) : '',
                'player_statements' => $action ? $action->player_statements ?? '' : '',
                'admin_notes' => $action ? $action->admin_notes ?? '' : '',
                'decision_outcome' => $action ? $action->decision_outcome ?? '' : '',
                'decision_reasons' => $action ? $action->decision_reasons ?? '' : '',
                'effective_date' => $action && $action->effective_date ? date('Y-m-d', strtotime($action->effective_date)) : '',
                'is_acknowledged' => $action ? (bool) $action->is_acknowledged : false,
                'signed_document' => $action ? $action->signed_document : null,
            ];
        })->values();

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /**
     * Personal space: the member answers a clarification request (طلب توضيح) concerning them.
     * Allowed until the administration has written its decision.
     */
    public function reply(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:disciplinary_cases,id',
            'player_statements' => 'required|string|max:5000',
        ], [
            'player_statements.required' => 'اكتب ردك',
        ]);

        $case = DisciplinaryCase::findOrFail($data['id']);
        $mine = \App\Models\Individual::where('user_id', $request->user()->id)->where('id', $case->individuals_id)->exists();
        if (! $mine) {
            return response()->json(['message' => 'هذا الإجراء لا يخصك'], 403);
        }

        $action = DisciplinaryAction::where('case_id', $case->id)->orderBy('id', 'desc')->first();
        if (! $action || $action->action_type !== 'طلب توضيح') {
            return response()->json(['message' => 'الرد متاح فقط على طلبات التوضيح'], 422);
        }
        if (trim((string) $action->admin_notes) !== '' || trim((string) $action->decision_outcome) !== '') {
            return response()->json(['message' => 'صدر القرار، لا يمكن تعديل الرد'], 422);
        }

        $action->player_statements = trim($data['player_statements']);
        $action->save();

        return response()->json(['status' => 'success']);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'memberId' => 'required|exists:individuals,id',
            'actionType' => 'required|string',
            'incidentDate' => 'required|date',
            'reason' => 'required|string',
            'status' => 'required|string',
            'incidentLocation' => 'nullable|string',
            'violatedRule' => 'nullable|string',
            'presentPeople' => 'nullable|string',
            'attachments' => 'nullable|string',
            'deadlineOrHearingDate' => 'nullable|date',
            'hearingLocation' => 'nullable|string',
            'hearingOfficer' => 'nullable|string|max:255',
            'hearingEndTime' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'player_statements' => 'nullable|string',
            'admin_notes' => 'nullable|string',
            'decision_outcome' => 'nullable|string',
            'decision_reasons' => 'nullable|string',
            'effective_date' => 'nullable|date',
        ]);

        DB::beginTransaction();

        try {
            $case = DisciplinaryCase::create([
                'individuals_id' => $validated['memberId'],
                'incident_date' => $validated['incidentDate'],
                'description' => $validated['reason'],
                'case_status' => $validated['status'],
                'incident_location' => $validated['incidentLocation'] ?? null,
                'violated_rule' => $validated['violatedRule'] ?? null,
                'present_people' => $validated['presentPeople'] ?? null,
                'attachments' => $validated['attachments'] ?? null,
                'added_by' => auth()->id() ?? 1,
            ]);

            $action = DisciplinaryAction::create([
                'case_id' => $case->id,
                'action_type' => $validated['actionType'],
                'action_date' => $validated['incidentDate'],
                'deadline_or_hearing_date' => $validated['deadlineOrHearingDate'] ?? null,
                'hearing_location' => $validated['hearingLocation'] ?? null,
                'hearing_officer' => $validated['hearingOfficer'] ?? null,
                'hearing_end_time' => $validated['hearingEndTime'] ?? null,
                'player_statements' => $validated['player_statements'] ?? null,
                'admin_notes' => $validated['admin_notes'] ?? null,
                'decision_outcome' => $validated['decision_outcome'] ?? null,
                'decision_reasons' => $validated['decision_reasons'] ?? null,
                'effective_date' => $validated['effective_date'] ?? null,
                'is_acknowledged' => $request->has('is_acknowledged') ? $request->is_acknowledged : false,
                'acknowledged_at' => $request->has('acknowledged_at') ? $request->acknowledged_at : null,
                'added_by' => auth()->id() ?? 1,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'تم إضافة الإجراء بنجاح',
                'data' => [
                    'id' => (string) $case->id,
                    'memberId' => (string) $case->individuals_id,
                    'memberName' => $case->individualsId ? $case->individualsId->first_name . ' ' . $case->individualsId->last_name : '',
                    'actionType' => $action->action_type,
                    'incidentDate' => date('Y-m-d', strtotime($case->incident_date)),
                    'reason' => $case->description,
                    'status' => $case->case_status,
                    'incidentLocation' => $case->incident_location,
                    'violatedRule' => $case->violated_rule,
                    'presentPeople' => $case->present_people,
                    'attachments' => $case->attachments,
                    'deadlineOrHearingDate' => $action->deadline_or_hearing_date ? date('Y-m-d', strtotime($action->deadline_or_hearing_date)) : '',
                    'hearingLocation' => $action->hearing_location,
                    'hearingOfficer' => $action->hearing_officer,
                    'hearingEndTime' => $action->hearing_end_time,
                    'player_statements' => $action->player_statements,
                    'admin_notes' => $action->admin_notes,
                    'decision_outcome' => $action->decision_outcome,
                    'decision_reasons' => $action->decision_reasons,
                    'effective_date' => $action->effective_date ? date('Y-m-d', strtotime($action->effective_date)) : '',
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:disciplinary_cases,id',
            'memberId' => 'required|exists:individuals,id',
            'actionType' => 'required|string',
            'incidentDate' => 'required|date',
            'reason' => 'required|string',
            'status' => 'required|string',
            'incidentLocation' => 'nullable|string',
            'violatedRule' => 'nullable|string',
            'presentPeople' => 'nullable|string',
            'attachments' => 'nullable|string',
            'deadlineOrHearingDate' => 'nullable|date',
            'hearingLocation' => 'nullable|string',
            'hearingOfficer' => 'nullable|string|max:255',
            'hearingEndTime' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'player_statements' => 'nullable|string',
            'admin_notes' => 'nullable|string',
            'decision_outcome' => 'nullable|string',
            'decision_reasons' => 'nullable|string',
            'effective_date' => 'nullable|date',
        ]);

        DB::beginTransaction();

        try {
            $case = DisciplinaryCase::findOrFail($validated['id']);
            $case->update([
                'individuals_id' => $validated['memberId'],
                'incident_date' => $validated['incidentDate'],
                'description' => $validated['reason'],
                'case_status' => $validated['status'],
                'incident_location' => $validated['incidentLocation'] ?? null,
                'violated_rule' => $validated['violatedRule'] ?? null,
                'present_people' => $validated['presentPeople'] ?? null,
                'attachments' => $validated['attachments'] ?? null,
            ]);

            $action = DisciplinaryAction::where('case_id', $case->id)->orderBy('id', 'desc')->first();
            if ($action) {
                $action->update([
                    'action_type' => $validated['actionType'],
                    'action_date' => $validated['incidentDate'],
                    'deadline_or_hearing_date' => $validated['deadlineOrHearingDate'] ?? null,
                    'hearing_location' => $validated['hearingLocation'] ?? null,
                    'hearing_officer' => $validated['hearingOfficer'] ?? null,
                    'hearing_end_time' => $validated['hearingEndTime'] ?? null,
                    'player_statements' => $validated['player_statements'] ?? null,
                    'admin_notes' => $validated['admin_notes'] ?? null,
                    'decision_outcome' => $validated['decision_outcome'] ?? null,
                    'decision_reasons' => $validated['decision_reasons'] ?? null,
                    'effective_date' => $validated['effective_date'] ?? null,
                    'is_acknowledged' => $request->has('is_acknowledged') ? $request->is_acknowledged : ($action ? $action->is_acknowledged : false),
                    'acknowledged_at' => $request->has('acknowledged_at') ? $request->acknowledged_at : ($action ? $action->acknowledged_at : null),
                ]);
            } else {
                DisciplinaryAction::create([
                    'case_id' => $case->id,
                    'action_type' => $validated['actionType'],
                    'action_date' => $validated['incidentDate'],
                    'deadline_or_hearing_date' => $validated['deadlineOrHearingDate'] ?? null,
                    'hearing_location' => $validated['hearingLocation'] ?? null,
                    'hearing_officer' => $validated['hearingOfficer'] ?? null,
                    'hearing_end_time' => $validated['hearingEndTime'] ?? null,
                    'player_statements' => $validated['player_statements'] ?? null,
                    'admin_notes' => $validated['admin_notes'] ?? null,
                    'decision_outcome' => $validated['decision_outcome'] ?? null,
                    'decision_reasons' => $validated['decision_reasons'] ?? null,
                    'effective_date' => $validated['effective_date'] ?? null,
                    'is_acknowledged' => $request->has('is_acknowledged') ? $request->is_acknowledged : false,
                    'acknowledged_at' => $request->has('acknowledged_at') ? $request->acknowledged_at : null,
                    'added_by' => auth()->id() ?? 1,
                ]);
            }

            DB::commit();

            return response()->json(['message' => 'تم التحديث بنجاح'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:disciplinary_cases,id',
        ]);

        DB::beginTransaction();

        try {
            $case = DisciplinaryCase::findOrFail($validated['id']);
            
            // Delete related actions first
            DisciplinaryAction::where('case_id', $case->id)->delete();
            
            $case->delete();

            DB::commit();

            return response()->json(['message' => 'تم الحذف بنجاح'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    
    public function uploadDocument(\Illuminate\Http\Request $request, $id)
    {
        try {
            $request->validate([
                'document' => 'required|file|mimes:jpeg,png,jpg,pdf|max:5120',
            ]);

            $case = \App\Models\DisciplinaryCase::find($id);

            if (!$case) {
                return response()->json(['status' => 'error', 'message' => 'الإجراء غير موجود'], 404);
            }

            if ($request->hasFile('document')) {
                $file = $request->file('document');
                $extension = $file->getClientOriginalExtension();
                $filename = 'signed_doc_' . time() . '.' . $extension;
                
                // Create directory if it doesn't exist
                $destinationPath = public_path('uploads/disciplinary');
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }

                $file->move($destinationPath, $filename);
                $path = asset('uploads/disciplinary/' . $filename);
                
                $action = \App\Models\DisciplinaryAction::where('case_id', $case->id)->orderBy('id', 'desc')->first();
                if ($action) {
                    $action->signed_document = $path;
                    $action->save();
                }
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'تم رفع الوثيقة بنجاح',
                    'path' => $path
                ]);
            }

            return response()->json(['status' => 'error', 'message' => 'لم يتم إرسال أي ملف'], 400);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}