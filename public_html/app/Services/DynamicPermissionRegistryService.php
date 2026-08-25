<?php

namespace App\Services;

use IPKF\Database\Database;

class DynamicPermissionRegistryService extends BaseService
{
    public function syncModule(array $module): void
    {
        if (!Database::tableExists('permissions')) {
            return;
        }

        $moduleKey = strtolower(
            trim((string) ($module['module_key'] ?? ''))
        );

        if (
            $moduleKey === ''
            || preg_match(
                '/^[a-z][a-z0-9_-]{1,79}$/',
                $moduleKey
            ) !== 1
        ) {
            return;
        }

        $active =
            (int) ($module['is_active'] ?? 0) === 1;

        /*
         * Module disable:
         * keep role_permissions untouched,
         * only deactivate permission catalog rows.
         */
        if (!$active) {
            $statement = Database::connect()->prepare("
                UPDATE permissions
                SET
                    is_active = 0,
                    updated_at = CURRENT_TIMESTAMP
                WHERE module = ?
            ");

            $statement->execute([$moduleKey]);

            return;
        }

        $displayName = trim(
            (string) ($module['display_name'] ?? $moduleKey)
        );

        $permissionCode = trim(
            (string) ($module['permission_key'] ?? '')
        );

        if ($permissionCode === '') {
            $permissionCode = $moduleKey . '.view';
        }

        if (
            strlen($permissionCode) > 150
            || preg_match(
                '/^[a-z][a-z0-9_.-]{2,149}$/',
                $permissionCode
            ) !== 1
        ) {
            throw new \RuntimeException(
                'Invalid module permission code: '
                . $permissionCode
            );
        }

        $resource = $this->resourceFromPermission(
            $permissionCode,
            $moduleKey
        );

        $action = $this->actionFromPermission(
            $permissionCode
        );

        $description = trim(
            (string) (
                $module['dashboard_description']
                ?? ''
            )
        );

        if ($description === '') {
            $description =
                'دسترسی به ماژول '
                . $displayName;
        }

        $sortOrder = max(
            0,
            (int) ($module['sort_order'] ?? 0)
        );

        $statement = Database::connect()->prepare("
            INSERT INTO permissions
            (
                code,
                module,
                resource,
                action,
                title,
                description,
                is_active,
                parent_code,
                display_group,
                display_type,
                sort_order,
                is_sensitive,
                created_at,
                updated_at
            )
            VALUES
            (
                :code,
                :module,
                :resource,
                :action,
                :title,
                :description,
                1,
                NULL,
                :display_group,
                'operation',
                :sort_order,
                0,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                module = VALUES(module),
                resource = VALUES(resource),
                action = VALUES(action),
                title = VALUES(title),
                description = VALUES(description),
                is_active = 1,
                display_group = VALUES(display_group),
                display_type = VALUES(display_type),
                sort_order = VALUES(sort_order),
                updated_at = CURRENT_TIMESTAMP
        ");

        $statement->execute([
            'code' => $permissionCode,
            'module' => $moduleKey,
            'resource' => $resource,
            'action' => $action,
            'title' => 'دسترسی به ' . $displayName,
            'description' => $description,
            'display_group' => $displayName,
            'sort_order' => $sortOrder,
        ]);
    }


    private function resourceFromPermission(
        string $permissionCode,
        string $fallback
    ): string {
        $parts = explode('.', $permissionCode);

        $resource = trim(
            (string) ($parts[count($parts) - 2] ?? $fallback)
        );

        return mb_substr(
            $resource !== '' ? $resource : $fallback,
            0,
            80
        );
    }


    private function actionFromPermission(
        string $permissionCode
    ): string {
        $parts = explode('.', $permissionCode);

        $action = trim(
            (string) end($parts)
        );

        return mb_substr(
            $action !== '' ? $action : 'view',
            0,
            80
        );
    }
}
