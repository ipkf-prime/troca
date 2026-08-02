<?php

namespace App\Repositories;

use IPKF\Database\Database;
use PDO;
use RuntimeException;
use Throwable;

class InternalMessageRepository extends BaseRepository
{
    public function available(): bool
    {
        return Database::tableExists('message_conversations')
            && Database::tableExists('message_messages');
    }

    public function recipientOptions(int $senderUserId): array
    {
        if (!$this->available()) {
            return [];
        }

        $policy = $this->connection()->query("
            SELECT evaluator_code
            FROM message_recipient_policies
            WHERE status_code = 'active'
            ORDER BY is_default DESC, priority DESC, id ASC
            LIMIT 1
        ")->fetchColumn();

        if ((string) $policy !== 'all_active_users') {
            return [];
        }

        $statement = $this->connection()->prepare("
            SELECT
                users.id,
                COALESCE(
                    NULLIF(persons.full_name, ''),
                    NULLIF(users.username, ''),
                    NULLIF(users.email, ''),
                    CONCAT('کاربر ', users.id)
                ) AS title,
                users.username
            FROM users
            LEFT JOIN persons ON persons.id = users.person_id
            WHERE users.status = 'active'
              AND users.id <> ?
            ORDER BY title ASC, users.id ASC
        ");
        $statement->execute([$senderUserId]);

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'username' => (string) ($row['username'] ?? ''),
            ],
            $statement->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    public function recipientAllowed(
        int $senderUserId,
        int $recipientUserId
    ): bool {
        foreach ($this->recipientOptions($senderUserId) as $user) {
            if ((int) $user['id'] === $recipientUserId) {
                return true;
            }
        }

        return false;
    }

    public function createConversation(
        int $senderUserId,
        int $recipientUserId,
        string $subject,
        string $body
    ): array {
        $db = $this->connection();
        $db->beginTransaction();

        try {
            $conversationReference =
                'msgc_' . bin2hex(random_bytes(12));
            $messageReference =
                'msg_' . bin2hex(random_bytes(12));

            $statement = $db->prepare("
                INSERT INTO message_conversations (
                    public_reference,
                    conversation_type,
                    subject,
                    created_by_user_id,
                    status_code,
                    last_message_at,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?, 'direct', ?, ?, 'active',
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");
            $statement->execute([
                $conversationReference,
                $subject,
                $senderUserId,
            ]);
            $conversationId = (int) $db->lastInsertId();

            $participant = $db->prepare("
                INSERT INTO message_conversation_participants (
                    conversation_id,
                    user_id,
                    participant_role,
                    joined_at,
                    created_at,
                    updated_at
                )
                VALUES (?, ?, ?, CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            $participant->execute([
                $conversationId,
                $senderUserId,
                'owner',
            ]);
            $participant->execute([
                $conversationId,
                $recipientUserId,
                'member',
            ]);

            $message = $db->prepare("
                INSERT INTO message_messages (
                    public_reference,
                    conversation_id,
                    sender_user_id,
                    message_type,
                    body,
                    sent_at,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?, ?, ?, 'text', ?,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");
            $message->execute([
                $messageReference,
                $conversationId,
                $senderUserId,
                $body,
            ]);
            $messageId = (int) $db->lastInsertId();

            $db->prepare("
                UPDATE message_conversation_participants
                SET last_read_message_id = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE conversation_id = ?
                  AND user_id = ?
            ")->execute([
                $messageId,
                $conversationId,
                $senderUserId,
            ]);

            $db->commit();

            return [
                'conversation_reference' => $conversationReference,
                'message_reference' => $messageReference,
                'recipient_user_ids' => [$recipientUserId],
            ];
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function reply(
        int $userId,
        string $conversationReference,
        string $body
    ): array {
        $conversationId = $this->conversationIdForUser(
            $userId,
            $conversationReference
        );

        if ($conversationId === null) {
            throw new RuntimeException(
                'message_conversation_forbidden'
            );
        }

        if ($this->conversationStatus($conversationId) !== 'active') {
            throw new RuntimeException('message_conversation_closed');
        }

        $db = $this->connection();
        $db->beginTransaction();

        try {
            $messageReference =
                'msg_' . bin2hex(random_bytes(12));

            $message = $db->prepare("
                INSERT INTO message_messages (
                    public_reference,
                    conversation_id,
                    sender_user_id,
                    message_type,
                    body,
                    sent_at,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?, ?, ?, 'text', ?,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");
            $message->execute([
                $messageReference,
                $conversationId,
                $userId,
                $body,
            ]);
            $messageId = (int) $db->lastInsertId();

            $db->prepare("
                UPDATE message_conversations
                SET last_message_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ")->execute([$conversationId]);

            $db->prepare("
                UPDATE message_conversation_participants
                SET last_read_message_id = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE conversation_id = ?
                  AND user_id = ?
            ")->execute([
                $messageId,
                $conversationId,
                $userId,
            ]);

            $recipients = $db->prepare("
                SELECT user_id
                FROM message_conversation_participants
                WHERE conversation_id = ?
                  AND user_id <> ?
                  AND left_at IS NULL
            ");
            $recipients->execute([$conversationId, $userId]);
            $recipientIds = array_map(
                'intval',
                $recipients->fetchAll(PDO::FETCH_COLUMN) ?: []
            );

            $db->commit();

            return [
                'conversation_reference' => $conversationReference,
                'message_reference' => $messageReference,
                'recipient_user_ids' => $recipientIds,
            ];
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function inbox(int $userId, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));

        $statement = $this->connection()->prepare("
            SELECT
                conversations.public_reference,
                conversations.subject,
                conversations.status_code,
                conversations.last_message_at,
                last_message.body AS last_message_body,
                COALESCE(
                    NULLIF(other_person.full_name, ''),
                    NULLIF(other_user.username, ''),
                    NULLIF(other_user.email, ''),
                    CONCAT('کاربر ', other_participant.user_id)
                ) AS counterpart_title,
                (
                    SELECT COUNT(*)
                    FROM message_messages AS unread_messages
                    WHERE unread_messages.conversation_id =
                        conversations.id
                      AND unread_messages.deleted_at IS NULL
                      AND unread_messages.sender_user_id <> :unread_user
                      AND unread_messages.id >
                        COALESCE(participants.last_read_message_id, 0)
                ) AS unread_count
            FROM message_conversation_participants AS participants
            INNER JOIN message_conversations AS conversations
              ON conversations.id = participants.conversation_id
            LEFT JOIN message_messages AS last_message
              ON last_message.id = (
                  SELECT MAX(latest.id)
                  FROM message_messages AS latest
                  WHERE latest.conversation_id = conversations.id
                    AND latest.deleted_at IS NULL
              )
            LEFT JOIN message_conversation_participants AS other_participant
              ON other_participant.conversation_id = conversations.id
             AND other_participant.user_id <> :other_user
             AND other_participant.left_at IS NULL
            LEFT JOIN users AS other_user
              ON other_user.id = other_participant.user_id
            LEFT JOIN persons AS other_person
              ON other_person.id = other_user.person_id
            WHERE participants.user_id = :user_id
              AND participants.left_at IS NULL
              AND participants.archived_at IS NULL
              AND conversations.status_code IN ('active', 'closed')
            ORDER BY conversations.last_message_at DESC,
                conversations.id DESC
            LIMIT {$limit}
        ");
        $statement->execute([
            'unread_user' => $userId,
            'other_user' => $userId,
            'user_id' => $userId,
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function sent(int $userId, int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));

        $statement = $this->connection()->prepare("
            SELECT
                messages.body,
                messages.sent_at,
                (SELECT COUNT(*) FROM message_attachments ma WHERE ma.message_id = messages.id) AS attachment_count,
                conversations.public_reference
                    AS conversation_reference,
                conversations.subject,
                conversations.status_code,
                (
                    SELECT GROUP_CONCAT(
                        COALESCE(
                            NULLIF(recipient_person.full_name, ''),
                            NULLIF(recipient_user.username, ''),
                            NULLIF(recipient_user.email, ''),
                            CONCAT('کاربر ', recipient_participant.user_id)
                        )
                        ORDER BY recipient_participant.id
                        SEPARATOR '، '
                    )
                    FROM message_conversation_participants
                        AS recipient_participant
                    INNER JOIN users AS recipient_user
                      ON recipient_user.id =
                         recipient_participant.user_id
                    LEFT JOIN persons AS recipient_person
                      ON recipient_person.id =
                         recipient_user.person_id
                    WHERE recipient_participant.conversation_id =
                        conversations.id
                      AND recipient_participant.user_id <> ?
                      AND recipient_participant.left_at IS NULL
                ) AS recipients_title
            FROM message_messages AS messages
            INNER JOIN message_conversations AS conversations
              ON conversations.id = messages.conversation_id
            WHERE messages.sender_user_id = ?
              AND messages.deleted_at IS NULL
            ORDER BY messages.sent_at DESC, messages.id DESC
            LIMIT {$limit}
        ");
        $statement->execute([$userId, $userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function thread(
        int $userId,
        string $reference
    ): ?array {
        $conversationId = $this->conversationIdForUser(
            $userId,
            $reference
        );

        if ($conversationId === null) {
            return null;
        }

        $conversation = $this->connection()->prepare("
            SELECT *
            FROM message_conversations
            WHERE id = ?
            LIMIT 1
        ");
        $conversation->execute([$conversationId]);
        $header = $conversation->fetch(PDO::FETCH_ASSOC);

        if (!$header) {
            return null;
        }

        $messages = $this->connection()->prepare("
            SELECT
                messages.public_reference,
                messages.sender_user_id,
                messages.body,
                messages.sent_at,
                COALESCE(
                    NULLIF(persons.full_name, ''),
                    NULLIF(users.username, ''),
                    NULLIF(users.email, ''),
                    'سامانه'
                ) AS sender_title
            FROM message_messages AS messages
            LEFT JOIN users ON users.id = messages.sender_user_id
            LEFT JOIN persons ON persons.id = users.person_id
            WHERE messages.conversation_id = ?
              AND messages.deleted_at IS NULL
            ORDER BY messages.sent_at ASC, messages.id ASC
        ");
        $messages->execute([$conversationId]);
        $items = $messages->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (Database::tableExists('message_attachments')) {
            $attachment = $this->connection()->prepare("SELECT public_reference, message_id, original_name, mime_type, size_bytes
                FROM message_attachments WHERE message_id IN (SELECT id FROM message_messages WHERE conversation_id = ?) ORDER BY id");
            $attachment->execute([$conversationId]);
            $byMessage = [];
            foreach ($attachment->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $byMessage[(int) $row['message_id']][] = $row;
            }
            $ids = $this->connection()->prepare('SELECT id, public_reference FROM message_messages WHERE conversation_id = ?');
            $ids->execute([$conversationId]);
            $referenceToId = array_column($ids->fetchAll(PDO::FETCH_ASSOC) ?: [], 'id', 'public_reference');
            foreach ($items as &$item) {
                $item['attachments'] = $byMessage[(int) ($referenceToId[$item['public_reference']] ?? 0)] ?? [];
            }
            unset($item);
        }

        $this->markRead($userId, $conversationId);

        return [
            'conversation' => $header,
            'messages' => $items,
        ];
    }

    public function setStatus(
        int $userId,
        string $reference,
        string $status
    ): void {
        if (!in_array($status, ['active', 'closed'], true)) {
            throw new RuntimeException('message_status_invalid');
        }

        $conversationId = $this->conversationIdForUser(
            $userId,
            $reference
        );

        if ($conversationId === null) {
            throw new RuntimeException('message_conversation_forbidden');
        }

        $statement = $this->connection()->prepare("
            UPDATE message_conversations
            SET status_code = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $statement->execute([$status, $conversationId]);
    }

    public function unreadCount(int $userId): int
    {
        if (!$this->available()) {
            return 0;
        }

        $statement = $this->connection()->prepare("
            SELECT COUNT(*)
            FROM message_conversation_participants AS participants
            INNER JOIN message_messages AS messages
              ON messages.conversation_id =
                 participants.conversation_id
            WHERE participants.user_id = ?
              AND participants.left_at IS NULL
              AND participants.archived_at IS NULL
              AND messages.deleted_at IS NULL
              AND messages.sender_user_id <> ?
              AND messages.id >
                COALESCE(participants.last_read_message_id, 0)
        ");
        $statement->execute([$userId, $userId]);

        return (int) $statement->fetchColumn();
    }

    public function addAttachment(string $messageReference, int $userId, array $file): void
    {
        $statement = $this->connection()->prepare("INSERT INTO message_attachments (
            public_reference, message_id, original_name, stored_name, storage_path,
            mime_type, extension, size_bytes, checksum_sha256, uploaded_by_user_id, created_at
        ) SELECT ?, messages.id, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP
          FROM message_messages AS messages WHERE messages.public_reference = ? LIMIT 1");
        $statement->execute([
            $file['public_reference'], $file['original_name'], $file['stored_name'],
            $file['storage_path'], $file['mime_type'], $file['extension'],
            $file['size_bytes'], $file['checksum_sha256'], $userId, $messageReference,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('message_attachment_message_not_found');
        }
    }

    public function attachmentForUser(int $userId, string $reference): ?array
    {
        $statement = $this->connection()->prepare("SELECT attachments.*
            FROM message_attachments AS attachments
            INNER JOIN message_messages AS messages ON messages.id = attachments.message_id
            INNER JOIN message_conversation_participants AS participants
              ON participants.conversation_id = messages.conversation_id
             AND participants.user_id = ? AND participants.left_at IS NULL
            WHERE attachments.public_reference = ? LIMIT 1");
        $statement->execute([$userId, $reference]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function settings(): array
    {
        if (!Database::tableExists('message_settings')) {
            return [];
        }
        return $this->connection()->query('SELECT setting_key, setting_value FROM message_settings')
            ->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    }

    public function saveSettings(int $userId, array $settings): void
    {
        $statement = $this->connection()->prepare('INSERT INTO message_settings
            (setting_key, setting_value, updated_by_user_id, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value),
            updated_by_user_id = VALUES(updated_by_user_id), updated_at = CURRENT_TIMESTAMP');
        foreach ($settings as $key => $value) {
            $statement->execute([(string) $key, (string) $value, $userId]);
        }
    }

    public function monitor(int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        return $this->connection()->query("SELECT conversations.public_reference,
            conversations.subject, conversations.status_code, conversations.last_message_at,
            COUNT(messages.id) AS message_count, COUNT(attachments.id) AS attachment_count,
            COALESCE(NULLIF(persons.full_name, ''), users.username, users.email) AS creator_title
            FROM message_conversations conversations
            INNER JOIN users ON users.id = conversations.created_by_user_id
            LEFT JOIN persons ON persons.id = users.person_id
            LEFT JOIN message_messages messages ON messages.conversation_id = conversations.id AND messages.deleted_at IS NULL
            LEFT JOIN message_attachments attachments ON attachments.message_id = messages.id
            GROUP BY conversations.id ORDER BY conversations.last_message_at DESC LIMIT {$limit}")
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function auditLog(int $limit = 100): array
    {
        if (!Database::tableExists('message_audit_events')) return [];
        $limit = max(1, min(200, $limit));
        return $this->connection()->query("SELECT audit.*, COALESCE(NULLIF(persons.full_name, ''), users.username, users.email) actor_title
            FROM message_audit_events audit INNER JOIN users ON users.id = audit.actor_user_id
            LEFT JOIN persons ON persons.id = users.person_id ORDER BY audit.occurred_at DESC, audit.id DESC LIMIT {$limit}")
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function monitoredThread(string $reference): ?array
    {
        $conversation = $this->connection()->prepare('SELECT * FROM message_conversations WHERE public_reference = ? LIMIT 1');
        $conversation->execute([$reference]);
        $header = $conversation->fetch(PDO::FETCH_ASSOC);
        if (!$header) {
            return null;
        }
        $messages = $this->connection()->prepare("SELECT messages.*, COALESCE(NULLIF(persons.full_name, ''), users.username, users.email, 'سامانه') sender_title
            FROM message_messages messages LEFT JOIN users ON users.id = messages.sender_user_id
            LEFT JOIN persons ON persons.id = users.person_id WHERE messages.conversation_id = ? AND messages.deleted_at IS NULL ORDER BY messages.id");
        $messages->execute([(int) $header['id']]);
        $items = $messages->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (Database::tableExists('message_attachments')) {
            $files = $this->connection()->prepare("SELECT attachments.public_reference, attachments.message_id,
                attachments.original_name, attachments.mime_type, attachments.size_bytes
                FROM message_attachments attachments INNER JOIN message_messages messages ON messages.id = attachments.message_id
                WHERE messages.conversation_id = ? ORDER BY attachments.id");
            $files->execute([(int) $header['id']]);
            $byMessage = [];
            foreach ($files->fetchAll(PDO::FETCH_ASSOC) ?: [] as $file) $byMessage[(int) $file['message_id']][] = $file;
            foreach ($items as &$item) $item['attachments'] = $byMessage[(int) $item['id']] ?? [];
            unset($item);
        }
        return ['conversation' => $header, 'messages' => $items];
    }

    public function audit(int $actorUserId, string $event, ?int $conversationId, ?int $messageId, ?int $attachmentId, string $reason): void
    {
        $statement = $this->connection()->prepare("INSERT INTO message_audit_events
            (public_reference, actor_user_id, conversation_id, message_id, attachment_id, event_code,
             reason, ip_address, user_agent, occurred_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
        $statement->execute(['msga_' . bin2hex(random_bytes(12)), $actorUserId, $conversationId,
            $messageId, $attachmentId, $event, $reason,
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500)]);
    }

    public function userLabel(int $userId): string
    {
        $statement = $this->connection()->prepare("
            SELECT COALESCE(
                NULLIF(persons.full_name, ''),
                NULLIF(users.username, ''),
                NULLIF(users.email, ''),
                CONCAT('کاربر ', users.id)
            )
            FROM users
            LEFT JOIN persons ON persons.id = users.person_id
            WHERE users.id = ?
            LIMIT 1
        ");
        $statement->execute([$userId]);
        $label = $statement->fetchColumn();

        return $label === false ? 'کاربر' : (string) $label;
    }

    private function conversationIdForUser(
        int $userId,
        string $reference
    ): ?int {
        $statement = $this->connection()->prepare("
            SELECT conversations.id
            FROM message_conversations AS conversations
            INNER JOIN message_conversation_participants AS participants
              ON participants.conversation_id = conversations.id
            WHERE conversations.public_reference = ?
              AND participants.user_id = ?
              AND participants.left_at IS NULL
            LIMIT 1
        ");
        $statement->execute([$reference, $userId]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function conversationStatus(int $conversationId): string
    {
        $statement = $this->connection()->prepare("
            SELECT status_code
            FROM message_conversations
            WHERE id = ?
            LIMIT 1
        ");
        $statement->execute([$conversationId]);

        return (string) $statement->fetchColumn();
    }

    private function markRead(int $userId, int $conversationId): void
    {
        $statement = $this->connection()->prepare("
            UPDATE message_conversation_participants
            SET last_read_message_id = (
                    SELECT MAX(messages.id)
                    FROM message_messages AS messages
                    WHERE messages.conversation_id = ?
                      AND messages.deleted_at IS NULL
                ),
                updated_at = CURRENT_TIMESTAMP
            WHERE conversation_id = ?
              AND user_id = ?
        ");
        $statement->execute([
            $conversationId,
            $conversationId,
            $userId,
        ]);
    }
}
