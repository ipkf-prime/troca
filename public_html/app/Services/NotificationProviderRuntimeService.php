<?php

namespace App\Services;

use App\Repositories\NotificationProviderManagementRepository;
use RuntimeException;
use Throwable;

class NotificationProviderRuntimeService extends BaseService
{
    public function __construct(
        private ?NotificationProviderManagementRepository $repository = null,
        private ?NotificationProviderSecretService $secrets = null
    ) {
        $this->repository ??=
            new NotificationProviderManagementRepository();
        $this->secrets ??=
            new NotificationProviderSecretService();
    }

    public function instance(string $reference): array
    {
        $reference = trim($reference);

        if (
            preg_match(
                '/^npi_[a-f0-9]{24}$/',
                $reference
            ) !== 1
        ) {
            throw new RuntimeException(
                'notification_gateway_provider_invalid'
            );
        }

        $instance = $this->repository
            ->instanceByReference($reference);

        if (
            !is_array($instance)
            || empty($instance['is_enabled'])
            || (string) ($instance['status_code'] ?? '')
                !== 'active'
        ) {
            throw new RuntimeException(
                'notification_gateway_provider_unavailable'
            );
        }

        return $instance;
    }

    public function configuration(array $instance): array
    {
        $decoded = json_decode(
            (string) (
                $instance['configuration_json'] ?? ''
            ),
            true
        );

        return is_array($decoded) ? $decoded : [];
    }

    public function secrets(array $instance): array
    {
        $secretSet = $this->repository->secretSet(
            (int) ($instance['id'] ?? 0)
        );

        if (!is_array($secretSet)) {
            return [];
        }

        try {
            $values = $this->secrets->decrypt(
                (string) (
                    $secretSet['encrypted_payload'] ?? ''
                )
            );

            return is_array($values) ? $values : [];
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'notification_gateway_secret_unavailable',
                0,
                $exception
            );
        }
    }

    public function assertHttpsEndpoint(
        string $url,
        array $allowedHosts
    ): void {
        $parts = parse_url($url);
        $host = strtolower(
            (string) ($parts['host'] ?? '')
        );

        if (
            !is_array($parts)
            || strtolower(
                (string) ($parts['scheme'] ?? '')
            ) !== 'https'
            || !in_array($host, $allowedHosts, true)
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new RuntimeException(
                'notification_gateway_endpoint_invalid'
            );
        }
    }
}
