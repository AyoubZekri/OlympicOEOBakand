<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GeneralGoal;
use App\Models\Individual;
use App\Services\RolePermissions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** The management's general goals: each one a goal, the member responsible for it (المكلّف) and its season */
class GeneralGoalController extends Controller
{
    private const MEMBER_TYPES = ['player' => 'لاعب', 'coach' => 'مدرب', 'employee' => 'موظف'];

    public function index(Request $request)
    {
        $this->allow($request, 'view');
        $goals = GeneralGoal::with('individual:id,first_name,last_name,type,photo')
            ->orderByDesc('season')
            ->orderByDesc('id')
            ->get();

        return response()->json(['status' => 'success', 'data' => $goals->map(fn ($g) => $this->present($g))]);
    }

    /** What the form picks from: the members, and the seasons already used */
    public function options(Request $request)
    {
        $this->allow($request, 'view');
        $members = Individual::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'type', 'photo'])
            ->map(fn ($m) => $this->member($m));
        $seasons = GeneralGoal::distinct()->orderByDesc('season')->pluck('season');

        return response()->json(['status' => 'success', 'data' => ['members' => $members, 'seasons' => $seasons]]);
    }

    public function store(Request $request)
    {
        $this->allow($request, 'add');
        $goal = GeneralGoal::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return response()->json(['status' => 'success', 'data' => $this->present($goal->load('individual'))], 201);
    }

    public function update(Request $request)
    {
        $this->allow($request, 'edit');
        $request->validate(['id' => 'required|integer|exists:general_goals,id']);
        $goal = GeneralGoal::findOrFail($request->input('id'));
        $goal->update($this->validated($request));

        return response()->json(['status' => 'success', 'data' => $this->present($goal->fresh('individual'))]);
    }

    public function destroy(Request $request)
    {
        $this->allow($request, 'delete');
        $data = $request->validate(['id' => 'required|integer|exists:general_goals,id']);
        GeneralGoal::whereKey($data['id'])->delete();

        return response()->json(['status' => 'success']);
    }

    private function allow(Request $request, string $action): void
    {
        abort_unless(RolePermissions::can($request->user(), 'goals', $action), 403, 'ليست لديك صلاحية');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'goal' => 'required|string|max:5000',
            'individual_id' => 'required|integer|exists:individuals,id',
            'season' => 'required|string|max:20',
        ], [
            'goal.required' => 'اكتب الهدف',
            'individual_id.required' => 'اختر المكلّف',
            'individual_id.exists' => 'العضو غير موجود',
            'season.required' => 'اختر الموسم',
        ]);

        // One way of writing a season: "2026 - 2027" (also accepted: 2026-2027, 2026/2027)
        if (!preg_match('/^\s*(\d{4})\s*[-\/]\s*(\d{4})\s*$/', $data['season'], $m) || (int) $m[2] !== (int) $m[1] + 1) {
            throw ValidationException::withMessages(['season' => 'اكتب الموسم بصيغة 2026 - 2027']);
        }
        $data['season'] = "{$m[1]} - {$m[2]}";
        $data['goal'] = trim($data['goal']);

        return $data;
    }

    private function member(Individual $m): array
    {
        return [
            'id' => $m->id,
            'name' => trim("{$m->first_name} {$m->last_name}"),
            'type' => self::MEMBER_TYPES[$m->type] ?? $m->type,
            'photo' => $m->photo,
        ];
    }

    private function present(GeneralGoal $g): array
    {
        return [
            'id' => $g->id,
            'goal' => $g->goal,
            'individual_id' => $g->individual_id,
            'member' => $g->individual ? $this->member($g->individual) : null,
            'season' => $g->season,
            'created_at' => $g->created_at?->toDateTimeString(),
            'updated_at' => $g->updated_at?->toDateTimeString(),
        ];
    }
}
