<?php

namespace App\Repositories;

use IPKF\Database\Database;
use PDO;

class NotificationApprovalAlertRepository extends BaseRepository
{
    public function approverUserIds(
        string $permissionCode
    ): array {
        $permissionCode = trim($permissionCode);

        if ($permissionCode === '') {
            return [];
        }

        $hasOverrides = Database::tableExists(
            'user_permission_overrides'
        );

        $assignmentOverride = $hasOverrides
            ? "(
                SELECT assignment_override.effect_code
                FROM user_permission_overrides
                    AS assignment_override
                INNER JOIN permissions
                    AS assignment_permission
                  ON assignment_permission.id =
                    assignment_override.permission_id
                WHERE assignment_override.user_id =
                    assignments.user_id
                  AND assignment_override.role_assignment_id =
                    assignments.id
                  AND assignment_permission.code = ?
                  AND assignment_permission.is_active = 1
                LIMIT 1
            )"
            : "NULL";

        $globalOverride = $hasOverrides
            ? "(
                SELECT global_override.effect_code
                FROM user_permission_overrides
                    AS global_override
                INNER JOIN permissions
                    AS global_permission
                  ON global_permission.id =
                    global_override.permission_id
                WHERE global_override.user_id =
                    assignments.user_id
                  AND global_override.role_assignment_id = 0
                  AND global_permission.code = ?
                  AND global_permission.is_active = 1
                LIMIT 1
            )"
            : "NULL";

        $statement = $this->connection()->prepare("
            SELECT
                assignments.user_id,
                assignments.id AS assignment_id,
                roles.code AS role_code,
                EXISTS (
                    SELECT 1
                    FROM role_permissions
                    INNER JOIN permissions
                      ON permissions.id =
                        role_permissions.permission_id
                    WHERE role_permissions.role_id = roles.id
                      AND permissions.code = ?
                      AND permissions.is_active = 1
                ) AS role_has_permission,
                {$assignmentOverride}
                    AS assignment_override,
                {$globalOverride}
                    AS global_override
            FROM user_role_assignments AS assignments
            INNER JOIN roles
              ON roles.id = assignments.role_id
            INNER JOIN users
              ON users.id = assignments.user_id
            WHERE assignments.is_active = 1
              AND roles.is_active = 1
              AND users.deleted_at IS NULL
              AND users.status = 'active'
              AND (
                  assignments.starts_at IS NULL
                  OR assignments.starts_at <= CURRENT_TIMESTAMP
              )
              AND (
                  assignments.ends_at IS NULL
                  OR assignments.ends_at >= CURRENT_TIMESTAMP
              )
            ORDER BY
                assignments.user_id ASC,
                assignments.id ASC
        ");

        $params = [$permissionCode];

        if ($hasOverrides) {
            $params[] = $permissionCode;
            $params[] = $permissionCode;
        }

        $statement->execute($params);

        $result = [];

        foreach (
            $statement->fetchAll(PDO::FETCH_ASSOC) ?: []
            as $row
        ) {
            $userId = (int) ($row['user_id'] ?? 0);

            if ($userId < 1) {
                continue;
            }

            if (
                (string) ($row['role_code'] ?? '')
                    === 'super_admin'
            ) {
                $result[$userId] = $userId;
                continue;
            }

            $assignmentOverrideValue =
                $row['assignment_override'] ?? null;

            $globalOverrideValue =
                $row['global_override'] ?? null;

            $effectiveOverride =
                $assignmentOverrideValue !== null
                    ? (string) $assignmentOverrideValue
                    : (
                        $globalOverrideValue !== null
                            ? (string) $globalOverrideValue
                            : null
                    );

            if ($effectiveOverride !== null) {
                if ($effectiveOverride === 'allow') {
                    $result[$userId] = $userId;
                }

                continue;
            }

            if (
                (int) ($row['role_has_permission'] ?? 0)
                    === 1
            ) {
                $result[$userId] = $userId;
            }
        }

        ksort($result, SORT_NUMERIC);

        return array_values($result);
    }

    public function userTitle(int $userId): string
    {
        if ($userId < 1) {
            return 'کاربر';
        }

        $hasPersons = Database::tableExists('persons');

        $title = $hasPersons
            ? "COALESCE(
                NULLIF(persons.full_name, ''),
                NULLIF(users.username, ''),
                CONCAT('کاربر ', users.id)
            )"
            : "COALESCE(
                NULLIF(users.username, ''),
                CONCAT('کاربر ', users.id)
            )";

        $join = $hasPersons
            ? "LEFT JOIN persons
                 ON persons.id = users.person_id"
            : "";

        $statement = $this->connection()->prepare("
            SELECT {$title}
            FROM users
            {$join}
            WHERE users.id = ?
              AND users.deleted_at IS NULL
            LIMIT 1
        ");

        $statement->execute([$userId]);

        $value = trim(
            (string) ($statement->fetchColumn() ?: '')
        );

        return $value !== ''
            ? $value
            : 'کاربر ' . $userId;
    }

    public function completePendingOutboxForRequest(
        string $publicReference
    ): int {
        $publicReference = trim(
            $publicReference
        );

        if ($publicReference === '') {
            return 0;
        }

        $statement = $this->connection()->prepare("
            UPDATE notification_outbox AS outbox
            INNER JOIN notification_events AS events
              ON events.id = outbox.event_id
            SET outbox.status_code = 'completed',
                outbox.processed_at = COALESCE(
                    outbox.processed_at,
                    CURRENT_TIMESTAMP
                ),
                outbox.locked_at = NULL,
                outbox.locked_by = NULL,
                outbox.last_error = NULL,
                outbox.updated_at =
                    CURRENT_TIMESTAMP
            WHERE events.event_type =
                    'notifications.approval.pending'
              AND events.source_module =
                    'communications'
              AND events.source_entity_type =
                    'notification_approval_request'
              AND events.source_entity_reference = ?
              AND outbox.status_code IN (
                    'pending',
                    'failed'
              )
        ");

        $statement->execute([
            $publicReference,
        ]);

        return $statement->rowCount();
    }

    public function markActionReadForAll(
        string $actionUrl
    ): int {
        $actionUrl = trim($actionUrl);

        if ($actionUrl === '') {
            return 0;
        }

        $statement = $this->connection()->prepare("
            UPDATE notification_recipients AS recipients
            INNER JOIN notifications
              ON notifications.id =
                recipients.notification_id
            SET recipients.seen_at = COALESCE(
                    recipients.seen_at,
                    CURRENT_TIMESTAMP
                ),
                recipients.read_at = COALESCE(
                    recipients.read_at,
                    CURRENT_TIMESTAMP
                ),
                recipients.updated_at =
                    CURRENT_TIMESTAMP
            WHERE notifications.action_url = ?
              AND recipients.read_at IS NULL
              AND recipients.archived_at IS NULL
        ");

        $statement->execute([$actionUrl]);

        return $statement->rowCount();
    }
}
