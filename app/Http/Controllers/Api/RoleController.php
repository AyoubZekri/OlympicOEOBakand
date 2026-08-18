<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
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
        ]);

        $role = Role::create($validated);

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
        ]);

        $role->update($validated);

        return response()->json(['message' => 'Role updated successfully', 'role' => $role]);
    }

    public function destroy(Request $request)
    {
        $role = Role::findOrFail($request->id);
        $role->delete();

        return response()->json(['message' => 'Role deleted successfully']);
    }
}
