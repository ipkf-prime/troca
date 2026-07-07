<?php

namespace IPKF\Core;

class RBAC
{
    protected array $permissions = [
        'super_admin' => ['*'],
        'system_admin' => ['users.view', 'users.edit'],
        'user' => ['dashboard.view']
    ];

    public function can(string $permission): bool
    {
        $user = $_SESSION['user'] ?? null;

        if (!$user) return false;

        $role = $user['role'] ?? 'user';

        $perms = $this->permissions[$role] ?? [];

        return in_array('*', $perms) || in_array($permission, $perms);
    }
}