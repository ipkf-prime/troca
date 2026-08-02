<?php

namespace App\Services;

use App\Repositories\InternalMessageRepository;
use InvalidArgumentException;
use Throwable;

class InternalMessageService extends BaseService
{
    public function __construct(
        private ?InternalMessageRepository $messages = null,
        private ?NotificationPublisherService $publisher = null,
        private ?NotificationOutboxProcessorService $outbox = null,
        private ?NotificationInboxService $notificationInbox = null
    ) {
        $this->messages ??= new InternalMessageRepository();
        $this->publisher ??= new NotificationPublisherService();
        $this->outbox ??= new NotificationOutboxProcessorService();
        $this->notificationInbox ??= new NotificationInboxService();
    }

    public function composePage(int $userId): array
    {
        return [
            'recipients' => $this->messages->recipientOptions($userId),
        ];
    }

    public function inbox(int $userId): array
    {
        return [
            'items' => $this->messages->inbox($userId),
            'unread_count' => $this->messages->unreadCount($userId),
        ];
    }

    public function sent(int $userId): array
    {
        return ['items' => $this->messages->sent($userId)];
    }

    public function thread(
        int $userId,
        string $reference
    ): ?array {
        $thread = $this->messages->thread($userId, $reference);

        if ($thread !== null) {
            $this->notificationInbox->markActionRead(
                $userId,
                '/admin/messages/thread/' . rawurlencode($reference)
            );
        }

        return $thread;
    }

    public function unreadCount(int $userId): int
    {
        try {
            return $this->messages->unreadCount($userId);
        } catch (Throwable) {
            return 0;
        }
    }

    public function send(int $senderUserId, array $input): array
    {
        $recipientUserId = (int) (
            $input['recipient_user_id'] ?? 0
        );
        $subject = trim((string) ($input['subject'] ?? ''));
        $body = trim((string) ($input['body'] ?? ''));

        if (
            $recipientUserId < 1
            || !$this->messages->recipientAllowed(
                $senderUserId,
                $recipientUserId
            )
        ) {
            throw new InvalidArgumentException(
                'message_recipient_not_allowed'
            );
        }

        if ($subject === '') {
            throw new InvalidArgumentException(
                'message_subject_required'
            );
        }

        if ($body === '') {
            throw new InvalidArgumentException(
                'message_body_required'
            );
        }

        $result = $this->messages->createConversation(
            $senderUserId,
            $recipientUserId,
            mb_substr($subject, 0, 300, 'UTF-8'),
            mb_substr($body, 0, 20000, 'UTF-8')
        );

        $this->notify(
            $senderUserId,
            $result['recipient_user_ids'],
            $result,
            $subject
        );

        return $result;
    }

    public function reply(
        int $userId,
        string $conversationReference,
        string $body
    ): array {
        $body = trim($body);

        if ($body === '') {
            throw new InvalidArgumentException(
                'message_body_required'
            );
        }

        $result = $this->messages->reply(
            $userId,
            $conversationReference,
            mb_substr($body, 0, 20000, 'UTF-8')
        );

        $thread = $this->messages->thread(
            $userId,
            $conversationReference
        );
        $subject = (string) (
            $thread['conversation']['subject']
            ?? 'پاسخ جدید'
        );

        $this->notify(
            $userId,
            $result['recipient_user_ids'] ?? [],
            $result,
            $subject
        );

        return $result;
    }

    public function close(
        int $userId,
        string $conversationReference
    ): void {
        $this->messages->setStatus(
            $userId,
            $conversationReference,
            'closed'
        );
    }

    public function reopen(
        int $userId,
        string $conversationReference
    ): void {
        $this->messages->setStatus(
            $userId,
            $conversationReference,
            'active'
        );
    }

    private function notify(
        int $senderUserId,
        array $recipientUserIds,
        array $message,
        string $subject
    ): void {
        if ($recipientUserIds === []) {
            return;
        }

        try {
            $senderName = $this->messages->userLabel($senderUserId);
            $actionUrl = '/admin/messages/thread/'
                . rawurlencode(
                    (string) $message['conversation_reference']
                );

            $this->publisher->publish([
                'event_type' => 'messages.new',
                'source_module' => 'communications',
                'source_entity_type' => 'message',
                'source_entity_reference' =>
                    (string) $message['message_reference'],
                'actor_user_reference' => (string) $senderUserId,
                'template_code' => 'messages.new',
                'title' => 'پیام جدید از ' . $senderName,
                'body' => $subject,
                'template_data' => [
                    'sender_name' => $senderName,
                    'subject' => $subject,
                    'action_url' => $actionUrl,
                ],
                'action_url' => $actionUrl,
                'category_code' => 'messages',
                'priority_code' => 'normal',
                'recipient_user_references' =>
                    array_map('strval', $recipientUserIds),
                'channels' => ['in_app'],
                'idempotency_key' => 'messages.new:'
                    . (string) $message['message_reference'],
            ]);

            $this->outbox->process(
                10,
                'web:internal-message'
            );
        } catch (Throwable) {
            // Message persistence must not be rolled back.
        }
    }
}
