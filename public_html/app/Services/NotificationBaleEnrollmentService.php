<?php

namespace App\Services;

use App\Repositories\NotificationMessengerEnrollmentRepository;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class NotificationBaleEnrollmentService extends BaseService
{
    public function __construct(
        private ?NotificationMessengerEnrollmentRepository $repository = null,
        private ?NotificationGatewayService $gateway = null,
        private ?NotificationProviderRuntimeService $runtime = null,
        private ?NotificationProviderHttpTransport $http = null,
        private ?AuthorizationService $authorization = null
    ) {
        $this->repository ??=
            new NotificationMessengerEnrollmentRepository();
        $this->gateway ??=
            new NotificationGatewayService();
        $this->runtime ??=
            new NotificationProviderRuntimeService();
        $this->http ??=
            new NotificationProviderHttpTransport();
        $this->authorization ??=
            new AuthorizationService();
    }

    public function invite(
        int $actorUserId,
        array $userIds
    ): array {
        $this->authorize($actorUserId);

        $userIds = array_values(array_unique(
            array_filter(
                array_map('intval', $userIds),
                static fn (int $id): bool => $id > 0
            )
        ));

        if ($userIds === []) {
            throw new InvalidArgumentException(
                'notification_bale_invitation_recipient_required'
            );
        }

        if (count($userIds) > 30) {
            throw new InvalidArgumentException(
                'notification_bale_invitation_limit'
            );
        }

        $providers =
            $this->repository
                ->membershipAuthBaleProviders();

        if ($providers === []) {
            throw new RuntimeException(
                'notification_bale_auth_provider_unconfigured'
            );
        }

        if (count($providers) > 1) {
            throw new RuntimeException(
                'notification_bale_auth_provider_ambiguous'
            );
        }

        $provider = $providers[0];

        $configuration =
            $this->runtime->configuration($provider);
        $template = trim((string) (
            $configuration[
                'enrollment_link_template'
            ] ?? ''
        ));
        $username = ltrim(trim((string) (
            $configuration['bot_username'] ?? ''
        )), '@');

        if ($template === '' && $username !== '') {
            $template =
                'https://ble.ir/'
                . rawurlencode($username)
                . '?start={token}';
        }

        if (
            $template === ''
            || !str_contains($template, '{token}')
        ) {
            throw new RuntimeException(
                'notification_bale_enrollment_link_missing'
            );
        }

        $sent = 0;
        $failed = 0;
        $items = [];

        foreach (
            $this->repository
                ->mobileRecipients($userIds)
            as $recipient
        ) {
            $mobile = $this->normalizeMobile(
                (string) ($recipient['mobile'] ?? '')
            );

            if ($mobile === null) {
                $failed++;
                $items[] = [
                    'user_id' => (int) $recipient['id'],
                    'title' => (string) $recipient['title'],
                    'status_code' => 'skipped',
                    'error_code' =>
                        'recipient_mobile_missing',
                ];
                continue;
            }

            $token = bin2hex(random_bytes(24));
            $link = str_replace(
                '{token}',
                rawurlencode($token),
                $template
            );
            $this->assertEnrollmentLink($link);

            $enrollment =
                $this->repository->createEnrollment(
                    (int) $recipient['id'],
                    (int) $provider['id'],
                    $mobile,
                    hash('sha256', $token),
                    $actorUserId,
                    date(
                        'Y-m-d H:i:s',
                        time() + 86400
                    )
                );

            try {
                $message =
                    "برای فعال‌سازی اعلان‌های بله سامانه، "
                    . "لینک زیر را باز کنید و شماره همراه "
                    . "خود را تأیید کنید:\n"
                    . $link;

                $result = $this->gateway->sendDirect(
                    $actorUserId,
                    [
                        'channel_code' => 'sms',
                        'purpose_code' =>
                            'messenger_enrollment',
                        'scope_type' => 'global',
                        'scope_reference' => '*',
                        'destination' => $mobile,
                        'recipient_user_id' =>
                            (int) $recipient['id'],
                        'recipient_user_reference' =>
                            'user:' . (int) $recipient['id'],
                        'subject' => '',
                        'body' => $message,
                    ]
                );

                $this->repository->setInviteDelivery(
                    (int) $enrollment['id'],
                    (string) (
                        $result['delivery_reference'] ?? ''
                    )
                );

                $sent++;
                $items[] = [
                    'user_id' => (int) $recipient['id'],
                    'title' => (string) $recipient['title'],
                    'status_code' => 'sent',
                ];
            } catch (Throwable $exception) {
                $failed++;
                $error = $this->errorCode($exception);
                $this->repository->markInviteFailed(
                    (int) $enrollment['id'],
                    $error
                );
                $items[] = [
                    'user_id' => (int) $recipient['id'],
                    'title' => (string) $recipient['title'],
                    'status_code' => 'failed',
                    'error_code' => $error,
                ];
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'items' => $items,
        ];
    }

    public function handleWebhook(
        string $providerReference,
        array $update
    ): array {
        $provider =
            $this->repository->providerByReference(
                $providerReference
            );

        if (!is_array($provider)) {
            throw new RuntimeException(
                'notification_bale_provider_unavailable'
            );
        }

        $message = is_array(
            $update['message'] ?? null
        ) ? $update['message'] : [];

        if ($message === []) {
            return ['ok' => true, 'ignored' => true];
        }

        $chat = is_array(
            $message['chat'] ?? null
        ) ? $message['chat'] : [];
        $from = is_array(
            $message['from'] ?? null
        ) ? $message['from'] : [];
        $chatId = trim((string) (
            $chat['id'] ?? ''
        ));
        $externalUserId = trim((string) (
            $from['id'] ?? ''
        ));

        if (
            preg_match('/^-?[0-9]{1,30}$/', $chatId)
                !== 1
            || preg_match(
                '/^[0-9]{1,30}$/',
                $externalUserId
            ) !== 1
        ) {
            return ['ok' => true, 'ignored' => true];
        }

        $text = trim((string) (
            $message['text'] ?? ''
        ));

        if (
            preg_match(
                '/^\/start(?:\s+([A-Fa-f0-9]{48}))?$/',
                $text,
                $matches
            ) === 1
        ) {
            $token = (string) ($matches[1] ?? '');

            if ($token === '') {
                $this->sendText(
                    $provider,
                    $chatId,
                    'برای اتصال حساب بله، لینک فعال‌سازی '
                    . 'ارسال‌شده با پیامک را باز کنید.'
                );

                return ['ok' => true];
            }

            $enrollment =
                $this->repository->pendingByTokenHash(
                    (int) $provider['id'],
                    hash('sha256', $token)
                );

            if (!is_array($enrollment)) {
                $this->sendText(
                    $provider,
                    $chatId,
                    'لینک فعال‌سازی معتبر نیست یا منقضی شده است.'
                );

                return ['ok' => true];
            }

            $this->repository->markStarted(
                (int) $enrollment['id'],
                $chatId,
                $externalUserId,
                [
                    'first_name' => (string) (
                        $from['first_name'] ?? ''
                    ),
                    'last_name' => (string) (
                        $from['last_name'] ?? ''
                    ),
                    'username' => (string) (
                        $from['username'] ?? ''
                    ),
                ]
            );

            $this->sendText(
                $provider,
                $chatId,
                'برای تکمیل اتصال، دکمه زیر را بزنید و '
                . 'شماره همراه خود را ارسال کنید.',
                [
                    'keyboard' => [
                        [
                            [
                                'text' =>
                                    'تأیید و اشتراک شماره همراه من',
                                'request_contact' => true,
                            ],
                        ],
                    ],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                ]
            );

            return ['ok' => true];
        }

        $contact = is_array(
            $message['contact'] ?? null
        ) ? $message['contact'] : [];

        if ($contact === []) {
            return ['ok' => true, 'ignored' => true];
        }

        $enrollment =
            $this->repository->pendingByChat(
                (int) $provider['id'],
                $chatId
            );

        if (!is_array($enrollment)) {
            $this->sendText(
                $provider,
                $chatId,
                'ابتدا لینک فعال‌سازی ارسال‌شده با پیامک را باز کنید.'
            );

            return ['ok' => true];
        }

        $contactUserId = trim((string) (
            $contact['user_id'] ?? ''
        ));
        $mobile = $this->normalizeMobile(
            (string) (
                $contact['phone_number'] ?? ''
            )
        );

        if (
            $mobile === null
            || $mobile !== (string) (
                $enrollment['mobile_norm'] ?? ''
            )
            || (
                $contactUserId !== ''
                && $contactUserId !== $externalUserId
            )
        ) {
            $this->sendText(
                $provider,
                $chatId,
                'شماره ارسال‌شده با شماره ثبت‌شده در سامانه '
                . 'مطابقت ندارد.'
            );

            return ['ok' => true];
        }

        $displayName = trim(
            (string) ($from['first_name'] ?? '')
            . ' '
            . (string) ($from['last_name'] ?? '')
        );

        $this->repository->complete(
            $enrollment,
            $chatId,
            $externalUserId,
            (string) ($from['username'] ?? ''),
            $displayName
        );

        $this->sendText(
            $provider,
            $chatId,
            'اتصال بله با موفقیت انجام شد. از این پس '
            . 'اعلان‌های سامانه را در این گفتگو دریافت می‌کنید.',
            ['remove_keyboard' => true]
        );

        return ['ok' => true, 'verified' => true];
    }

    private function sendText(
        array $provider,
        string $chatId,
        string $text,
        array $replyMarkup = []
    ): void {
        $configuration =
            $this->runtime->configuration($provider);
        $secrets =
            $this->runtime->secrets($provider);
        $botToken = trim((string) (
            $secrets['bot_token'] ?? ''
        ));
        $apiBase = rtrim(trim((string) (
            $configuration['api_base']
                ?? 'https://tapi.bale.ai'
        )), '/');

        if ($botToken === '') {
            throw new RuntimeException(
                'notification_gateway_secret_unavailable'
            );
        }

        $this->runtime->assertHttpsEndpoint(
            $apiBase,
            ['tapi.bale.ai']
        );

        $payload = [
            'chat_id' => (int) $chatId,
            'text' => $text,
        ];

        if ($replyMarkup !== []) {
            $payload['reply_markup'] =
                $replyMarkup;
        }

        $response = $this->http->postJson(
            $apiBase
                . '/bot'
                . rawurlencode($botToken)
                . '/sendMessage',
            $payload,
            15,
            'IPKF-Bale-Enrollment/1.0'
        );

        if (
            (int) ($response['status_code'] ?? 0)
                < 200
            || (int) ($response['status_code'] ?? 0)
                >= 300
            || !is_array($response['json'] ?? null)
            || empty($response['json']['ok'])
        ) {
            throw new RuntimeException(
                'notification_gateway_provider_rejected'
            );
        }
    }

    private function normalizeMobile(
        string $mobile
    ): ?string {
        $mobile = strtr(trim($mobile), [
            '۰' => '0', '۱' => '1',
            '۲' => '2', '۳' => '3',
            '۴' => '4', '۵' => '5',
            '۶' => '6', '۷' => '7',
            '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1',
            '٢' => '2', '٣' => '3',
            '٤' => '4', '٥' => '5',
            '٦' => '6', '٧' => '7',
            '٨' => '8', '٩' => '9',
        ]);
        $mobile = preg_replace(
            '/[\s()-]+/',
            '',
            $mobile
        ) ?? '';

        if (str_starts_with($mobile, '0098')) {
            $mobile = '0' . substr($mobile, 4);
        } elseif (str_starts_with($mobile, '+98')) {
            $mobile = '0' . substr($mobile, 3);
        } elseif (
            str_starts_with($mobile, '98')
            && strlen($mobile) === 12
        ) {
            $mobile = '0' . substr($mobile, 2);
        }

        return preg_match(
            '/^09[0-9]{9}$/',
            $mobile
        ) === 1
            ? $mobile
            : null;
    }

    private function assertEnrollmentLink(
        string $link
    ): void {
        $parts = parse_url($link);
        $host = strtolower((string) (
            $parts['host'] ?? ''
        ));

        if (
            !is_array($parts)
            || strtolower((string) (
                $parts['scheme'] ?? ''
            )) !== 'https'
            || !in_array(
                $host,
                ['ble.ir', 'bale.ai'],
                true
            )
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new RuntimeException(
                'notification_bale_enrollment_link_invalid'
            );
        }
    }

    private function authorize(int $userId): void
    {
        if (
            $userId < 1
            || !$this->authorization->hasPermission(
                $userId,
                'notifications.send.manage'
            )
        ) {
            throw new RuntimeException(
                'notification_send_forbidden'
            );
        }
    }

    private function errorCode(
        Throwable $exception
    ): string {
        $message = trim($exception->getMessage());

        return $message !== ''
            ? mb_substr(
                $message,
                0,
                190,
                'UTF-8'
            )
            : 'notification_bale_invitation_failed';
    }
}
