<?php

namespace App\Repositories;

use PDO;
use RuntimeException;
use Throwable;

class NotificationProviderManagementRepository extends BaseRepository
{
    public function providerTypes(): array
    {
        $statement = $this->connection()->query("
            SELECT *
            FROM notification_provider_types
            WHERE is_active = 1
            ORDER BY channel_code ASC, sort_order ASC, id ASC
        ");

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function providerType(int $id): ?array
    {
        $statement = $this->connection()->prepare("
            SELECT *
            FROM notification_provider_types
            WHERE id = ?
              AND is_active = 1
            LIMIT 1
        ");
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function instanceByReference(string $reference): ?array
    {
        $statement = $this->connection()->prepare("
            SELECT
                instances.*,
                types.code AS provider_type_code,
                types.title AS provider_type_title,
                types.channel_code,
                types.driver_code,
                types.config_schema_json,
                types.supports_balance,
                CASE
                    WHEN secrets.id IS NULL THEN 0
                    ELSE 1
                END AS has_secret
            FROM notification_provider_instances AS instances
            INNER JOIN notification_provider_types AS types
              ON types.id = instances.provider_type_id
            LEFT JOIN notification_provider_secret_sets AS secrets
              ON secrets.provider_instance_id = instances.id
            WHERE instances.public_reference = ?
            LIMIT 1
        ");
        $statement->execute([$reference]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function secretSet(int $providerInstanceId): ?array
    {
        $statement = $this->connection()->prepare("
            SELECT *
            FROM notification_provider_secret_sets
            WHERE provider_instance_id = ?
            LIMIT 1
        ");
        $statement->execute([$providerInstanceId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function codeExists(
        string $code,
        ?string $exceptReference = null
    ): bool {
        $sql = "
            SELECT COUNT(*)
            FROM notification_provider_instances
            WHERE code = ?
        ";
        $parameters = [$code];

        if ($exceptReference !== null && $exceptReference !== '') {
            $sql .= " AND public_reference <> ?";
            $parameters[] = $exceptReference;
        }

        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn() > 0;
    }

    public function save(
        array $instance,
        ?array $encryptedSecrets,
        int $actorUserId,
        array $auditSummary
    ): string {
        $db = $this->connection();
        $db->beginTransaction();

        try {
            $reference = trim(
                (string) ($instance['public_reference'] ?? '')
            );
            $existing = null;

            if ($reference !== '') {
                $statement = $db->prepare("
                    SELECT *
                    FROM notification_provider_instances
                    WHERE public_reference = ?
                    LIMIT 1
                    FOR UPDATE
                ");
                $statement->execute([$reference]);
                $existing = $statement->fetch(PDO::FETCH_ASSOC);

                if (!$existing) {
                    throw new RuntimeException(
                        'provider_instance_not_found'
                    );
                }
            }

            if ($existing === null) {
                $insert = $db->prepare("
                    INSERT INTO notification_provider_instances (
                        public_reference,
                        provider_type_id,
                        code,
                        title,
                        description,
                        instance_kind,
                        status_code,
                        is_enabled,
                        priority,
                        configuration_json,
                        configuration_version,
                        secret_reference,
                        daily_limit,
                        monthly_limit,
                        health_status_code,
                        created_by_user_id,
                        updated_by_user_id,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NULL,
                        ?, ?, 'unknown', ?, ?,
                        CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                    )
                ");
                $insert->execute([
                    $instance['public_reference'],
                    $instance['provider_type_id'],
                    $instance['code'],
                    $instance['title'],
                    $instance['description'],
                    $instance['instance_kind'],
                    $instance['status_code'],
                    $instance['is_enabled'],
                    $instance['priority'],
                    $instance['configuration_json'],
                    $instance['daily_limit'],
                    $instance['monthly_limit'],
                    $actorUserId,
                    $actorUserId,
                ]);

                $providerInstanceId =
                    (int) $db->lastInsertId();
                $reference =
                    (string) $instance['public_reference'];
                $action = 'provider.created';
            } else {
                $providerInstanceId = (int) $existing['id'];

                $update = $db->prepare("
                    UPDATE notification_provider_instances
                    SET provider_type_id = ?,
                        code = ?,
                        title = ?,
                        description = ?,
                        instance_kind = ?,
                        status_code = ?,
                        is_enabled = ?,
                        priority = ?,
                        configuration_json = ?,
                        configuration_version =
                            configuration_version + 1,
                        daily_limit = ?,
                        monthly_limit = ?,
                        updated_by_user_id = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $update->execute([
                    $instance['provider_type_id'],
                    $instance['code'],
                    $instance['title'],
                    $instance['description'],
                    $instance['instance_kind'],
                    $instance['status_code'],
                    $instance['is_enabled'],
                    $instance['priority'],
                    $instance['configuration_json'],
                    $instance['daily_limit'],
                    $instance['monthly_limit'],
                    $actorUserId,
                    $providerInstanceId,
                ]);

                $action = 'provider.updated';
            }

            if ($encryptedSecrets !== null) {
                $secret = $db->prepare("
                    INSERT INTO notification_provider_secret_sets (
                        provider_instance_id,
                        cipher_code,
                        key_version,
                        encrypted_payload,
                        payload_checksum,
                        rotated_at,
                        created_by_user_id,
                        updated_by_user_id,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        ?, ?, ?, ?, ?, CURRENT_TIMESTAMP,
                        ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                    )
                    ON DUPLICATE KEY UPDATE
                        cipher_code = VALUES(cipher_code),
                        key_version = VALUES(key_version),
                        encrypted_payload =
                            VALUES(encrypted_payload),
                        payload_checksum =
                            VALUES(payload_checksum),
                        rotated_at = CURRENT_TIMESTAMP,
                        updated_by_user_id =
                            VALUES(updated_by_user_id),
                        updated_at = CURRENT_TIMESTAMP
                ");
                $secret->execute([
                    $providerInstanceId,
                    $encryptedSecrets['cipher_code'],
                    $encryptedSecrets['key_version'],
                    $encryptedSecrets['encrypted_payload'],
                    $encryptedSecrets['payload_checksum'],
                    $actorUserId,
                    $actorUserId,
                ]);

                $secretReference =
                    'database:notification_provider_secret_sets:'
                    . $reference;

                $updateSecretReference = $db->prepare("
                    UPDATE notification_provider_instances
                    SET secret_reference = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $updateSecretReference->execute([
                    $secretReference,
                    $providerInstanceId,
                ]);
            }

            $this->insertAudit(
                $db,
                $providerInstanceId,
                $actorUserId,
                $action,
                $auditSummary
            );

            $db->commit();

            return $reference;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function setEnabled(
        string $reference,
        bool $enabled,
        int $actorUserId
    ): void {
        $db = $this->connection();
        $db->beginTransaction();

        try {
            $statement = $db->prepare("
                SELECT id, is_enabled
                FROM notification_provider_instances
                WHERE public_reference = ?
                LIMIT 1
                FOR UPDATE
            ");
            $statement->execute([$reference]);
            $instance = $statement->fetch(PDO::FETCH_ASSOC);

            if (!$instance) {
                throw new RuntimeException(
                    'provider_instance_not_found'
                );
            }

            $update = $db->prepare("
                UPDATE notification_provider_instances
                SET is_enabled = ?,
                    status_code = ?,
                    updated_by_user_id = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $update->execute([
                $enabled ? 1 : 0,
                $enabled ? 'active' : 'inactive',
                $actorUserId,
                (int) $instance['id'],
            ]);

            $this->insertAudit(
                $db,
                (int) $instance['id'],
                $actorUserId,
                $enabled
                    ? 'provider.enabled'
                    : 'provider.disabled',
                [
                    'before_enabled' =>
                        !empty($instance['is_enabled']),
                    'after_enabled' => $enabled,
                ]
            );

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    private function insertAudit(
        PDO $db,
        int $providerInstanceId,
        int $actorUserId,
        string $action,
        array $summary
    ): void {
        $statement = $db->prepare("
            INSERT INTO notification_provider_audit_events (
                provider_instance_id,
                actor_user_id,
                action_code,
                change_summary_json,
                request_reference,
                occurred_at,
                created_at
            )
            VALUES (
                ?, ?, ?, ?, ?, CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ");
        $statement->execute([
            $providerInstanceId,
            $actorUserId,
            $action,
            json_encode(
                $summary,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
            'npa_' . bin2hex(random_bytes(12)),
        ]);
    }
}
