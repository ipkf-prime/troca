<?php

namespace App\Repositories;

use PDO;
use Throwable;

class NotificationGatewayRepository extends BaseRepository
{
    public function createDirectDelivery(
        int $actorUserId,
        string $channelCode,
        string $purposeCode,
        string $destination,
        string $subject,
        string $body,
        int $maxAttempts,
        ?int $recipientUserId = null,
        ?string $recipientUserReference = null,
        string $messageTypeCode = 'text',
        array $mediaAssets = []
    ): array {
        $db = $this->connection();
        $db->beginTransaction();

        try {
            $eventReference =
                $this->reference('nev');
            $notificationReference =
                $this->reference('ntf');
            $deliveryReference =
                $this->reference('ndl');
            $requestReference =
                $this->reference('ngw');

            $event = $db->prepare("
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
                    ?,
                    ?,
                    'notification.manual.direct',
                    'core',
                    'notification_gateway',
                    ?,
                    ?,
                    ?,
                    'recorded',
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");
            $event->execute([
                $eventReference,
                $requestReference,
                $requestReference,
                'user:' . $actorUserId,
                json_encode(
                    [
                        'channel_code' => $channelCode,
                        'purpose_code' => $purposeCode,
                        'destination_fingerprint' => substr(
                            hash('sha256', $destination),
                            0,
                            24
                        ),
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                ),
            ]);
            $eventId = (int) $db->lastInsertId();

            $notification = $db->prepare("
                INSERT INTO notifications (
                    public_reference,
                    event_id,
                    template_code,
                    message_type_code,
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
                    ?,
                    ?,
                    NULL,
                    ?,
                    ?,
                    ?,
                    NULL,
                    'normal',
                    'general',
                    NULL,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");
            $notification->execute([
                $notificationReference,
                $eventId,
                $messageTypeCode,
                $subject !== ''
                    ? $subject
                    : 'اعلان سامانه',
                $body,
            ]);
            $notificationId =
                (int) $db->lastInsertId();

            $recipient = $db->prepare("
                INSERT INTO notification_recipients (
                    notification_id,
                    user_id,
                    user_reference,
                    delivery_policy_code,
                    seen_at,
                    read_at,
                    archived_at,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    'immediate',
                    NULL,
                    NULL,
                    NULL,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");
            $recipientUserId =
                $recipientUserId !== null
                && $recipientUserId > 0
                    ? $recipientUserId
                    : null;
            $recipientUserReference = trim(
                (string) $recipientUserReference
            );

            if (
                $recipientUserId !== null
                && $recipientUserReference === ''
            ) {
                $recipientUserReference =
                    'user:' . $recipientUserId;
            }

            if ($recipientUserReference === '') {
                $recipientUserReference =
                    'external:'
                    . substr(
                        hash('sha256', $destination),
                        0,
                        32
                    );
            }

            $recipient->execute([
                $notificationId,
                $recipientUserId,
                $recipientUserReference,
            ]);
            $recipientId =
                (int) $db->lastInsertId();

            $delivery = $db->prepare("
                INSERT INTO notification_deliveries (
                    public_reference,
                    recipient_id,
                    channel_code,
                    purpose_code,
                    status_code,
                    destination_snapshot,
                    provider_code,
                    provider_instance_id,
                    provider_type_code,
                    provider_message_reference,
                    request_reference,
                    available_at,
                    attempt_count,
                    max_attempts,
                    last_attempt_at,
                    sent_at,
                    delivered_at,
                    failed_at,
                    last_error,
                    last_response_code,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    'pending',
                    ?,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    ?,
                    CURRENT_TIMESTAMP,
                    0,
                    ?,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");
            $delivery->execute([
                $deliveryReference,
                $recipientId,
                $channelCode,
                $purposeCode,
                $destination,
                $requestReference,
                max(1, $maxAttempts),
            ]);

            $deliveryId =
                (int) $db->lastInsertId();

            if ($mediaAssets !== []) {
                $link = $db->prepare("
                    INSERT IGNORE INTO notification_media_links (
                        notification_id,
                        asset_id,
                        sort_order,
                        is_primary,
                        alt_text,
                        created_at
                    )
                    VALUES (?, ?, ?, ?, NULL, CURRENT_TIMESTAMP)
                ");

                foreach (array_values($mediaAssets) as $index => $asset) {
                    $assetId = (int) ($asset['id'] ?? 0);

                    if ($assetId > 0) {
                        $link->execute([
                            $notificationId,
                            $assetId,
                            $index,
                            $index === 0 ? 1 : 0,
                        ]);
                    }
                }
            }

            $db->commit();

            return [
                'event_id' => $eventId,
                'event_reference' => $eventReference,
                'notification_id' => $notificationId,
                'notification_reference' =>
                    $notificationReference,
                'recipient_id' => $recipientId,
                'delivery_id' => $deliveryId,
                'delivery_reference' =>
                    $deliveryReference,
                'request_reference' =>
                    $requestReference,
            ];
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function beginAttempt(
        int $deliveryId,
        array $instance
    ): int {
        $db = $this->connection();
        $db->beginTransaction();

        try {
            $lock = $db->prepare("
                SELECT attempt_count
                FROM notification_deliveries
                WHERE id = ?
                LIMIT 1
                FOR UPDATE
            ");
            $lock->execute([$deliveryId]);
            $attemptNumber =
                (int) $lock->fetchColumn() + 1;

            $update = $db->prepare("
                UPDATE notification_deliveries
                SET status_code = 'processing',
                    provider_code = ?,
                    provider_instance_id = ?,
                    provider_type_code = ?,
                    attempt_count = ?,
                    last_attempt_at =
                        CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $update->execute([
                (string) (
                    $instance['provider_type_code'] ?? ''
                ),
                (int) ($instance['id'] ?? 0),
                (string) (
                    $instance['provider_type_code'] ?? ''
                ),
                $attemptNumber,
                $deliveryId,
            ]);

            $db->commit();

            return $attemptNumber;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function completeSuccess(
        int $deliveryId,
        int $attemptNumber,
        array $instance,
        array $result
    ): void {
        $db = $this->connection();
        $db->beginTransaction();

        try {
            $this->insertAttempt(
                $db,
                $deliveryId,
                $attemptNumber,
                $instance,
                'sent',
                $result
            );

            $update = $db->prepare("
                UPDATE notification_deliveries
                SET status_code = 'sent',
                    provider_message_reference = ?,
                    sent_at = CURRENT_TIMESTAMP,
                    failed_at = NULL,
                    last_error = NULL,
                    last_response_code = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $update->execute([
                (string) (
                    $result[
                        'provider_message_reference'
                    ] ?? ''
                ),
                (string) (
                    $result['response_code'] ?? ''
                ),
                $deliveryId,
            ]);

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function completeFailure(
        int $deliveryId,
        int $attemptNumber,
        array $instance,
        string $errorCode,
        bool $final
    ): void {
        $db = $this->connection();
        $db->beginTransaction();

        try {
            $this->insertAttempt(
                $db,
                $deliveryId,
                $attemptNumber,
                $instance,
                'failed',
                [
                    'response_code' => '',
                    'response_message' => $errorCode,
                    'duration_ms' => 0,
                    'metadata' => [
                        'error_code' => $errorCode,
                    ],
                ]
            );

            if ($final) {
                $update = $db->prepare("
                    UPDATE notification_deliveries
                    SET status_code = 'failed',
                        failed_at = CURRENT_TIMESTAMP,
                        last_error = ?,
                        last_response_code = NULL,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
            } else {
                $update = $db->prepare("
                    UPDATE notification_deliveries
                    SET status_code = 'pending',
                        available_at = CURRENT_TIMESTAMP,
                        failed_at = NULL,
                        last_error = ?,
                        last_response_code = NULL,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
            }

            $update->execute([
                $errorCode,
                $deliveryId,
            ]);

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function failWithoutProvider(
        int $deliveryId,
        string $errorCode
    ): void {
        $statement = $this->connection()->prepare("
            UPDATE notification_deliveries
            SET status_code = 'failed',
                failed_at = CURRENT_TIMESTAMP,
                last_error = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $statement->execute([
            $errorCode,
            $deliveryId,
        ]);
    }

    private function insertAttempt(
        PDO $db,
        int $deliveryId,
        int $attemptNumber,
        array $instance,
        string $statusCode,
        array $result
    ): void {
        $statement = $db->prepare("
            INSERT INTO notification_delivery_attempts (
                delivery_id,
                attempt_number,
                status_code,
                provider_instance_id,
                provider_type_code,
                provider_message_reference,
                provider_response_code,
                provider_response_message,
                duration_ms,
                response_metadata_json,
                attempted_at,
                created_at
            )
            VALUES (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ");
        $statement->execute([
            $deliveryId,
            $attemptNumber,
            $statusCode,
            (int) ($instance['id'] ?? 0),
            (string) (
                $instance['provider_type_code'] ?? ''
            ),
            (string) (
                $result[
                    'provider_message_reference'
                ] ?? ''
            ),
            (string) (
                $result['response_code'] ?? ''
            ),
            (string) (
                $result['response_message'] ?? ''
            ),
            max(
                0,
                (int) ($result['duration_ms'] ?? 0)
            ),
            json_encode(
                is_array($result['metadata'] ?? null)
                    ? $result['metadata']
                    : [],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
        ]);
    }

    private function reference(string $prefix): string
    {
        return $prefix
            . '_'
            . bin2hex(random_bytes(12));
    }
}
