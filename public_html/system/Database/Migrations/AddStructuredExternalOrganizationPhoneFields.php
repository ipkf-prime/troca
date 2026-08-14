<?php

namespace IPKF\Database\Migrations;

class AddStructuredExternalOrganizationPhoneFields extends Migration
{
    public function up(): void
    {
        if (
            !$this->tableExists(
                'external_organization_contact_methods'
            )
        ) {
            return;
        }

        if (
            !$this->columnExists(
                'external_organization_contact_methods',
                'area_code'
            )
        ) {
            $this->db->exec("
                ALTER TABLE external_organization_contact_methods
                ADD COLUMN area_code VARCHAR(20) NULL
                    AFTER normalized_value
            ");
        }

        if (
            !$this->columnExists(
                'external_organization_contact_methods',
                'extension'
            )
        ) {
            $this->db->exec("
                ALTER TABLE external_organization_contact_methods
                ADD COLUMN extension VARCHAR(30) NULL
                    AFTER area_code
            ");
        }

        /*
         * Legacy extension contact type is retained for history,
         * but must no longer be selectable for new contact methods.
         */
        if ($this->tableExists('contact_types')) {
            $this->db->exec("
                UPDATE contact_types
                SET status = 'inactive',
                    updated_at = CURRENT_TIMESTAMP
                WHERE code = 'extension'
            ");
        }
    }

    public function down(): void
    {
        /*
         * Deliberately non-destructive.
         * Structured phone history must not be dropped.
         */
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
}
