<?php

namespace App\Services;

use App\Repositories\CommunicationSettingsRepository;

class CommunicationSettingsService extends BaseService
{
    private const SECTIONS = [
        'providers' => [
            'title' => 'سرویس‌دهنده‌ها',
            'permission' => 'notifications.providers.manage',
        ],
        'defaults' => [
            'title' => 'پیش‌فرض سرویس‌دهنده‌ها',
            'permission' => 'notifications.providers.manage',
        ],
        'routing' => [
            'title' => 'قواعد ارسال',
            'permission' => 'notifications.routing.manage',
        ],
        'preferences' => [
            'title' => 'روش‌های دریافت اعلان',
            'permission' => 'notifications.preferences.self',
        ],
        'reports' => [
            'title' => 'گزارش ارسال و تحویل',
            'permission' => 'notifications.reports.view',
        ],
        'internal' => [
            'title' => 'پیام‌رسان داخلی',
            'permission' => 'messages.admin.manage',
        ],
    ];

    public function __construct(
        private ?CommunicationSettingsRepository $repository = null,
        private ?AuthorizationService $authorization = null
    ) {
        $this->repository ??=
            new CommunicationSettingsRepository();
        $this->authorization ??=
            new AuthorizationService();
    }

    public function allowedSections(int $userId): array
    {
        $sections = [];

        foreach (self::SECTIONS as $code => $definition) {
            if ($this->authorization->hasPermission(
                $userId,
                $definition['permission']
            )) {
                $sections[$code] = $definition['title'];
            }
        }

        return $sections;
    }

    public function page(int $userId, string $section): array
    {
        $sections = $this->allowedSections($userId);

        if ($sections === []) {
            return [
                'allowed' => false,
                'section' => '',
                'sections' => [],
            ];
        }

        if (!array_key_exists($section, $sections)) {
            $section = (string) array_key_first($sections);
        }

        $providersAllowed = isset($sections['providers'])
            || isset($sections['defaults']);
        $routingAllowed = isset($sections['routing']);
        $preferencesAllowed = isset($sections['preferences']);
        $reportsAllowed = isset($sections['reports']);

        return [
            'allowed' => true,
            'section' => $section,
            'sections' => $sections,
            'provider_types' => $providersAllowed
                ? $this->repository->providerTypes()
                : [],
            'provider_instances' => $providersAllowed
                ? $this->repository->providerInstances()
                : [],
            'provider_defaults' => $providersAllowed
                ? $this->repository->providerDefaults()
                : [],
            'routing_rules' => $routingAllowed
                ? $this->repository->routingRules()
                : [],
            'events' => $routingAllowed
                ? $this->repository->events()
                : [],
            'channels' => $preferencesAllowed
                ? $this->repository->channels()
                : [],
            'preferences' => $preferencesAllowed
                ? $this->repository->preferences($userId)
                : [],
            'deliveries' => $reportsAllowed
                && $section === 'reports'
                    ? $this->repository->deliveryReport()
                    : [],
            'message_settings' => $section === 'internal'
                ? (new InternalMessageAdministrationService())->settings($userId)
                : [],
        ];
    }

    public function savePreferences(
        int $userId,
        mixed $channels
    ): void {
        if (!isset($this->allowedSections($userId)['preferences'])) {
            return;
        }

        $allowed = array_map(
            static fn (array $channel): string =>
                (string) $channel['code'],
            $this->repository->channels()
        );

        $enabled = is_array($channels)
            ? array_values(array_unique(array_filter(
                array_map('strval', $channels),
                static fn (string $channel): bool =>
                    in_array($channel, $allowed, true)
            )))
            : [];

        $this->repository->savePreferences(
            $userId,
            $enabled
        );
    }
}
