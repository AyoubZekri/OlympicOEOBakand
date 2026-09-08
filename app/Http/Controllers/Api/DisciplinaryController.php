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
                'actionId' => $action ? (string) $action->id : null,
                'incidentLocation' => $case->incident_location ?? '',
                'violatedRule' => $case->violated_rule ?? '',
                'presentPeople' => $case->present_people ?? '',
                'attachments' => $case->attachments ?? '',
                'deadlineOrHearingDate' => $action && $action->deadline_or_hearing_date ? date('Y-m-d', strtotime($action->deadline_or_hearing_date)) : '',
                'hearingLocation' => $action ? $action->hearing_location ?? '' : '',
                'player_statements' => $action ? $action->player_statements ?? '' : '',
                'admin_notes' => $action ? $action->admin_notes ?? '' : '',
                'decision_outcome' => $action ? $action->decision_outcome ?? '' : '',
                'decision_reasons' => $action ? $action->decision_reasons ?? '' : '',
                'effective_date' => $action && $action->effective_date ? date('Y-m-d', strtotime($action->effective_date)) : '',
            ];
        }

        return response()->json($data);
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
                'player_statements' => $validated['player_statements'] ?? null,
                'admin_notes' => $validated['admin_notes'] ?? null,
                'decision_outcome' => $validated['decision_outcome'] ?? null,
                'decision_reasons' => $validated['decision_reasons'] ?? null,
                'effective_date' => $validated['effective_date'] ?? null,
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
                    'player_statements' => $validated['player_statements'] ?? null,
                    'admin_notes' => $validated['admin_notes'] ?? null,
                    'decision_outcome' => $validated['decision_outcome'] ?? null,
                    'decision_reasons' => $validated['decision_reasons'] ?? null,
                    'effective_date' => $validated['effective_date'] ?? null,
                ]);
            } else {
                DisciplinaryAction::create([
                    'case_id' => $case->id,
                    'action_type' => $validated['actionType'],
                    'action_date' => $validated['incidentDate'],
                    'deadline_or_hearing_date' => $validated['deadlineOrHearingDate'] ?? null,
                    'hearing_location' => $validated['hearingLocation'] ?? null,
                    'player_statements' => $validated['player_statements'] ?? null,
                    'admin_notes' => $validated['admin_notes'] ?? null,
                    'decision_outcome' => $validated['decision_outcome'] ?? null,
                    'decision_reasons' => $validated['decision_reasons'] ?? null,
                    'effective_date' => $validated['effective_date'] ?? null,
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
}
