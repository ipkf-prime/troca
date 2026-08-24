<?php

namespace IPKF\Database\Application;

use IPKF\Database\Connections\ConnectionResolver;
use IPKF\Database\Seeds\Seeder;

class ApplicationSeederRegistry
{
    public function __construct(private ?ConnectionResolver $connections = null)
    {
        $this->connections ??= new ConnectionResolver();
    }

    public function groups(): array
    {
        return [
            'core' => [
                'connection' => 'core.primary',
                'seeders' => [
                    \IPKF\Database\Seeds\RuntimeCheckSeeder::class,
                    \IPKF\Database\Seeds\AuthRbacSeeder::class,
                    \IPKF\Database\Seeds\MultiSourceMetadataSeeder::class,
                    \IPKF\Database\Seeds\ExternalOrganizationContactCatalogSeeder::class,
                    \IPKF\Database\Seeds\AutomationCorrespondencePermissionsSeeder::class,
                    \IPKF\Database\Seeds\WorkManagementPermissionsSeeder::class,
                    \IPKF\Database\Seeds\CorrespondenceDocumentTemplateSeeder::class,
                    \IPKF\Database\Seeds\PlatformCommercialFoundationSeeder::class,
                    \IPKF\Database\Seeds\NotificationCoreSeeder::class,
                    \IPKF\Database\Seeds\CommunicationCenterSeeder::class,
                ],
            ],
            'automation' => [
                'connection' => 'automation.primary',
                'seeders' => [
                    \IPKF\Database\Seeds\AutomationCorrespondenceSeeder::class,
                    \IPKF\Database\Seeds\CorrespondenceDocumentTemplateSeeder::class,
                ],
            ],
            'work' => [
                'connection' => 'work.primary',
                'seeders' => [
                    \IPKF\Database\Seeds\WorkManagementFoundationSeeder::class,
                    \IPKF\Database\Seeds\WorkReferenceDataSeeder::class,
                ],
            ],
            'ticketing' => [
                'connection' => 'ticketing.primary',
                'seeders' => [
                ],
            ],
        ];
    }

    public function seedersFor(string $application): array
    {
        $group = $this->groups()[$application] ?? null;

        if ($group === null) {
            return [];
        }

        $pdo = $this->connections->resolve($group['connection']);

        return array_map(
            static fn (string $class): Seeder => new $class($pdo),
            $group['seeders']
        );
    }
}
