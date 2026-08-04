<?php

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

class NotificationKavenegarGatewayAdapter extends BaseService implements
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
            ) === 'kavenegar'
            && (string) (
                $instance['channel_code'] ?? ''
            ) === 'sms';
    }

    public function send(
        array $instance,
        array $message
    ): array {
        $configuration =
            $this->runtime->configuration($instance);
        $secrets = $this->runtime->secrets($instance);

        $apiKey = trim(
            (string) ($secrets['api_key'] ?? '')
        );
        $sender = trim(
            (string) ($configuration['sender'] ?? '')
        );
        $destination = $this->mobile(
            (string) ($message['destination'] ?? '')
        );
        $body = trim(
            (string) ($message['body'] ?? '')
        );

        if ($apiKey === '') {
            throw new InvalidArgumentException(
                'notification_gateway_secret_unavailable'
            );
        }

        if (
            mb_strlen($body, 'UTF-8') < 1
            || mb_strlen($body, 'UTF-8') > 1600
        ) {
            throw new InvalidArgumentException(
                'notification_gateway_message_invalid'
            );
        }

        $endpoint = trim(
            (string) ($configuration['endpoint'] ?? '')
        );

        if ($endpoint === '') {
            $endpoint =
                'https://api.kavenegar.com/v1/'
                . rawurlencode($apiKey)
                . '/sms/send.json';
        } else {
            $endpoint = str_replace(
                ['{API-KEY}', '{api_key}'],
                rawurlencode($apiKey),
                $endpoint
            );
        }

        $this->runtime->assertHttpsEndpoint(
            $endpoint,
            ['api.kavenegar.com']
        );

        try {
            $response = $this->http->postForm(
                $endpoint,
                [
                    'receptor' => $destination,
                    'sender' => $sender,
                    'message' => $body,
                ],
                15,
                'IPKF-Notification-Gateway/1.0'
            );

            $json = $response['json'] ?? null;
            $remoteStatus = is_array($json)
                ? (int) (
                    $json['return']['status'] ?? 0
                )
                : 0;
            $entries = is_array($json)
                ? ($json['entries'] ?? [])
                : [];
            $entry = is_array($entries)
                ? ($entries[0] ?? null)
                : null;

            if (
                (int) ($response['status_code'] ?? 0) < 200
                || (int) (
                    $response['status_code'] ?? 0
                ) >= 300
                || !is_array($json)
                || $remoteStatus !== 200
                || !is_array($entry)
            ) {
                throw new RuntimeException(
                    'notification_gateway_provider_rejected'
                );
            }

            return [
                'provider_message_reference' =>
                    (string) (
                        $entry['messageid'] ?? ''
                    ),
                'response_code' =>
                    (string) $remoteStatus,
                'response_message' =>
                    'kavenegar_accepted',
                'duration_ms' => (int) (
                    $response['duration_ms'] ?? 0
                ),
                'metadata' => [
                    'transport' => 'kavenegar',
                    'remote_delivery_status' =>
                        (int) ($entry['status'] ?? 0),
                    'target_suffix' =>
                        substr($destination, -4),
                ],
            ];
        } catch (Throwable $exception) {
            throw $this->gatewayException($exception);
        }
    }

    private function mobile(string $mobile): string
    {
        $mobile = preg_replace(
            '/[\s()-]+/',
            '',
            trim($mobile)
        ) ?? '';

        if (
            preg_match(
                '/^\+?[0-9]{10,15}$/',
                $mobile
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'notification_gateway_destination_invalid'
            );
        }

        return $mobile;
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
