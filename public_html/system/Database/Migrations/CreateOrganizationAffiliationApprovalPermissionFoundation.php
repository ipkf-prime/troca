<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

/**
 * Generic organizational-affiliation reviewer authorization.
 *
 * A dedicated permission avoids requiring reviewers to receive the broader
 * organizations.manage CRUD authority. Existing organizations.manage actors
 * remain compatible in OrganizationalAffiliationService.
 *
 * Administrative roles receive the permission; their existing assignment
 * scopes remain authoritative and are evaluated by ScopedAuthorizationService.
 */
final class CreateOrganizationAffiliationApprovalPermissionFoundation
    extends Migration
{
    private const PERMISSION =
        'organizations.affiliations.manage';


    public function up(): void
    {
        $this->db->beginTransaction();

        try {
            $this->seedPermission();
            $this->seedRoleGrants();
            $this->alignRoutes();

            $this->db->commit();

        } catch (\Throwable $exception) {

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }


    public function down(): void
    {
        /*
         * Non-destructive by design.
         *
         * Reviewer authority may already have been used in audit-relevant
         * workflows. Rollback is handled through the normal access-governance
         * UI rather than silently deleting permission grants.
         */
    }


    private function seedPermission(): void
    {
        $statement =
            $this->db->prepare("
                INSERT INTO permissions
                (
                    code,
                    module,
                    resource,
                    action,
                    title,
                    is_active,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    'core',
                    'organization_affiliations',
                    'manage',
                    'مدیریت تأیید وابستگی‌های سازمانی',
                    1,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
                ON DUPLICATE KEY UPDATE
                    module =
                        VALUES(module),
                    resource =
                        VALUES(resource),
                    action =
                        VALUES(action),
                    title =
                        VALUES(title),
                    is_active = 1,
                    updated_at =
                        CURRENT_TIMESTAMP
            ");

        $statement->execute([
            self::PERMISSION,
        ]);
    }


    private function seedRoleGrants(): void
    {
        /*
         * Preserve semantic inheritance:
         * any role already carrying organizations.manage receives the
         * narrower reviewer permission.
         */
        $this->db->exec("
            INSERT IGNORE INTO role_permissions
            (
                role_id,
                permission_id,
                created_at
            )
            SELECT DISTINCT
                existing.role_id,
                reviewer.id,
                CURRENT_TIMESTAMP
            FROM role_permissions AS existing
            INNER JOIN permissions AS broad
              ON broad.id =
                    existing.permission_id
             AND broad.code =
                    'organizations.manage'
             AND broad.is_active = 1
            CROSS JOIN permissions AS reviewer
            WHERE reviewer.code =
                    'organizations.affiliations.manage'
              AND reviewer.is_active = 1
        ");


        /*
         * Platform access administrators are also bootstrap reviewers.
         * This is permission inheritance at ROLE level; active assignment
         * scope and reviewer-independence checks still apply.
         */
        $this->db->exec("
            INSERT IGNORE INTO role_permissions
            (
                role_id,
                permission_id,
                created_at
            )
            SELECT DISTINCT
                existing.role_id,
                reviewer.id,
                CURRENT_TIMESTAMP
            FROM role_permissions AS existing
            INNER JOIN permissions AS broad
              ON broad.id =
                    existing.permission_id
             AND broad.code =
                    'access.manage'
             AND broad.is_active = 1
            CROSS JOIN permissions AS reviewer
            WHERE reviewer.code =
                    'organizations.affiliations.manage'
              AND reviewer.is_active = 1
        ");


        /*
         * Administrative organization roles are eligible reviewers.
         * Existing assignment scopes remain authoritative.
         *
         * system_admin is included for bootstrap installations that have not
         * yet assigned customer/province/company administrators.
         */
        $roleCodes = [
            'super_admin',
            'system_admin',
            'central_admin',
            'province_admin',
            'county_admin',
            'company_admin',
        ];

        $marks =
            implode(
                ',',
                array_fill(
                    0,
                    count($roleCodes),
                    '?'
                )
            );

        $statement =
            $this->db->prepare("
                INSERT IGNORE INTO role_permissions
                (
                    role_id,
                    permission_id,
                    created_at
                )
                SELECT
                    roles.id,
                    permissions.id,
                    CURRENT_TIMESTAMP
                FROM roles
                CROSS JOIN permissions
                WHERE roles.code
                        IN ({$marks})
                  AND roles.is_active = 1
                  AND permissions.code = ?
                  AND permissions.is_active = 1
            ");

        $statement->execute([
            ...$roleCodes,
            self::PERMISSION,
        ]);
    }


    private function alignRoutes(): void
    {
        if (
            !$this->tableExists(
                'admin_route_permissions'
            )
        ) {
            return;
        }

        $permissions =
            json_encode(
                [
                    self::PERMISSION,
                    'organizations.manage',
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );

        foreach (
            [
                [
                    '/admin/organization-affiliations',
                    'GET',
                ],
                [
                    '/admin/organization-affiliations/decision',
                    'POST',
                ],
            ]
            as [
                $path,
                $method,
            ]
        ) {

            $update =
                $this->db->prepare("
                    UPDATE admin_route_permissions
                    SET
                        permission_mode = 'any',
                        permission_codes_json = ?,
                        is_active = 1,
                        updated_at =
                            CURRENT_TIMESTAMP
                    WHERE route_pattern = ?
                      AND http_method = ?
                ");

            $update->execute([
                $permissions,
                $path,
                $method,
            ]);


            if ($update->rowCount() > 0) {
                continue;
            }


            $insert =
                $this->db->prepare("
                    INSERT INTO admin_route_permissions
                    (
                        route_pattern,
                        http_method,
                        permission_mode,
                        permission_codes_json,
                        priority,
                        is_active,
                        created_at,
                        updated_at
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        'any',
                        ?,
                        180,
                        1,
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    )
                ");

            $insert->execute([
                $path,
                $method,
                $permissions,
            ]);
        }
    }


    private function tableExists(
        string $table
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA =
                        DATABASE()
                  AND TABLE_NAME = ?
            ");

        $statement->execute([
            $table,
        ]);

        return
            (int) $statement
                ->fetchColumn()
            > 0;
    }
}
