<?php

namespace App\Repositories;

use PDO;
use RuntimeException;
use Throwable;

class NotificationMessengerEnrollmentRepository extends BaseRepository
{
    public function membershipAuthBaleProviders(): array
    {
        $statement = $this->connection()->query("
            SELECT
                instances.*,
                types.code AS provider_type_code,
                types.channel_code,
                types.driver_code
            FROM notification_provider_instances AS instances
            INNER JOIN notification_provider_types AS types
              ON types.id = instances.provider_type_id
            WHERE types.code = 'bale_bot'
              AND types.is_active = 1
              AND instances.status_code = 'active'
              AND instances.is_enabled = 1
            ORDER BY
                instances.priority DESC,
                instances.id ASC
        ");

        return array_values(array_filter(
            $statement->fetchAll(PDO::FETCH_ASSOC) ?: [],
            fn (array $row): bool =>
                $this->isMembershipAuthProvider($row)
        ));
    }

    public function providerByReference(
        string $reference
    ): ?array {
        $statement = $this->connection()->prepare("
            SELECT
                instances.*,
                types.code AS provider_type_code,
                types.channel_code,
                types.driver_code
            FROM notification_provider_instances AS instances
            INNER JOIN notification_provider_types AS types
              ON types.id = instances.provider_type_id
            WHERE instances.public_reference = ?
              AND types.code = 'bale_bot'
              AND types.is_active = 1
              AND instances.status_code = 'active'
              AND instances.is_enabled = 1
            LIMIT 1
        ");
        $statement->execute([$reference]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row)
            && $this->isMembershipAuthProvider($row)
                ? $row
                : null;
    }

    public function mobileRecipients(
        array $userIds
    ): array {
        $userIds = array_values(array_unique(
            array_filter(
                array_map('intval', $userIds),
                static fn (int $id): bool => $id > 0
            )
        ));

        if ($userIds === []) {
            return [];
        }

        $statement = $this->connection()->prepare("
            SELECT
                users.id,
                COALESCE(
                    NULLIF(persons.full_name, ''),
                    NULLIF(users.username, ''),
                    CONCAT('کاربر ', users.id)
                ) AS title,
                COALESCE(
                    NULLIF(persons.mobile_norm, ''),
                    NULLIF(persons.mobile, ''),
                    NULLIF(users.mobile_norm, ''),
                    NULLIF(users.mobile, ''),
                    ''
                ) AS mobile
            FROM users
            LEFT JOIN persons
              ON persons.id = users.person_id
            WHERE users.status = 'active'
              AND users.id IN ("
                . implode(
                    ',',
                    array_fill(0, count($userIds), '?')
                )
                . ")
            ORDER BY users.id ASC
        ");
        $statement->execute($userIds);

        return $statement->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
    }

    public function createEnrollment(
        int $userId,
        int $providerInstanceId,
        string $mobile,
        string $tokenHash,
        int $actorUserId,
        string $expiresAt
    ): array {
        $db = $this->connection();
        $db->beginTransaction();

        try {
            $db->prepare("
                UPDATE notification_messenger_enrollments
                SET status_code = 'cancelled',
                    cancelled_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE user_id = ?
                  AND provider_instance_id = ?
                  AND status_code IN ('pending', 'started')
            ")->execute([
                $userId,
                $providerInstanceId,
            ]);

            $reference =
                'nme_' . bin2hex(random_bytes(12));

            $statement = $db->prepare("
                INSERT INTO notification_messenger_enrollments (
                    public_reference,
                    user_id,
                    provider_instance_id,
                    mobile_norm,
                    token_hash,
                    status_code,
                    expires_at,
                    attempts,
                    invited_by_user_id,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?, ?, ?, ?, ?,
                    'pending',
                    ?,
                    0,
                    ?,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");
            $statement->execute([
                $reference,
                $userId,
                $providerInstanceId,
                $mobile,
                $tokenHash,
                $expiresAt,
                $actorUserId,
            ]);

            $id = (int) $db->lastInsertId();
            $db->commit();

            return [
                'id' => $id,
                'public_reference' => $reference,
            ];
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function setInviteDelivery(
        int $enrollmentId,
        string $deliveryReference
    ): void {
        $statement = $this->connection()->prepare("
            UPDATE notification_messenger_enrollments
            SET invite_delivery_reference = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $statement->execute([
            $deliveryReference,
            $enrollmentId,
        ]);
    }

    public function markInviteFailed(
        int $enrollmentId,
        string $errorCode
    ): void {
        $statement = $this->connection()->prepare("
            UPDATE notification_messenger_enrollments
            SET status_code = 'failed',
                last_error = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $statement->execute([
            mb_substr(
                $errorCode,
                0,
                190,
                'UTF-8'
            ),
            $enrollmentId,
        ]);
    }

    public function pendingByTokenHash(
        int $providerInstanceId,
        string $tokenHash
    ): ?array {
        $statement = $this->connection()->prepare("
            SELECT *
            FROM notification_messenger_enrollments
            WHERE provider_instance_id = ?
              AND token_hash = ?
              AND status_code IN ('pending', 'started')
              AND expires_at > CURRENT_TIMESTAMP
            LIMIT 1
        ");
        $statement->execute([
            $providerInstanceId,
            $tokenHash,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function pendingByChat(
        int $providerInstanceId,
        string $chatId
    ): ?array {
        $statement = $this->connection()->prepare("
            SELECT *
            FROM notification_messenger_enrollments
            WHERE provider_instance_id = ?
              AND linked_chat_id = ?
              AND status_code = 'started'
              AND expires_at > CURRENT_TIMESTAMP
            ORDER BY id DESC
            LIMIT 1
        ");
        $statement->execute([
            $providerInstanceId,
            $chatId,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function markStarted(
        int $enrollmentId,
        string $chatId,
        string $externalUserId,
        array $profile
    ): void {
        $statement = $this->connection()->prepare("
            UPDATE notification_messenger_enrollments
            SET status_code = 'started',
                linked_chat_id = ?,
                linked_external_user_id = ?,
                started_at = COALESCE(
                    started_at,
                    CURRENT_TIMESTAMP
                ),
                attempts = attempts + 1,
                metadata_json = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
              AND status_code IN ('pending', 'started')
        ");
        $statement->execute([
            $chatId,
            $externalUserId,
            json_encode(
                $profile,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
            $enrollmentId,
        ]);
    }

    public function complete(
        array $enrollment,
        string $chatId,
        string $externalUserId,
        string $username,
        string $displayName
    ): void {
        $db = $this->connection();
        $db->beginTransaction();

        try {
            $conflict = $db->prepare("
                SELECT user_id
                FROM notification_messenger_bindings
                WHERE provider_instance_id = ?
                  AND chat_id = ?
                  AND status_code = 'active'
                  AND user_id <> ?
                LIMIT 1
                FOR UPDATE
            ");
            $conflict->execute([
                (int) $enrollment[
                    'provider_instance_id'
                ],
                $chatId,
                (int) $enrollment['user_id'],
            ]);

            if ($conflict->fetchColumn() !== false) {
                throw new RuntimeException(
                    'notification_bale_chat_already_bound'
                );
            }

            $reference =
                'nmb_' . bin2hex(random_bytes(12));

            $binding = $db->prepare("
                INSERT INTO notification_messenger_bindings (
                    public_reference,
                    user_id,
                    provider_instance_id,
                    external_user_id,
                    chat_id,
                    mobile_norm,
                    username,
                    display_name,
                    status_code,
                    verified_at,
                    last_activity_at,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    'active',
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
                ON DUPLICATE KEY UPDATE
                    external_user_id =
                        VALUES(external_user_id),
                    chat_id = VALUES(chat_id),
                    mobile_norm = VALUES(mobile_norm),
                    username = VALUES(username),
                    display_name = VALUES(display_name),
                    status_code = 'active',
                    verified_at = CURRENT_TIMESTAMP,
                    last_activity_at = CURRENT_TIMESTAMP,
                    revoked_at = NULL,
                    updated_at = CURRENT_TIMESTAMP
            ");
            $binding->execute([
                $reference,
                (int) $enrollment['user_id'],
                (int) $enrollment[
                    'provider_instance_id'
                ],
                $externalUserId,
                $chatId,
                (string) $enrollment['mobile_norm'],
                $username,
                $displayName,
            ]);

            $update = $db->prepare("
                UPDATE notification_messenger_enrollments
                SET status_code = 'verified',
                    linked_chat_id = ?,
                    linked_external_user_id = ?,
                    verified_at = CURRENT_TIMESTAMP,
                    used_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $update->execute([
                $chatId,
                $externalUserId,
                (int) $enrollment['id'],
            ]);

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }
    private function isMembershipAuthProvider(
        array $provider
    ): bool {
        $configuration = json_decode(
            (string) (
                $provider['configuration_json'] ?? ''
            ),
            true
        );

        return is_array($configuration)
            && (string) (
                $configuration[
                    'bot_purpose_code'
                ] ?? ''
            ) === 'membership_auth';
    }

}
