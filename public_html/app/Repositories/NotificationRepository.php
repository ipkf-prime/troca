<?php

namespace App\Repositories;

use PDO;
use RuntimeException;
use Throwable;

class NotificationRepository extends BaseRepository
{
    public function createEventWithOutbox(array $event): array
    {
        $db = $this->connection();
        $db->beginTransaction();

        try {
            $existing = $db->prepare("
                SELECT
                    events.id,
                    events.public_reference,
                    outbox.public_reference AS outbox_reference
                FROM notification_events AS events
                LEFT JOIN notification_outbox AS outbox
                  ON outbox.event_id = events.id
                WHERE events.idempotency_key = ?
                LIMIT 1
            ");
            $existing->execute([$event['idempotency_key']]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $db->commit();

                return [
                    'event_id' => (int) $row['id'],
                    'event_reference' => (string) $row['public_reference'],
                    'outbox_reference' => (string) (
                        $row['outbox_reference'] ?? ''
                    ),
                    'duplicate' => true,
                ];
            }

            $insertEvent = $db->prepare("
                INSERT INTO notification_events (
                    public_reference,
                    idempotency_key,
                    event_type,
                    source_module,
                    source_entity_type,
                    source_entity_reference,
                    actor_user_reference,
                    payload_json,
                    status_code,
                    occurred_at,
                    created_at
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    'recorded',
                    ?,
                    CURRENT_TIMESTAMP
                )
            ");
            $insertEvent->execute([
                $event['public_reference'],
                $event['idempotency_key'],
                $event['event_type'],
                $event['source_module'],
                $event['source_entity_type'],
                $event['source_entity_reference'],
                $event['actor_user_reference'],
                $event['payload_json'],
                $event['occurred_at'],
            ]);
            $eventId = (int) $db->lastInsertId();

            $insertOutbox = $db->prepare("
                INSERT INTO notification_outbox (
                    public_reference,
                    event_id,
                    status_code,
                    priority_code,
                    available_at,
                    attempts_count,
                    max_attempts,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?,
                    ?,
                    'pending',
                    ?,
                    ?,
                    0,
                    ?,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");
            $insertOutbox->execute([
                $event['outbox_reference'],
                $eventId,
                $event['priority_code'],
                $event['available_at'],
                $event['max_attempts'],
            ]);

            $db->commit();

            return [
                'event_id' => $eventId,
                'event_reference' => $event['public_reference'],
                'outbox_reference' => $event['outbox_reference'],
                'duplicate' => false,
            ];
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function claimNextOutbox(string $workerId): ?array
    {
        $db = $this->connection();
        $db->beginTransaction();

        try {
            $statement = $db->query("
                SELECT
                    id,
                    public_reference,
                    event_id,
                    attempts_count,
                    max_attempts,
                    priority_code
                FROM notification_outbox
                WHERE (
                    status_code = 'pending'
                    AND available_at <= CURRENT_TIMESTAMP
                ) OR (
                    status_code = 'processing'
                    AND locked_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 5 MINUTE)
                )
                ORDER BY
                    CASE priority_code
                        WHEN 'urgent' THEN 4
                        WHEN 'high' THEN 3
                        WHEN 'normal' THEN 2
                        ELSE 1
                    END DESC,
                    available_at ASC,
                    id ASC
                LIMIT 1
                FOR UPDATE
            ");
            $row = $statement->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $db->commit();
                return null;
            }

            $update = $db->prepare("
                UPDATE notification_outbox
                SET status_code = 'processing',
                    locked_at = CURRENT_TIMESTAMP,
                    locked_by = ?,
                    attempts_count = attempts_count + 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $update->execute([
                $workerId,
                (int) $row['id'],
            ]);

            $row['attempts_count'] =
                (int) $row['attempts_count'] + 1;
            $row['max_attempts'] =
                (int) $row['max_attempts'];

            $db->commit();

            return $row;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function eventById(int $eventId): ?array
    {
        $statement = $this->connection()->prepare("
            SELECT *
            FROM notification_events
            WHERE id = ?
            LIMIT 1
        ");
        $statement->execute([$eventId]);
        $event = $statement->fetch(PDO::FETCH_ASSOC);

        return $event ?: null;
    }

    public function template(
        string $code,
        string $channel,
        string $locale = 'fa'
    ): ?array {
        $statement = $this->connection()->prepare("
            SELECT *
            FROM notification_templates
            WHERE code = ?
              AND channel_code = ?
              AND locale = ?
              AND is_active = 1
            ORDER BY version DESC, id DESC
            LIMIT 1
        ");
        $statement->execute([$code, $channel, $locale]);
        $template = $statement->fetch(PDO::FETCH_ASSOC);

        return $template ?: null;
    }

    public function materializeInApp(
        array $event,
        array $content,
        array $recipientReferences,
        array $channels
    ): array {
        $db = $this->connection();
        $db->beginTransaction();

        try {
            $existing = $db->prepare("
                SELECT id, public_reference
                FROM notifications
                WHERE event_id = ?
                LIMIT 1
            ");
            $existing->execute([(int) $event['id']]);
            $notification = $existing->fetch(PDO::FETCH_ASSOC);

            if (!$notification) {
                $insert = $db->prepare("
                    INSERT INTO notifications (
                        public_reference,
                        event_id,
                        template_code,
                        title,
                        body,
                        action_url,
                        priority_code,
                        category_code,
                        expires_at,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?,
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    )
                ");
                $insert->execute([
                    $content['public_reference'],
                    (int) $event['id'],
                    $content['template_code'],
                    $content['title'],
                    $content['body'],
                    $content['action_url'],
                    $content['priority_code'],
                    $content['category_code'],
                    $content['expires_at'],
                ]);

                $notificationId = (int) $db->lastInsertId();
                $notificationReference =
                    $content['public_reference'];
            } else {
                $notificationId = (int) $notification['id'];
                $notificationReference =
                    (string) $notification['public_reference'];
            }

            foreach ($recipientReferences as $reference) {
                $userId = $this->resolveUserId($reference);

                $recipient = $db->prepare("
                    INSERT INTO notification_recipients (
                        notification_id,
                        user_id,
                        user_reference,
                        delivery_policy_code,
                        created_at,
                        updated_at
                    )
                    VALUES (?, ?, ?, 'immediate', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE
                        user_id = COALESCE(
                            VALUES(user_id),
                            notification_recipients.user_id
                        ),
                        updated_at = CURRENT_TIMESTAMP
                ");
                $recipient->execute([
                    $notificationId,
                    $userId,
                    $reference,
                ]);

                $recipientIdStatement = $db->prepare("
                    SELECT id
                    FROM notification_recipients
                    WHERE notification_id = ?
                      AND user_reference = ?
                    LIMIT 1
                ");
                $recipientIdStatement->execute([
                    $notificationId,
                    $reference,
                ]);
                $recipientId =
                    (int) $recipientIdStatement->fetchColumn();

                if ($recipientId < 1) {
                    throw new RuntimeException(
                        'notification_recipient_missing'
                    );
                }

                foreach ($channels as $channel) {
                    $isInApp = $channel === 'in_app';
                    $delivery = $db->prepare("
                        INSERT INTO notification_deliveries (
                            recipient_id,
                            channel_code,
                            status_code,
                            available_at,
                            attempt_count,
                            max_attempts,
                            sent_at,
                            delivered_at,
                            created_at,
                            updated_at
                        )
                        VALUES (
                            ?, ?, ?,
                            CURRENT_TIMESTAMP,
                            ?,
                            8,
                            ?,
                            ?,
                            CURRENT_TIMESTAMP,
                            CURRENT_TIMESTAMP
                        )
                        ON DUPLICATE KEY UPDATE
                            updated_at = CURRENT_TIMESTAMP
                    ");
                    $now = $isInApp
                        ? gmdate('Y-m-d H:i:s')
                        : null;
                    $delivery->execute([
                        $recipientId,
                        $channel,
                        $isInApp ? 'delivered' : 'pending',
                        $isInApp ? 1 : 0,
                        $now,
                        $now,
                    ]);

                    if ($isInApp) {
                        $deliveryIdStatement = $db->prepare("
                            SELECT id
                            FROM notification_deliveries
                            WHERE recipient_id = ?
                              AND channel_code = 'in_app'
                            LIMIT 1
                        ");
                        $deliveryIdStatement->execute([
                            $recipientId,
                        ]);
                        $deliveryId =
                            (int) $deliveryIdStatement->fetchColumn();

                        if ($deliveryId > 0) {
                            $attempt = $db->prepare("
                                INSERT IGNORE INTO notification_delivery_attempts (
                                    delivery_id,
                                    attempt_number,
                                    status_code,
                                    provider_response_code,
                                    provider_response_message,
                                    attempted_at,
                                    created_at
                                )
                                VALUES (
                                    ?,
                                    1,
                                    'delivered',
                                    'in_app',
                                    'Stored in user inbox',
                                    CURRENT_TIMESTAMP,
                                    CURRENT_TIMESTAMP
                                )
                            ");
                            $attempt->execute([$deliveryId]);
                        }
                    }
                }
            }

            $db->commit();

            return [
                'notification_id' => $notificationId,
                'notification_reference' =>
                    $notificationReference,
                'recipient_count' =>
                    count($recipientReferences),
            ];
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function completeOutbox(int $outboxId): void
    {
        $statement = $this->connection()->prepare("
            UPDATE notification_outbox
            SET status_code = 'completed',
                processed_at = CURRENT_TIMESTAMP,
                locked_at = NULL,
                locked_by = NULL,
                last_error = NULL,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $statement->execute([$outboxId]);
    }

    public function failOutbox(
        int $outboxId,
        int $attempts,
        int $maxAttempts,
        string $error
    ): void {
        $terminal = $attempts >= $maxAttempts;
        $delaySeconds = min(
            3600,
            max(60, (2 ** min($attempts, 10)) * 15)
        );
        $availableAt = gmdate(
            'Y-m-d H:i:s',
            time() + $delaySeconds
        );

        $statement = $this->connection()->prepare("
            UPDATE notification_outbox
            SET status_code = ?,
                available_at = ?,
                locked_at = NULL,
                locked_by = NULL,
                last_error = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $statement->execute([
            $terminal ? 'failed' : 'pending',
            $availableAt,
            mb_substr($error, 0, 2000, 'UTF-8'),
            $outboxId,
        ]);
    }

    public function inbox(
        int $userId,
        string $filter,
        int $page,
        int $perPage
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;
        $userReference = (string) $userId;
        $filterSql = $filter === 'unread'
            ? ' AND recipients.read_at IS NULL'
            : '';

        $count = $this->connection()->prepare("
            SELECT COUNT(*)
            FROM notification_recipients AS recipients
            INNER JOIN notifications
              ON notifications.id = recipients.notification_id
            WHERE (
                recipients.user_id = ?
                OR recipients.user_reference = ?
            )
              AND recipients.archived_at IS NULL
              AND (
                  notifications.expires_at IS NULL
                  OR notifications.expires_at > CURRENT_TIMESTAMP
              )
              {$filterSql}
        ");
        $count->execute([$userId, $userReference]);
        $total = (int) $count->fetchColumn();

        $statement = $this->connection()->prepare("
            SELECT
                notifications.public_reference,
                notifications.title,
                notifications.body,
                notifications.action_url,
                notifications.priority_code,
                notifications.category_code,
                notifications.created_at,
                recipients.seen_at,
                recipients.read_at
            FROM notification_recipients AS recipients
            INNER JOIN notifications
              ON notifications.id = recipients.notification_id
            WHERE (
                recipients.user_id = :user_id
                OR recipients.user_reference = :user_reference
            )
              AND recipients.archived_at IS NULL
              AND (
                  notifications.expires_at IS NULL
                  OR notifications.expires_at > CURRENT_TIMESTAMP
              )
              {$filterSql}
            ORDER BY
                CASE WHEN recipients.read_at IS NULL THEN 0 ELSE 1 END,
                notifications.created_at DESC,
                notifications.id DESC
            LIMIT :limit OFFSET :offset
        ");
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(
            ':user_reference',
            $userReference,
            PDO::PARAM_STR
        );
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return [
            'items' => $statement->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
        ];
    }

    public function unreadCount(int $userId): int
    {
        $statement = $this->connection()->prepare("
            SELECT COUNT(*)
            FROM notification_recipients AS recipients
            INNER JOIN notifications
              ON notifications.id = recipients.notification_id
            WHERE (
                recipients.user_id = ?
                OR recipients.user_reference = ?
            )
              AND recipients.read_at IS NULL
              AND recipients.archived_at IS NULL
              AND (
                  notifications.expires_at IS NULL
                  OR notifications.expires_at > CURRENT_TIMESTAMP
              )
        ");
        $statement->execute([$userId, (string) $userId]);

        return (int) $statement->fetchColumn();
    }

    public function markRead(
        int $userId,
        string $notificationReference
    ): bool {
        $statement = $this->connection()->prepare("
            UPDATE notification_recipients AS recipients
            INNER JOIN notifications
              ON notifications.id = recipients.notification_id
            SET recipients.seen_at = COALESCE(
                    recipients.seen_at,
                    CURRENT_TIMESTAMP
                ),
                recipients.read_at = COALESCE(
                    recipients.read_at,
                    CURRENT_TIMESTAMP
                ),
                recipients.updated_at = CURRENT_TIMESTAMP
            WHERE notifications.public_reference = ?
              AND (
                  recipients.user_id = ?
                  OR recipients.user_reference = ?
              )
              AND recipients.archived_at IS NULL
        ");
        $statement->execute([
            $notificationReference,
            $userId,
            (string) $userId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function markAllRead(int $userId): int
    {
        $statement = $this->connection()->prepare("
            UPDATE notification_recipients
            SET seen_at = COALESCE(
                    seen_at,
                    CURRENT_TIMESTAMP
                ),
                read_at = COALESCE(
                    read_at,
                    CURRENT_TIMESTAMP
                ),
                updated_at = CURRENT_TIMESTAMP
            WHERE (
                user_id = ?
                OR user_reference = ?
            )
              AND read_at IS NULL
              AND archived_at IS NULL
        ");
        $statement->execute([$userId, (string) $userId]);

        return $statement->rowCount();
    }

    private function resolveUserId(string $reference): ?int
    {
        $reference = trim($reference);

        if ($reference === '') {
            return null;
        }

        if (ctype_digit($reference)) {
            $statement = $this->connection()->prepare("
                SELECT id
                FROM users
                WHERE id = ?
                  AND deleted_at IS NULL
                LIMIT 1
            ");
            $statement->execute([(int) $reference]);
            $id = $statement->fetchColumn();

            return $id === false ? null : (int) $id;
        }

        $statement = $this->connection()->prepare("
            SELECT id
            FROM users
            WHERE (
                username_norm = ?
                OR email_norm = ?
                OR mobile_norm = ?
            )
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $statement->execute([
            strtolower($reference),
            strtolower($reference),
            $reference,
        ]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }
}
