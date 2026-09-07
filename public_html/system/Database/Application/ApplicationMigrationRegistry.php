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
                    \IPKF\Database\Migrations\RepairAppSettingsUtf8mb4::class,
                    \IPKF\Database\Migrations\AddScopedAdminThemeSettings::class,
                    \IPKF\Database\Migrations\AddDefaultRoleAssignmentSelection::class,
                    \IPKF\Database\Migrations\CreateDynamicScopedAccessFoundation::class,
                    \IPKF\Database\Migrations\SeedExistingDynamicRoleGovernance::class,
                    \IPKF\Database\Migrations\EnsureTransactionalIdentityAccessGovernanceWriteSet::class,
                    \IPKF\Database\Migrations\AddRoleAssignmentLifecycleGovernance::class,
                    \IPKF\Database\Migrations\CreateUserInvitationFoundationTables::class,
                    \IPKF\Database\Migrations\AddRoleKindSortOrder::class,
                    \IPKF\Database\Migrations\CreatePublicLandingFoundation::class,
                    \IPKF\Database\Migrations\PromotePublicLandingIdentityToSystemTheme::class,
                    \IPKF\Database\Migrations\CreatePublicRuntimePresence::class,
                    \IPKF\Database\Migrations\CreateAdminUsersOrganizationTables::class,
                    \IPKF\Database\Migrations\CreateExtendedPersonDataTables::class,
                    \IPKF\Database\Migrations\CreateExternalOrganizationCorrespondenceDirectory::class,
                    \IPKF\Database\Migrations\AddStructuredExternalOrganizationPhoneFields::class,
                    \IPKF\Database\Migrations\CreateDynamicOrganizationCoreTables::class,
                    \IPKF\Database\Migrations\CreateOrganizationCatalogMembershipFoundation::class,
                    \IPKF\Database\Migrations\CreateDynamicGeographyTables::class,
                    \IPKF\Database\Migrations\CreateMultiSourceCodingGeographyTables::class,
                    \IPKF\Database\Migrations\CreateMinistryGeographyImportMetadata::class,
                    \IPKF\Database\Migrations\CreateStatisticalCenterGeographyImportMetadata::class,
                    \IPKF\Database\Migrations\CreateMinistrySciGeographyCrosswalkTables::class,
                    \IPKF\Database\Migrations\CreateMinistryCanonicalGeographyTables::class,
                    \IPKF\Database\Migrations\HardenMinistryCanonicalGeographyApply::class,
                    \IPKF\Database\Migrations\CreateAutomationCorrespondenceFoundationTables::class,
                    \IPKF\Database\Migrations\CreateCorrespondenceDocumentTemplateTables::class,
                    \IPKF\Database\Migrations\CreateEnterpriseAutomationSecretariatFoundation::class,
                    \IPKF\Database\Migrations\AddRegistryBookPublicReference::class,
                    \IPKF\Database\Migrations\CreateRegistryBookDirections::class,
                    \IPKF\Database\Migrations\AddRegistryNumberReservationIdempotency::class,
                    \IPKF\Database\Migrations\CreatePlatformCommercialFoundationTables::class,
                    \IPKF\Database\Migrations\CreateApplicationMigrationHistoryTable::class,
                    \IPKF\Database\Migrations\CreateApplicationModuleRegistryTable::class,
                    \IPKF\Database\Migrations\ExtendApplicationModuleRegistryRuntimeConfig::class,
                    \IPKF\Database\Migrations\CreateAuthenticationLoginHistoryTable::class,
                    \IPKF\Database\Migrations\RepairPersonAddressReferenceDataAndGeography::class,
                    \IPKF\Database\Migrations\CreateNotificationCoreFoundationTables::class,
                    \IPKF\Database\Migrations\SeedDynamicAuthMembershipMessageTemplates::class,
                    \IPKF\Database\Migrations\CreateDynamicMessageTemplateManagement::class,
                    \IPKF\Database\Migrations\CreatePublicRegistrationOtpFoundation::class,
                    \IPKF\Database\Migrations\CreateCommunicationCenterFoundationTables::class,
                    \IPKF\Database\Migrations\ExposeAutomationSecretariatNavigation::class,
                    \IPKF\Database\Migrations\ExposeTicketingNavigation::class,
                    \IPKF\Database\Migrations\ExposeTicketingModuleShellNavigation::class,
                    \IPKF\Database\Migrations\EnableTicketingProjectManagement::class,
                    \IPKF\Database\Migrations\EnableTicketingTopologyManagementRoutes::class,
                    \IPKF\Database\Migrations\EnableTicketingTopicRoutingManagementRoutes::class,
                    \IPKF\Database\Migrations\EnableTicketingStaffOperations::class,
                    \IPKF\Database\Migrations\EnableTicketingLifecycleOperations::class,
                    \IPKF\Database\Migrations\EnableTicketingRequesterReplyOperations::class,
                    \IPKF\Database\Migrations\EnableTicketingResolutionLifecycleOperations::class,
                    \IPKF\Database\Migrations\EnableTicketingStatusTitleManagement::class,
                    \IPKF\Database\Migrations\EnableTicketingParticipantDirectoryNavigation::class,
                    \IPKF\Database\Migrations\ExtendAdminNavigationCoreFeatureMetadata::class,
                    \IPKF\Database\Migrations\CreateSecureMessageExtensionTables::class,
                    \IPKF\Database\Migrations\ExtendNotificationProviderManagement::class,
                    \IPKF\Database\Migrations\EnableNotificationProviderManagementCrud::class,
                    \IPKF\Database\Migrations\ExpandNotificationProviderCatalog::class,
                    \IPKF\Database\Migrations\ExtendEmailProviderSenderIdentity::class,
                    \IPKF\Database\Migrations\EnableNotificationProviderTestSend::class,
                    \IPKF\Database\Migrations\EnableNotificationProviderExtendedTestSend::class,
                    \IPKF\Database\Migrations\EnableNotificationProviderDefaultManagement::class,
                    \IPKF\Database\Migrations\CreateNotificationGatewayFoundation::class,
                    \IPKF\Database\Migrations\EnableNotificationSendCenterFoundation::class,
                    \IPKF\Database\Migrations\EnableNotificationSendExperienceAndBaleEnrollment::class,
                ],
            ],
            'automation' => [
                'connection' => 'automation.primary',
                'migrations' => [
                    \IPKF\Database\Migrations\CreateStandaloneAutomationCorrespondenceFoundationTables::class,
                    \IPKF\Database\Migrations\CreateCorrespondenceDocumentTemplateTables::class,
                    \IPKF\Database\Migrations\CreateEnterpriseAutomationSecretariatFoundation::class,
                    \IPKF\Database\Migrations\AddRegistryBookPublicReference::class,
                    \IPKF\Database\Migrations\CreateRegistryBookDirections::class,
                    \IPKF\Database\Migrations\AddRegistryNumberReservationIdempotency::class,
                    \IPKF\Database\Migrations\AddExternalDirectoryReferencesToCorrespondenceParties::class,
                    \IPKF\Database\Migrations\CreateAutomationCorrespondenceDispatchFoundation::class,
                ],
            ],
            'work' => [
                'connection' => 'work.primary',
                'migrations' => [
                    \IPKF\Database\Migrations\CreateWorkManagementFoundationTables::class,
                    \IPKF\Database\Migrations\CreateModuleReferenceDataTables::class,
                ],
            ],
            'ticketing' => [
                'connection' => 'ticketing.primary',
                'migrations' => [
                    \IPKF\Database\Migrations\CreateTicketingDomainFoundationTables::class,
                    \IPKF\Database\Migrations\CreateTicketingSupportProjectFoundation::class,
                    \IPKF\Database\Migrations\CreateTicketingParticipantDirectoryFoundation::class,
                    \IPKF\Database\Migrations\CreateTicketingProjectOrganizationScopeFoundation::class,
                    \IPKF\Database\Migrations\CreateTicketingDynamicSupportTopologyFoundation::class,
                    \IPKF\Database\Migrations\CreateTicketingDynamicTopicRoutingFoundation::class,
                    \IPKF\Database\Migrations\CreateTicketingSlaFoundation::class,
                    \IPKF\Database\Migrations\ExtendTicketingSlaPolicyScopes::class,
                    \IPKF\Database\Migrations\CreateTicketingProjectAwareTicketNumber::class,
                    \IPKF\Database\Migrations\CreateSchedulerFoundation::class,
                    \IPKF\Database\Migrations\CreateTicketingRequesterOnboardingFoundation::class,
                    \IPKF\Database\Migrations\CreateTicketingDynamicMembershipFormFoundation::class,
                    \IPKF\Database\Migrations\CreateTicketingDynamicScopeDimensionFoundation::class,
                    \IPKF\Database\Migrations\CreateTicketingScopeSubjectFactsFoundation::class,
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
