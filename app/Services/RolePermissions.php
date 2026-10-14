<?php

namespace App\Services;

use App\Models\User;

/**
 * A role's permission on one section, read from the same role data as the frontend
 * (roles.type = full, or roles.permissions JSON → {module}.{action}). Not stored = not allowed.
 */
class RolePermissions
{
    public static function can(?User $user, string $module, string $action): bool
    {
        $role = $user?->role;
        if (!$role) {
            return false;
        }
        if (strtolower((string) $role->type) === 'full') {
            return true;
        }

        $permissions = is_string($role->permissions) ? json_decode($role->permissions, true) : (array) $role->permissions;

        return is_array($permissions) && (($permissions[$module][$action] ?? false) === true);
    }
}
