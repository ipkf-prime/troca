<?php

namespace IPKF\Database\Migrations;

class CreateDynamicGeographyTables extends Migration
{
    public function up(): void
    {
        $this->createLevelTypesTable();
        $this->createRelationTypesTable();
        $this->createLocationsTable();
        $this->createLocationRelationsTable();
        $this->createLegacyMappingsTable();
        $this->extendPersonAddressesTable();
    }

    public function down(): void
    {
    }

    private function createLevelTypesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS geographic_level_types (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(100) NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                parent_level_type_id BIGINT UNSIGNED NULL,
                hierarchy_order INT NULL,
                is_administrative TINYINT(1) NOT NULL DEFAULT 1,
                is_addressable TINYINT(1) NOT NULL DEFAULT 1,
                is_selectable TINYINT(1) NOT NULL DEFAULT 1,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                metadata_json LONGTEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY geo_level_types_code_unique (code),
                INDEX geo_level_types_parent_index (parent_level_type_id),
                INDEX geo_level_types_hierarchy_index (hierarchy_order),
                INDEX geo_level_types_status_index (status),
                INDEX geo_level_types_sort_index (sort_order)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->convertToUtf8mb4('geographic_level_types');
        $this->addForeignKeyIfPossible(
            'geographic_level_types',
            'geo_level_types_parent_foreign',
            'parent_level_type_id',
            'geographic_level_types',
            'id',
            'SET NULL'
        );
    }

    private function createRelationTypesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS geographic_relation_types (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(100) NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                is_hierarchical TINYINT(1) NOT NULL DEFAULT 0,
                is_administrative TINYINT(1) NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY geo_relation_types_code_unique (code),
                INDEX geo_relation_types_status_index (status),
                INDEX geo_relation_types_sort_index (sort_order)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->convertToUtf8mb4('geographic_relation_types');
    }

    private function createLocationsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS geographic_locations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                level_type_id BIGINT UNSIGNED NOT NULL,
                code VARCHAR(150) NULL,
                official_code VARCHAR(150) NULL,
                title VARCHAR(255) NOT NULL,
                short_title VARCHAR(180) NULL,
                latin_title VARCHAR(255) NULL,
                slug VARCHAR(191) NULL,
                country_iso_code VARCHAR(3) NULL,
                timezone VARCHAR(100) NULL,
                latitude DECIMAL(10,7) NULL,
                longitude DECIMAL(10,7) NULL,
                valid_from DATE NULL,
                valid_to DATE NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                metadata_json LONGTEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                INDEX geo_locations_level_type_index (level_type_id),
                INDEX geo_locations_title_index (title(191)),
                INDEX geo_locations_code_index (code),
                INDEX geo_locations_official_code_index (official_code),
                INDEX geo_locations_country_iso_index (country_iso_code),
                INDEX geo_locations_status_index (status),
                INDEX geo_locations_level_status_index (level_type_id, status),
                INDEX geo_locations_country_status_index (country_iso_code, status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->convertToUtf8mb4('geographic_locations');
        $this->addForeignKeyIfPossible(
            'geographic_locations',
            'geo_locations_level_type_foreign',
            'level_type_id',
            'geographic_level_types',
            'id',
            'RESTRICT'
        );
    }

    private function createLocationRelationsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS geographic_location_relations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                parent_location_id BIGINT UNSIGNED NOT NULL,
                child_location_id BIGINT UNSIGNED NOT NULL,
                relation_type_id BIGINT UNSIGNED NOT NULL,
                is_primary TINYINT(1) NOT NULL DEFAULT 1,
                valid_from DATE NULL,
                valid_to DATE NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                description TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                INDEX geo_location_relations_parent_index (parent_location_id),
                INDEX geo_location_relations_child_index (child_location_id),
                INDEX geo_location_relations_type_index (relation_type_id),
                INDEX geo_location_relations_parent_status_index (parent_location_id, status),
                INDEX geo_location_relations_child_status_index (child_location_id, status),
                INDEX geo_location_relations_child_type_status_index (child_location_id, relation_type_id, status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->convertToUtf8mb4('geographic_location_relations');
        $this->addForeignKeyIfPossible(
            'geographic_location_relations',
            'geo_location_relations_parent_foreign',
            'parent_location_id',
            'geographic_locations',
            'id',
            'RESTRICT'
        );
        $this->addForeignKeyIfPossible(
            'geographic_location_relations',
            'geo_location_relations_child_foreign',
            'child_location_id',
            'geographic_locations',
            'id',
            'RESTRICT'
        );
        $this->addForeignKeyIfPossible(
            'geographic_location_relations',
            'geo_location_relations_type_foreign',
            'relation_type_id',
            'geographic_relation_types',
            'id',
            'RESTRICT'
        );
    }

    private function createLegacyMappingsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS geographic_legacy_mappings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                legacy_source VARCHAR(64) NOT NULL,
                legacy_table VARCHAR(64) NOT NULL,
                legacy_record_id VARCHAR(63) NOT NULL,
                geographic_location_id BIGINT UNSIGNED NOT NULL,
                mapping_status VARCHAR(30) NOT NULL DEFAULT 'active',
                confidence_source VARCHAR(150) NULL,
                mapped_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY geo_legacy_mappings_source_record_unique (legacy_source, legacy_table, legacy_record_id),
                INDEX geo_legacy_mappings_source_index (legacy_source),
                INDEX geo_legacy_mappings_table_record_index (legacy_table, legacy_record_id),
                INDEX geo_legacy_mappings_location_index (geographic_location_id),
                INDEX geo_legacy_mappings_status_index (mapping_status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->convertToUtf8mb4('geographic_legacy_mappings');
        $this->addForeignKeyIfPossible(
            'geographic_legacy_mappings',
            'geo_legacy_mappings_location_foreign',
            'geographic_location_id',
            'geographic_locations',
            'id',
            'RESTRICT'
        );
    }

    private function extendPersonAddressesTable(): void
    {
        if (!$this->tableExists('person_addresses')) {
            return;
        }

        $this->addColumnIfMissing(
            'person_addresses',
            'geographic_location_id',
            'BIGINT UNSIGNED NULL'
        );
        $this->addIndexIfMissing(
            'person_addresses',
            'person_addresses_geographic_location_id_index',
            'geographic_location_id'
        );
        $this->addForeignKeyIfPossible(
            'person_addresses',
            'person_addresses_geographic_location_foreign',
            'geographic_location_id',
            'geographic_locations',
            'id',
            'SET NULL'
        );
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
