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
        $mediaAssets = array_values(array_filter(
            is_array($message['media_assets'] ?? null)
                ? $message['media_assets']
                : [],
            static fn (mixed $asset): bool =>
                is_array($asset)
                && is_readable((string) (
                    $asset['storage_path'] ?? ''
                ))
        ));

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
            trim((string) (
                $configuration['api_base']
                ?? 'https://tapi.bale.ai'
            )),
            '/'
        );

        if ($apiBase === '') {
            $apiBase = 'https://tapi.bale.ai';
        }

        $this->runtime->assertHttpsEndpoint(
            $apiBase,
            ['tapi.bale.ai']
        );

        $parseMode = trim((string) (
            $configuration['parse_mode'] ?? ''
        ));

        if (
            $parseMode !== ''
            && preg_match(
                '/^[A-Za-z0-9_]{1,30}$/',
                $parseMode
            ) !== 1
        ) {
            $parseMode = '';
        }

        try {
            if ($mediaAssets === []) {
                return $this->sendText(
                    $apiBase,
                    $botToken,
                    $chatId,
                    $body,
                    $parseMode
                );
            }

            return $this->sendMedia(
                $apiBase,
                $botToken,
                $chatId,
                $body,
                $parseMode,
                $mediaAssets
            );
        } catch (Throwable $exception) {
            throw $this->gatewayException($exception);
        }
    }

    private function sendText(
        string $apiBase,
        string $botToken,
        string $chatId,
        string $body,
        string $parseMode
    ): array {
        $payload = [
            'chat_id' => (int) $chatId,
            'text' => $body,
        ];

        if ($parseMode !== '') {
            $payload['parse_mode'] = $parseMode;
        }

        $response = $this->http->postJson(
            $apiBase . '/bot'
                . rawurlencode($botToken)
                . '/sendMessage',
            $payload,
            15,
            'IPKF-Notification-Gateway/1.0'
        );
        $json = $this->accepted($response);

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
                'media_count' => 0,
                'target_fingerprint' => substr(
                    hash('sha256', $chatId),
                    0,
                    16
                ),
            ],
        ];
    }

    private function sendMedia(
        string $apiBase,
        string $botToken,
        string $chatId,
        string $body,
        string $parseMode,
        array $assets
    ): array {
        $duration = 0;
        $primaryReference = '';
        $responseCode = '';
        $caption = mb_strlen($body, 'UTF-8') <= 900
            ? $body
            : '';

        if ($caption === '') {
            $text = $this->sendText(
                $apiBase,
                $botToken,
                $chatId,
                $body,
                $parseMode
            );
            $duration += (int) (
                $text['duration_ms'] ?? 0
            );
            $primaryReference = (string) (
                $text['provider_message_reference'] ?? ''
            );
            $responseCode = (string) (
                $text['response_code'] ?? ''
            );
        }

        foreach ($assets as $index => $asset) {
            [$method, $field] = match (
                (string) (
                    $asset['media_kind'] ?? 'document'
                )
            ) {
                'image' => ['sendPhoto', 'photo'],
                'video' => ['sendVideo', 'video'],
                'audio' => ['sendAudio', 'audio'],
                default => ['sendDocument', 'document'],
            };

            $fields = ['chat_id' => (int) $chatId];

            if ($index === 0 && $caption !== '') {
                $fields['caption'] = $caption;

                if ($parseMode !== '') {
                    $fields['parse_mode'] = $parseMode;
                }
            }

            $response = $this->http->postMultipart(
                $apiBase . '/bot'
                    . rawurlencode($botToken)
                    . '/' . $method,
                $fields,
                [
                    'field_name' => $field,
                    'path' => (string) (
                        $asset['storage_path']
                    ),
                    'mime_type' => (string) (
                        $asset['mime_type']
                    ),
                    'original_name' => (string) (
                        $asset['original_name']
                    ),
                ],
                45,
                'IPKF-Notification-Gateway/1.0'
            );
            $json = $this->accepted($response);
            $reference = (string) (
                $json['result']['message_id'] ?? ''
            );

            if ($primaryReference === '') {
                $primaryReference = $reference;
            }

            $duration += (int) (
                $response['duration_ms'] ?? 0
            );
            $responseCode = (string) (
                $response['status_code'] ?? ''
            );
        }

        return [
            'provider_message_reference' =>
                $primaryReference,
            'response_code' => $responseCode,
            'response_message' =>
                'bale_media_accepted',
            'duration_ms' => $duration,
            'metadata' => [
                'transport' => 'bale',
                'media_count' => count($assets),
                'target_fingerprint' => substr(
                    hash('sha256', $chatId),
                    0,
                    16
                ),
            ],
        ];
    }

    private function accepted(array $response): array
    {
        $json = $response['json'] ?? null;

        if (
            (int) ($response['status_code'] ?? 0) < 200
            || (int) ($response['status_code'] ?? 0) >= 300
            || !is_array($json)
            || empty($json['ok'])
            || !is_array($json['result'] ?? null)
        ) {
            throw new RuntimeException(
                'notification_gateway_provider_rejected'
            );
        }

        return $json;
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
