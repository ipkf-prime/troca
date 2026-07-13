<?php

namespace IPKF\Database\Migrations;

class CreateExtendedPersonDataTables extends Migration
{
    public function up(): void
    {
        $this->createPersonProfilesTable();
        $this->createContactTypesTable();
        $this->createPersonContactsTable();
        $this->createAddressTypesTable();
        $this->createPersonAddressesTable();
        $this->addNationalCodeUniqueIndexWhenSafe();
    }

    public function down(): void
    {
    }

    private function createPersonProfilesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS person_profiles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                person_id BIGINT UNSIGNED NOT NULL,
                birth_place VARCHAR(150) NULL,
                identity_number VARCHAR(50) NULL,
                identity_serial VARCHAR(50) NULL,
                description TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY person_profiles_person_id_unique (person_id)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec('ALTER TABLE person_profiles CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addForeignKeyIfPossible(
            'person_profiles',
            'person_profiles_person_id_foreign',
            'person_id',
            'persons',
            'id'
        );
    }

    private function createContactTypesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS contact_types (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(80) NOT NULL,
                title VARCHAR(150) NOT NULL,
                channel VARCHAR(50) NULL,
                validation_pattern VARCHAR(500) NULL,
                sort_order INT NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY contact_types_code_unique (code),
                INDEX contact_types_channel_index (channel),
                INDEX contact_types_sort_order_index (sort_order),
                INDEX contact_types_status_index (status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec('ALTER TABLE contact_types CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    private function createPersonContactsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS person_contacts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                person_id BIGINT UNSIGNED NOT NULL,
                contact_type_id BIGINT UNSIGNED NOT NULL,
                value VARCHAR(500) NOT NULL,
                normalized_value VARCHAR(500) NULL,
                label VARCHAR(150) NULL,
                is_primary TINYINT(1) NOT NULL DEFAULT 0,
                is_verified TINYINT(1) NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                INDEX person_contacts_person_id_index (person_id),
                INDEX person_contacts_contact_type_id_index (contact_type_id),
                INDEX person_contacts_person_status_index (person_id, status),
                INDEX person_contacts_normalized_value_index (normalized_value(191)),
                INDEX person_contacts_person_primary_index (person_id, is_primary)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec('ALTER TABLE person_contacts CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addForeignKeyIfPossible(
            'person_contacts',
            'person_contacts_person_id_foreign',
            'person_id',
            'persons',
            'id'
        );
        $this->addForeignKeyIfPossible(
            'person_contacts',
            'person_contacts_contact_type_id_foreign',
            'contact_type_id',
            'contact_types',
            'id',
            'RESTRICT'
        );
    }

    private function createAddressTypesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS address_types (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(80) NOT NULL,
                title VARCHAR(150) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY address_types_code_unique (code),
                INDEX address_types_sort_order_index (sort_order),
                INDEX address_types_status_index (status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec('ALTER TABLE address_types CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    private function createPersonAddressesTable(): void
    {
        $provinceType = $this->referenceColumnType('provinces', 'id', 'BIGINT');
        $cityType = $this->referenceColumnType('cities', 'id', 'BIGINT');

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS person_addresses (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                person_id BIGINT UNSIGNED NOT NULL,
                address_type_id BIGINT UNSIGNED NULL,
                province_id {$provinceType} NULL,
                city_id {$cityType} NULL,
                district VARCHAR(150) NULL,
                address_line TEXT NOT NULL,
                postal_code VARCHAR(30) NULL,
                is_primary TINYINT(1) NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                INDEX person_addresses_person_id_index (person_id),
                INDEX person_addresses_address_type_id_index (address_type_id),
                INDEX person_addresses_province_id_index (province_id),
                INDEX person_addresses_city_id_index (city_id),
                INDEX person_addresses_person_status_index (person_id, status),
                INDEX person_addresses_person_primary_index (person_id, is_primary)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec('ALTER TABLE person_addresses CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addForeignKeyIfPossible(
            'person_addresses',
            'person_addresses_person_id_foreign',
            'person_id',
            'persons',
            'id'
        );
        $this->addForeignKeyIfPossible(
            'person_addresses',
            'person_addresses_address_type_id_foreign',
            'address_type_id',
            'address_types',
            'id',
            'SET NULL'
        );
        $this->addForeignKeyIfPossible(
            'person_addresses',
            'person_addresses_province_id_foreign',
            'province_id',
            'provinces',
            'id',
            'SET NULL'
        );
        $this->addForeignKeyIfPossible(
            'person_addresses',
            'person_addresses_city_id_foreign',
            'city_id',
            'cities',
            'id',
            'SET NULL'
        );
    }

    private function addNationalCodeUniqueIndexWhenSafe(): void
    {
        if (!$this->tableExists('persons') || !$this->columnExists('persons', 'national_code')) {
            return;
        }

        if ($this->indexExists('persons', 'persons_national_code_unique')
            || $this->uniqueSingleColumnIndexExists('persons', 'national_code')
        ) {
            return;
        }

        $duplicates = (int) $this->db->query("
            SELECT COUNT(*)
            FROM (
                SELECT national_code
                FROM persons
                WHERE national_code IS NOT NULL
                GROUP BY national_code
                HAVING COUNT(*) > 1
            ) duplicated_national_codes
        ")->fetchColumn();

        if ($duplicates === 0) {
            $this->db->exec('ALTER TABLE persons ADD UNIQUE KEY persons_national_code_unique (national_code)');
        }
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
        string $onDelete = 'CASCADE'
    ): void {
        if (!$this->tableExists($table)
            || !$this->tableExists($referenceTable)
            || !$this->columnExists($table, $column)
            || !$this->columnExists($referenceTable, $referenceColumn)
            || $this->foreignKeyExists($table, $constraint)
            || !$this->supportsForeignKeys($table)
            || !$this->supportsForeignKeys($referenceTable)
        ) {
            return;
        }

        if ($this->columnType($table, $column) !== $this->columnType($referenceTable, $referenceColumn)) {
            return;
        }

        $this->db->exec("
            ALTER TABLE {$table}
            ADD CONSTRAINT {$constraint}
            FOREIGN KEY ({$column}) REFERENCES {$referenceTable} ({$referenceColumn})
            ON UPDATE CASCADE ON DELETE {$onDelete}
        ");
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

    private function uniqueSingleColumnIndexExists(string $table, string $column): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.statistics candidate
            WHERE candidate.table_schema = DATABASE()
              AND candidate.table_name = ?
              AND candidate.column_name = ?
              AND candidate.non_unique = 0
              AND (
                  SELECT COUNT(*)
                  FROM information_schema.statistics index_columns
                  WHERE index_columns.table_schema = candidate.table_schema
                    AND index_columns.table_name = candidate.table_name
                    AND index_columns.index_name = candidate.index_name
              ) = 1
        ");
        $statement->execute([$table, $column]);

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
