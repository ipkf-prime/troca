<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

class NotificationKavenegarLookupGatewayAdapter extends BaseService implements
    NotificationGatewayAdapterInterface
{
    public function __construct(
        private ?NotificationProviderRuntimeService $runtime = null,
        private ?NotificationProviderHttpTransport $http = null
    ) {
        $this->runtime ??= new NotificationProviderRuntimeService();
        $this->http ??= new NotificationProviderHttpTransport();
    }

    public function supports(array $instance): bool
    {
        return (string) ($instance['provider_type_code'] ?? '')
            === 'kavenegar_lookup'
            && (string) ($instance['channel_code'] ?? '') === 'sms';
    }

    public function send(array $instance, array $message): array
    {
        if (!$this->supports($instance)) {
            throw new RuntimeException(
                'notification_gateway_adapter_unsupported'
            );
        }

        $recipient = preg_replace(
            '/[\s()-]+/',
            '',
            trim((string) ($message['destination'] ?? ''))
        ) ?? '';
        $token = trim((string) ($message['lookup_token'] ?? ''));
        $purpose = strtolower(trim(
            (string) ($message['purpose_code'] ?? '')
        ));

        if (preg_match('/^(?:09[0-9]{9}|\+989[0-9]{9}|989[0-9]{9})$/D', $recipient) !== 1) {
            throw new InvalidArgumentException(
                'notification_gateway_destination_invalid'
            );
        }
        if (preg_match('/^[0-9]{6}$/D', $token) !== 1) {
            throw new InvalidArgumentException(
                'notification_gateway_lookup_token_invalid'
            );
        }

        $templateField = match ($purpose) {
            'registration' => 'register_template',
            'login' => 'login_template',
            'approval' => 'approval_template',
            default => throw new InvalidArgumentException(
                'notification_gateway_lookup_purpose_invalid'
            ),
        };

        $config = $this->runtime->configuration($instance);
        $template = trim((string) ($config[$templateField] ?? ''));
        if (preg_match('/^[A-Za-z0-9]{3,64}$/D', $template) !== 1) {
            throw new InvalidArgumentException(
                'notification_gateway_lookup_template_invalid'
            );
        }

        $secret = $this->runtime->secrets($instance);
        $key = trim((string) ($secret['api_key'] ?? ''));
        if ($key === '') {
            throw new RuntimeException(
                'notification_gateway_secret_unavailable'
            );
        }

        $policy = (new SmsDeliveryPolicyService())->decision();
        if (!($policy['allowed'] ?? false)) {
            throw new RuntimeException(
                'notification_gateway_sms_window_closed'
            );
        }

        $url = 'https://api.kavenegar.com/v1/'
            . rawurlencode($key)
            . '/verify/lookup.json';
        $this->runtime->assertHttpsEndpoint(
            $url,
            ['api.kavenegar.com']
        );

        try {
            $response = $this->http->postForm(
                $url,
                [
                    'receptor' => $recipient,
                    'token' => $token,
                    'template' => $template,
                    'type' => 'sms',
                ],
                15,
                'IPKF-Lookup/1.0'
            );
            $json = $response['json'] ?? null;
            $entries = is_array($json) ? ($json['entries'] ?? null) : null;
            $entry = is_array($entries)
                ? ($entries[0] ?? $entries)
                : null;

            if (
                (int) ($response['status_code'] ?? 0) < 200
                || (int) ($response['status_code'] ?? 0) >= 300
                || !is_array($json)
                || (int) ($json['return']['status'] ?? 0) !== 200
                || !is_array($entry)
                || trim((string) ($entry['messageid'] ?? '')) === ''
            ) {
                throw new RuntimeException(
                    'notification_gateway_provider_rejected'
                );
            }

            return [
                'provider_message_reference' => (string) $entry['messageid'],
                'response_code' => '200',
                'response_message' => 'kavenegar_lookup_accepted',
                'duration_ms' => (int) ($response['duration_ms'] ?? 0),
                'metadata' => [
                    'transport' => 'kavenegar_lookup',
                    'purpose_code' => $purpose,
                    'remote_delivery_status' => (int) (
                        $entry['status'] ?? 0
                    ),
                    'target_suffix' => substr($recipient, -4),
                ],
            ];
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
            if (!str_starts_with($error, 'notification_gateway_')) {
                $error = 'notification_gateway_provider_rejected';
            }
            throw new RuntimeException(
                $error,
                0,
                $exception
            );
        }
    }
}
