<?php

namespace IPKF\Database\Migrations;

class CompleteOrganizationalIdentityAndSignatureFoundation extends Migration
{
    public function up(): void
    {
        $this->extendLocalizedEntities();
        $this->extendAppointments();
        $this->createOrganizationalContextEvents();
        $this->createSignatureAssets();
        $this->createSignatureAuthorizations();
    }

    public function down(): void
    {
    }

    private function extendLocalizedEntities(): void
    {
        foreach ([
            'persons' => [
                'public_reference' => "CHAR(36) NULL",
                'display_name_fa' => "VARCHAR(255) NULL",
                'display_name_en' => "VARCHAR(255) NULL",
            ],
            'organizations' => [
                'public_reference' => "CHAR(36) NULL",
                'title_fa' => "VARCHAR(255) NULL",
                'title_en' => "VARCHAR(255) NULL",
            ],
            'org_units' => [
                'public_reference' => "CHAR(36) NULL",
                'title_fa' => "VARCHAR(255) NULL",
                'title_en' => "VARCHAR(255) NULL",
            ],
            'positions' => [
                'public_reference' => "CHAR(36) NULL",
                'title_fa' => "VARCHAR(255) NULL",
                'title_en' => "VARCHAR(255) NULL",
            ],
            'organization_positions' => [
                'public_reference' => "CHAR(36) NULL",
                'title_fa' => "VARCHAR(255) NULL",
                'title_en' => "VARCHAR(255) NULL",
            ],
        ] as $table => $columns) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($columns as $column => $definition) {
                $this->addColumnIfMissing($table, $column, $definition);
            }

            if ($this->columnExists($table, 'public_reference')) {
                $this->backfillPublicReferences($table);
                $this->addUniqueIndexIfMissing($table, $table . '_public_reference_unique', 'public_reference');
            }
        }
    }

    private function extendAppointments(): void
    {
        if (!$this->tableExists('organization_appointments')) {
            return;
        }

        $columns = [
            'public_reference' => "CHAR(36) NULL",
            'appointment_kind' => "VARCHAR(40) NOT NULL DEFAULT 'permanent'",
            'delegated_from_appointment_id' => "BIGINT UNSIGNED NULL",
            'revoked_at' => "TIMESTAMP NULL",
            'revoked_by' => "BIGINT UNSIGNED NULL",
        ];

        foreach ($columns as $column => $definition) {
            $this->addColumnIfMissing('organization_appointments', $column, $definition);
        }

        $this->backfillPublicReferences('organization_appointments');
        $this->addUniqueIndexIfMissing('organization_appointments', 'org_appointments_public_reference_unique', 'public_reference');
        $this->addIndexIfMissing('organization_appointments', 'org_appointments_delegated_from_index', 'delegated_from_appointment_id');
        $this->addIndexIfMissing('organization_appointments', 'org_appointments_kind_index', 'appointment_kind');
    }

    private function createOrganizationalContextEvents(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS organizational_context_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference CHAR(36) NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                appointment_id BIGINT UNSIGNED NULL,
                event_type VARCHAR(50) NOT NULL,
                occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                metadata_json LONGTEXT NULL,
                UNIQUE KEY organizational_context_events_public_unique (public_reference),
                INDEX organizational_context_events_user_index (user_id),
                INDEX organizational_context_events_appointment_index (appointment_id),
                INDEX organizational_context_events_type_index (event_type),
                INDEX organizational_context_events_occurred_index (occurred_at)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createSignatureAssets(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS signature_assets (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference CHAR(36) NOT NULL,
                person_id BIGINT UNSIGNED NOT NULL,
                language_code VARCHAR(10) NOT NULL,
                asset_kind VARCHAR(40) NOT NULL,
                storage_key VARCHAR(500) NOT NULL,
                original_filename VARCHAR(255) NULL,
                mime_type VARCHAR(100) NOT NULL,
                byte_size BIGINT UNSIGNED NOT NULL,
                sha256_hash CHAR(64) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                valid_from TIMESTAMP NULL,
                valid_until TIMESTAMP NULL,
                created_by BIGINT UNSIGNED NULL,
                revoked_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY signature_assets_public_reference_unique (public_reference),
                INDEX signature_assets_person_index (person_id),
                INDEX signature_assets_language_index (language_code),
                INDEX signature_assets_kind_index (asset_kind),
                INDEX signature_assets_status_index (status),
                INDEX signature_assets_person_language_index (person_id, language_code, status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createSignatureAuthorizations(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS signature_authorizations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference CHAR(36) NOT NULL,
                signature_asset_id BIGINT UNSIGNED NOT NULL,
                appointment_id BIGINT UNSIGNED NOT NULL,
                organization_id BIGINT UNSIGNED NULL,
                org_unit_id BIGINT UNSIGNED NULL,
                organization_position_id BIGINT UNSIGNED NULL,
                purpose_code VARCHAR(80) NOT NULL,
                allowed_language_code VARCHAR(10) NOT NULL,
                allow_shared_fallback TINYINT(1) NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                valid_from TIMESTAMP NULL,
                valid_until TIMESTAMP NULL,
                created_by BIGINT UNSIGNED NULL,
                revoked_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY signature_authorizations_public_unique (public_reference),
                INDEX signature_authorizations_asset_index (signature_asset_id),
                INDEX signature_authorizations_appointment_index (appointment_id),
                INDEX signature_authorizations_org_index (organization_id),
                INDEX signature_authorizations_unit_index (org_unit_id),
                INDEX signature_authorizations_position_index (organization_position_id),
                INDEX signature_authorizations_purpose_index (purpose_code),
                INDEX signature_authorizations_status_index (status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function backfillPublicReferences(string $table): void
    {
        $this->db->exec("UPDATE {$table} SET public_reference = UUID() WHERE public_reference IS NULL OR public_reference = ''");
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $statement->execute([$table]);
        return (int) $statement->fetchColumn() > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $statement = $this->db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
        $statement->execute([$table, $column]);
        return (int) $statement->fetchColumn() > 0;
    }

    private function indexExists(string $table, string $index): bool
    {
        $statement = $this->db->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?");
        $statement->execute([$table, $index]);
        return (int) $statement->fetchColumn() > 0;
    }

    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        if (!$this->columnExists($table, $column)) {
            $this->db->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }

    private function addIndexIfMissing(string $table, string $index, string $columns): void
    {
        if (!$this->indexExists($table, $index)) {
            $this->db->exec("ALTER TABLE {$table} ADD INDEX {$index} ({$columns})");
        }
    }

    private function addUniqueIndexIfMissing(string $table, string $index, string $columns): void
    {
        if (!$this->indexExists($table, $index)) {
            $this->db->exec("ALTER TABLE {$table} ADD UNIQUE INDEX {$index} ({$columns})");
        }
    }
}
