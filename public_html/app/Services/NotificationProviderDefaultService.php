<?php

namespace App\Services;

use App\Repositories\NotificationProviderDefaultRepository;
use InvalidArgumentException;
use RuntimeException;

class NotificationProviderDefaultService extends BaseService
{
    private const CHANNELS = [
        'email' => 'ایمیل',
        'sms' => 'پیام کوتاه (SMS)',
        'messenger' => 'پیام‌رسان',
    ];

    public function __construct(
        private ?NotificationProviderDefaultRepository $repository = null,
        private ?NotificationProviderResolver $resolver = null,
        private ?AuthorizationService $authorization = null
    ) {
        $this->repository ??=
            new NotificationProviderDefaultRepository();
        $this->resolver ??=
            new NotificationProviderResolver(
                $this->repository
            );
        $this->authorization ??=
            new AuthorizationService();
    }

    public function page(int $userId): array
    {
        $this->authorize($userId);

        $instances = array_fill_keys(
            array_keys(self::CHANNELS),
            []
        );

        foreach (
            $this->repository->enabledInstances()
            as $instance
        ) {
            $channel = (string) (
                $instance['channel_code'] ?? ''
            );

            if (isset($instances[$channel])) {
                $instances[$channel][] = $instance;
            }
        }

        $selections = [];

        foreach (array_keys(self::CHANNELS) as $channel) {
            $selections[$channel] = [
                'primary_reference' => '',
                'fallback_reference' => '',
            ];
        }

        foreach (
            $this->repository->configuredDefaults()
            as $default
        ) {
            $channel = (string) (
                $default['channel_code'] ?? ''
            );

            if (!isset($selections[$channel])) {
                continue;
            }

            $reference = (string) (
                $default['public_reference'] ?? ''
            );

            if (!empty($default['is_default'])) {
                $selections[$channel][
                    'primary_reference'
                ] = $reference;
            } elseif (
                $selections[$channel][
                    'fallback_reference'
                ] === ''
            ) {
                $selections[$channel][
                    'fallback_reference'
                ] = $reference;
            }
        }

        $channels = [];

        foreach (self::CHANNELS as $code => $title) {
            $channels[$code] = [
                'code' => $code,
                'title' => $title,
                'instances' => $instances[$code],
                'selection' => $selections[$code],
                'resolved' => $this->resolver->resolve(
                    $code,
                    'general',
                    'global',
                    '*'
                ),
            ];
        }

        return [
            'scope_type' => 'global',
            'scope_reference' => '*',
            'purpose_code' => 'general',
            'channels' => $channels,
        ];
    }

    public function save(
        int $userId,
        mixed $input
    ): void {
        $this->authorize($userId);

        if (!is_array($input)) {
            throw new InvalidArgumentException(
                'provider_defaults_input_invalid'
            );
        }

        $normalized = [];

        foreach (array_keys(self::CHANNELS) as $channel) {
            $selection = is_array(
                $input[$channel] ?? null
            ) ? $input[$channel] : [];

            $primary = trim(
                (string) (
                    $selection['primary_reference'] ?? ''
                )
            );
            $fallback = trim(
                (string) (
                    $selection['fallback_reference'] ?? ''
                )
            );

            if ($primary === '' && $fallback !== '') {
                throw new InvalidArgumentException(
                    'provider_defaults_primary_required'
                );
            }

            if (
                $primary !== ''
                && $primary === $fallback
            ) {
                throw new InvalidArgumentException(
                    'provider_defaults_duplicate'
                );
            }

            $normalized[$channel] = [
                'primary_reference' => $primary,
                'fallback_reference' => $fallback,
            ];
        }

        $this->repository->saveGlobalDefaults(
            $normalized,
            $userId
        );
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
