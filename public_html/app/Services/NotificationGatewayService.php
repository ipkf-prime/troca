<?php

namespace App\Services;

use App\Repositories\NotificationGatewayRepository;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class NotificationGatewayService extends BaseService
{
    private const CHANNELS = [
        'email',
        'sms',
        'messenger',
    ];

    public function __construct(
        private ?NotificationProviderResolver $resolver = null,
        private ?NotificationProviderRuntimeService $runtime = null,
        private ?NotificationGatewayAdapterRegistry $adapters = null,
        private ?NotificationGatewayRepository $repository = null
    ) {
        $this->resolver ??=
            new NotificationProviderResolver();
        $this->runtime ??=
            new NotificationProviderRuntimeService();
        $this->adapters ??=
            new NotificationGatewayAdapterRegistry();
        $this->repository ??=
            new NotificationGatewayRepository();
    }

    public function sendDirect(
        int $actorUserId,
        array $input
    ): array {
        if ($actorUserId < 1) {
            throw new InvalidArgumentException(
                'notification_gateway_actor_invalid'
            );
        }

        $channel = strtolower(trim(
            (string) ($input['channel_code'] ?? '')
        ));
        $purpose = strtolower(trim(
            (string) (
                $input['purpose_code'] ?? 'general'
            )
        ));
        $destination = trim(
            (string) ($input['destination'] ?? '')
        );
        $subject = trim(
            (string) ($input['subject'] ?? '')
        );
        $body = trim(
            (string) ($input['body'] ?? '')
        );
        $messageType = strtolower(trim(
            (string) (
                $input['message_type_code']
                ?? 'text'
            )
        ));
        $mediaAssets = array_values(array_filter(
            is_array($input['media_assets'] ?? null)
                ? $input['media_assets']
                : [],
            static fn (mixed $asset): bool =>
                is_array($asset)
                && (int) ($asset['id'] ?? 0) > 0
                && is_readable((string) (
                    $asset['storage_path'] ?? ''
                ))
        ));
        $recipientUserId = (int) (
            $input['recipient_user_id'] ?? 0
        );
        $recipientUserReference = trim(
            (string) (
                $input[
                    'recipient_user_reference'
                ] ?? ''
            )
        );

        $this->validate(
            $channel,
            $purpose,
            $destination,
            $subject,
            $body
        );

        /*
         * SMS_POLICY_GATEWAY_GATE_V1
         *
         * A synchronous SMS outside the configured
         * window is rejected before a delivery row
         * or provider attempt is created.
         */
        if ($channel === 'sms') {
            $policy =
                (new SmsDeliveryPolicyService())
                    ->decision();

            if (!($policy['allowed'] ?? false)) {
                throw new RuntimeException(
                    'notification_gateway_sms_window_closed'
                );
            }
        }

        $candidates = $this->resolver->resolve(
            $channel,
            $purpose,
            (string) (
                $input['scope_type'] ?? 'global'
            ),
            (string) (
                $input['scope_reference'] ?? '*'
            )
        );

        $tracking =
            $this->repository->createDirectDelivery(
                $actorUserId,
                $channel,
                $purpose,
                $destination,
                $subject,
                $body,
                count($candidates),
                $recipientUserId > 0
                    ? $recipientUserId
                    : null,
                $recipientUserReference,
                $messageType,
                $mediaAssets
            );

        if ($candidates === []) {
            $this->repository->failWithoutProvider(
                (int) $tracking['delivery_id'],
                'notification_gateway_provider_unavailable'
            );

            throw new RuntimeException(
                'notification_gateway_provider_unavailable'
            );
        }

        $lastIndex = count($candidates) - 1;

        foreach ($candidates as $index => $candidate) {
            $instance = null;
            $attemptNumber = 0;

            try {
                $instance = $this->runtime->instance(
                    (string) (
                        $candidate[
                            'public_reference'
                        ] ?? ''
                    )
                );

                $attemptNumber =
                    $this->repository->beginAttempt(
                        (int) $tracking['delivery_id'],
                        $instance
                    );

                $adapter =
                    $this->adapters->adapter($instance);

                $result = $adapter->send(
                    $instance,
                    [
                        'destination' => $destination,
                        'subject' => $subject,
                        'body' => $body,
                        'purpose_code' => $purpose,
                        'request_reference' =>
                            $tracking['request_reference'],
                        'message_type_code' =>
                            $messageType,
                        'media_assets' =>
                            $mediaAssets,
                    ]
                );

                $this->repository->completeSuccess(
                    (int) $tracking['delivery_id'],
                    $attemptNumber,
                    $instance,
                    $result
                );

                return $tracking + [
                    'status_code' =>
                        'notification_gateway_sent',
                    'channel_code' => $channel,
                    'purpose_code' => $purpose,
                    'provider_instance_reference' =>
                        (string) (
                            $instance[
                                'public_reference'
                            ] ?? ''
                        ),
                    'provider_title' => (string) (
                        $instance['title'] ?? ''
                    ),
                    'provider_type_code' => (string) (
                        $instance[
                            'provider_type_code'
                        ] ?? ''
                    ),
                    'provider_message_reference' =>
                        (string) (
                            $result[
                                'provider_message_reference'
                            ] ?? ''
                        ),
                    'attempt_count' => $index + 1,
                    'fallback_used' => $index > 0,
                    'media_count' => count(
                        $mediaAssets
                    ),
                ];
            } catch (Throwable $exception) {
                $errorCode =
                    $this->errorCode($exception);
                $final = $index === $lastIndex;

                if (
                    is_array($instance)
                    && $attemptNumber > 0
                ) {
                    $this->repository->completeFailure(
                        (int) $tracking['delivery_id'],
                        $attemptNumber,
                        $instance,
                        $errorCode,
                        $final
                    );
                } elseif ($final) {
                    $this->repository
                        ->failWithoutProvider(
                            (int) $tracking[
                                'delivery_id'
                            ],
                            $errorCode
                        );
                }

                if ($final) {
                    throw new RuntimeException(
                        'notification_gateway_all_providers_failed',
                        0,
                        $exception
                    );
                }
            }
        }

        throw new RuntimeException(
            'notification_gateway_all_providers_failed'
        );
    }

    private function validate(
        string $channel,
        string $purpose,
        string $destination,
        string $subject,
        string $body
    ): void {
        if (!in_array(
            $channel,
            self::CHANNELS,
            true
        )) {
            throw new InvalidArgumentException(
                'notification_gateway_channel_invalid'
            );
        }

        if (
            preg_match(
                '/^[a-z][a-z0-9._-]{1,59}$/',
                $purpose
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'notification_gateway_purpose_invalid'
            );
        }

        if (
            $destination === ''
            || mb_strlen(
                $destination,
                'UTF-8'
            ) > 500
            || str_contains($destination, "\n")
            || str_contains($destination, "\r")
        ) {
            throw new InvalidArgumentException(
                'notification_gateway_destination_invalid'
            );
        }

        if (
            mb_strlen($subject, 'UTF-8') > 190
            || mb_strlen($body, 'UTF-8') < 1
            || mb_strlen($body, 'UTF-8') > 10000
        ) {
            throw new InvalidArgumentException(
                'notification_gateway_message_invalid'
            );
        }

        if (
            $channel === 'email'
            && $subject === ''
        ) {
            throw new InvalidArgumentException(
                'notification_gateway_message_invalid'
            );
        }
    }

    private function errorCode(
        Throwable $exception
    ): string {
        $code = trim($exception->getMessage());

        if (
            $code === ''
            || !str_starts_with(
                $code,
                'notification_gateway_'
            )
        ) {
            return
                'notification_gateway_provider_rejected';
        }

        return $code;
    }
}
