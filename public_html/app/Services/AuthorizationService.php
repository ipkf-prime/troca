<?php

namespace App\Services;

use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use IPKF\Support\Session;

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
        return $this->permissions->getUserPermissions($userId, $this->activeAssignmentId());
    }

    public function hasPermission(
        int $userId,
        string $permissionCode
    ): bool {
        $assignmentId = $this->activeAssignmentId();

        if ($assignmentId !== null) {
            $assignment = $this->roles->assignmentForUser(
                $userId,
                $assignmentId
            );

            if (($assignment['role_code'] ?? '') === 'super_admin') {
                return true;
            }
        }

        $override = $this->permissions->userPermissionOverride(
            $userId,
            $permissionCode,
            $assignmentId
        );

        if ($override !== null) {
            return $override === 'allow';
        }

        return $this->permissions->userHasPermission(
            $userId,
            $permissionCode,
            $assignmentId
        );
    }

    private function activeAssignmentId(): ?int
    {
        $id = Session::get('active_role_assignment_id');

        return $id === null ? null : (int) $id;
    }
}
