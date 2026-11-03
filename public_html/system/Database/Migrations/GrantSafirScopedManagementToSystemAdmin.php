<?php
declare(strict_types=1);

namespace IPKF\Database\Migrations;

use RuntimeException;
use Throwable;

/**
 * Idempotent Safir v2.4.2 role grant.
 *
 * The new permission is deliberately NOT notifications.send.manage:
 * it authorizes templates, previews and reports only. Sending stays
 * protected by its existing separate permission and server-side locks.
 */
final class GrantSafirScopedManagementToSystemAdmin extends Migration
{
    private const PERMISSION = 'notifications.safir.manage';

    public function up(): void
    {
        $this->db->beginTransaction();
        try {
            $role = $this->db->query(
                "SELECT id FROM roles WHERE code = 'system_admin' LIMIT 1 FOR UPDATE"
            )->fetchColumn();
            if ($role === false) {
                throw new RuntimeException('safir_system_admin_role_missing');
            }

            $permission = $this->db->prepare("
                INSERT INTO permissions
                    (code, module, resource, action, title, is_active,
                     created_at, updated_at)
                VALUES (?, 'communications', 'safir', 'manage',
                        'مدیریت قالب‌های سفیر و مشاهده گزارش‌های آن',
                        1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                    is_active = 1,
                    updated_at = CURRENT_TIMESTAMP
            ");
            $permission->execute([self::PERMISSION]);

            $grant = $this->db->prepare("
                INSERT IGNORE INTO role_permissions
                    (role_id, permission_id, created_at)
                SELECT r.id, p.id, CURRENT_TIMESTAMP
                FROM roles r
                INNER JOIN permissions p ON p.code = ?
                WHERE r.code IN ('system_admin', 'super_admin')
                  AND p.is_active = 1
            ");
            $grant->execute([self::PERMISSION]);

            $check = $this->db->prepare("
                SELECT COUNT(*)
                FROM role_permissions rp
                INNER JOIN roles r ON r.id = rp.role_id
                INNER JOIN permissions p ON p.id = rp.permission_id
                WHERE r.code = 'system_admin'
                  AND p.code = ?
                  AND p.is_active = 1
            ");
            $check->execute([self::PERMISSION]);
            if ((int) $check->fetchColumn() !== 1) {
                throw new RuntimeException('safir_system_admin_grant_incomplete');
            }

            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function down(): void
    {
        // Non-destructive: role grants may subsequently be managed in UI.
    }
}
