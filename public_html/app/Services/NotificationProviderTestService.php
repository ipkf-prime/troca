<?php

namespace App\Services;

use App\Repositories\NotificationProviderManagementRepository;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class NotificationProviderTestService extends BaseService
{
    public function __construct(
        private ?NotificationProviderManagementRepository $repository = null,
        private ?NotificationProviderSecretService $secrets = null,
        private ?NotificationSmtpTransport $smtp = null,
        private ?NotificationProviderHttpTransport $http = null,
        private ?AuthorizationService $authorization = null
    ) {
        $this->repository ??=
            new NotificationProviderManagementRepository();
        $this->secrets ??=
            new NotificationProviderSecretService();
        $this->smtp ??=
            new NotificationSmtpTransport();
        $this->http ??=
            new NotificationProviderHttpTransport();
        $this->authorization ??=
            new AuthorizationService();
    }

    public function send(
        int $userId,
        string $reference,
        array $input
    ): array {
        $this->authorize($userId);
        $instance = $this->instance($reference);
        $providerCode = (string) (
            $instance['provider_type_code'] ?? ''
        );
        $channelCode = (string) (
            $instance['channel_code'] ?? ''
        );
        $driverCode = (string) (
            $instance['driver_code'] ?? ''
        );

        if (
            $channelCode === 'email'
            && $driverCode === 'smtp'
        ) {
            return $this->sendEmailInstance(
                $userId,
                $instance,
                $input
            );
        }

        if ($providerCode === 'kavenegar') {
            return $this->sendKavenegarInstance(
                $userId,
                $instance,
                $input
            );
        }

        if ($providerCode === 'bale_bot') {
            return $this->sendBaleInstance(
                $userId,
                $instance,
                $input
            );
        }

        throw new InvalidArgumentException(
            'provider_test_unsupported'
        );
    }

    public function sendEmail(
        int $userId,
        string $reference,
        array $input
    ): array {
        $this->authorize($userId);
        $instance = $this->instance($reference);

        if (
            (string) ($instance['channel_code'] ?? '')
                !== 'email'
            || (string) ($instance['driver_code'] ?? '')
                !== 'smtp'
        ) {
            throw new InvalidArgumentException(
                'provider_test_email_unsupported'
            );
        }

        return $this->sendEmailInstance(
            $userId,
            $instance,
            $input
        );
    }

    private function sendEmailInstance(
        int $userId,
        array $instance,
        array $input
    ): array {
        $configuration = $this->configuration($instance);
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
                'provider_test_config_invalid'
            );
        }

        $recipient = trim(
            (string) ($input['recipient'] ?? '')
        );
        $subject = trim(
            (string) ($input['subject'] ?? '')
        );
        $body = $this->message($input);

        if (
            filter_var(
                $recipient,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            throw new InvalidArgumentException(
                'provider_test_recipient_invalid'
            );
        }

        if (
            mb_strlen($subject, 'UTF-8') < 1
            || mb_strlen($subject, 'UTF-8') > 190
        ) {
            throw new InvalidArgumentException(
                'provider_test_subject_invalid'
            );
        }

        $secretValues = $this->secretValues($instance);
        $auditBase = [
            'provider_type_code' =>
                (string) $instance['provider_type_code'],
            'channel_code' => 'email',
            'target_domain' => $this->emailDomain(
                $recipient
            ),
            'subject_length' =>
                mb_strlen($subject, 'UTF-8'),
            'body_length' =>
                mb_strlen($body, 'UTF-8'),
        ];

        try {
            $result = $this->smtp->send([
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
                'username' => $username,
                'password' => (string) (
                    $secretValues['password'] ?? ''
                ),
                'from_address' => $fromAddress,
                'from_name' => $fromName,
                'recipient' => $recipient,
                'subject' => $subject,
                'body' => $body,
                'timeout' => 12,
            ]);

            $result['status_code'] =
                'provider_test_email_sent';

            $this->record(
                $instance,
                $userId,
                true,
                'provider_test_email_sent',
                $auditBase + [
                    'duration_ms' =>
                        (int) $result['duration_ms'],
                    'response_code' =>
                        (int) $result['response_code'],
                    'message_id' =>
                        (string) $result['message_id'],
                ],
                'email'
            );

            return $result;
        } catch (Throwable $exception) {
            $errorCode = $this->errorCode(
                $exception,
                'provider_test_failed'
            );

            $this->record(
                $instance,
                $userId,
                false,
                $errorCode,
                $auditBase + [
                    'error_code' => $errorCode,
                ],
                'email'
            );

            throw new RuntimeException(
                $errorCode,
                0,
                $exception
            );
        }
    }

    private function sendKavenegarInstance(
        int $userId,
        array $instance,
        array $input
    ): array {
        $configuration = $this->configuration($instance);
        $secretValues = $this->secretValues($instance);
        $apiKey = trim(
            (string) ($secretValues['api_key'] ?? '')
        );
        $sender = trim(
            (string) ($configuration['sender'] ?? '')
        );
        $recipient = $this->mobile(
            (string) ($input['recipient'] ?? '')
        );
        $body = $this->message($input);

        if ($apiKey === '') {
            throw new InvalidArgumentException(
                'provider_test_api_key_missing'
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

        $this->assertEndpoint(
            $endpoint,
            ['api.kavenegar.com']
        );

        $auditBase = [
            'provider_type_code' => 'kavenegar',
            'channel_code' => 'sms',
            'target_suffix' => substr($recipient, -4),
            'body_length' =>
                mb_strlen($body, 'UTF-8'),
        ];

        try {
            $response = $this->http->postForm(
                $endpoint,
                [
                    'receptor' => $recipient,
                    'sender' => $sender,
                    'message' => $body,
                ],
                15
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
                (int) ($response['status_code'] ?? 0)
                    < 200
                || (int) ($response['status_code'] ?? 0)
                    >= 300
                || !is_array($json)
                || $remoteStatus !== 200
                || !is_array($entry)
            ) {
                throw new RuntimeException(
                    is_array($json)
                        ? 'provider_test_api_rejected'
                        : 'provider_test_api_response_invalid'
                );
            }

            $result = [
                'status_code' => 'provider_test_sms_sent',
                'message_id' => (string) (
                    $entry['messageid'] ?? ''
                ),
                'response_code' => $remoteStatus,
                'duration_ms' => (int) (
                    $response['duration_ms'] ?? 0
                ),
            ];

            $this->record(
                $instance,
                $userId,
                true,
                'provider_test_sms_sent',
                $auditBase + [
                    'duration_ms' =>
                        $result['duration_ms'],
                    'response_code' => $remoteStatus,
                    'message_id' =>
                        $result['message_id'],
                    'remote_delivery_status' =>
                        (int) ($entry['status'] ?? 0),
                ],
                'sms'
            );

            return $result;
        } catch (Throwable $exception) {
            $errorCode = $this->errorCode(
                $exception,
                'provider_test_api_rejected'
            );

            $this->record(
                $instance,
                $userId,
                false,
                $errorCode,
                $auditBase + [
                    'error_code' => $errorCode,
                ],
                'sms'
            );

            throw new RuntimeException(
                $errorCode,
                0,
                $exception
            );
        }
    }

    private function sendBaleInstance(
        int $userId,
        array $instance,
        array $input
    ): array {
        $configuration = $this->configuration($instance);
        $secretValues = $this->secretValues($instance);
        $botToken = trim(
            (string) ($secretValues['bot_token'] ?? '')
        );
        $chatId = trim(
            (string) ($input['recipient'] ?? '')
        );
        $body = $this->message($input);

        if ($botToken === '') {
            throw new InvalidArgumentException(
                'provider_test_bot_token_missing'
            );
        }

        if (
            preg_match(
                '/^-?[0-9]{1,20}$/',
                $chatId
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'provider_test_chat_id_invalid'
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

        $this->assertEndpoint(
            $apiBase,
            ['tapi.bale.ai']
        );

        $endpoint = $apiBase
            . '/bot'
            . rawurlencode($botToken)
            . '/sendMessage';

        $payload = [
            'chat_id' => (int) $chatId,
            'text' => $body,
        ];

        $parseMode = trim(
            (string) ($configuration['parse_mode'] ?? '')
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

        $auditBase = [
            'provider_type_code' => 'bale_bot',
            'channel_code' => 'messenger',
            'target_fingerprint' => substr(
                hash('sha256', $chatId),
                0,
                16
            ),
            'body_length' =>
                mb_strlen($body, 'UTF-8'),
        ];

        try {
            $response = $this->http->postJson(
                $endpoint,
                $payload,
                15
            );
            $json = $response['json'] ?? null;

            if (
                (int) ($response['status_code'] ?? 0)
                    < 200
                || (int) ($response['status_code'] ?? 0)
                    >= 300
                || !is_array($json)
                || empty($json['ok'])
                || !is_array($json['result'] ?? null)
            ) {
                throw new RuntimeException(
                    is_array($json)
                        ? 'provider_test_api_rejected'
                        : 'provider_test_api_response_invalid'
                );
            }

            $result = [
                'status_code' => 'provider_test_bale_sent',
                'message_id' => (string) (
                    $json['result']['message_id'] ?? ''
                ),
                'response_code' => (int) (
                    $response['status_code'] ?? 0
                ),
                'duration_ms' => (int) (
                    $response['duration_ms'] ?? 0
                ),
            ];

            $this->record(
                $instance,
                $userId,
                true,
                'provider_test_bale_sent',
                $auditBase + [
                    'duration_ms' =>
                        $result['duration_ms'],
                    'response_code' =>
                        $result['response_code'],
                    'message_id' =>
                        $result['message_id'],
                ],
                'bale'
            );

            return $result;
        } catch (Throwable $exception) {
            $errorCode = $this->errorCode(
                $exception,
                'provider_test_api_rejected'
            );

            $this->record(
                $instance,
                $userId,
                false,
                $errorCode,
                $auditBase + [
                    'error_code' => $errorCode,
                ],
                'bale'
            );

            throw new RuntimeException(
                $errorCode,
                0,
                $exception
            );
        }
    }

    private function instance(string $reference): array
    {
        $reference = trim($reference);

        if (
            preg_match(
                '/^npi_[a-f0-9]{24}$/',
                $reference
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'provider_reference_invalid'
            );
        }

        $instance = $this->repository
            ->instanceByReference($reference);

        if ($instance === null) {
            throw new RuntimeException(
                'provider_instance_not_found'
            );
        }

        return $instance;
    }

    private function configuration(array $instance): array
    {
        $configuration = json_decode(
            (string) (
                $instance['configuration_json'] ?? ''
            ),
            true
        );

        return is_array($configuration)
            ? $configuration
            : [];
    }

    private function secretValues(array $instance): array
    {
        $secretSet = $this->repository->secretSet(
            (int) $instance['id']
        );

        if ($secretSet === null) {
            return [];
        }

        try {
            return $this->secrets->decrypt(
                (string) $secretSet[
                    'encrypted_payload'
                ]
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'provider_test_secret_unavailable',
                0,
                $exception
            );
        }
    }

    private function message(array $input): string
    {
        $body = trim(
            (string) ($input['body'] ?? '')
        );
        $length = mb_strlen($body, 'UTF-8');

        if ($length < 1 || $length > 10000) {
            throw new InvalidArgumentException(
                'provider_test_body_invalid'
            );
        }

        return $body;
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
                'provider_test_mobile_invalid'
            );
        }

        return $mobile;
    }

    private function assertEndpoint(
        string $url,
        array $allowedHosts
    ): void {
        $parts = parse_url($url);

        if (
            !is_array($parts)
            || strtolower(
                (string) ($parts['scheme'] ?? '')
            ) !== 'https'
            || !in_array(
                strtolower(
                    (string) ($parts['host'] ?? '')
                ),
                $allowedHosts,
                true
            )
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new InvalidArgumentException(
                'provider_test_api_endpoint_invalid'
            );
        }
    }

    private function record(
        array $instance,
        int $userId,
        bool $success,
        string $message,
        array $summary,
        string $testKind
    ): void {
        try {
            $this->repository->recordTestResult(
                (int) $instance['id'],
                $userId,
                $success,
                $message,
                $summary,
                $testKind
            );
        } catch (Throwable) {
            // A test result must not be sent twice because audit failed.
        }
    }

    private function errorCode(
        Throwable $exception,
        string $fallback
    ): string {
        $errorCode = trim($exception->getMessage());

        if (
            $errorCode === ''
            || !str_starts_with(
                $errorCode,
                'provider_test_'
            )
        ) {
            return $fallback;
        }

        return $errorCode;
    }

    private function emailDomain(string $email): string
    {
        $position = strrpos($email, '@');

        return $position === false
            ? ''
            : strtolower(substr($email, $position + 1));
    }

    private function authorize(int $userId): void
    {
        if (!$this->authorization->hasPermission(
            $userId,
            'notifications.providers.manage'
        )) {
            throw new RuntimeException(
                'provider_management_forbidden'
            );
        }
    }
}
