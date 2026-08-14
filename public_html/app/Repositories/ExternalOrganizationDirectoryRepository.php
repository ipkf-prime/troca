<?php

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

final class ExternalOrganizationDirectoryRepository
{
    private PDO $db;

    public function __construct(
        ?ConnectionResolver $connections = null
    ) {
        $this->db =
            ($connections ?? new ConnectionResolver())
                ->resolve('core.primary');
    }

    public function organizations(
        string $query = '',
        bool $includeInactive = false
    ): array {
        $where = [];
        $parameters = [];

        if (!$includeInactive) {
            $where[] = "status = 'active'";
        }

        if ($query !== '') {
            $like = '%' . $query . '%';

            $where[] = "(
                title_fa LIKE ?
                OR title_en LIKE ?
                OR short_title LIKE ?
                OR national_id LIKE ?
                OR registration_number LIKE ?
            )";

            array_push(
                $parameters,
                $like,
                $like,
                $like,
                $like,
                $like
            );
        }

        $sql = "
            SELECT
                id,
                public_reference,
                title_fa,
                title_en,
                short_title,
                national_id,
                registration_number,
                website_url,
                notes,
                status,
                created_at,
                updated_at
            FROM external_organizations
        ";

        if ($where !== []) {
            $sql .=
                ' WHERE '
                . implode(
                    ' AND ',
                    $where
                );
        }

        $sql .= "
            ORDER BY
                CASE
                    WHEN status = 'active'
                        THEN 0
                    ELSE 1
                END,
                title_fa,
                id
        ";

        $statement =
            $this->db->prepare(
                $sql
            );

        $statement->execute(
            $parameters
        );

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }

    public function organization(
        string $reference
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT *
                FROM external_organizations
                WHERE public_reference = ?
                LIMIT 1
            ");

        $statement->execute([
            $reference,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return
            is_array($row)
                ? $row
                : null;
    }

    public function createOrganization(
        array $data
    ): string {
        $statement =
            $this->db->prepare("
                INSERT INTO external_organizations (
                    public_reference,
                    title_fa,
                    title_en,
                    short_title,
                    national_id,
                    registration_number,
                    website_url,
                    notes,
                    status,
                    created_at,
                    updated_at
                ) VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'active',
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )
            ");

        $statement->execute([
            $data['public_reference'],
            $data['title_fa'],
            $data['title_en'],
            $data['short_title'],
            $data['national_id'],
            $data['registration_number'],
            $data['website_url'],
            $data['notes'],
        ]);

        return
            (string) $data[
                'public_reference'
            ];
    }

    public function updateOrganization(
        string $reference,
        array $data
    ): bool {
        $statement =
            $this->db->prepare("
                UPDATE external_organizations
                SET
                    title_fa = ?,
                    title_en = ?,
                    short_title = ?,
                    national_id = ?,
                    registration_number = ?,
                    website_url = ?,
                    notes = ?,
                    updated_at = UTC_TIMESTAMP()
                WHERE public_reference = ?
            ");

        $statement->execute([
            $data['title_fa'],
            $data['title_en'],
            $data['short_title'],
            $data['national_id'],
            $data['registration_number'],
            $data['website_url'],
            $data['notes'],
            $reference,
        ]);

        return
            $statement->rowCount() > 0
            || $this->organization(
                $reference
            ) !== null;
    }

    public function deactivateOrganization(
        string $reference
    ): bool {
        $statement =
            $this->db->prepare("
                UPDATE external_organizations
                SET
                    status = 'inactive',
                    updated_at = UTC_TIMESTAMP()
                WHERE public_reference = ?
                  AND status <> 'inactive'
            ");

        $statement->execute([
            $reference,
        ]);

        return
            $statement->rowCount() > 0;
    }

    public function contactPoints(
        int $organizationId
    ): array {
        $statement =
            $this->db->prepare("
                SELECT *
                FROM external_organization_contact_points
                WHERE external_organization_id = ?
                ORDER BY
                    CASE
                        WHEN status = 'active'
                            THEN 0
                        ELSE 1
                    END,
                    is_primary DESC,
                    title,
                    id
            ");

        $statement->execute([
            $organizationId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }

    public function contactPoint(
        string $reference
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT *
                FROM external_organization_contact_points
                WHERE public_reference = ?
                LIMIT 1
            ");

        $statement->execute([
            $reference,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return
            is_array($row)
                ? $row
                : null;
    }

    public function createContactPoint(
        int $organizationId,
        array $data
    ): string {
        $statement =
            $this->db->prepare("
                INSERT INTO external_organization_contact_points (
                    public_reference,
                    external_organization_id,
                    code,
                    title,
                    point_kind_code,
                    contact_person_name,
                    contact_person_title,
                    business_hours,
                    preferred_dispatch_channel_code,
                    is_primary,
                    status,
                    created_at,
                    updated_at
                ) VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'active',
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )
            ");

        $statement->execute([
            $data['public_reference'],
            $organizationId,
            $data['code'],
            $data['title'],
            $data['point_kind_code'],
            $data['contact_person_name'],
            $data['contact_person_title'],
            $data['business_hours'],
            $data['preferred_dispatch_channel_code'],
            $data['is_primary'],
        ]);

        return
            (string) $data[
                'public_reference'
            ];
    }

    public function updateContactPoint(
        string $reference,
        array $data
    ): bool {
        $statement =
            $this->db->prepare("
                UPDATE external_organization_contact_points
                SET
                    code = ?,
                    title = ?,
                    point_kind_code = ?,
                    contact_person_name = ?,
                    contact_person_title = ?,
                    business_hours = ?,
                    preferred_dispatch_channel_code = ?,
                    is_primary = ?,
                    updated_at = UTC_TIMESTAMP()
                WHERE public_reference = ?
            ");

        $statement->execute([
            $data['code'],
            $data['title'],
            $data['point_kind_code'],
            $data['contact_person_name'],
            $data['contact_person_title'],
            $data['business_hours'],
            $data['preferred_dispatch_channel_code'],
            $data['is_primary'],
            $reference,
        ]);

        return
            $statement->rowCount() > 0
            || $this->contactPoint(
                $reference
            ) !== null;
    }

    public function deactivateContactPoint(
        string $reference
    ): bool {
        $statement =
            $this->db->prepare("
                UPDATE external_organization_contact_points
                SET
                    status = 'inactive',
                    updated_at = UTC_TIMESTAMP()
                WHERE public_reference = ?
                  AND status <> 'inactive'
            ");

        $statement->execute([
            $reference,
        ]);

        return
            $statement->rowCount() > 0;
    }

    public function transaction(
        callable $operation
    ): mixed {
        $ownsTransaction =
            !$this->db->inTransaction();

        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $result =
                $operation();

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return $result;

        } catch (\Throwable $exception) {
            if (
                $ownsTransaction
                && $this->db->inTransaction()
            ) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function clearPrimaryContactPoints(
        int $organizationId,
        ?int $exceptContactPointId = null
    ): void {
        $sql = "
            UPDATE external_organization_contact_points
            SET
                is_primary = 0,
                updated_at = UTC_TIMESTAMP()
            WHERE external_organization_id = ?
              AND is_primary = 1
        ";

        $parameters = [
            $organizationId,
        ];

        if (
            $exceptContactPointId !== null
            && $exceptContactPointId > 0
        ) {
            $sql .= "
              AND id <> ?
            ";

            $parameters[] =
                $exceptContactPointId;
        }

        $statement =
            $this->db->prepare(
                $sql
            );

        $statement->execute(
            $parameters
        );
    }

    public function contactMethodByType(
        int $contactPointId,
        int $contactTypeId
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT *
                FROM external_organization_contact_methods
                WHERE contact_point_id = ?
                  AND contact_type_id = ?
                ORDER BY
                    CASE
                        WHEN status = 'active'
                            THEN 0
                        ELSE 1
                    END,
                    is_primary DESC,
                    id DESC
                LIMIT 1
            ");

        $statement->execute([
            $contactPointId,
            $contactTypeId,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return
            is_array($row)
                ? $row
                : null;
    }

    public function preferredAddressForPoint(
        int $contactPointId
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT *
                FROM external_organization_contact_addresses
                WHERE contact_point_id = ?
                ORDER BY
                    CASE
                        WHEN status = 'active'
                            THEN 0
                        ELSE 1
                    END,
                    is_primary DESC,
                    supports_dispatch DESC,
                    id DESC
                LIMIT 1
            ");

        $statement->execute([
            $contactPointId,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return
            is_array($row)
                ? $row
                : null;
    }

    public function contactTypes(): array
    {
        $statement =
            $this->db->query("
                SELECT
                    id,
                    code,
                    title,
                    channel,
                    sort_order
                FROM contact_types
                WHERE status = 'active'
                ORDER BY
                    sort_order,
                    id
            ");

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }

    public function contactType(
        string $code
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    id,
                    code,
                    title,
                    channel
                FROM contact_types
                WHERE code = ?
                  AND status = 'active'
                LIMIT 1
            ");

        $statement->execute([
            $code,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return
            is_array($row)
                ? $row
                : null;
    }

    public function contactMethods(
        int $contactPointId
    ): array {
        $statement =
            $this->db->prepare("
                SELECT
                    methods.*,
                    types.code
                        AS contact_type_code,
                    types.title
                        AS contact_type_title,
                    types.channel
                        AS contact_type_channel
                FROM external_organization_contact_methods
                    AS methods
                INNER JOIN contact_types
                    AS types
                    ON types.id =
                       methods.contact_type_id
                WHERE methods.contact_point_id = ?
                ORDER BY
                    CASE
                        WHEN methods.status = 'active'
                            THEN 0
                        ELSE 1
                    END,
                    methods.is_primary DESC,
                    methods.sort_order,
                    methods.id
            ");

        $statement->execute([
            $contactPointId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }

    public function contactMethod(
        string $reference
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    methods.*,
                    types.code
                        AS contact_type_code
                FROM external_organization_contact_methods
                    AS methods
                INNER JOIN contact_types
                    AS types
                    ON types.id =
                       methods.contact_type_id
                WHERE methods.public_reference = ?
                LIMIT 1
            ");

        $statement->execute([
            $reference,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return
            is_array($row)
                ? $row
                : null;
    }

    public function createContactMethod(
        int $contactPointId,
        array $data
    ): string {
        $statement =
            $this->db->prepare("
                INSERT INTO external_organization_contact_methods (
                    public_reference,
                    contact_point_id,
                    contact_type_id,
                    value,
                    normalized_value,
                    area_code,
                    extension,
                    label,
                    is_primary,
                    is_verified,
                    supports_dispatch,
                    supports_followup,
                    sort_order,
                    status,
                    created_at,
                    updated_at
                ) VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'active',
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )
            ");

        $statement->execute([
            $data['public_reference'],
            $contactPointId,
            $data['contact_type_id'],
            $data['value'],
            $data['normalized_value'],
            $data['area_code'] ?? null,
            $data['extension'] ?? null,
            $data['label'],
            $data['is_primary'],
            $data['is_verified'],
            $data['supports_dispatch'],
            $data['supports_followup'],
            $data['sort_order'],
        ]);

        return
            (string) $data[
                'public_reference'
            ];
    }

    public function updateContactMethod(
        string $reference,
        array $data
    ): bool {
        $statement =
            $this->db->prepare("
                UPDATE external_organization_contact_methods
                SET
                    contact_type_id = ?,
                    value = ?,
                    normalized_value = ?,
                    area_code = ?,
                    extension = ?,
                    label = ?,
                    is_primary = ?,
                    is_verified = ?,
                    supports_dispatch = ?,
                    supports_followup = ?,
                    sort_order = ?,
                    status = 'active',
                    updated_at = UTC_TIMESTAMP()
                WHERE public_reference = ?
            ");

        $statement->execute([
            $data['contact_type_id'],
            $data['value'],
            $data['normalized_value'],
            $data['area_code'] ?? null,
            $data['extension'] ?? null,
            $data['label'],
            $data['is_primary'],
            $data['is_verified'],
            $data['supports_dispatch'],
            $data['supports_followup'],
            $data['sort_order'],
            $reference,
        ]);

        return
            $statement->rowCount() > 0
            || $this->contactMethod(
                $reference
            ) !== null;
    }

    public function deactivateContactMethod(
        string $reference
    ): bool {
        $statement =
            $this->db->prepare("
                UPDATE external_organization_contact_methods
                SET
                    status = 'inactive',
                    updated_at = UTC_TIMESTAMP()
                WHERE public_reference = ?
                  AND status <> 'inactive'
            ");

        $statement->execute([
            $reference,
        ]);

        return
            $statement->rowCount() > 0;
    }

    public function addressTypes(): array
    {
        $statement =
            $this->db->query("
                SELECT
                    id,
                    code,
                    title,
                    sort_order
                FROM address_types
                WHERE status = 'active'
                ORDER BY
                    sort_order,
                    id
            ");

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }

    public function addressType(
        string $code
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    id,
                    code,
                    title
                FROM address_types
                WHERE code = ?
                  AND status = 'active'
                LIMIT 1
            ");

        $statement->execute([
            $code,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return
            is_array($row)
                ? $row
                : null;
    }

    public function addresses(
        int $contactPointId
    ): array {
        $statement =
            $this->db->prepare("
                SELECT
                    addresses.*,
                    types.code
                        AS address_type_code,
                    types.title
                        AS address_type_title
                FROM external_organization_contact_addresses
                    AS addresses
                LEFT JOIN address_types
                    AS types
                    ON types.id =
                       addresses.address_type_id
                WHERE addresses.contact_point_id = ?
                ORDER BY
                    CASE
                        WHEN addresses.status = 'active'
                            THEN 0
                        ELSE 1
                    END,
                    addresses.is_primary DESC,
                    addresses.id
            ");

        $statement->execute([
            $contactPointId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }

    public function address(
        string $reference
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT *
                FROM external_organization_contact_addresses
                WHERE public_reference = ?
                LIMIT 1
            ");

        $statement->execute([
            $reference,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return
            is_array($row)
                ? $row
                : null;
    }

    public function createAddress(
        int $contactPointId,
        array $data
    ): string {
        $statement =
            $this->db->prepare("
                INSERT INTO external_organization_contact_addresses (
                    public_reference,
                    contact_point_id,
                    address_type_id,
                    geographic_location_id,
                    district,
                    address_line,
                    postal_code,
                    is_primary,
                    supports_dispatch,
                    status,
                    created_at,
                    updated_at
                ) VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'active',
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )
            ");

        $statement->execute([
            $data['public_reference'],
            $contactPointId,
            $data['address_type_id'],
            $data['geographic_location_id'],
            $data['district'],
            $data['address_line'],
            $data['postal_code'],
            $data['is_primary'],
            $data['supports_dispatch'],
        ]);

        return
            (string) $data[
                'public_reference'
            ];
    }

    public function updateAddress(
        string $reference,
        array $data
    ): bool {
        $statement =
            $this->db->prepare("
                UPDATE external_organization_contact_addresses
                SET
                    address_type_id = ?,
                    geographic_location_id = ?,
                    district = ?,
                    address_line = ?,
                    postal_code = ?,
                    is_primary = ?,
                    supports_dispatch = ?,
                    status = 'active',
                    updated_at = UTC_TIMESTAMP()
                WHERE public_reference = ?
            ");

        $statement->execute([
            $data['address_type_id'],
            $data['geographic_location_id'],
            $data['district'],
            $data['address_line'],
            $data['postal_code'],
            $data['is_primary'],
            $data['supports_dispatch'],
            $reference,
        ]);

        return
            $statement->rowCount() > 0
            || $this->address(
                $reference
            ) !== null;
    }

    public function deactivateAddress(
        string $reference
    ): bool {
        $statement =
            $this->db->prepare("
                UPDATE external_organization_contact_addresses
                SET
                    status = 'inactive',
                    updated_at = UTC_TIMESTAMP()
                WHERE public_reference = ?
                  AND status <> 'inactive'
            ");

        $statement->execute([
            $reference,
        ]);

        return
            $statement->rowCount() > 0;
    }
}
