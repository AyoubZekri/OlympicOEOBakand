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
        ]);

        DB::beginTransaction();

        try {
            $case = DisciplinaryCase::create([
                'individuals_id' => $validated['memberId'],
                'incident_date' => $validated['incidentDate'],
                'description' => $validated['reason'],
                'case_status' => $validated['status'],
                'added_by' => auth()->id() ?? 1,
            ]);

            $action = DisciplinaryAction::create([
                'case_id' => $case->id,
                'action_type' => $validated['actionType'],
                'action_date' => $validated['incidentDate'],
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
        ]);

        DB::beginTransaction();

        try {
            $case = DisciplinaryCase::findOrFail($validated['id']);
            $case->update([
                'individuals_id' => $validated['memberId'],
                'incident_date' => $validated['incidentDate'],
                'description' => $validated['reason'],
                'case_status' => $validated['status'],
            ]);

            $action = DisciplinaryAction::where('case_id', $case->id)->orderBy('id', 'desc')->first();
            if ($action) {
                $action->update([
                    'action_type' => $validated['actionType'],
                    'action_date' => $validated['incidentDate'],
                ]);
            } else {
                DisciplinaryAction::create([
                    'case_id' => $case->id,
                    'action_type' => $validated['actionType'],
                    'action_date' => $validated['incidentDate'],
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
