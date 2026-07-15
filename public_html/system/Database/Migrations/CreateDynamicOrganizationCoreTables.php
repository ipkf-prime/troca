<?php

namespace IPKF\Database\Migrations;

class CreateDynamicOrganizationCoreTables extends Migration
{
    public function up(): void
    {
        $this->createClassificationSchemesTable();
        $this->createClassificationTermsTable();
        $this->createOrganizationClassificationsTable();
        $this->createRelationTypesTable();
        $this->createOrganizationRelationsTable();
        $this->createOrganizationUnitTypesTable();
        $this->extendOrgUnitsTable();
        $this->createOrganizationPositionsTable();
        $this->createOrganizationAppointmentsTable();
    }

    public function down(): void
    {
    }

    private function createClassificationSchemesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS organization_classification_schemes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(100) NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                selection_mode VARCHAR(20) NOT NULL DEFAULT 'single',
                is_hierarchical TINYINT(1) NOT NULL DEFAULT 0,
                is_required TINYINT(1) NOT NULL DEFAULT 0,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY org_class_schemes_code_unique (code),
                INDEX org_class_schemes_status_index (status),
                INDEX org_class_schemes_sort_index (sort_order)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->convertToUtf8mb4('organization_classification_schemes');
    }

    private function createClassificationTermsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS organization_classification_terms (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                scheme_id BIGINT UNSIGNED NOT NULL,
                parent_id BIGINT UNSIGNED NULL,
                code VARCHAR(100) NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                metadata_json LONGTEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY org_class_terms_scheme_code_unique (scheme_id, code),
                INDEX org_class_terms_scheme_index (scheme_id),
                INDEX org_class_terms_parent_index (parent_id),
                INDEX org_class_terms_status_index (status),
                INDEX org_class_terms_sort_index (sort_order)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->convertToUtf8mb4('organization_classification_terms');
        $this->addForeignKeyIfPossible(
            'organization_classification_terms',
            'org_class_terms_scheme_foreign',
            'scheme_id',
            'organization_classification_schemes',
            'id',
            'RESTRICT'
        );
        $this->addForeignKeyIfPossible(
            'organization_classification_terms',
            'org_class_terms_parent_foreign',
            'parent_id',
            'organization_classification_terms',
            'id',
            'SET NULL'
        );
    }

    private function createOrganizationClassificationsTable(): void
    {
        $organizationIdType = $this->referenceColumnType('organizations', 'id', 'BIGINT UNSIGNED');

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS organization_classifications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                organization_id {$organizationIdType} NOT NULL,
                classification_term_id BIGINT UNSIGNED NOT NULL,
                is_primary TINYINT(1) NOT NULL DEFAULT 0,
                valid_from DATE NULL,
                valid_to DATE NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY org_classes_org_term_unique (organization_id, classification_term_id),
                INDEX org_classes_org_index (organization_id),
                INDEX org_classes_term_index (classification_term_id),
                INDEX org_classes_org_status_index (organization_id, status),
                INDEX org_classes_term_status_index (classification_term_id, status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->convertToUtf8mb4('organization_classifications');
        $this->addForeignKeyIfPossible(
            'organization_classifications',
            'org_classes_organization_foreign',
            'organization_id',
            'organizations',
            'id',
            'RESTRICT'
        );
        $this->addForeignKeyIfPossible(
            'organization_classifications',
            'org_classes_term_foreign',
            'classification_term_id',
            'organization_classification_terms',
            'id',
            'RESTRICT'
        );
    }

    private function createRelationTypesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS organization_relation_types (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(100) NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                is_directional TINYINT(1) NOT NULL DEFAULT 1,
                is_hierarchical TINYINT(1) NOT NULL DEFAULT 0,
                allows_percentage TINYINT(1) NOT NULL DEFAULT 0,
                allows_dates TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY org_relation_types_code_unique (code),
                INDEX org_relation_types_status_index (status),
                INDEX org_relation_types_sort_index (sort_order)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->convertToUtf8mb4('organization_relation_types');
    }

    private function createOrganizationRelationsTable(): void
    {
        $organizationIdType = $this->referenceColumnType('organizations', 'id', 'BIGINT UNSIGNED');

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS organization_relations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_organization_id {$organizationIdType} NOT NULL,
                target_organization_id {$organizationIdType} NOT NULL,
                relation_type_id BIGINT UNSIGNED NOT NULL,
                ownership_percentage DECIMAL(7,4) NULL,
                is_primary TINYINT(1) NOT NULL DEFAULT 0,
                valid_from DATE NULL,
                valid_to DATE NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                reference_number VARCHAR(150) NULL,
                description TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                INDEX org_relations_source_index (source_organization_id),
                INDEX org_relations_target_index (target_organization_id),
                INDEX org_relations_type_index (relation_type_id),
                INDEX org_relations_source_type_index (source_organization_id, relation_type_id),
                INDEX org_relations_target_type_index (target_organization_id, relation_type_id),
                INDEX org_relations_status_index (status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->convertToUtf8mb4('organization_relations');
        $this->addForeignKeyIfPossible(
            'organization_relations',
            'org_relations_source_foreign',
            'source_organization_id',
            'organizations',
            'id',
            'RESTRICT'
        );
        $this->addForeignKeyIfPossible(
            'organization_relations',
            'org_relations_target_foreign',
            'target_organization_id',
            'organizations',
            'id',
            'RESTRICT'
        );
        $this->addForeignKeyIfPossible(
            'organization_relations',
            'org_relations_type_foreign',
            'relation_type_id',
            'organization_relation_types',
            'id',
            'RESTRICT'
        );
    }

    private function createOrganizationUnitTypesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS organization_unit_types (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(100) NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY org_unit_types_code_unique (code),
                INDEX org_unit_types_status_index (status),
                INDEX org_unit_types_sort_index (sort_order)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->convertToUtf8mb4('organization_unit_types');
    }

    private function extendOrgUnitsTable(): void
    {
        if (!$this->tableExists('org_units')) {
            return;
        }

        $organizationIdType = $this->referenceColumnType('organizations', 'id', 'BIGINT UNSIGNED');

        $this->addColumnIfMissing('org_units', 'organization_id', "{$organizationIdType} NULL");
        $this->addColumnIfMissing('org_units', 'unit_type_id', 'BIGINT UNSIGNED NULL');

        $this->addIndexIfMissing('org_units', 'org_units_organization_id_index', 'organization_id');
        $this->addIndexIfMissing('org_units', 'org_units_unit_type_id_index', 'unit_type_id');
        $this->addIndexIfMissing('org_units', 'org_units_org_parent_index', 'organization_id, parent_id');
        $this->addIndexIfMissing('org_units', 'org_units_org_status_index', 'organization_id, status');
        $this->addIndexIfMissing('org_units', 'org_units_org_sort_index', 'organization_id, sort_order');

        $this->normalizeOrgUnitCodeIndexWhenSafe();

        $this->addForeignKeyIfPossible(
            'org_units',
            'org_units_organization_foreign',
            'organization_id',
            'organizations',
            'id',
            'SET NULL'
        );
        $this->addForeignKeyIfPossible(
            'org_units',
            'org_units_unit_type_foreign',
            'unit_type_id',
            'organization_unit_types',
            'id',
            'SET NULL'
        );
    }

    private function createOrganizationPositionsTable(): void
    {
        $organizationIdType = $this->referenceColumnType('organizations', 'id', 'BIGINT UNSIGNED');
        $orgUnitIdType = $this->referenceColumnType('org_units', 'id', 'BIGINT UNSIGNED');
        $positionIdType = $this->referenceColumnType('positions', 'id', 'BIGINT UNSIGNED');

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS organization_positions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                organization_id {$organizationIdType} NOT NULL,
                org_unit_id {$orgUnitIdType} NULL,
                position_id {$positionIdType} NOT NULL,
                parent_position_id BIGINT UNSIGNED NULL,
                code VARCHAR(100) NULL,
                title_override VARCHAR(255) NULL,
                headcount_limit INT UNSIGNED NULL,
                is_head TINYINT(1) NOT NULL DEFAULT 0,
                is_acting_allowed TINYINT(1) NOT NULL DEFAULT 1,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                valid_from DATE NULL,
                valid_to DATE NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                INDEX org_positions_organization_index (organization_id),
                INDEX org_positions_unit_index (org_unit_id),
                INDEX org_positions_position_index (position_id),
                INDEX org_positions_parent_index (parent_position_id),
                INDEX org_positions_org_status_index (organization_id, status),
                INDEX org_positions_unit_status_index (org_unit_id, status),
                INDEX org_positions_org_code_index (organization_id, code)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->convertToUtf8mb4('organization_positions');
        $this->addForeignKeyIfPossible(
            'organization_positions',
            'org_positions_organization_foreign',
            'organization_id',
            'organizations',
            'id',
            'RESTRICT'
        );
        $this->addForeignKeyIfPossible(
            'organization_positions',
            'org_positions_unit_foreign',
            'org_unit_id',
            'org_units',
            'id',
            'SET NULL'
        );
        $this->addForeignKeyIfPossible(
            'organization_positions',
            'org_positions_position_foreign',
            'position_id',
            'positions',
            'id',
            'RESTRICT'
        );
        $this->addForeignKeyIfPossible(
            'organization_positions',
            'org_positions_parent_foreign',
            'parent_position_id',
            'organization_positions',
            'id',
            'SET NULL'
        );
    }

    private function createOrganizationAppointmentsTable(): void
    {
        $organizationIdType = $this->referenceColumnType('organizations', 'id', 'BIGINT UNSIGNED');
        $personIdType = $this->referenceColumnType('persons', 'id', 'BIGINT UNSIGNED');

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS organization_appointments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                organization_id {$organizationIdType} NOT NULL,
                person_id {$personIdType} NOT NULL,
                organization_position_id BIGINT UNSIGNED NOT NULL,
                appointment_type VARCHAR(80) NULL,
                is_primary TINYINT(1) NOT NULL DEFAULT 0,
                is_acting TINYINT(1) NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                valid_from DATE NULL,
                valid_to DATE NULL,
                appointment_reference VARCHAR(150) NULL,
                description TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                INDEX org_appointments_organization_index (organization_id),
                INDEX org_appointments_person_index (person_id),
                INDEX org_appointments_position_index (organization_position_id),
                INDEX org_appointments_person_status_index (person_id, status),
                INDEX org_appointments_position_status_index (organization_position_id, status),
                INDEX org_appointments_org_status_index (organization_id, status),
                INDEX org_appointments_valid_from_index (valid_from),
                INDEX org_appointments_valid_to_index (valid_to)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->convertToUtf8mb4('organization_appointments');
        $this->addForeignKeyIfPossible(
            'organization_appointments',
            'org_appointments_organization_foreign',
            'organization_id',
            'organizations',
            'id',
            'RESTRICT'
        );
        $this->addForeignKeyIfPossible(
            'organization_appointments',
            'org_appointments_person_foreign',
            'person_id',
            'persons',
            'id',
            'RESTRICT'
        );
        $this->addForeignKeyIfPossible(
            'organization_appointments',
            'org_appointments_position_foreign',
            'organization_position_id',
            'organization_positions',
            'id',
            'RESTRICT'
        );
    }

    private function normalizeOrgUnitCodeIndexWhenSafe(): void
    {
        if ($this->indexExists('org_units', 'org_units_organization_code_unique')) {
            return;
        }

        if (!$this->indexExists('org_units', 'org_units_code_unique')) {
            if ($this->duplicateScopedOrgUnitCodes() === 0) {
                $this->addIndexIfMissing(
                    'org_units',
                    'org_units_organization_code_unique',
                    'organization_id, code',
                    true
                );
            } else {
                $this->addIndexIfMissing(
                    'org_units',
                    'org_units_organization_code_index',
                    'organization_id, code'
                );
            }

            return;
        }

        $unscopedCodes = (int) $this->db->query("
            SELECT COUNT(*)
            FROM org_units
            WHERE code IS NOT NULL
              AND organization_id IS NULL
        ")->fetchColumn();

        $duplicateCodes = $this->duplicateScopedOrgUnitCodes();

        if ($unscopedCodes !== 0 || $duplicateCodes !== 0) {
            $this->addIndexIfMissing('org_units', 'org_units_organization_code_index', 'organization_id, code');

            return;
        }

        $this->db->exec('ALTER TABLE org_units DROP INDEX org_units_code_unique');
        $this->addIndexIfMissing(
            'org_units',
            'org_units_organization_code_unique',
            'organization_id, code',
            true
        );
    }

    private function duplicateScopedOrgUnitCodes(): int
    {
        return (int) $this->db->query("
            SELECT COUNT(*)
            FROM (
                SELECT organization_id, code
                FROM org_units
                WHERE organization_id IS NOT NULL
                  AND code IS NOT NULL
                GROUP BY organization_id, code
                HAVING COUNT(*) > 1
            ) duplicate_org_unit_codes
        ")->fetchColumn();
    }

    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        if (!$this->columnExists($table, $column)) {
            $this->db->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }

    private function addIndexIfMissing(
        string $table,
        string $index,
        string $columns,
        bool $unique = false
    ): void {
        if ($this->indexExists($table, $index)) {
            return;
        }

        $modifier = $unique ? 'UNIQUE ' : '';
        $this->db->exec("ALTER TABLE {$table} ADD {$modifier}INDEX {$index} ({$columns})");
    }

    private function referenceColumnType(string $table, string $column, string $default): string
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, $column)) {
            return $default;
        }

        $statement = $this->db->prepare("
            SELECT COLUMN_TYPE
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
            LIMIT 1
        ");
        $statement->execute([$table, $column]);
        $type = strtoupper((string) $statement->fetchColumn());

        return preg_match('/^(TINYINT|SMALLINT|MEDIUMINT|INT|BIGINT)(\\(\\d+\\))?( UNSIGNED)?$/', $type) === 1
            ? $type
            : $default;
    }

    private function addForeignKeyIfPossible(
        string $table,
        string $constraint,
        string $column,
        string $referenceTable,
        string $referenceColumn,
        string $onDelete
    ): void {
        if (!$this->tableExists($table)
            || !$this->tableExists($referenceTable)
            || !$this->columnExists($table, $column)
            || !$this->columnExists($referenceTable, $referenceColumn)
            || $this->foreignKeyExists($table, $constraint)
            || !$this->supportsForeignKeys($table)
            || !$this->supportsForeignKeys($referenceTable)
            || $this->columnType($table, $column) !== $this->columnType($referenceTable, $referenceColumn)
        ) {
            return;
        }

        $this->db->exec("
            ALTER TABLE {$table}
            ADD CONSTRAINT {$constraint}
            FOREIGN KEY ({$column}) REFERENCES {$referenceTable} ({$referenceColumn})
            ON UPDATE CASCADE ON DELETE {$onDelete}
        ");
    }

    private function convertToUtf8mb4(string $table): void
    {
        if ($this->tableExists($table)) {
            $this->db->exec("ALTER TABLE {$table} CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
        ");
        $statement->execute([$table]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
        ");
        $statement->execute([$table, $column]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function columnType(string $table, string $column): string
    {
        $statement = $this->db->prepare("
            SELECT COLUMN_TYPE
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
            LIMIT 1
        ");
        $statement->execute([$table, $column]);

        return strtolower((string) $statement->fetchColumn());
    }

    private function indexExists(string $table, string $index): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND index_name = ?
        ");
        $statement->execute([$table, $index]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function supportsForeignKeys(string $table): bool
    {
        $statement = $this->db->prepare("
            SELECT ENGINE
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
            LIMIT 1
        ");
        $statement->execute([$table]);

        return strtolower((string) $statement->fetchColumn()) === 'innodb';
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.table_constraints
            WHERE constraint_schema = DATABASE()
              AND table_name = ?
              AND constraint_name = ?
              AND constraint_type = 'FOREIGN KEY'
        ");
        $statement->execute([$table, $constraint]);

        return (int) $statement->fetchColumn() > 0;
    }
}
