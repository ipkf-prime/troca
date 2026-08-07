<?php

namespace App\Repositories;

use PDO;
use Throwable;

class NotificationApprovalRepository extends BaseRepository
{
    public function createPendingRequest(
        array $request,
        array $targets,
        array $mediaAssets,
        array $step
    ): array {
        $db = $this->connection();
        $db->beginTransaction();

        try {
            $statement = $db->prepare("
                INSERT INTO notification_approval_requests (
                    public_reference,
                    idempotency_key,
                    requester_user_id,
                    requester_scope_type,
                    requester_scope_reference,
                    requester_context_json,
                    status_code,
                    approval_mode_code,
                    current_step_order,
                    total_steps,
                    message_type_code,
                    purpose_code,
                    priority_code,
                    subject,
                    body,
                    channels_json,
                    request_reason,
                    payload_checksum_sha256,
                    submitted_at,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?,
                    'pending', 'single', 1, 1,
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");

            $statement->execute([
                $request['public_reference'],
                $request['idempotency_key'],
                $request['requester_user_id'],
                $request['requester_scope_type'],
                $request['requester_scope_reference'],
                $request['requester_context_json'],
                $request['message_type_code'],
                $request['purpose_code'],
                $request['priority_code'],
                $request['subject'],
                $request['body'],
                $request['channels_json'],
                $request['request_reason'],
                $request['payload_checksum_sha256'],
            ]);

            $requestId = (int) $db->lastInsertId();

            $targetInsert = $db->prepare("
                INSERT INTO notification_approval_targets (
                    public_reference,
                    request_id,
                    source_type,
                    recipient_user_id,
                    recipient_user_reference,
                    recipient_title,
                    channel_code,
                    destination_snapshot,
                    destination_masked,
                    destination_hash,
                    status_code,
                    sort_order,
                    metadata_json,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    'pending', ?, ?,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");

            foreach ($targets as $index => $target) {
                $targetInsert->execute([
                    $target['public_reference'],
                    $requestId,
                    $target['source_type'],
                    $target['recipient_user_id'],
                    $target['recipient_user_reference'],
                    $target['recipient_title'],
                    $target['channel_code'],
                    $target['destination_snapshot'],
                    $target['destination_masked'],
                    $target['destination_hash'],
                    (int) (
                        $target['sort_order']
                        ?? $index
                    ),
                    $target['metadata_json'] ?? null,
                ]);
            }

            $stepInsert = $db->prepare("
                INSERT INTO notification_approval_steps (
                    public_reference,
                    request_id,
                    step_order,
                    title,
                    approval_policy_code,
                    approver_rule_json,
                    required_decisions,
                    completed_decisions,
                    status_code,
                    activated_at,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?, ?, 1, ?, 'any', ?, 1, 0,
                    'active',
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");

            $stepInsert->execute([
                $step['public_reference'],
                $requestId,
                $step['title'],
                $step['approver_rule_json'],
            ]);

            if ($mediaAssets !== []) {
                $mediaInsert = $db->prepare("
                    INSERT INTO notification_approval_media_links (
                        request_id,
                        asset_id,
                        sort_order,
                        is_primary,
                        created_at
                    )
                    VALUES (
                        ?, ?, ?, ?, CURRENT_TIMESTAMP
                    )
                ");

                foreach ($mediaAssets as $index => $asset) {
                    $assetId = (int) (
                        $asset['id'] ?? 0
                    );

                    if ($assetId < 1) {
                        continue;
                    }

                    $mediaInsert->execute([
                        $requestId,
                        $assetId,
                        $index,
                        $index === 0 ? 1 : 0,
                    ]);
                }
            }

            $this->insertEvent(
                $requestId,
                (int) $request['requester_user_id'],
                'request_created',
                null,
                'draft',
                null,
                [
                    'origin' =>
                        'notification_send_center',
                ]
            );

            $this->insertEvent(
                $requestId,
                (int) $request['requester_user_id'],
                'request_submitted',
                'draft',
                'pending',
                $request['request_reason'],
                [
                    'approval_mode_code' => 'single',
                    'current_step_order' => 1,
                ]
            );

            $db->commit();

            return [
                'id' => $requestId,
                'public_reference' =>
                    (string) $request[
                        'public_reference'
                    ],
                'status_code' => 'pending',
                'current_step_order' => 1,
                'total_steps' => 1,
                'target_count' => count($targets),
            ];
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function findByReference(
        string $publicReference
    ): ?array {
        $statement = $this->connection()->prepare("
            SELECT *
            FROM notification_approval_requests
            WHERE public_reference = ?
            LIMIT 1
        ");

        $statement->execute([
            $publicReference,
        ]);

        $row = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return is_array($row)
            ? $row
            : null;
    }

    private function insertEvent(
        int $requestId,
        ?int $actorUserId,
        string $eventCode,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $reason,
        array $metadata
    ): void {
        $statement = $this->connection()->prepare("
            INSERT INTO notification_approval_events (
                public_reference,
                request_id,
                actor_user_id,
                event_code,
                from_status,
                to_status,
                reason,
                metadata_json,
                happened_at,
                created_at
            )
            VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ");

        $statement->execute([
            'nae_' . bin2hex(
                random_bytes(12)
            ),
            $requestId,
            $actorUserId,
            $eventCode,
            $fromStatus,
            $toStatus,
            $reason,
            json_encode(
                $metadata,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
        ]);
    }
}
