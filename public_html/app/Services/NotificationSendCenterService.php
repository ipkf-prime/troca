<?php

namespace App\Services;

use App\Repositories\NotificationSendCenterRepository;
use InvalidArgumentException;
use IPKF\Support\Session;
use RuntimeException;
use Throwable;

class NotificationSendCenterService extends BaseService
{
    private const CHANNELS = [
        'email',
        'sms',
        'messenger',
    ];

    private const IMMEDIATE_LIMIT = 30;

    public function __construct(
        private ?NotificationSendCenterRepository $repository = null,
        private ?NotificationGatewayService $gateway = null,
        private ?AuthorizationService $authorization = null
    ) {
        $this->repository ??=
            new NotificationSendCenterRepository();
        $this->gateway ??=
            new NotificationGatewayService();
        $this->authorization ??=
            new AuthorizationService();
    }

    public function page(int $userId): array
    {
        $this->authorize($userId);

        $recipients =
            $this->repository->recipientOptions();

        $organizations = [];
        $roles = [];
        $cities = [];

        foreach ($recipients as $recipient) {
            $organization = trim((string) (
                $recipient['organization_title'] ?? ''
            ));

            if ($organization !== '') {
                $organizations[$organization] =
                    $organization;
            }

            $city = trim((string) (
                $recipient['city_title'] ?? ''
            ));

            if ($city !== '') {
                $cities[$city] = $city;
            }

            foreach (
                preg_split(
                    '/\s*،\s*/u',
                    (string) (
                        $recipient['role_titles'] ?? ''
                    ),
                    -1,
                    PREG_SPLIT_NO_EMPTY
                ) ?: []
                as $role
            ) {
                $roles[$role] = $role;
            }
        }

        ksort($organizations, SORT_NATURAL);
        ksort($roles, SORT_NATURAL);
        ksort($cities, SORT_NATURAL);

        return [
            'recipients' => $recipients,
            'organizations' =>
                array_values($organizations),
            'roles' => array_values($roles),
            'cities' => array_values($cities),
            'immediate_limit' =>
                self::IMMEDIATE_LIMIT,
            'result' =>
                $this->consumeResult($userId),
        ];
    }

    public function send(
        int $actorUserId,
        array $input
    ): array {
        $this->authorize($actorUserId);

        $messageType = strtolower(trim(
            (string) (
                $input['message_type_code']
                    ?? 'text'
            )
        ));

        if (!in_array(
            $messageType,
            ['text', 'multimedia'],
            true
        )) {
            throw new InvalidArgumentException(
                'notification_send_message_type_invalid'
            );
        }

        if ($messageType === 'multimedia') {
            throw new InvalidArgumentException(
                'notification_send_multimedia_delivery_pending'
            );
        }

        $channels = array_values(array_unique(
            array_filter(
                array_map(
                    static fn (mixed $channel): string =>
                        strtolower(trim(
                            (string) $channel
                        )),
                    is_array(
                        $input['channels'] ?? null
                    ) ? $input['channels'] : []
                ),
                static fn (string $channel): bool =>
                    in_array(
                        $channel,
                        self::CHANNELS,
                        true
                    )
            )
        ));

        if ($channels === []) {
            throw new InvalidArgumentException(
                'notification_send_channel_required'
            );
        }

        if (empty($input['confirm_dispatch'])) {
            throw new InvalidArgumentException(
                'notification_send_confirmation_required'
            );
        }

        $subject = trim((string) (
            $input['subject'] ?? ''
        ));
        $body = trim((string) (
            $input['body'] ?? ''
        ));

        if (
            in_array('email', $channels, true)
            && (
                $subject === ''
                || mb_strlen(
                    $subject,
                    'UTF-8'
                ) > 190
            )
        ) {
            throw new InvalidArgumentException(
                'notification_send_subject_invalid'
            );
        }

        if (
            mb_strlen($subject, 'UTF-8') > 190
            || mb_strlen($body, 'UTF-8') < 1
            || mb_strlen($body, 'UTF-8') > 10000
        ) {
            throw new InvalidArgumentException(
                'notification_send_body_invalid'
            );
        }

        $userIds = array_values(array_unique(
            array_filter(
                array_map(
                    'intval',
                    is_array(
                        $input[
                            'recipient_user_ids'
                        ] ?? null
                    )
                        ? $input[
                            'recipient_user_ids'
                        ]
                        : []
                ),
                static fn (int $id): bool =>
                    $id > 0
            )
        ));

        if (count($userIds) > 100) {
            throw new InvalidArgumentException(
                'notification_send_recipient_limit'
            );
        }

        $targets = [];
        $skipped = [];

        foreach (
            $this->repository
                ->destinationsForUsers($userIds)
            as $recipient
        ) {
            foreach ($channels as $channel) {
                $destination = $this->normalize(
                    $channel,
                    (string) (
                        $recipient[
                            $channel
                            . '_destination'
                        ] ?? ''
                    )
                );

                if ($destination === null) {
                    $skipped[] = [
                        'channel_code' => $channel,
                        'recipient_title' =>
                            (string) (
                                $recipient['title']
                                ?? ''
                            ),
                        'destination_masked' => '—',
                        'status_code' => 'skipped',
                        'error_code' =>
                            'recipient_destination_missing',
                    ];
                    continue;
                }

                $this->addTarget(
                    $targets,
                    $channel,
                    $destination,
                    (int) $recipient['id'],
                    (string) (
                        $recipient['title'] ?? ''
                    )
                );
            }
        }

        foreach ($channels as $channel) {
            foreach (
                $this->manualDestinations(
                    $input[
                        'manual_' . $channel
                    ] ?? ''
                )
                as $rawDestination
            ) {
                $destination = $this->normalize(
                    $channel,
                    $rawDestination
                );

                if ($destination === null) {
                    $skipped[] = [
                        'channel_code' => $channel,
                        'recipient_title' =>
                            'مقصد دستی',
                        'destination_masked' =>
                            $this->mask(
                                $channel,
                                $rawDestination
                            ),
                        'status_code' => 'skipped',
                        'error_code' =>
                            'manual_destination_invalid',
                    ];
                    continue;
                }

                $this->addTarget(
                    $targets,
                    $channel,
                    $destination,
                    null,
                    'مقصد دستی'
                );
            }
        }

        if ($targets === []) {
            throw new InvalidArgumentException(
                'notification_send_destination_required'
            );
        }

        if (
            count($targets)
            > self::IMMEDIATE_LIMIT
        ) {
            throw new InvalidArgumentException(
                'notification_send_immediate_limit_exceeded'
            );
        }

        $reference =
            'nsc_' . bin2hex(random_bytes(12));
        $items = [];
        $sent = 0;
        $failed = 0;
        $channelSummary = [];

        foreach ($targets as $target) {
            $channel =
                (string) $target['channel_code'];

            $channelSummary[$channel] ??= [
                'total' => 0,
                'sent' => 0,
                'failed' => 0,
            ];
            $channelSummary[$channel]['total']++;

            try {
                $result = $this->gateway->sendDirect(
                    $actorUserId,
                    [
                        'channel_code' => $channel,
                        'purpose_code' => 'general',
                        'scope_type' => 'global',
                        'scope_reference' => '*',
                        'destination' =>
                            $target['destination'],
                        'recipient_user_id' =>
                            $target['user_id'],
                        'recipient_user_reference' =>
                            $target['user_id'] === null
                                ? ''
                                : 'user:'
                                    . $target['user_id'],
                        'subject' => $subject,
                        'body' => $body,
                    ]
                );

                $sent++;
                $channelSummary[$channel]['sent']++;

                $items[] = [
                    'channel_code' => $channel,
                    'recipient_title' =>
                        $target['recipient_title'],
                    'destination_masked' =>
                        $this->mask(
                            $channel,
                            $target['destination']
                        ),
                    'status_code' => 'sent',
                    'provider_title' =>
                        (string) (
                            $result[
                                'provider_title'
                            ] ?? ''
                        ),
                    'provider_type_code' =>
                        (string) (
                            $result[
                                'provider_type_code'
                            ] ?? ''
                        ),
                    'delivery_reference' =>
                        (string) (
                            $result[
                                'delivery_reference'
                            ] ?? ''
                        ),
                    'fallback_used' =>
                        !empty(
                            $result[
                                'fallback_used'
                            ]
                        ),
                    'error_code' => '',
                ];
            } catch (Throwable $exception) {
                $failed++;
                $channelSummary[$channel]['failed']++;

                $items[] = [
                    'channel_code' => $channel,
                    'recipient_title' =>
                        $target['recipient_title'],
                    'destination_masked' =>
                        $this->mask(
                            $channel,
                            $target['destination']
                        ),
                    'status_code' => 'failed',
                    'provider_title' => '',
                    'provider_type_code' => '',
                    'delivery_reference' => '',
                    'fallback_used' => false,
                    'error_code' =>
                        $this->errorCode($exception),
                ];
            }
        }

        foreach ($skipped as $item) {
            $channel =
                (string) $item['channel_code'];
            $channelSummary[$channel] ??= [
                'total' => 0,
                'sent' => 0,
                'failed' => 0,
            ];
        }

        return [
            'public_reference' => $reference,
            'created_at' => date(
                'Y-m-d H:i:s'
            ),
            'total' => count($targets),
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => count($skipped),
            'channels' => $channelSummary,
            'items' => array_merge(
                $items,
                $skipped
            ),
        ];
    }

    public function storeResult(
        int $userId,
        array $result
    ): void {
        Session::put(
            $this->resultKey($userId),
            $result
        );
    }

    private function consumeResult(
        int $userId
    ): array {
        $key = $this->resultKey($userId);
        $result = Session::get($key, []);
        Session::forget($key);

        return is_array($result)
            ? $result
            : [];
    }

    private function resultKey(int $userId): string
    {
        return 'notification_send_center_result_'
            . $userId;
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

    private function addTarget(
        array &$targets,
        string $channel,
        string $destination,
        ?int $userId,
        string $recipientTitle
    ): void {
        $key = $channel
            . ':'
            . strtolower($destination);

        if (isset($targets[$key])) {
            return;
        }

        $targets[$key] = [
            'channel_code' => $channel,
            'destination' => $destination,
            'user_id' => $userId,
            'recipient_title' =>
                $recipientTitle !== ''
                    ? $recipientTitle
                    : 'گیرنده',
        ];
    }

    private function manualDestinations(
        mixed $value
    ): array {
        return array_values(array_unique(
            array_filter(
                array_map(
                    'trim',
                    preg_split(
                        '/[\r\n,;،]+/u',
                        (string) $value
                    ) ?: []
                ),
                static fn (string $item): bool =>
                    $item !== ''
            )
        ));
    }

    private function normalize(
        string $channel,
        string $destination
    ): ?string {
        $destination = trim(
            $this->latinDigits($destination)
        );

        if ($channel === 'email') {
            $destination = strtolower(
                $destination
            );

            return filter_var(
                $destination,
                FILTER_VALIDATE_EMAIL
            ) !== false
                ? $destination
                : null;
        }

        if ($channel === 'sms') {
            $destination = preg_replace(
                '/[\s()-]+/',
                '',
                $destination
            ) ?? '';

            if (str_starts_with(
                $destination,
                '0098'
            )) {
                $destination =
                    '0' . substr($destination, 4);
            } elseif (str_starts_with(
                $destination,
                '+98'
            )) {
                $destination =
                    '0' . substr($destination, 3);
            } elseif (
                str_starts_with(
                    $destination,
                    '98'
                )
                && strlen($destination) === 12
            ) {
                $destination =
                    '0' . substr($destination, 2);
            }

            return preg_match(
                '/^09[0-9]{9}$/',
                $destination
            ) === 1
                ? $destination
                : null;
        }

        if ($channel === 'messenger') {
            return preg_match(
                '/^-?[0-9]{5,30}$/',
                $destination
            ) === 1
                ? $destination
                : null;
        }

        return null;
    }

    private function latinDigits(
        string $value
    ): string {
        return strtr(
            $value,
            [
                '۰' => '0',
                '۱' => '1',
                '۲' => '2',
                '۳' => '3',
                '۴' => '4',
                '۵' => '5',
                '۶' => '6',
                '۷' => '7',
                '۸' => '8',
                '۹' => '9',
                '٠' => '0',
                '١' => '1',
                '٢' => '2',
                '٣' => '3',
                '٤' => '4',
                '٥' => '5',
                '٦' => '6',
                '٧' => '7',
                '٨' => '8',
                '٩' => '9',
            ]
        );
    }

    private function mask(
        string $channel,
        string $destination
    ): string {
        $destination = trim($destination);

        if (
            $channel === 'email'
            && str_contains(
                $destination,
                '@'
            )
        ) {
            [$local, $domain] = explode(
                '@',
                $destination,
                2
            );

            return mb_substr(
                $local,
                0,
                2,
                'UTF-8'
            ) . '***@' . $domain;
        }

        $length = mb_strlen(
            $destination,
            'UTF-8'
        );

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat(
            '*',
            max(3, $length - 4)
        ) . mb_substr(
            $destination,
            -4,
            null,
            'UTF-8'
        );
    }

    private function errorCode(
        Throwable $exception
    ): string {
        $current = $exception;
        $message = '';

        for ($level = 0; $level < 8; $level++) {
            $candidate = trim(
                $current->getMessage()
            );

            if ($candidate !== '') {
                $message = $candidate;
            }

            $previous =
                $current->getPrevious();

            if (!$previous instanceof Throwable) {
                break;
            }

            $current = $previous;
        }

        return $message !== ''
            ? mb_substr(
                $message,
                0,
                190,
                'UTF-8'
            )
            : 'notification_send_failed';
    }
}
