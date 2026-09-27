<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\NotificationMessengerEnrollmentRepository;
use IPKF\Database\Database;
use PDO;
use RuntimeException;

final class BaleServiceAccessConfirmationService
{
    public function confirm(
        string $body,
        string $timestamp,
        string $signature
    ): bool {
        if (
            strlen($body) < 10 ||
            strlen($body) > 4096 ||
            preg_match('/^[0-9]{10}$/D', $timestamp) !== 1 ||
            abs(time() - (int) $timestamp) > 120 ||
            preg_match('/^[a-f0-9]{64}$/D', $signature) !== 1
        ) {
            throw new RuntimeException('INVALID_BRIDGE_REQUEST');
        }

        $repository =
            new NotificationMessengerEnrollmentRepository();

        $runtime = new NotificationProviderRuntimeService();

        /*
         * Resolve the receiving bot from the signature
         * and the existing active service_access providers.
         *
         * Never accept a provider ID supplied by the caller
         * as proof of the destination bot.
         */
        $matched = null;

        foreach (
            $repository->serviceAccessBaleProviders()
            as $provider
        ) {
            $secrets = $runtime->secrets($provider);

            $botToken = trim((string) (
                $secrets['bot_token'] ?? ''
            ));

            if ($botToken === '') {
                continue;
            }

            $expected = hash_hmac(
                'sha256',
                $timestamp . "\n" . hash('sha256', $body),
                $botToken
            );

            if (!hash_equals($expected, $signature)) {
                continue;
            }

            if ($matched !== null) {
                throw new RuntimeException(
                    'AMBIGUOUS_SERVICE_BOT'
                );
            }

            $matched = $provider;
        }

        if (!is_array($matched)) {
            throw new RuntimeException(
                'SERVICE_BOT_SIGNATURE_INVALID'
            );
        }

        $payload = json_decode(
            $body,
            true,
            16,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($payload)) {
            throw new RuntimeException('INVALID_PAYLOAD');
        }

        $chatType = $payload['chat_type'] ?? null;
        $chatId = (string) ($payload['chat_id'] ?? '');
        $senderId = (string) ($payload['sender_id'] ?? '');
        $updateId = (string) ($payload['update_id'] ?? '');
        $text = $payload['text'] ?? null;

        if (
            $chatType !== 'private' ||
            preg_match('/^[0-9]{1,20}$/D', $chatId) !== 1 ||
            preg_match('/^[0-9]{1,20}$/D', $senderId) !== 1 ||
            preg_match('/^[0-9]{1,20}$/D', $updateId) !== 1 ||
            !is_string($text) ||
            strlen($text) > 150
        ) {
            throw new RuntimeException(
                'INVALID_BALE_MESSAGE'
            );
        }

        $configuration = $runtime->configuration(
            $matched
        );

        $username = ltrim(
            trim((string) (
                $configuration['bot_username'] ?? ''
            )),
            '@'
        );

        if (
            preg_match(
                '/^[A-Za-z][A-Za-z0-9_]{4,31}$/D',
                $username
            ) !== 1
        ) {
            throw new RuntimeException(
                'SERVICE_BOT_USERNAME_INVALID'
            );
        }

        /*
         * Both the ordinary /start command and the
         * username-qualified command are supported.
         */
        $pattern = '#^/start(?:@'
            . preg_quote($username, '#')
            . ')?\s+([a-f0-9]{48})$#Di';

        if (
            preg_match(
                $pattern,
                trim($text),
                $matches
            ) !== 1
        ) {
            return false;
        }

        $tokenHash = hash(
            'sha256',
            strtolower($matches[1])
        );

        $providerId = (int) (
            $matched['id'] ?? 0
        );

        if ($providerId < 1) {
            throw new RuntimeException(
                'SERVICE_PROVIDER_INVALID'
            );
        }

        $db = Database::connect();

        $db->beginTransaction();

        try {
            /*
             * Lock the exact pending enrollment.
             * Expired, cancelled and previously used
             * tokens cannot establish a connection.
             */
            $query = $db->prepare("
                SELECT *
                FROM notification_messenger_enrollments
                WHERE provider_instance_id = ?
                  AND token_hash = ?
                  AND status_code = 'pending'
                  AND expires_at > CURRENT_TIMESTAMP
                LIMIT 1
                FOR UPDATE
            ");

            $query->execute([
                $providerId,
                $tokenHash,
            ]);

            $enrollment = $query->fetch(
                PDO::FETCH_ASSOC
            );

            if (!is_array($enrollment)) {
                $db->rollBack();
                return false;
            }

            $userId = (int) (
                $enrollment['user_id'] ?? 0
            );

            if ($userId < 1) {
                throw new RuntimeException(
                    'ENROLLMENT_USER_INVALID'
                );
            }

            /*
             * A chat must not be assigned to another
             * user. Existing bindings are not silently
             * reassigned or overwritten.
             */
            $conflict = $db->prepare("
                SELECT id
                FROM notification_messenger_bindings
                WHERE provider_instance_id = ?
                  AND (
                      user_id = ?
                      OR chat_id = ?
                  )
                LIMIT 1
                FOR UPDATE
            ");

            $conflict->execute([
                $providerId,
                $userId,
                $chatId,
            ]);

            if ($conflict->fetchColumn() !== false) {
                $db->rollBack();
                return false;
            }

            $consume = $db->prepare("
                UPDATE notification_messenger_enrollments
                SET status_code = 'verified',
                    linked_chat_id = ?,
                    linked_external_user_id = ?,
                    started_at = COALESCE(
                        started_at,
                        CURRENT_TIMESTAMP
                    ),
                    verified_at = CURRENT_TIMESTAMP,
                    used_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
                  AND status_code = 'pending'
                  AND expires_at > CURRENT_TIMESTAMP
            ");

            $consume->execute([
                $chatId,
                $senderId,
                (int) $enrollment['id'],
            ]);

            if ($consume->rowCount() !== 1) {
                throw new RuntimeException(
                    'ENROLLMENT_ALREADY_CONSUMED'
                );
            }

            $insert = $db->prepare("
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
                    metadata_json,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    'active',
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP,
                    ?,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");

            $insert->execute([
                'nmb_' . bin2hex(random_bytes(12)),
                $userId,
                $providerId,
                $senderId,
                $chatId,
                (string) (
                    $enrollment['mobile_norm'] ?? ''
                ),
                '',
                '',
                json_encode(
                    [
                        'source' =>
                            'trusted_service_bot_polling',
                        'update_id' => $updateId,
                    ],
                    JSON_THROW_ON_ERROR
                ),
            ]);

            $db->commit();

            return true;

        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }
}
