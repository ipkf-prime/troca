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
        private ?AuthorizationService $authorization = null
    ) {
        $this->repository ??=
            new NotificationProviderManagementRepository();
        $this->secrets ??=
            new NotificationProviderSecretService();
        $this->smtp ??=
            new NotificationSmtpTransport();
        $this->authorization ??=
            new AuthorizationService();
    }

    public function sendEmail(
        int $userId,
        string $reference,
        array $input
    ): array {
        $this->authorize($userId);

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

        $configuration = json_decode(
            (string) (
                $instance['configuration_json'] ?? ''
            ),
            true
        );

        if (!is_array($configuration)) {
            $configuration = [];
        }

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
        $body = trim(
            (string) ($input['body'] ?? '')
        );

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

        if (
            mb_strlen($body, 'UTF-8') < 1
            || mb_strlen($body, 'UTF-8') > 10000
        ) {
            throw new InvalidArgumentException(
                'provider_test_body_invalid'
            );
        }

        $secretValues = [];
        $secretSet = $this->repository->secretSet(
            (int) $instance['id']
        );

        if ($secretSet !== null) {
            try {
                $secretValues = $this->secrets->decrypt(
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

            try {
                $this->repository->recordTestResult(
                    (int) $instance['id'],
                    $userId,
                    true,
                    'provider_test_sent',
                    $auditBase + [
                        'duration_ms' =>
                            (int) $result['duration_ms'],
                        'response_code' =>
                            (int) $result['response_code'],
                        'message_id' =>
                            (string) $result['message_id'],
                    ]
                );
            } catch (Throwable) {
                // Sending succeeded; audit failure must not cause a resend.
            }

            return $result;
        } catch (Throwable $exception) {
            $errorCode = $exception instanceof RuntimeException
                ? trim($exception->getMessage())
                : 'provider_test_failed';

            if (
                $errorCode === ''
                || !str_starts_with(
                    $errorCode,
                    'provider_test_'
                )
            ) {
                $errorCode = 'provider_test_failed';
            }

            try {
                $this->repository->recordTestResult(
                    (int) $instance['id'],
                    $userId,
                    false,
                    $errorCode,
                    $auditBase + [
                        'error_code' => $errorCode,
                    ]
                );
            } catch (Throwable) {
                // Preserve the original transport failure.
            }

            throw new RuntimeException(
                $errorCode,
                0,
                $exception
            );
        }
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
