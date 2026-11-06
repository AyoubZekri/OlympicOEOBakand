<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        return response()->json(Role::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'permissions' => 'nullable|string',
            'user_ids' => 'sometimes|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $role = Role::create(collect($validated)->except('user_ids')->all());
        if ($request->has('user_ids')) {
            $this->syncUsers($role, $validated['user_ids'] ?? [], $request->user());
        }

        return response()->json(['message' => 'Role created successfully', 'role' => $role], 201);
    }

    public function show(Request $request)
    {
        $role = Role::findOrFail($request->id);
        return response()->json($role);
    }

    public function update(Request $request)
    {
        $role = Role::findOrFail($request->id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|max:255',
            'permissions' => 'nullable|string',
            'user_ids' => 'sometimes|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $role->update(collect($validated)->except('user_ids')->all());
        if ($request->has('user_ids')) {
            $this->syncUsers($role, $validated['user_ids'] ?? [], $request->user());
        }

        return response()->json(['message' => 'Role updated successfully', 'role' => $role]);
    }

    /**
     * The role's users, chosen from the role's window: the ones chosen take this role,
     * the ones taken off it are left without a role (never the one saving it, so nobody locks themselves out).
     */
    private function syncUsers(Role $role, array $userIds, ?User $actor): void
    {
        $ids = array_map('intval', $userIds);
        User::whereIn('id', $ids)->update(['role_id' => $role->id]);
        User::where('role_id', $role->id)
            ->whereNotIn('id', $ids)
            ->when($actor, fn ($q) => $q->where('id', '!=', $actor->id))
            ->update(['role_id' => null]);
    }

    public function destroy(Request $request)
    {
        $role = Role::findOrFail($request->id);
        $role->delete();

        return response()->json(['message' => 'Role deleted successfully']);
    }
}
