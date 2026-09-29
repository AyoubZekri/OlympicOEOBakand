<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return response()->json(User::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role_id' => 'nullable|exists:roles,id',
            'profile_image' => 'nullable|string',
        ]);

        $plain = $validated['password'];
        unset($validated['password']);
        $user = new User($validated);
        $user->setPasswordWithCopy($plain);
        $user->save();

        return response()->json(['message' => 'User created successfully', 'user' => $user], 201);
    }

    public function show(Request $request)
    {
        $user = User::findOrFail($request->id);
        return response()->json($user);
    }

    public function update(Request $request)
    {
        $user = User::findOrFail($request->id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'user_notify_status' => 'boolean',
            'fcm_token' => 'nullable|string',
            'role_id' => 'nullable|exists:roles,id',
            'profile_image' => 'nullable|string',
        ]);

        $plain = $validated['password'] ?? null;
        unset($validated['password']);
        $user->fill($validated);
        if ($plain) {
            $user->setPasswordWithCopy($plain);
        }
        $user->save();
        // A member's account: the member's name and email follow (the other direction is in IndividualController)
        \App\Services\MemberAccount::syncFromUser($user);

        return response()->json(['message' => 'User updated successfully', 'user' => $user]);
    }

    /** The account's password in clear, for those allowed to edit users (null: set before copies were kept) */
    public function password(Request $request)
    {
        if (!$this->canManageUsers($request->user())) {
            return response()->json(['message' => 'لا تملك صلاحية عرض كلمات المرور'], 403);
        }
        $user = User::findOrFail($request->input('id'));

        return response()->json(['status' => 'success', 'password' => $user->passwordCopy()]);
    }

    /** A new random password for the account, returned so it can be handed to its owner */
    public function generatePassword(Request $request)
    {
        if (!$this->canManageUsers($request->user())) {
            return response()->json(['message' => 'لا تملك صلاحية تغيير كلمات المرور'], 403);
        }
        $user = User::findOrFail($request->input('id'));
        $plain = \Illuminate\Support\Str::password(10, symbols: false);
        $user->setPasswordWithCopy($plain);
        $user->save();

        return response()->json(['status' => 'success', 'password' => $plain]);
    }

    /** Full-access roles, or roles with usersAndRoles.editUsers */
    private function canManageUsers(?User $user): bool
    {
        $role = $user?->role;
        if (!$role) {
            return false;
        }
        if (strtolower((string) $role->type) === 'full') {
            return true;
        }
        $permissions = is_string($role->permissions) ? json_decode($role->permissions, true) : (array) $role->permissions;
        return ($permissions['usersAndRoles']['editUsers'] ?? false) === true;
    }

    public function destroy(Request $request)
    {
        $user = User::findOrFail($request->id);
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}
