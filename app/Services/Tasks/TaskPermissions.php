<?php

namespace App\Services\Tasks;

use App\Models\User;

/**
 * Role permissions for the tasks section, read from the same role data as the frontend
 * (roles.type = full, or roles.permissions JSON → tasks.{view, add, edit, delete, review, manage, templates}).
 * A role saved before tasks existed may only see its own tasks.
 */
class TaskPermissions
{
    public static function can(?User $user, string $action): bool
    {
        if (!$user) {
            return false;
        }

        $role = $user->role;
        if (!$role) {
            return $action === 'view';
        }
        if (strtolower((string) $role->type) === 'full') {
            return true;
        }

        $permissions = is_string($role->permissions) ? json_decode($role->permissions, true) : (array) $role->permissions;
        if (!is_array($permissions) || !isset($permissions['tasks']) || !is_array($permissions['tasks'])) {
            return $action === 'view';
        }

        return ($permissions['tasks'][$action] ?? false) === true;
    }
}
