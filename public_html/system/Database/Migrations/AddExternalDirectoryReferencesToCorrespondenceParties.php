<?php

namespace IPKF\Database\Migrations;

class AddExternalDirectoryReferencesToCorrespondenceParties
    extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists(
            'correspondence_parties'
        )) {
            return;
        }

        if (!$this->columnExists(
            'correspondence_parties',
            'external_organization_public_reference'
        )) {
            $this->db->exec("
                ALTER TABLE correspondence_parties

                ADD COLUMN
                    external_organization_public_reference
                    CHAR(36)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL

                AFTER external_contact_or_address
            ");
        }

        if (!$this->columnExists(
            'correspondence_parties',
            'external_contact_point_public_reference'
        )) {
            $this->db->exec("
                ALTER TABLE correspondence_parties

                ADD COLUMN
                    external_contact_point_public_reference
                    CHAR(36)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL

                AFTER external_organization_public_reference
            ");
        }

        if (!$this->indexExists(
            'correspondence_parties',
            'corr_party_external_org_ref_index'
        )) {
            $this->db->exec("
                ALTER TABLE correspondence_parties

                ADD INDEX
                    corr_party_external_org_ref_index (
                        external_organization_public_reference
                    )
            ");
        }

        if (!$this->indexExists(
            'correspondence_parties',
            'corr_party_external_point_ref_index'
        )) {
            $this->db->exec("
                ALTER TABLE correspondence_parties

                ADD INDEX
                    corr_party_external_point_ref_index (
                        external_contact_point_public_reference
                    )
            ");
        }
    }

    public function down(): void
    {
    }

    private function tableExists(
        string $table
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = ?
            ");

        $statement->execute([
            $table,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }

    private function columnExists(
        string $table,
        string $column
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                  AND column_name = ?
            ");

        $statement->execute([
            $table,
            $column,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }

    private function indexExists(
        string $table,
        string $index
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                  AND index_name = ?
            ");

        $statement->execute([
            $table,
            $index,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }
}
