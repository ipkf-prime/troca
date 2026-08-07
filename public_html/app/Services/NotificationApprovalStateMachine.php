<?php

namespace App\Services;

use DomainException;

final class NotificationApprovalStateMachine
{
    public const DRAFT = 'draft';
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';
    public const DISPATCHING = 'dispatching';
    public const DISPATCHED = 'dispatched';
    public const PARTIALLY_DISPATCHED =
        'partially_dispatched';
    public const FAILED = 'failed';

    private const TRANSITIONS = [
        self::DRAFT => [
            self::PENDING,
            self::CANCELLED,
        ],
        self::PENDING => [
            self::APPROVED,
            self::REJECTED,
            self::CANCELLED,
            self::EXPIRED,
        ],
        self::APPROVED => [
            self::DISPATCHING,
        ],
        self::DISPATCHING => [
            self::DISPATCHED,
            self::PARTIALLY_DISPATCHED,
            self::FAILED,
        ],
        self::PARTIALLY_DISPATCHED => [
            self::DISPATCHING,
        ],
        self::FAILED => [
            self::DISPATCHING,
            self::CANCELLED,
        ],
        self::REJECTED => [],
        self::CANCELLED => [],
        self::EXPIRED => [],
        self::DISPATCHED => [],
    ];

    private const LABELS = [
        self::DRAFT => 'پیش‌نویس',
        self::PENDING => 'در انتظار تأیید',
        self::APPROVED => 'تأییدشده',
        self::REJECTED => 'ردشده',
        self::CANCELLED => 'لغوشده',
        self::EXPIRED => 'منقضی‌شده',
        self::DISPATCHING => 'در حال ارسال',
        self::DISPATCHED => 'ارسال‌شده',
        self::PARTIALLY_DISPATCHED =>
            'ارسال ناقص',
        self::FAILED => 'ناموفق',
    ];

    public function statuses(): array
    {
        return array_keys(self::TRANSITIONS);
    }

    public function label(string $status): string
    {
        $this->assertKnownStatus($status);

        return self::LABELS[$status];
    }

    public function canTransition(
        string $from,
        string $to
    ): bool {
        $this->assertKnownStatus($from);
        $this->assertKnownStatus($to);

        return in_array(
            $to,
            self::TRANSITIONS[$from],
            true
        );
    }

    public function assertTransition(
        string $from,
        string $to
    ): void {
        if (!$this->canTransition($from, $to)) {
            throw new DomainException(
                'notification_approval_transition_invalid'
            );
        }
    }

    public function isTerminal(string $status): bool
    {
        $this->assertKnownStatus($status);

        return self::TRANSITIONS[$status] === [];
    }

    public function isDecisionPending(
        string $status
    ): bool {
        $this->assertKnownStatus($status);

        return $status === self::PENDING;
    }

    public function isDispatchable(
        string $status
    ): bool {
        $this->assertKnownStatus($status);

        return in_array(
            $status,
            [
                self::APPROVED,
                self::PARTIALLY_DISPATCHED,
                self::FAILED,
            ],
            true
        );
    }

    private function assertKnownStatus(
        string $status
    ): void {
        if (!array_key_exists(
            $status,
            self::TRANSITIONS
        )) {
            throw new DomainException(
                'notification_approval_status_invalid'
            );
        }
    }
}
