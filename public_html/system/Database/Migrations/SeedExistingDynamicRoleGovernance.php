<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;
use Throwable;

/**
 * DYNAMIC_ROLE_GOVERNANCE_V2_2
 *
 * Adds explicit policies to legacy roles without replacing their current
 * permissions or assignments. Re-running up() is intentionally safe.
 */
class SeedExistingDynamicRoleGovernance extends Migration
{
    public function up(): void
    {
        $this->db->beginTransaction();

        try {
            $scopeMap = [
                'super_admin' => [
                    ['global', 1, 1],
                ],
                'system_admin' => [
                    ['global', 1, 1],
                ],
                'central_admin' => [
                    ['national', 1, 1],
                ],
                'province_admin' => [
                    ['province', 1, 1],
                ],
                'county_admin' => [
                    ['county', 1, 1],
                ],
                'company_admin' => [
                    ['company', 1, 1],
                ],
                'warehouse_manager' => [
                    ['company', 0, 0],
                    ['warehouse', 1, 1],
                ],
                'ticketing_staff' => [
                    ['project', 1, 1],
                    ['assigned', 0, 0],
                ],
                'operator' => [
                    ['center', 1, 1],
                    ['org_unit', 0, 0],
                    ['assigned', 0, 0],
                ],
                'user' => [
                    ['own', 1, 1],
                ],
            ];

            $identityMap = [
                'super_admin' => [
                    'full_name', 'national_code', 'mobile', 'email',
                ],
                'system_admin' => [
                    'full_name', 'national_code', 'mobile', 'email',
                ],
                'central_admin' => [
                    'full_name', 'national_code', 'mobile',
                    'organization', 'position',
                ],
                'province_admin' => [
                    'full_name', 'national_code', 'mobile', 'province',
                    'organization', 'position',
                ],
                'county_admin' => [
                    'full_name', 'national_code', 'mobile', 'province',
                    'county', 'organization', 'position',
                ],
                'company_admin' => [
                    'full_name', 'national_code', 'mobile', 'province',
                    'county', 'organization', 'position',
                ],
                'warehouse_manager' => [
                    'full_name', 'national_code', 'mobile',
                    'organization', 'position',
                ],
                'ticketing_staff' => [
                    'full_name', 'national_code', 'mobile',
                    'organization', 'position',
                ],
                'operator' => [
                    'full_name', 'national_code', 'mobile',
                    'organization', 'position',
                ],
                'user' => [
                    'full_name', 'mobile',
                ],
            ];

            $roleQuery = $this->db->prepare(
                'SELECT id FROM roles WHERE code = ? LIMIT 1'
            );
            $policyCount = $this->db->prepare(
                'SELECT COUNT(*) FROM role_scope_policies WHERE role_id = ?'
            );
            $policyInsert = $this->db->prepare("
                INSERT INTO role_scope_policies (
                    role_id,
                    scope_type_code,
                    is_required,
                    is_default,
                    created_at,
                    updated_at
                )
                SELECT ?, code, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                FROM access_scope_types
                WHERE code = ?
                  AND is_active = 1
            ");
            $identityCount = $this->db->prepare(
                'SELECT COUNT(*) FROM role_identity_requirements WHERE role_id = ?'
            );
            $identityInsert = $this->db->prepare("
                INSERT INTO role_identity_requirements (
                    role_id,
                    field_code,
                    verification_mode_code,
                    is_required,
                    sort_order,
                    created_at,
                    updated_at
                )
                VALUES (?, ?, ?, 1, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");

            foreach ($scopeMap as $roleCode => $policies) {
                $roleQuery->execute([$roleCode]);
                $roleId = (int) $roleQuery->fetchColumn();

                if ($roleId < 1) {
                    continue;
                }

                $policyCount->execute([$roleId]);

                if ((int) $policyCount->fetchColumn() === 0) {
                    foreach ($policies as [$scope, $required, $default]) {
                        $policyInsert->execute([
                            $roleId,
                            $required,
                            $default,
                            $scope,
                        ]);
                    }
                }

                $identityCount->execute([$roleId]);

                if ((int) $identityCount->fetchColumn() === 0) {
                    $sortOrder = 10;

                    foreach ($identityMap[$roleCode] ?? [] as $field) {
                        $identityInsert->execute([
                            $roleId,
                            $field,
                            in_array($field, ['mobile', 'email'], true)
                                ? 'verified'
                                : 'present',
                            $sortOrder,
                        ]);
                        $sortOrder += 10;
                    }
                }
            }

            $this->db->exec("
                UPDATE roles
                SET role_kind_id = (
                        SELECT id
                        FROM role_kinds
                        WHERE code = 'support'
                        LIMIT 1
                    ),
                    updated_at = CURRENT_TIMESTAMP
                WHERE code = 'ticketing_staff'
                  AND EXISTS (
                      SELECT 1
                      FROM role_kinds
                      WHERE code = 'support'
                  )
            ");

            $this->db->exec("
                UPDATE roles
                SET is_deletable = 0,
                    updated_at = CURRENT_TIMESTAMP
                WHERE code IN ('super_admin', 'system_admin', 'user')
            ");

            $this->db->exec("
                INSERT IGNORE INTO role_permissions (
                    role_id,
                    permission_id,
                    created_at
                )
                SELECT roles.id, permissions.id, CURRENT_TIMESTAMP
                FROM roles
                INNER JOIN permissions
                    ON permissions.code = 'access.roles.manage'
                   AND permissions.is_active = 1
                WHERE roles.code = 'system_admin'
                  AND roles.is_active = 1
            ");

            $scopeInsert = $this->db->prepare("
                INSERT INTO role_assignment_scopes (
                    role_assignment_id,
                    scope_type_code,
                    scope_reference,
                    effect_code,
                    include_descendants,
                    metadata_json,
                    created_by_user_id,
                    updated_by_user_id,
                    created_at,
                    updated_at
                )
                SELECT assignments.id, ?, '*', 'allow', ?, NULL,
                       COALESCE(assignments.assigned_by, assignments.user_id),
                       COALESCE(assignments.assigned_by, assignments.user_id),
                       CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                FROM user_role_assignments AS assignments
                INNER JOIN roles ON roles.id = assignments.role_id
                WHERE roles.code = ?
                  AND assignments.is_active = 1
                  AND NOT EXISTS (
                      SELECT 1
                      FROM role_assignment_scopes AS existing
                      WHERE existing.role_assignment_id = assignments.id
                  )
            ");

            foreach ([
                ['global', 1, 'super_admin'],
                ['global', 1, 'system_admin'],
                ['own', 0, 'user'],
            ] as [$scope, $descendants, $roleCode]) {
                $scopeInsert->execute([
                    $scope,
                    $descendants,
                    $roleCode,
                ]);
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
        $this->db->beginTransaction();

        try {
            $roleCodes = [
                'super_admin', 'system_admin', 'central_admin',
                'province_admin', 'county_admin', 'company_admin',
                'warehouse_manager', 'ticketing_staff', 'operator', 'user',
            ];
            $placeholders = implode(',', array_fill(0, count($roleCodes), '?'));

            $delete = $this->db->prepare("
                DELETE assignment_scopes
                FROM role_assignment_scopes AS assignment_scopes
                INNER JOIN user_role_assignments AS assignments
                    ON assignments.id = assignment_scopes.role_assignment_id
                INNER JOIN roles ON roles.id = assignments.role_id
                WHERE roles.code IN ('super_admin', 'system_admin', 'user')
                  AND assignment_scopes.scope_reference = '*'
                  AND assignment_scopes.effect_code = 'allow'
                  AND assignment_scopes.scope_type_code IN ('global', 'own')
            ");
            $delete->execute();

            $delete = $this->db->prepare("
                DELETE requirements
                FROM role_identity_requirements AS requirements
                INNER JOIN roles ON roles.id = requirements.role_id
                WHERE roles.code IN ($placeholders)
            ");
            $delete->execute($roleCodes);

            $delete = $this->db->prepare("
                DELETE policies
                FROM role_scope_policies AS policies
                INNER JOIN roles ON roles.id = policies.role_id
                WHERE roles.code IN ($placeholders)
            ");
            $delete->execute($roleCodes);

            $delete = $this->db->prepare("
                DELETE role_permissions
                FROM role_permissions
                INNER JOIN roles ON roles.id = role_permissions.role_id
                INNER JOIN permissions
                    ON permissions.id = role_permissions.permission_id
                WHERE roles.code = 'system_admin'
                  AND permissions.code = 'access.roles.manage'
            ");
            $delete->execute();

            $this->db->exec("
                UPDATE roles
                SET role_kind_id = (
                        SELECT id
                        FROM role_kinds
                        WHERE code = 'customer'
                        LIMIT 1
                    ),
                    updated_at = CURRENT_TIMESTAMP
                WHERE code = 'ticketing_staff'
            ");
            $this->db->exec("
                UPDATE roles
                SET is_deletable = 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE code = 'user'
            ");

            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }
}
