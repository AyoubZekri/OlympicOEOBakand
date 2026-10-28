<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GeneralGoal;
use App\Models\User;
use App\Services\RolePermissions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** The management's general goals: each one a goal, the user responsible for it (المكلّف) and its season */
class GeneralGoalController extends Controller
{
    public function index(Request $request)
    {
        $this->allow($request, 'view');
        $goals = GeneralGoal::with('user:id,name')
            ->orderByDesc('season')
            ->orderByDesc('id')
            ->get();

        return response()->json(['status' => 'success', 'data' => $goals->map(fn ($g) => $this->present($g))]);
    }

    /** What the form picks from: the users, and the seasons already used */
    public function options(Request $request)
    {
        $this->allow($request, 'view');
        $users = User::orderBy('name')->get(['id', 'name'])->map(fn ($u) => $this->user($u));
        $seasons = GeneralGoal::distinct()->orderByDesc('season')->pluck('season');

        return response()->json(['status' => 'success', 'data' => ['users' => $users, 'seasons' => $seasons]]);
    }

    public function store(Request $request)
    {
        $this->allow($request, 'add');
        $goal = GeneralGoal::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return response()->json(['status' => 'success', 'data' => $this->present($goal->load('user'))], 201);
    }

    public function update(Request $request)
    {
        $this->allow($request, 'edit');
        $request->validate(['id' => 'required|integer|exists:general_goals,id']);
        $goal = GeneralGoal::findOrFail($request->input('id'));
        $goal->update($this->validated($request));

        return response()->json(['status' => 'success', 'data' => $this->present($goal->fresh('user'))]);
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
            'user_id' => 'required|integer|exists:users,id',
            'season' => 'required|string|max:20',
        ], [
            'goal.required' => 'اكتب الهدف',
            'user_id.required' => 'اختر المكلّف',
            'user_id.exists' => 'المستخدم غير موجود',
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

    private function user(User $u): array
    {
        return ['id' => $u->id, 'name' => $u->name];
    }

    private function present(GeneralGoal $g): array
    {
        return [
            'id' => $g->id,
            'goal' => $g->goal,
            'user_id' => $g->user_id,
            'user' => $g->user ? $this->user($g->user) : null,
            'season' => $g->season,
            'created_at' => $g->created_at?->toDateTimeString(),
            'updated_at' => $g->updated_at?->toDateTimeString(),
        ];
    }
}
