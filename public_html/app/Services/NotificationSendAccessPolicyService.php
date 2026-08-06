<?php

namespace App\Services;

class NotificationSendAccessPolicyService extends BaseService
{
    public function __construct(
        private ?AuthorizationService $authorization = null
    ) {
        $this->authorization ??= new AuthorizationService();
    }

    public function resolve(int $userId): string
    {
        if (
            $this->authorization->hasPermission(
                $userId,
                'notifications.send.manage'
            )
            || $this->authorization->hasPermission(
                $userId,
                'notifications.send.direct'
            )
        ) {
            return 'direct';
        }

        if (
            $this->authorization->hasPermission(
                $userId,
                'notifications.send.view'
            )
            && $this->authorization->hasPermission(
                $userId,
                'notifications.send.request'
            )
        ) {
            return 'approval_required';
        }

        return 'hidden';
    }

    public function canSearch(int $userId): bool
    {
        return $this->authorization->hasPermission(
            $userId,
            'notifications.recipients.search'
        )
            || $this->authorization->hasPermission(
                $userId,
                'notifications.send.manage'
            );
    }

    public function canViewDetails(int $userId): bool
    {
        return $this->authorization->hasPermission(
            $userId,
            'notifications.recipients.details'
        )
            || $this->authorization->hasPermission(
                $userId,
                'notifications.send.manage'
            );
    }

    public function canUseManual(int $userId): bool
    {
        return $this->authorization->hasPermission(
            $userId,
            'notifications.manual_targets.use'
        )
            || $this->authorization->hasPermission(
                $userId,
                'notifications.send.manage'
            );
    }
}
