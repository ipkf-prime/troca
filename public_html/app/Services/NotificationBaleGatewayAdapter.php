<?php

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

class NotificationBaleGatewayAdapter extends BaseService implements
    NotificationGatewayAdapterInterface
{
    public function __construct(
        private ?NotificationProviderRuntimeService $runtime = null,
        private ?NotificationProviderHttpTransport $http = null
    ) {
        $this->runtime ??=
            new NotificationProviderRuntimeService();
        $this->http ??=
            new NotificationProviderHttpTransport();
    }

    public function supports(array $instance): bool
    {
        return
            (string) (
                $instance['provider_type_code'] ?? ''
            ) === 'bale_bot'
            && (string) (
                $instance['channel_code'] ?? ''
            ) === 'messenger';
    }

    public function send(
        array $instance,
        array $message
    ): array {
        $configuration =
            $this->runtime->configuration($instance);
        $secrets = $this->runtime->secrets($instance);

        $botToken = trim(
            (string) ($secrets['bot_token'] ?? '')
        );
        $chatId = trim(
            (string) ($message['destination'] ?? '')
        );
        $body = trim(
            (string) ($message['body'] ?? '')
        );

        if ($botToken === '') {
            throw new InvalidArgumentException(
                'notification_gateway_secret_unavailable'
            );
        }

        if (
            preg_match(
                '/^-?[0-9]{1,20}$/',
                $chatId
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'notification_gateway_destination_invalid'
            );
        }

        if (
            mb_strlen($body, 'UTF-8') < 1
            || mb_strlen($body, 'UTF-8') > 4096
        ) {
            throw new InvalidArgumentException(
                'notification_gateway_message_invalid'
            );
        }

        $apiBase = rtrim(
            trim(
                (string) (
                    $configuration['api_base']
                    ?? 'https://tapi.bale.ai'
                )
            ),
            '/'
        );

        if ($apiBase === '') {
            $apiBase = 'https://tapi.bale.ai';
        }

        $this->runtime->assertHttpsEndpoint(
            $apiBase,
            ['tapi.bale.ai']
        );

        $endpoint =
            $apiBase
            . '/bot'
            . rawurlencode($botToken)
            . '/sendMessage';

        $payload = [
            'chat_id' => (int) $chatId,
            'text' => $body,
        ];

        $parseMode = trim(
            (string) (
                $configuration['parse_mode'] ?? ''
            )
        );

        if (
            $parseMode !== ''
            && preg_match(
                '/^[A-Za-z0-9_]{1,30}$/',
                $parseMode
            ) === 1
        ) {
            $payload['parse_mode'] = $parseMode;
        }

        try {
            $response = $this->http->postJson(
                $endpoint,
                $payload,
                15,
                'IPKF-Notification-Gateway/1.0'
            );
            $json = $response['json'] ?? null;

            if (
                (int) ($response['status_code'] ?? 0) < 200
                || (int) (
                    $response['status_code'] ?? 0
                ) >= 300
                || !is_array($json)
                || empty($json['ok'])
                || !is_array($json['result'] ?? null)
            ) {
                throw new RuntimeException(
                    'notification_gateway_provider_rejected'
                );
            }

            return [
                'provider_message_reference' =>
                    (string) (
                        $json['result']['message_id'] ?? ''
                    ),
                'response_code' => (string) (
                    $response['status_code'] ?? ''
                ),
                'response_message' => 'bale_accepted',
                'duration_ms' => (int) (
                    $response['duration_ms'] ?? 0
                ),
                'metadata' => [
                    'transport' => 'bale',
                    'target_fingerprint' => substr(
                        hash('sha256', $chatId),
                        0,
                        16
                    ),
                ],
            ];
        } catch (Throwable $exception) {
            throw $this->gatewayException($exception);
        }
    }

    private function gatewayException(
        Throwable $exception
    ): RuntimeException {
        $code = trim($exception->getMessage());

        if (str_starts_with($code, 'provider_test_')) {
            $code =
                'notification_gateway_'
                . substr(
                    $code,
                    strlen('provider_test_')
                );
        }

        if (
            $code === ''
            || !str_starts_with(
                $code,
                'notification_gateway_'
            )
        ) {
            $code =
                'notification_gateway_provider_rejected';
        }

        return new RuntimeException(
            $code,
            0,
            $exception
        );
    }
}
