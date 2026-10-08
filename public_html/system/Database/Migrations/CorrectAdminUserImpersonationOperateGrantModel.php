<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;
use RuntimeException;
use Throwable;

final class CorrectAdminUserImpersonationOperateGrantModel
    extends Migration
{
    private const BASE_PERMISSION =
        'users.impersonate';

    private const OPERATE_PERMISSION =
        'users.impersonate.operate';

    private const ASSIGN_PERMISSION =
        'users.impersonate.operate.assign';


    public function up(): void
    {
        $this->db->beginTransaction();

        try {
            $operateId =
                $this->permissionId(
                    self::OPERATE_PERMISSION,
                    true
                );

            $grantAuthorityRoleIds =
                $this->grantAuthorityRoleIds();

            if (
                count(
                    $grantAuthorityRoleIds
                ) !== 2
            ) {
                throw new RuntimeException(
                    'impersonation_operate_grant_authority_must_be_exactly_two_roles'
                );
            }

            $this->seedAssignPermission();

            $assignId =
                $this->permissionId(
                    self::ASSIGN_PERMISSION,
                    true
                );

            /*
             * Operate is NEVER inherited from a Role.
             */
            $deleteOperateRoleGrants =
                $this->db->prepare(
                    'DELETE FROM role_permissions
                     WHERE permission_id = ?'
                );

            $deleteOperateRoleGrants
                ->execute([
                    $operateId,
                ]);

            /*
             * Grant/Revoke authority belongs only to the
             * two structurally protected system-manager
             * roles discovered above.
             */
            $grant =
                $this->db->prepare(
                    'INSERT IGNORE INTO role_permissions
                     (
                        role_id,
                        permission_id,
                        created_at
                     )
                     VALUES
                     (
                        ?,
                        ?,
                        CURRENT_TIMESTAMP
                     )'
                );

            foreach (
                $grantAuthorityRoleIds
                as $roleId
            ) {
                $grant->execute([
                    $roleId,
                    $assignId,
                ]);
            }

            /*
             * Hard postconditions.
             */
            if (
                $this->roleGrantCount(
                    $operateId
                ) !== 0
            ) {
                throw new RuntimeException(
                    'impersonation_operate_role_inheritance_not_removed'
                );
            }

            if (
                $this->roleGrantCount(
                    $assignId
                ) !== 2
            ) {
                throw new RuntimeException(
                    'impersonation_operate_assign_grant_count_invalid'
                );
            }

            if (
                $this->personOverrideCount(
                    $operateId
                ) !== 0
            ) {
                throw new RuntimeException(
                    'impersonation_operate_unexpected_person_override'
                );
            }

            $this->db->commit();

        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }


    public function down(): void
    {
        /*
         * Non-destructive migration history.
         * Controlled rollback is handled by the deployment
         * procedure with the captured preimage.
         */
    }


    private function seedAssignPermission(): void
    {
        $statement =
            $this->db->prepare(
                "INSERT INTO permissions
                (
                    code,
                    module,
                    resource,
                    action,
                    title,
                    description,
                    display_group,
                    display_type,
                    sort_order,
                    is_sensitive,
                    is_active,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    'core',
                    'users',
                    'impersonate_operate_assign',
                    'اعطا یا لغو دسترسی عملیاتی ورود مدیریتی',
                    'اعطا یا لغو مجوز ورود عملیاتی مدیریتی به صورت فردی.',
                    'کاربران',
                    'operation',
                    912,
                    1,
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
                    description =
                        VALUES(description),
                    display_group =
                        VALUES(display_group),
                    display_type =
                        VALUES(display_type),
                    sort_order =
                        VALUES(sort_order),
                    is_sensitive = 1,
                    is_active = 1,
                    updated_at =
                        CURRENT_TIMESTAMP"
            );

        $statement->execute([
            self::ASSIGN_PERMISSION,
        ]);
    }


    private function grantAuthorityRoleIds(): array
    {
        $statement =
            $this->db->prepare(
                "SELECT DISTINCT
                    roles.id

                 FROM roles

                 INNER JOIN role_permissions
                    AS base_grant
                   ON base_grant.role_id =
                        roles.id

                 INNER JOIN permissions
                    AS base_permission
                   ON base_permission.id =
                        base_grant.permission_id

                 WHERE roles.is_active = 1
                   AND roles.is_system = 1
                   AND roles.can_manage_other_users = 1
                   AND base_permission.code = ?
                   AND base_permission.is_active = 1

                 ORDER BY roles.priority DESC,
                          roles.id"
            );

        $statement->execute([
            self::BASE_PERMISSION,
        ]);

        return
            array_values(
                array_map(
                    'intval',
                    $statement->fetchAll(
                        PDO::FETCH_COLUMN
                    ) ?: []
                )
            );
    }


    private function permissionId(
        string $code,
        bool $mustBeSensitive
    ): int {
        $statement =
            $this->db->prepare(
                'SELECT
                    id,
                    is_sensitive,
                    is_active
                 FROM permissions
                 WHERE code = ?
                 LIMIT 1'
            );

        $statement->execute([
            $code,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        if (
            !is_array($row)
            || (int) (
                $row['id']
                ?? 0
            ) < 1
            || (int) (
                $row['is_active']
                ?? 0
            ) !== 1
            || (
                $mustBeSensitive
                && (int) (
                    $row['is_sensitive']
                    ?? 0
                ) !== 1
            )
        ) {
            throw new RuntimeException(
                'permission_contract_invalid:'
                . $code
            );
        }

        return
            (int) $row['id'];
    }


    private function roleGrantCount(
        int $permissionId
    ): int {
        $statement =
            $this->db->prepare(
                'SELECT COUNT(*)
                 FROM role_permissions
                 WHERE permission_id = ?'
            );

        $statement->execute([
            $permissionId,
        ]);

        return
            (int) $statement
                ->fetchColumn();
    }


    private function personOverrideCount(
        int $permissionId
    ): int {
        $statement =
            $this->db->prepare(
                'SELECT COUNT(*)
                 FROM user_permission_overrides
                 WHERE permission_id = ?'
            );

        $statement->execute([
            $permissionId,
        ]);

        return
            (int) $statement
                ->fetchColumn();
    }
}
