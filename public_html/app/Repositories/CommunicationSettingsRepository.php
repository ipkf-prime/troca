<?php

namespace App\Repositories;

use PDO;

class CommunicationSettingsRepository extends BaseRepository
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

    public function providerInstances(): array
    {
        $statement = $this->connection()->query("
            SELECT
                instances.*,
                types.code AS provider_type_code,
                types.title AS provider_type_title,
                types.channel_code,
                types.driver_code,
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
            ORDER BY
                types.channel_code ASC,
                instances.priority DESC,
                instances.id ASC
        ");

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function providerDefaults(): array
    {
        $statement = $this->connection()->query("
            SELECT
                defaults.*,
                instances.title AS provider_title,
                types.title AS provider_type_title
            FROM notification_provider_defaults AS defaults
            INNER JOIN notification_provider_instances AS instances
              ON instances.id = defaults.provider_instance_id
            INNER JOIN notification_provider_types AS types
              ON types.id = instances.provider_type_id
            WHERE defaults.is_active = 1
            ORDER BY
                defaults.channel_code ASC,
                defaults.priority ASC,
                defaults.id ASC
        ");

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function routingRules(): array
    {
        $statement = $this->connection()->query("
            SELECT
                rules.*,
                events.title AS event_title,
                instances.title AS provider_title
            FROM notification_routing_rules AS rules
            LEFT JOIN notification_event_catalog AS events
              ON events.event_type = rules.event_type
            LEFT JOIN notification_provider_instances AS instances
              ON instances.id = rules.provider_instance_id
            ORDER BY
                rules.event_type ASC,
                rules.sort_order ASC,
                rules.id ASC
        ");

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function events(): array
    {
        $statement = $this->connection()->query("
            SELECT *
            FROM notification_event_catalog
            WHERE is_active = 1
            ORDER BY source_module ASC, sort_order ASC, id ASC
        ");

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function channels(): array
    {
        $statement = $this->connection()->query("
            SELECT *
            FROM notification_channels
            WHERE is_active = 1
            ORDER BY sort_order ASC, id ASC
        ");

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function preferences(int $userId): array
    {
        $statement = $this->connection()->prepare("
            SELECT *
            FROM notification_preferences
            WHERE user_id = ?
              AND event_type = '*'
            ORDER BY channel_code ASC
        ");
        $statement->execute([$userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function savePreferences(
        int $userId,
        array $enabledChannels
    ): void {
        $statement = $this->connection()->prepare("
            INSERT INTO notification_preferences (
                user_id,
                event_type,
                channel_code,
                is_enabled,
                timezone,
                digest_mode,
                created_at,
                updated_at
            )
            VALUES (
                ?,
                '*',
                ?,
                ?,
                'Asia/Tehran',
                'immediate',
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                is_enabled = VALUES(is_enabled),
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($this->channels() as $channel) {
            $code = (string) $channel['code'];
            $statement->execute([
                $userId,
                $code,
                in_array($code, $enabledChannels, true) ? 1 : 0,
            ]);
        }
    }

    public function deliveryReport(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        $statement = $this->connection()->query("
            SELECT
                deliveries.channel_code,
                deliveries.status_code,
                deliveries.provider_code,
                deliveries.attempt_count,
                deliveries.sent_at,
                deliveries.delivered_at,
                deliveries.failed_at,
                deliveries.last_error,
                notifications.title,
                recipients.user_id,
                recipients.user_reference,
                COALESCE(
                    NULLIF(persons.full_name, ''),
                    NULLIF(users.username, ''),
                    users.email,
                    recipients.user_reference
                ) AS user_title
            FROM notification_deliveries AS deliveries
            INNER JOIN notification_recipients AS recipients
              ON recipients.id = deliveries.recipient_id
            INNER JOIN notifications
              ON notifications.id = recipients.notification_id
            LEFT JOIN users
              ON users.id = recipients.user_id
            LEFT JOIN persons
              ON persons.id = users.person_id
            ORDER BY deliveries.id DESC
            LIMIT {$limit}
        ");

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
