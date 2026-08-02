<?php

namespace IPKF\Database\Application;

use IPKF\Database\Connections\ConnectionResolver;
use IPKF\Database\Migrations\Migration;

class ApplicationMigrationRegistry
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
                'migrations' => [
                    \IPKF\Database\Migrations\CreateRuntimeChecksTable::class,
                    \IPKF\Database\Migrations\CreateAuthRbacSchemaTables::class,
                    \IPKF\Database\Migrations\EnsureUtf8mb4AuthRbacTables::class,
                    \IPKF\Database\Migrations\CreateIdentityAccessFoundationTables::class,
                    \IPKF\Database\Migrations\CreateAdminPanelShellTables::class,
                    \IPKF\Database\Migrations\AddScopedAdminThemeSettings::class,
                    \IPKF\Database\Migrations\CreateAdminUsersOrganizationTables::class,
                    \IPKF\Database\Migrations\CreateExtendedPersonDataTables::class,
                    \IPKF\Database\Migrations\CreateDynamicOrganizationCoreTables::class,
                    \IPKF\Database\Migrations\CreateDynamicGeographyTables::class,
                    \IPKF\Database\Migrations\CreateMultiSourceCodingGeographyTables::class,
                    \IPKF\Database\Migrations\CreateMinistryGeographyImportMetadata::class,
                    \IPKF\Database\Migrations\CreateStatisticalCenterGeographyImportMetadata::class,
                    \IPKF\Database\Migrations\CreateMinistrySciGeographyCrosswalkTables::class,
                    \IPKF\Database\Migrations\CreateMinistryCanonicalGeographyTables::class,
                    \IPKF\Database\Migrations\HardenMinistryCanonicalGeographyApply::class,
                    \IPKF\Database\Migrations\CreateAutomationCorrespondenceFoundationTables::class,
                    \IPKF\Database\Migrations\CreateCorrespondenceDocumentTemplateTables::class,
                    \IPKF\Database\Migrations\CreatePlatformCommercialFoundationTables::class,
                    \IPKF\Database\Migrations\CreateApplicationMigrationHistoryTable::class,
                    \IPKF\Database\Migrations\CreateApplicationModuleRegistryTable::class,
                    \IPKF\Database\Migrations\ExtendApplicationModuleRegistryRuntimeConfig::class,
                    \IPKF\Database\Migrations\CreateAuthenticationLoginHistoryTable::class,
                    \IPKF\Database\Migrations\RepairPersonAddressReferenceDataAndGeography::class,
                    \IPKF\Database\Migrations\CreateNotificationCoreFoundationTables::class,
                    \IPKF\Database\Migrations\CreateCommunicationCenterFoundationTables::class,
                    \IPKF\Database\Migrations\CreateSecureMessageExtensionTables::class,
                ],
            ],
            'automation' => [
                'connection' => 'automation.primary',
                'migrations' => [
                    \IPKF\Database\Migrations\CreateStandaloneAutomationCorrespondenceFoundationTables::class,
                    \IPKF\Database\Migrations\CreateCorrespondenceDocumentTemplateTables::class,
                ],
            ],
            'work' => [
                'connection' => 'work.primary',
                'migrations' => [
                    \IPKF\Database\Migrations\CreateWorkManagementFoundationTables::class,
                    \IPKF\Database\Migrations\CreateModuleReferenceDataTables::class,
                ],
            ],
        ];
    }

    public function migrationsFor(string $application): array
    {
        $group = $this->groups()[$application] ?? null;

        if ($group === null) {
            return [];
        }

        $pdo = $this->connections->resolve($group['connection']);

        return array_map(
            static fn (string $class): Migration => new $class($pdo),
            $group['migrations']
        );
    }
}
