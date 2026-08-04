<?php

namespace App\Repositories;

use PDO;
use RuntimeException;
use Throwable;

class NotificationProviderDefaultRepository extends BaseRepository
{
    public function enabledInstances(): array
    {
        $statement = $this->connection()->query("
            SELECT
                instances.id,
                instances.public_reference,
                instances.title,
                instances.priority AS instance_priority,
                instances.health_status_code,
                instances.last_test_status_code,
                types.code AS provider_type_code,
                types.title AS provider_type_title,
                types.channel_code,
                types.driver_code,
                CASE WHEN secrets.id IS NULL THEN 0 ELSE 1 END
                    AS has_secret
            FROM notification_provider_instances AS instances
            INNER JOIN notification_provider_types AS types
              ON types.id = instances.provider_type_id
            LEFT JOIN notification_provider_secret_sets AS secrets
              ON secrets.provider_instance_id = instances.id
            WHERE instances.is_enabled = 1
              AND instances.status_code = 'active'
              AND types.is_active = 1
            ORDER BY
                types.channel_code ASC,
                instances.priority DESC,
                instances.id ASC
        ");

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function configuredDefaults(): array
    {
        $statement = $this->connection()->query("
            SELECT
                defaults.*,
                instances.public_reference,
                instances.title AS provider_title,
                instances.priority AS instance_priority,
                types.code AS provider_type_code,
                types.title AS provider_type_title,
                types.driver_code
            FROM notification_provider_defaults AS defaults
            INNER JOIN notification_provider_instances AS instances
              ON instances.id = defaults.provider_instance_id
            INNER JOIN notification_provider_types AS types
              ON types.id = instances.provider_type_id
            WHERE defaults.scope_type = 'global'
              AND defaults.scope_reference = '*'
              AND defaults.purpose_code = 'general'
              AND defaults.is_active = 1
            ORDER BY
                defaults.channel_code ASC,
                defaults.is_default DESC,
                defaults.fallback_order ASC,
                defaults.priority ASC,
                defaults.id ASC
        ");

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function candidates(string $channelCode): array
    {
        $statement = $this->connection()->prepare("
            SELECT
                defaults.id AS default_id,
                defaults.scope_type,
                defaults.scope_reference,
                defaults.channel_code,
                defaults.purpose_code,
                defaults.priority AS default_priority,
                defaults.is_default,
                defaults.fallback_order,
                instances.id AS provider_instance_id,
                instances.public_reference,
                instances.title,
                instances.priority AS instance_priority,
                instances.health_status_code,
                instances.last_test_status_code,
                types.code AS provider_type_code,
                types.title AS provider_type_title,
                types.driver_code
            FROM notification_provider_defaults AS defaults
            INNER JOIN notification_provider_instances AS instances
              ON instances.id = defaults.provider_instance_id
            INNER JOIN notification_provider_types AS types
              ON types.id = instances.provider_type_id
            WHERE defaults.channel_code = ?
              AND defaults.is_active = 1
              AND instances.is_enabled = 1
              AND instances.status_code = 'active'
              AND types.is_active = 1
            ORDER BY
                defaults.is_default DESC,
                defaults.fallback_order ASC,
                defaults.priority ASC,
                instances.priority DESC,
                defaults.id ASC
        ");
        $statement->execute([$channelCode]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveGlobalDefaults(
        array $selections,
        int $actorUserId
    ): void {
        $db = $this->connection();
        $db->beginTransaction();

        try {
            $summary = [];

            foreach (
                ['email', 'sms', 'messenger']
                as $channelCode
            ) {
                $delete = $db->prepare("
                    DELETE FROM notification_provider_defaults
                    WHERE scope_type = 'global'
                      AND scope_reference = '*'
                      AND channel_code = ?
                      AND purpose_code = 'general'
                ");
                $delete->execute([$channelCode]);

                $selection = is_array(
                    $selections[$channelCode] ?? null
                ) ? $selections[$channelCode] : [];

                $primary = trim(
                    (string) (
                        $selection['primary_reference'] ?? ''
                    )
                );
                $fallback = trim(
                    (string) (
                        $selection['fallback_reference'] ?? ''
                    )
                );

                $summary[$channelCode] = [
                    'primary_reference' => null,
                    'fallback_reference' => null,
                ];

                if ($primary === '') {
                    continue;
                }

                $primaryInstance = $this->lockInstance(
                    $db,
                    $primary,
                    $channelCode
                );

                $this->insertDefault(
                    $db,
                    $channelCode,
                    (int) $primaryInstance['id'],
                    10,
                    true,
                    0
                );

                $summary[$channelCode][
                    'primary_reference'
                ] = $primary;

                if ($fallback === '') {
                    continue;
                }

                if ($fallback === $primary) {
                    throw new RuntimeException(
                        'provider_defaults_duplicate'
                    );
                }

                $fallbackInstance = $this->lockInstance(
                    $db,
                    $fallback,
                    $channelCode
                );

                $this->insertDefault(
                    $db,
                    $channelCode,
                    (int) $fallbackInstance['id'],
                    20,
                    false,
                    1
                );

                $summary[$channelCode][
                    'fallback_reference'
                ] = $fallback;
            }

            $audit = $db->prepare("
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
                    NULL,
                    ?,
                    'provider.defaults.updated',
                    ?,
                    NULL,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");
            $audit->execute([
                $actorUserId,
                json_encode(
                    [
                        'scope_type' => 'global',
                        'scope_reference' => '*',
                        'purpose_code' => 'general',
                        'channels' => $summary,
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                ),
            ]);

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    private function lockInstance(
        PDO $db,
        string $reference,
        string $channelCode
    ): array {
        if (
            preg_match(
                '/^npi_[a-f0-9]{24}$/',
                $reference
            ) !== 1
        ) {
            throw new RuntimeException(
                'provider_defaults_instance_invalid'
            );
        }

        $statement = $db->prepare("
            SELECT
                instances.id,
                instances.is_enabled,
                instances.status_code,
                types.channel_code
            FROM notification_provider_instances AS instances
            INNER JOIN notification_provider_types AS types
              ON types.id = instances.provider_type_id
            WHERE instances.public_reference = ?
            LIMIT 1
            FOR UPDATE
        ");
        $statement->execute([$reference]);
        $instance = $statement->fetch(PDO::FETCH_ASSOC);

        if (
            !is_array($instance)
            || empty($instance['is_enabled'])
            || (string) $instance['status_code'] !== 'active'
        ) {
            throw new RuntimeException(
                'provider_defaults_instance_invalid'
            );
        }

        if (
            (string) $instance['channel_code']
            !== $channelCode
        ) {
            throw new RuntimeException(
                'provider_defaults_channel_mismatch'
            );
        }

        return $instance;
    }

    private function insertDefault(
        PDO $db,
        string $channelCode,
        int $instanceId,
        int $priority,
        bool $isDefault,
        int $fallbackOrder
    ): void {
        $statement = $db->prepare("
            INSERT INTO notification_provider_defaults (
                scope_type,
                scope_reference,
                channel_code,
                purpose_code,
                provider_instance_id,
                priority,
                is_default,
                fallback_order,
                is_active,
                created_at,
                updated_at
            )
            VALUES (
                'global',
                '*',
                ?,
                'general',
                ?,
                ?,
                ?,
                ?,
                1,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ");
        $statement->execute([
            $channelCode,
            $instanceId,
            $priority,
            $isDefault ? 1 : 0,
            $fallbackOrder,
        ]);
    }
}
