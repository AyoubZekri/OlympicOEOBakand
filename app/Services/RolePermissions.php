<?php

namespace App\Services;

use App\Models\User;

/**
 * A role's permission on one section, read from the same role data as the frontend
 * (roles.type = full, or roles.permissions JSON → {module}.{action}). Not stored = not allowed.
 */
class RolePermissions
{
    /**
     * Actions added after roles were saved: until a role is saved again, each follows the one it came from
     * (the home page's voice notes, status and conversation; the appointments, once part of the tasks).
     * The same rule as the frontend's (role_model FOLLOWS).
     */
    private const FALLBACK = [
        'tasks.voice' => ['tasks', 'add'],
        'tasks.changeStatus' => ['tasks', 'edit'],
        'tasks.chat' => ['tasks', 'view'],
        'appointments.view' => ['tasks', 'view'],
        'appointments.viewUpcoming' => ['appointments', 'view'],
        'appointments.add' => ['tasks', 'add'],
        'appointments.edit' => ['tasks', 'edit'],
        'appointments.toggle' => ['tasks', 'edit'],
        'appointments.delete' => ['tasks', 'delete'],
    ];

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

        if (!is_array($permissions)) {
            return false;
        }
        $value = $permissions[$module][$action] ?? null;
        if ($value === null && isset(self::FALLBACK["{$module}.{$action}"])) {
            // What it follows (which may itself follow another)
            [$from, $fromAction] = self::FALLBACK["{$module}.{$action}"];

            return self::can($user, $from, $fromAction);
        }

        return $value === true;
    }
}
