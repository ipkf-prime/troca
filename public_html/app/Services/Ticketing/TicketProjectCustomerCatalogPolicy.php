<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use PDO;

/**
 * Project customer identity is explicit and distinct from installation,
 * Realm, organization membership, and Core catalog organizational ownership.
 * No customer/catalog permission is inferred from "shared".
 */
final class TicketProjectCustomerCatalogPolicy
{
    public function __construct(
        private PDO $core,
        private PDO $ticketing
    ) {
    }

    public function customerReference(int $projectId): ?string
    {
        if ($projectId < 1) {
            return null;
        }

        $statement = $this->ticketing->prepare("
            SELECT ownership.customer_reference
            FROM ticketing_project_customer_ownership ownership
            INNER JOIN ticketing_support_projects projects
                ON projects.id = ownership.project_id
            WHERE ownership.project_id = ?
              AND projects.archived_at IS NULL
              AND projects.is_active = 1
            LIMIT 1
        ");
        $statement->execute([$projectId]);
        $reference = trim((string) ($statement->fetchColumn() ?: ''));
        if ($reference === '') {
            return null;
        }

        $active = $this->core->prepare("
            SELECT 1 FROM platform_customers
            WHERE public_reference = ? AND status = 'active'
            LIMIT 1
        ");
        $active->execute([$reference]);

        return $active->fetchColumn() ? $reference : null;
    }

    /**
     * @param list<string>|null $references Null means all allowed catalogs.
     * @return array<string,array<string,string>> keyed by Core public ref.
     */
    public function allowedCatalogMap(int $projectId, ?array $references = null): array
    {
        $customer = $this->customerReference($projectId);
        if ($customer === null) {
            return [];
        }

        if ($references !== null) {
            $references = array_values(array_unique(array_filter(
                array_map(static fn ($r): string => trim((string) $r), $references),
                static fn (string $r): bool => $r !== ''
            )));
            if ($references === []) {
                return [];
            }
        }

        $sql = "
            SELECT catalog.public_reference, catalog.code, catalog.title
            FROM organization_catalogs catalog
            WHERE catalog.status = 'active'
              AND (
                  EXISTS (
                      SELECT 1 FROM platform_customer_catalog_owners owners
                      WHERE owners.catalog_reference = catalog.public_reference
                        AND owners.customer_reference = ?
                  )
                  OR EXISTS (
                      SELECT 1 FROM platform_customer_catalog_grants grants
                      WHERE grants.catalog_reference = catalog.public_reference
                        AND grants.customer_reference = ?
                        AND grants.status = 'active'
                  )
              )
        ";
        $parameters = [$customer, $customer];
        if ($references !== null) {
            $sql .= ' AND catalog.public_reference IN ('
                . implode(',', array_fill(0, count($references), '?')) . ')';
            array_push($parameters, ...$references);
        }
        $sql .= ' ORDER BY catalog.title, catalog.code, catalog.id';

        $statement = $this->core->prepare($sql);
        $statement->execute($parameters);
        $result = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $reference = (string) $row['public_reference'];
            $result[$reference] = $row;
        }
        return $result;
    }
}
