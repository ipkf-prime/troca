<?php

namespace IPKF\Auth;

use App\Models\UserRole;
use App\Models\RolePermission;

class Authorization
{
    public function hasRole(int $userId, string $roleSlug): bool
    {
        $roles = UserRole::query()
            ->where('user_id', '=', $userId)
            ->get();

        foreach ($roles as $role) {
            if ($role['role_id'] === $roleSlug) {
                return true;
            }
        }

        return false;
    }

    public function hasPermission(int $roleId, string $permissionSlug): bool
    {
        $permissions = RolePermission::query()
            ->where('role_id', '=', $roleId)
            ->get();

        foreach ($permissions as $perm) {
            if ($perm['permission_id'] === $permissionSlug) {
                return true;
            }
        }

        return false;
    }
}