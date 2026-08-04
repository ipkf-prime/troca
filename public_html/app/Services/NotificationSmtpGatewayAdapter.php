<?php

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

class NotificationSmtpGatewayAdapter extends BaseService implements
    NotificationGatewayAdapterInterface
{
    public function __construct(
        private ?NotificationProviderRuntimeService $runtime = null,
        private ?NotificationSmtpTransport $transport = null
    ) {
        $this->runtime ??=
            new NotificationProviderRuntimeService();
        $this->transport ??=
            new NotificationSmtpTransport();
    }

    public function supports(array $instance): bool
    {
        return
            (string) ($instance['channel_code'] ?? '')
                === 'email'
            && (string) ($instance['driver_code'] ?? '')
                === 'smtp';
    }

    public function send(
        array $instance,
        array $message
    ): array {
        $configuration =
            $this->runtime->configuration($instance);
        $secrets = $this->runtime->secrets($instance);

        $host = trim(
            (string) ($configuration['host'] ?? '')
        );
        $port = (int) ($configuration['port'] ?? 0);
        $encryption = strtolower(trim(
            (string) (
                $configuration['encryption'] ?? 'none'
            )
        ));
        $username = trim(
            (string) ($configuration['username'] ?? '')
        );
        $fromAddress = trim(
            (string) (
                $configuration['from_address'] ?? ''
            )
        );
        $fromName = trim(
            (string) (
                $configuration['from_name']
                ?? $instance['title']
                ?? ''
            )
        );
        $destination = trim(
            (string) ($message['destination'] ?? '')
        );
        $subject = trim(
            (string) ($message['subject'] ?? '')
        );
        $body = trim(
            (string) ($message['body'] ?? '')
        );

        if (
            $host === ''
            || preg_match(
                '/^[a-z0-9._:-]+$/i',
                $host
            ) !== 1
            || $port < 1
            || $port > 65535
            || !in_array(
                $encryption,
                ['none', 'tls', 'ssl'],
                true
            )
            || filter_var(
                $fromAddress,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            throw new InvalidArgumentException(
                'notification_gateway_config_invalid'
            );
        }

        if (
            filter_var(
                $destination,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            throw new InvalidArgumentException(
                'notification_gateway_destination_invalid'
            );
        }

        if (
            mb_strlen($subject, 'UTF-8') < 1
            || mb_strlen($subject, 'UTF-8') > 190
            || mb_strlen($body, 'UTF-8') < 1
            || mb_strlen($body, 'UTF-8') > 10000
        ) {
            throw new InvalidArgumentException(
                'notification_gateway_message_invalid'
            );
        }

        try {
            $result = $this->transport->send([
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
                'username' => $username,
                'password' => (string) (
                    $secrets['password'] ?? ''
                ),
                'from_address' => $fromAddress,
                'from_name' => $fromName,
                'recipient' => $destination,
                'subject' => $subject,
                'body' => $body,
                'timeout' => 15,
                'is_test' => false,
            ]);

            return [
                'provider_message_reference' =>
                    (string) (
                        $result['message_id'] ?? ''
                    ),
                'response_code' => (string) (
                    $result['response_code'] ?? ''
                ),
                'response_message' => 'smtp_accepted',
                'duration_ms' => (int) (
                    $result['duration_ms'] ?? 0
                ),
                'metadata' => [
                    'transport' => 'smtp',
                    'target_domain' =>
                        $this->emailDomain($destination),
                ],
            ];
        } catch (Throwable $exception) {
            throw $this->gatewayException($exception);
        }
    }

    private function emailDomain(string $email): string
    {
        $position = strrpos($email, '@');

        return $position === false
            ? ''
            : strtolower(
                substr($email, $position + 1)
            );
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
