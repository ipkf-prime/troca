<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use RuntimeException;

final class RetireLegacyTicketingOrganizationMembershipField
    extends Migration
{
    private const PROJECT_REFERENCE = 'TSP-NEP';

    private const FIELD_TITLE =
        'نام اتحادیه / شرکت';


    public function up(): void
    {
        $row =
            $this->target();

        if ($row === null) {
            return;
        }


        if (
            (string) (
                $row['field_type']
                ?? ''
            ) !== 'lookup'
            ||
            $row['data_source_key']
                !== null
            ||
            $row['options_json']
                !== null
        ) {
            throw new RuntimeException(
                'legacy_ticketing_organization_field_contract_changed'
            );
        }


        $statement =
            $this->db->prepare(
                "
                UPDATE
                    ticketing_support_project_membership_fields

                SET
                    is_required = 0,
                    is_active = 0,
                    updated_at = CURRENT_TIMESTAMP

                WHERE id = ?
                  AND public_reference = ?
                "
            );

        $statement->execute([
            (int) $row['id'],
            (string) $row['public_reference'],
        ]);
    }


    public function down(): void
    {
        $row =
            $this->target();

        if ($row === null) {
            return;
        }


        $statement =
            $this->db->prepare(
                "
                UPDATE
                    ticketing_support_project_membership_fields

                SET
                    is_required = 1,
                    is_active = 1,
                    updated_at = CURRENT_TIMESTAMP

                WHERE id = ?
                  AND public_reference = ?
                "
            );

        $statement->execute([
            (int) $row['id'],
            (string) $row['public_reference'],
        ]);
    }


    private function target(): ?array
    {
        $statement =
            $this->db->prepare(
                "
                SELECT
                    fields.id,
                    fields.public_reference,
                    fields.field_type,
                    fields.data_source_key,
                    fields.options_json,
                    fields.is_required,
                    fields.is_active

                FROM
                    ticketing_support_project_membership_fields
                        AS fields

                INNER JOIN
                    ticketing_support_projects AS projects
                  ON projects.id =
                        fields.project_id

                WHERE projects.public_reference = ?
                  AND fields.title = ?

                ORDER BY fields.id
                "
            );

        $statement->execute([
            self::PROJECT_REFERENCE,
            self::FIELD_TITLE,
        ]);


        $rows =
            $statement->fetchAll(
                \PDO::FETCH_ASSOC
            )
            ?: [];


        if (count($rows) > 1) {
            throw new RuntimeException(
                'legacy_ticketing_organization_field_not_unique'
            );
        }


        return
            $rows[0]
            ?? null;
    }
}
