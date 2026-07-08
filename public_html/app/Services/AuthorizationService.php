<?php

namespace App\Services;

use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;

class AuthorizationService extends BaseService
{
    protected RoleRepository $roles;

    protected PermissionRepository $permissions;

    public function __construct(?RoleRepository $roles = null, ?PermissionRepository $permissions = null)
    {
        $this->roles = $roles ?? new RoleRepository();
        $this->permissions = $permissions ?? new PermissionRepository();
    }

    public function rolesForUser(int $userId): array
    {
        return $this->roles->getUserRoles($userId);
    }

    public function permissionsForUser(int $userId): array
    {
        return $this->permissions->getUserPermissions($userId);
    }

    public function hasPermission(int $userId, string $permissionCode): bool
    {
        foreach ($this->rolesForUser($userId) as $role) {
            if (($role['code'] ?? '') === 'super_admin') {
                return true;
            }
        }

        return $this->permissions->userHasPermission($userId, $permissionCode);
    }
}
