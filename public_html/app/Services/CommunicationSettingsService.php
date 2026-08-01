<?php

namespace App\Services;

use App\Repositories\CommunicationSettingsRepository;

class CommunicationSettingsService extends BaseService
{
    public function __construct(
        private ?CommunicationSettingsRepository $repository = null
    ) {
        $this->repository ??= new CommunicationSettingsRepository();
    }

    public function page(int $userId, string $section): array
    {
        $allowed = [
            'providers',
            'defaults',
            'routing',
            'preferences',
            'reports',
        ];
        $section = in_array($section, $allowed, true)
            ? $section
            : 'providers';

        return [
            'section' => $section,
            'provider_types' => $this->repository->providerTypes(),
            'provider_instances' => $this->repository->providerInstances(),
            'provider_defaults' => $this->repository->providerDefaults(),
            'routing_rules' => $this->repository->routingRules(),
            'events' => $this->repository->events(),
            'channels' => $this->repository->channels(),
            'preferences' => $this->repository->preferences($userId),
            'deliveries' => $section === 'reports'
                ? $this->repository->deliveryReport()
                : [],
        ];
    }

    public function savePreferences(
        int $userId,
        mixed $channels
    ): void {
        $enabled = is_array($channels)
            ? array_values(array_unique(array_map('strval', $channels)))
            : [];

        $this->repository->savePreferences($userId, $enabled);
    }
}
