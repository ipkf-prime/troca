<?php

namespace App\Services;

use IPKF\Support\Session;
use Throwable;

class InternalMessageLoginNotifierService extends BaseService
{
    public function notify(int $userId): void
    {
        try {
            // Direct message notifications are canonical; do not create a second
            // unread-on-login notification for the same underlying messages.
            return;
            $unread = (new InternalMessageService())
                ->unreadCount($userId);

            if ($unread < 1) {
                return;
            }

            (new NotificationPublisherService())->publish([
                'event_type' => 'messages.unread_on_login',
                'source_module' => 'communications',
                'source_entity_type' => 'user',
                'source_entity_reference' => (string) $userId,
                'template_code' => 'messages.unread_on_login',
                'title' => 'پیام خوانده‌نشده دارید',
                'body' => 'تعداد پیام‌های خوانده‌نشده: ' . $unread,
                'template_data' => [
                    'unread_count' => (string) $unread,
                ],
                'action_url' => '/admin/messages/inbox',
                'category_code' => 'messages',
                'priority_code' => 'high',
                'recipient_user_references' => [(string) $userId],
                'channels' => ['in_app'],
                'idempotency_key' => 'messages.login:'
                    . $userId
                    . ':'
                    . $unread,
            ]);

            (new NotificationOutboxProcessorService())
                ->process(10, 'login:message-reminder');

            Session::put('messages_unread_on_login', $unread);
        } catch (Throwable) {
            // Login must continue if notification storage is unavailable.
        }
    }
}
