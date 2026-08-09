<?php

namespace App\Services;

use App\Repositories\NotificationApprovalRepository;
use Throwable;

class NavigationBadgeResolverService extends BaseService
{
    public function value(string $source, int $userId): string
    {
        try {
            $count = match ($source) {
                'messages_unread_count' =>
                    (new InternalMessageService())->unreadCount($userId),
                'notifications_unread_count' =>
                    (new NotificationInboxService())->unreadCount($userId),
                'notification_approval_pending_count' =>
                    (int) (
                        (new NotificationApprovalRepository())
                            ->approvalSummary()['pending']
                        ?? 0
                    ),
                'communications_unread_total' =>
                    (new InternalMessageService())->unreadCount($userId)
                    + (new NotificationInboxService())->unreadCount($userId),
                default => 0,
            };

            return $count > 0
                ? \App\Support\AdminFormat::digits($count)
                : '';
        } catch (Throwable) {
            return '';
        }
    }
}
