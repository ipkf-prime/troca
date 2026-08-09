<?php

namespace App\Services;

use App\Repositories\NotificationApprovalAlertRepository;
use App\Support\AdminFormat;
use Throwable;

class NotificationApprovalAlertService extends BaseService
{
    private const DECIDE_PERMISSION =
        'notifications.approvals.decide';

    public function __construct(
        private ?NotificationApprovalAlertRepository $repository = null,
        private ?NotificationPublisherService $publisher = null,
        private ?NotificationOutboxProcessorService $outbox = null,
        private ?NotificationInboxService $inbox = null
    ) {
        $this->repository ??=
            new NotificationApprovalAlertRepository();

        $this->publisher ??=
            new NotificationPublisherService();

        $this->outbox ??=
            new NotificationOutboxProcessorService();

        $this->inbox ??=
            new NotificationInboxService();
    }

    public function notifyPending(
        int $requesterUserId,
        array $request,
        array $context = []
    ): void {
        $reference = trim(
            (string) (
                $request['public_reference']
                ?? ''
            )
        );

        if (!$this->validReference($reference)) {
            return;
        }

        try {
            $approverUserIds =
                $this->repository
                    ->approverUserIds(
                        self::DECIDE_PERMISSION
                    );

            if ($approverUserIds === []) {
                return;
            }

            $requesterTitle =
                $this->repository
                    ->userTitle($requesterUserId);

            $subject = trim(
                (string) (
                    $context['subject']
                    ?? ''
                )
            );

            $subjectLabel = $subject !== ''
                ? $subject
                : 'بدون موضوع';

            $targetCount = max(
                0,
                (int) (
                    $request['target_count']
                    ?? 0
                )
            );

            $channels = $this->channelLabels(
                is_array(
                    $context['channels']
                    ?? null
                )
                    ? $context['channels']
                    : []
            );

            $body =
                "یک درخواست ارسال اعلان نیازمند تأیید شماست."
                . "\nدرخواست‌دهنده: "
                . $requesterTitle
                . "\nموضوع: "
                . $subjectLabel
                . "\nگیرندگان: "
                . AdminFormat::digits($targetCount);

            if ($channels !== []) {
                $body .=
                    "\nکانال‌ها: "
                    . implode('، ', $channels);
            }

            $actionUrl =
                $this->actionUrl($reference);

            $this->publisher->publish([
                'event_type' =>
                    'notifications.approval.pending',

                'source_module' =>
                    'communications',

                'source_entity_type' =>
                    'notification_approval_request',

                'source_entity_reference' =>
                    $reference,

                'actor_user_reference' =>
                    (string) $requesterUserId,

                'title' =>
                    'درخواست جدید برای تأیید اعلان',

                'body' =>
                    $body,

                'action_url' =>
                    $actionUrl,

                'category_code' =>
                    'system',

                'priority_code' =>
                    'high',

                'recipient_user_references' =>
                    array_map(
                        'strval',
                        $approverUserIds
                    ),

                'channels' =>
                    ['in_app'],

                'idempotency_key' =>
                    'notifications.approval.pending:'
                    . $reference,
            ]);

            $this->outbox->process(
                20,
                'web:notification-approval'
            );
        } catch (Throwable) {
            /*
             * Approval request persistence must remain durable
             * even if the notification subsystem is unavailable.
             */
        }
    }

    public function markViewed(
        int $userId,
        string $reference
    ): void {
        if (
            $userId < 1
            || !$this->validReference($reference)
        ) {
            return;
        }

        $this->inbox->markActionRead(
            $userId,
            $this->actionUrl($reference)
        );
    }

    public function resolve(
        string $reference
    ): void {
        if (!$this->validReference($reference)) {
            return;
        }

        try {
            $this->repository
                ->completePendingOutboxForRequest(
                    $reference
                );

            $this->repository
                ->markActionReadForAll(
                    $this->actionUrl($reference)
                );
        } catch (Throwable) {
            // Decision persistence must not be rolled back.
        }
    }

    private function actionUrl(
        string $reference
    ): string {
        return '/admin/communications/settings'
            . '?section=approvals'
            . '&approval_reference='
            . rawurlencode($reference);
    }

    private function validReference(
        string $reference
    ): bool {
        return preg_match(
            '/^nar_[a-f0-9]{24}$/',
            trim($reference)
        ) === 1;
    }

    private function channelLabels(
        array $channels
    ): array {
        $result = [];

        foreach ($channels as $channel) {
            $code = strtolower(
                trim((string) $channel)
            );

            $label = match ($code) {
                'messenger' => 'پیام‌رسان',
                'sms' => 'پیامک',
                'email' => 'ایمیل',
                default => '',
            };

            if ($label !== '') {
                $result[$code] = $label;
            }
        }

        return array_values($result);
    }
}
