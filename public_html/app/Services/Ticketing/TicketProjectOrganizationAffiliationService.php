<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use App\Services\Organization\OrganizationalAffiliationService;
use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;

/**
 * Project-local consumption of canonical Core organizational affiliation.
 *
 * Core is authoritative for:
 * - organization
 * - catalog / network
 * - user organizational membership
 *
 * Ticketing is authoritative for:
 * - project membership
 * - project role
 * - project access scope
 *
 * This service never derives Ticketing role from organizational role.
 */
final class TicketProjectOrganizationAffiliationService
{
    private PDO $core;

    private PDO $ticketing;

    private TicketProjectCustomerCatalogPolicy $customerCatalogPolicy;


    public function __construct(
        ?ConnectionResolver $resolver = null,
        ?PDO $ticketing = null,
        ?PDO $core = null
    ) {
        $resolver ??=
            new ConnectionResolver();

        $this->core =
            $core
            ?? $resolver->resolve(
                'core.primary'
            );

        $this->ticketing =
            $ticketing
            ?? $resolver->resolve(
                'ticketing.primary'
            );

        $this->customerCatalogPolicy = new TicketProjectCustomerCatalogPolicy(
            $this->core, $this->ticketing
        );
    }


    public function catalogOptions(int $projectId = 0): array
    {
        return array_values(
            $this->customerCatalogPolicy->allowedCatalogMap($projectId)
        );
    }


    public function configuration(
        int $projectId
    ): array {
        if ($projectId < 1) {
            return [
                'catalog_options' => [],
                'selected_catalog_references' => [],
                'primary_catalog_reference' => '',
            ];
        }

        $statement =
            $this->ticketing
                ->prepare("
                    SELECT
                        core_catalog_reference,
                        is_primary
                    FROM
                        ticketing_project_catalog_bindings
                    WHERE project_id = ?
                      AND binding_role_code =
                            'organization_context'
                      AND status = 'active'
                    ORDER BY
                        is_primary DESC,
                        id
                ");

        $statement->execute([
            $projectId,
        ]);

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        $allowed = $this->customerCatalogPolicy->allowedCatalogMap($projectId);
        $selected = [];
        $primary = '';

        foreach ($rows as $row) {
            $reference =
                trim(
                    (string) (
                        $row[
                            'core_catalog_reference'
                        ]
                        ?? ''
                    )
                );

            if ($reference === '' || !isset($allowed[$reference])) {
                continue;
            }

            $selected[] =
                $reference;

            if (
                $primary === ''
                &&
                (int) (
                    $row[
                        'is_primary'
                    ]
                    ?? 0
                ) === 1
            ) {
                $primary =
                    $reference;
            }
        }

        $selected =
            array_values(
                array_unique(
                    $selected
                )
            );

        if (
            $primary === ''
            &&
            $selected !== []
        ) {
            $primary =
                (string) $selected[0];
        }

        return [
            'catalog_options' =>
                $this->catalogOptions($projectId),

            'selected_catalog_references' =>
                $selected,

            'primary_catalog_reference' =>
                $primary,

            'catalog_scope_review_required' =>
                $this->hasUnverifiedExistingBindings($projectId),
        ];
    }


    public function normalizeSelection(
        mixed $references,
        mixed $primaryReference
    ): array {
        if (!is_array($references)) {
            $references =
                $references === null
                    || $references === ''
                    ? []
                    : [$references];
        }

        $selected = [];

        foreach ($references as $reference) {
            $reference =
                trim(
                    (string) $reference
                );

            if ($reference === '') {
                continue;
            }

            $selected[] =
                mb_substr(
                    $reference,
                    0,
                    100
                );
        }

        $selected =
            array_values(
                array_unique(
                    $selected
                )
            );

        $primary =
            trim(
                (string) $primaryReference
            );

        if ($primary !== '') {
            $primary =
                mb_substr(
                    $primary,
                    0,
                    100
                );
        }

        if ($selected === []) {
            $primary = '';
        } elseif ($primary === '') {
            $primary =
                (string) $selected[0];
        }

        return [
            'references' =>
                $selected,

            'primary_reference' =>
                $primary,
        ];
    }


    public function selectionErrors(
        array $selection,
        int $projectId = 0
    ): array {
        $references =
            is_array(
                $selection[
                    'references'
                ]
                ?? null
            )
                ? array_values(
                    $selection[
                        'references'
                    ]
                )
                : [];

        $primary =
            trim(
                (string) (
                    $selection[
                        'primary_reference'
                    ]
                    ?? ''
                )
            );

        // Preserve old bindings until an administrator explicitly resolves
        // their customer ownership. An empty HTML selection is not consent
        // to erase bindings hidden by the new customer-scoped options.
        if ($this->hasUnverifiedExistingBindings($projectId)) {
            return [
                'organization_catalogs' =>
                    'project_organization_legacy_binding_requires_review',
            ];
        }

        if ($references === []) {
            return $this->customerCatalogPolicy->customerReference($projectId) === null
                ? ['organization_catalogs' => 'project_customer_unresolved']
                : [];
        }

        if (
            $primary === ''
            ||
            !in_array(
                $primary,
                $references,
                true
            )
        ) {
            return [
                'organization_catalogs' =>
                    'شبکه سازمانی اصلی باید یکی از شبکه‌های انتخاب‌شده باشد.',
            ];
        }

        $catalogs = $this->customerCatalogPolicy->allowedCatalogMap(
            $projectId, $references
        );

        foreach ($references as $reference) {
            if (!isset($catalogs[$reference])) {
                return [
                    'organization_catalogs' =>
                        'یکی از شبکه‌های سازمانی انتخاب‌شده معتبر یا فعال نیست.',
                ];
            }
        }

        return [];
    }


    public function replaceProjectBindings(
        int $projectId,
        array $selection,
        string $actorReference
    ): void {
        if ($projectId < 1) {
            throw new RuntimeException(
                'project_organization_project_invalid'
            );
        }

        $errors =
            $this->selectionErrors(
                $selection,
                $projectId
            );

        if ($errors !== []) {
            throw new RuntimeException(
                'project_organization_catalog_invalid'
            );
        }

        $references =
            array_values(
                $selection[
                    'references'
                ]
                ?? []
            );

        $primary =
            trim(
                (string) (
                    $selection[
                        'primary_reference'
                    ]
                    ?? ''
                )
            );

        $catalogMap = $this->customerCatalogPolicy->allowedCatalogMap(
            $projectId, $references
        );

        if (count($catalogMap) !== count($references)) {
            throw new RuntimeException('project_organization_catalog_access_denied');
        }

        /*
         * Preserve rows for audit/history but make the current
         * project context explicit through active bindings only.
         */
        $disable =
            $this->ticketing
                ->prepare("
                    UPDATE
                        ticketing_project_catalog_bindings
                    SET
                        status = 'inactive',
                        is_primary = 0,
                        updated_by_user_reference = ?,
                        updated_at = UTC_TIMESTAMP()
                    WHERE project_id = ?
                      AND binding_role_code =
                            'organization_context'
                ");

        $disable->execute([
            $actorReference,
            $projectId,
        ]);

        if ($references === []) {
            return;
        }

        $upsert =
            $this->ticketing
                ->prepare("
                    INSERT INTO
                        ticketing_project_catalog_bindings
                    (
                        public_reference,
                        project_id,
                        core_catalog_reference,
                        catalog_code_snapshot,
                        catalog_title_snapshot,
                        binding_role_code,
                        is_primary,
                        status,
                        created_by_user_reference,
                        updated_by_user_reference,
                        created_at,
                        updated_at
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        'organization_context',
                        ?,
                        'active',
                        ?,
                        ?,
                        UTC_TIMESTAMP(),
                        UTC_TIMESTAMP()
                    )

                    ON DUPLICATE KEY UPDATE

                        catalog_code_snapshot =
                            VALUES(
                                catalog_code_snapshot
                            ),

                        catalog_title_snapshot =
                            VALUES(
                                catalog_title_snapshot
                            ),

                        is_primary =
                            VALUES(
                                is_primary
                            ),

                        status =
                            'active',

                        updated_by_user_reference =
                            VALUES(
                                updated_by_user_reference
                            ),

                        updated_at =
                            UTC_TIMESTAMP()
                ");

        foreach ($references as $reference) {
            $catalog =
                $catalogMap[$reference]
                ?? null;

            if (!is_array($catalog)) {
                throw new RuntimeException(
                    'project_organization_catalog_invalid'
                );
            }

            $upsert->execute([
                'TPCB-'
                    . strtoupper(
                        bin2hex(
                            random_bytes(10)
                        )
                    ),

                $projectId,

                $reference,

                (string) (
                    $catalog['code']
                    ?? ''
                ),

                (string) (
                    $catalog['title']
                    ?? ''
                ),

                hash_equals(
                    $primary,
                    $reference
                )
                    ? 1
                    : 0,

                $actorReference,
                $actorReference,
            ]);
        }
    }


    public function verifiedAffiliationsForUser(
        int $userId
    ): array {
        if ($userId < 1) {
            return [];
        }

        $statement =
            $this->core
                ->prepare("
                    SELECT
                        memberships.id,
                        memberships.public_reference
                            AS membership_reference,

                        memberships.organization_id,
                        memberships.role_code
                            AS organization_role_code,

                        memberships.is_primary,

                        organizations.public_reference
                            AS organization_reference,

                        COALESCE(
                            NULLIF(
                                organizations.title_fa,
                                ''
                            ),
                            organizations.title
                        ) AS organization_title

                    FROM organization_memberships
                        AS memberships

                    INNER JOIN organizations
                        ON organizations.id =
                            memberships.organization_id

                    WHERE memberships.user_id = ?
                      AND memberships.status =
                            'active'
                      AND memberships.verification_state_code =
                            'verified'

                      AND (
                            memberships.valid_from
                                IS NULL
                            OR
                            memberships.valid_from
                                <= CURRENT_DATE
                      )

                      AND (
                            memberships.valid_until
                                IS NULL
                            OR
                            memberships.valid_until
                                >= CURRENT_DATE
                      )

                      AND organizations.is_active = 1

                    ORDER BY
                        memberships.is_primary DESC,
                        organization_title,
                        memberships.id
                ");

        $statement->execute([
            $userId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }


    public function projectAffiliationOptions(
        int $projectId,
        int $userId
    ): array {
        $catalogReferences =
            $this->boundCatalogReferences(
                $projectId
            );

        if ($this->hasUnverifiedExistingBindings($projectId)) {
            return [
                'required' => true,
                'catalog_references' => [],
                'items' => [],
            ];
        }

        if ($catalogReferences === []) {
            // A legacy binding without confirmed customer authorization must
            // NOT silently turn an organization-scoped project into an open one.
            return [
                'required' => $this->hasAnyBoundCatalog($projectId),
                'catalog_references' => [],
                'items' => [],
            ];
        }

        return [
            'required' => true,

            'catalog_references' =>
                $catalogReferences,

            'items' =>
                $this->verifiedAffiliationsForCatalogs(
                    $userId,
                    $catalogReferences
                ),
        ];
    }


    public function resolveForProject(
        int $projectId,
        int $userId,
        ?string $requestedMembershipReference
            = null
    ): ?array {
        $context =
            $this->projectAffiliationOptions(
                $projectId,
                $userId
            );

        if (empty($context['required'])) {
            return null;
        }

        $items =
            is_array(
                $context['items']
                ?? null
            )
                ? array_values(
                    $context['items']
                )
                : [];

        if ($items === []) {
            throw new RuntimeException(
                'requester_affiliation_required'
            );
        }

        $requested =
            trim(
                (string) (
                    $requestedMembershipReference
                    ?? ''
                )
            );

        if ($requested !== '') {
            foreach ($items as $item) {
                $candidate =
                    trim(
                        (string) (
                            $item[
                                'membership_reference'
                            ]
                            ?? ''
                        )
                    );

                if (
                    $candidate !== ''
                    &&
                    hash_equals(
                        $candidate,
                        $requested
                    )
                ) {
                    return $item;
                }
            }

            throw new RuntimeException(
                'requester_affiliation_invalid'
            );
        }

        if (count($items) === 1) {
            return $items[0];
        }

        throw new RuntimeException(
            'requester_affiliation_selection_required'
        );
    }


    public function applyToProjectMember(
        int $memberId,
        ?array $affiliation,
        string $actorReference,
        bool $allowChange
    ): void {
        /*
         * An organization-agnostic project does not rewrite historical
         * organization snapshots.
         */
        if ($affiliation === null) {
            return;
        }

        if ($memberId < 1) {
            throw new RuntimeException(
                'requester_membership_not_found'
            );
        }

        $statement =
            $this->ticketing
                ->prepare("
                    SELECT
                        id,
                        core_organization_membership_reference,
                        organization_reference
                    FROM
                        ticketing_support_project_members
                    WHERE id = ?
                    LIMIT 1
                    FOR UPDATE
                ");

        $statement->execute([
            $memberId,
        ]);

        $member =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        if (!is_array($member)) {
            throw new RuntimeException(
                'requester_membership_not_found'
            );
        }

        $newMembershipReference =
            trim(
                (string) (
                    $affiliation[
                        'membership_reference'
                    ]
                    ?? ''
                )
            );

        $newOrganizationReference =
            trim(
                (string) (
                    $affiliation[
                        'organization_reference'
                    ]
                    ?? ''
                )
            );

        if (
            $newMembershipReference === ''
            ||
            $newOrganizationReference === ''
        ) {
            throw new RuntimeException(
                'requester_affiliation_invalid'
            );
        }

        $currentMembershipReference =
            trim(
                (string) (
                    $member[
                        'core_organization_membership_reference'
                    ]
                    ?? ''
                )
            );

        if (
            !$allowChange
            &&
            $currentMembershipReference !== ''
            &&
            !hash_equals(
                $currentMembershipReference,
                $newMembershipReference
            )
        ) {
            throw new RuntimeException(
                'requester_affiliation_change_requires_management'
            );
        }

        $update =
            $this->ticketing
                ->prepare("
                    UPDATE
                        ticketing_support_project_members
                    SET
                        core_organization_membership_reference = ?,
                        organization_reference = ?,
                        organization_title_snapshot = ?,
                        organization_role_code_snapshot = ?,
                        updated_by_user_reference = ?,
                        updated_at = UTC_TIMESTAMP()
                    WHERE id = ?
                ");

        $update->execute([
            $newMembershipReference,

            $newOrganizationReference,

            (string) (
                $affiliation[
                    'organization_title'
                ]
                ?? ''
            ),

            (string) (
                $affiliation[
                    'organization_role_code'
                ]
                ?? ''
            ),

            $actorReference,
            $memberId,
        ]);
    }


    /**
     * Project-scoped organization selection for requester onboarding.
     * The project/customer/catalog policy remains authoritative.
     */
    public function affiliationRequestContext(
        int $projectId,
        int $userId
    ): array {
        $catalogReferences =
            $this->boundCatalogReferences($projectId);

        if ($projectId < 1 || $userId < 1 || $catalogReferences === []) {
            return [
                'identity_ready' => false,
                'organizations' => [],
                'positions' => [],
                'pending' => [],
            ];
        }

        $identity = $this->core->prepare("\n            SELECT users.person_id, persons.public_reference AS person_reference\n            FROM users\n            LEFT JOIN persons ON persons.id = users.person_id\n            WHERE users.id = ?\n              AND users.status = 'active'\n              AND users.deleted_at IS NULL\n            LIMIT 1\n        ");
        $identity->execute([$userId]);
        $identityRow = $identity->fetch(PDO::FETCH_ASSOC);
        $identityReady =
            is_array($identityRow)
            && (int)($identityRow['person_id'] ?? 0) > 0
            && trim((string)($identityRow['person_reference'] ?? '')) !== '';

        $marks = implode(',', array_fill(0, count($catalogReferences), '?'));

        $organizations = $this->core->prepare("\n            SELECT DISTINCT\n                organizations.id,\n                organizations.public_reference,\n                COALESCE(NULLIF(organizations.title_fa, ''), organizations.title) AS title\n            FROM organization_catalog_entries AS entries\n            INNER JOIN organization_catalogs AS catalogs\n              ON catalogs.id = entries.catalog_id\n             AND catalogs.status = 'active'\n            INNER JOIN organizations\n              ON organizations.id = entries.organization_id\n             AND organizations.is_active = 1\n             AND organizations.deleted_at IS NULL\n            WHERE entries.status = 'active'\n              AND catalogs.public_reference IN ({$marks})\n            ORDER BY title, organizations.id\n        ");
        $organizations->execute($catalogReferences);
        $organizationRows = $organizations->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $organizationIds = array_values(array_filter(array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            $organizationRows
        ), static fn(int $id): bool => $id > 0));

        $positions = [];
        if ($organizationIds !== []) {
            $idMarks = implode(',', array_fill(0, count($organizationIds), '?'));
            $positionStatement = $this->core->prepare("\n                SELECT\n                    org_positions.public_reference,\n                    organizations.public_reference AS organization_reference,\n                    COALESCE(\n                        NULLIF(org_positions.title_fa, ''),\n                        NULLIF(org_positions.title_override, ''),\n                        position_catalog.title\n                    ) AS title,\n                    COALESCE(NULLIF(org_units.title_fa, ''), org_units.title) AS unit_title\n                FROM organization_positions AS org_positions\n                INNER JOIN organizations\n                  ON organizations.id = org_positions.organization_id\n                INNER JOIN positions AS position_catalog\n                  ON position_catalog.id = org_positions.position_id\n                LEFT JOIN org_units ON org_units.id = org_positions.org_unit_id\n                WHERE org_positions.status = 'active'\n                  AND organizations.is_active = 1\n                  AND org_positions.organization_id IN ({$idMarks})\n                ORDER BY organizations.id, unit_title, title, org_positions.id\n            ");
            $positionStatement->execute($organizationIds);
            $positions = $positionStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $pending = $this->core->prepare("\n            SELECT DISTINCT\n                memberships.public_reference AS membership_reference,\n                organizations.public_reference AS organization_reference,\n                COALESCE(NULLIF(organizations.title_fa, ''), organizations.title) AS organization_title\n            FROM organization_memberships AS memberships\n            INNER JOIN organizations\n              ON organizations.id = memberships.organization_id\n            INNER JOIN organization_catalog_entries AS entries\n              ON entries.organization_id = organizations.id\n             AND entries.status = 'active'\n            INNER JOIN organization_catalogs AS catalogs\n              ON catalogs.id = entries.catalog_id\n             AND catalogs.status = 'active'\n            INNER JOIN organization_membership_verifications AS verifications\n              ON verifications.membership_id = memberships.id\n             AND verifications.verification_type_code = 'organizational_affiliation'\n             AND verifications.status_code = 'pending'\n            WHERE memberships.user_id = ?\n              AND memberships.status = 'active'\n              AND memberships.verification_state_code = 'unverified'\n              AND organizations.is_active = 1\n              AND catalogs.public_reference IN ({$marks})\n            ORDER BY memberships.id DESC\n        ");
        $pending->execute(array_merge([$userId], $catalogReferences));

        return [
            'identity_ready' => $identityReady,
            'organizations' => $organizationRows,
            'positions' => $positions,
            'pending' => $pending->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ];
    }


    public function requestAffiliationForProject(
        int $projectId,
        int $userId,
        array $input
    ): string {
        $context = $this->affiliationRequestContext($projectId, $userId);

        if (empty($context['identity_ready'])) {
            throw new RuntimeException('affiliation_person_required');
        }

        $organizationReference = trim((string)($input['organization_reference'] ?? ''));
        $allowed = false;

        foreach ((array)($context['organizations'] ?? []) as $organization) {
            $candidate = trim((string)($organization['public_reference'] ?? ''));
            if ($candidate !== '' && $organizationReference !== '' && hash_equals($candidate, $organizationReference)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            throw new RuntimeException('requester_affiliation_organization_invalid');
        }

        return (new OrganizationalAffiliationService($this->core))->request(
            $userId,
            [
                'organization_reference' => $organizationReference,
                'position_reference' => trim((string)($input['position_reference'] ?? '')),
                'is_primary' => !empty($input['is_primary']),
            ]
        );
    }


    private function boundCatalogReferences(
        int $projectId
    ): array {
        if ($projectId < 1) {
            return [];
        }

        $statement =
            $this->ticketing
                ->prepare("
                    SELECT
                        core_catalog_reference
                    FROM
                        ticketing_project_catalog_bindings
                    WHERE project_id = ?
                      AND binding_role_code =
                            'organization_context'
                      AND status = 'active'
                    ORDER BY
                        is_primary DESC,
                        id
                ");

        $statement->execute([
            $projectId,
        ]);

        $references = [];

        foreach (
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: []
            as $row
        ) {
            $reference =
                trim(
                    (string) (
                        $row[
                            'core_catalog_reference'
                        ]
                        ?? ''
                    )
                );

            if ($reference !== '') {
                $references[] =
                    $reference;
            }
        }

        $allowed = $this->customerCatalogPolicy->allowedCatalogMap(
            $projectId, $references
        );
        return array_values(array_filter(
            array_unique($references),
            static fn (string $reference): bool => isset($allowed[$reference])
        ));
    }


    private function hasUnverifiedExistingBindings(int $projectId): bool
    {
        if ($projectId < 1) {
            return false;
        }
        $statement = $this->ticketing->prepare("
            SELECT core_catalog_reference
            FROM ticketing_project_catalog_bindings
            WHERE project_id = ? AND binding_role_code = 'organization_context'
              AND status = 'active'
        ");
        $statement->execute([$projectId]);
        $references = array_values(array_unique(array_filter(array_map(
            static fn ($r): string => trim((string) $r),
            $statement->fetchAll(PDO::FETCH_COLUMN) ?: []
        ))));
        if ($references === []) {
            return false;
        }
        $allowed = $this->customerCatalogPolicy->allowedCatalogMap(
            $projectId, $references
        );
        return count($allowed) !== count($references);
    }


    private function hasAnyBoundCatalog(int $projectId): bool
    {
        if ($projectId < 1) {
            return false;
        }
        $statement = $this->ticketing->prepare("
            SELECT 1 FROM ticketing_project_catalog_bindings
            WHERE project_id = ? AND binding_role_code = 'organization_context'
              AND status = 'active' LIMIT 1
        ");
        $statement->execute([$projectId]);
        return (bool) $statement->fetchColumn();
    }


    private function verifiedAffiliationsForCatalogs(
        int $userId,
        array $catalogReferences
    ): array {
        if (
            $userId < 1
            ||
            $catalogReferences === []
        ) {
            return [];
        }

        $catalogReferences =
            array_values(
                array_unique(
                    array_filter(
                        array_map(
                            static fn ($value): string =>
                                trim(
                                    (string) $value
                                ),
                            $catalogReferences
                        ),
                        static fn (
                            string $value
                        ): bool =>
                            $value !== ''
                    )
                )
            );

        if ($catalogReferences === []) {
            return [];
        }

        $placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count(
                        $catalogReferences
                    ),
                    '?'
                )
            );

        $sql = "
            SELECT DISTINCT
                memberships.id,
                memberships.public_reference
                    AS membership_reference,

                memberships.organization_id,
                memberships.role_code
                    AS organization_role_code,

                memberships.is_primary,

                organizations.public_reference
                    AS organization_reference,

                COALESCE(
                    NULLIF(
                        organizations.title_fa,
                        ''
                    ),
                    organizations.title
                ) AS organization_title

            FROM organization_memberships
                AS memberships

            INNER JOIN organizations
                ON organizations.id =
                    memberships.organization_id

            INNER JOIN
                organization_catalog_entries
                    AS entries
                ON entries.organization_id =
                    organizations.id
               AND entries.status =
                    'active'

            INNER JOIN
                organization_catalogs
                    AS catalogs
                ON catalogs.id =
                    entries.catalog_id
               AND catalogs.status =
                    'active'

            WHERE memberships.user_id = ?

              AND memberships.status =
                    'active'

              AND memberships.verification_state_code =
                    'verified'

              AND organizations.is_active = 1

              AND catalogs.public_reference
                    IN ($placeholders)

              AND (
                    memberships.valid_from
                        IS NULL
                    OR
                    memberships.valid_from
                        <= CURRENT_DATE
              )

              AND (
                    memberships.valid_until
                        IS NULL
                    OR
                    memberships.valid_until
                        >= CURRENT_DATE
              )

            ORDER BY
                memberships.is_primary DESC,
                organization_title,
                memberships.id
        ";

        $statement =
            $this->core
                ->prepare($sql);

        $statement->execute(
            array_merge(
                [$userId],
                $catalogReferences
            )
        );

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }


}
