<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Individual;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        // The member linked to each account (its type, and its photo for the account's picture), and its one role's name
        return response()->json(User::with(['individual:id,user_id,type,photo', 'role:id,name'])->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role_id' => 'nullable|exists:roles,id',
            'profile_image' => 'nullable|string',
            // The member's post: every user is a member too (added with the account)
            'type' => 'nullable|string|max:50',
        ]);

        $plain = $validated['password'];
        $type = $validated['type'] ?? null;
        unset($validated['password'], $validated['type']);
        $user = DB::transaction(function () use ($validated, $plain, $type) {
            $user = new User($validated);
            $user->setPasswordWithCopy($plain);
            $user->save();
            if ($type) {
                $this->memberOf($user, $type);
            }
            return $user;
        });

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
            'type' => 'nullable|string|max:50',
        ]);

        $plain = $validated['password'] ?? null;
        $type = $validated['type'] ?? null;
        unset($validated['password'], $validated['type']);
        // Another role: their own permissions were for the old one
        if (array_key_exists('role_id', $validated) && (string) $validated['role_id'] !== (string) $user->role_id) {
            $user->permissions = null;
        }
        $user->fill($validated);
        if ($plain) {
            $user->setPasswordWithCopy($plain);
        }
        $user->save();
        // A member's account: the member's name and email follow (the other direction is in IndividualController)
        \App\Services\MemberAccount::syncFromUser($user);
        // Its post: the member's type (an account without a member gets one)
        if ($type) {
            $this->memberOf($user, $type);
        }

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
        $role = $user?->effectiveRole();
        if (!$role) {
            return false;
        }
        if (strtolower((string) $role->type) === 'full') {
            return true;
        }
        $permissions = is_string($role->permissions) ? json_decode($role->permissions, true) : (array) $role->permissions;
        return ($permissions['usersAndRoles']['editUsers'] ?? false) === true;
    }

    /**
     * A member's own permissions, in place of their role's (null: back to the role's). From the role's window, by who
     * may edit roles.
     */
    public function permissions(Request $request)
    {
        $actor = $request->user();
        if (!\App\Services\RolePermissions::can($actor, 'usersAndRoles', 'editRoles')) {
            return response()->json(['message' => 'ليست لديك صلاحية تعديل الأدوار'], 403);
        }
        $data = $request->validate(['id' => 'required|integer|exists:users,id', 'permissions' => 'nullable|string']);
        if ((int) $data['id'] === (int) $actor->id) {
            return response()->json(['message' => 'لا يمكنك تغيير صلاحياتك بنفسك'], 422);
        }
        $user = User::findOrFail($data['id']);
        $user->permissions = $data['permissions'] ?? null;
        $user->save();

        return response()->json(['message' => 'تم الحفظ', 'permissions' => $user->permissions]);
    }

    /** The account's member, with this post: updated, or added (its name split as the member's first and last name) */
    private function memberOf(User $user, string $type): Individual
    {
        $member = Individual::where('user_id', $user->id)->first();
        if ($member) {
            if ($member->type !== $type) {
                $member->type = $type;
                $member->save();
            }
            return $member;
        }
        $words = preg_split('/\s+/u', trim((string) $user->name), -1, PREG_SPLIT_NO_EMPTY) ?: ['عضو'];
        $first = array_shift($words);

        return Individual::create([
            'first_name' => $first,
            'last_name' => $words ? implode(' ', $words) : $first,
            'type' => $type,
            'email' => $user->email,
            'status' => 'active',
            'user_id' => $user->id,
        ]);
    }

    public function destroy(Request $request)
    {
        $user = User::findOrFail($request->id);
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}
