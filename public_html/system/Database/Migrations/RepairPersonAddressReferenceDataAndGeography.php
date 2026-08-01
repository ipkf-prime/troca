<?php

namespace IPKF\Database\Migrations;

class RepairPersonAddressReferenceDataAndGeography extends Migration
{
    public function up(): void
    {
        $this->seedAddressTypes();
        $this->ensureGeographicLocationColumn();
    }

    public function down(): void
    {
    }

    private function seedAddressTypes(): void
    {
        if (!$this->tableExists('address_types')) {
            return;
        }

        $statement = $this->db->prepare("
            INSERT INTO address_types (
                code,
                title,
                sort_order,
                status,
                created_at,
                updated_at
            )
            VALUES (
                ?, ?, ?, 'active',
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                sort_order = VALUES(sort_order),
                status = 'active',
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ([
            ['home', 'منزل', 10],
            ['work', 'محل کار', 20],
            ['correspondence', 'نشانی مکاتبات', 30],
            ['other', 'سایر', 100],
        ] as $addressType) {
            $statement->execute($addressType);
        }
    }

    private function ensureGeographicLocationColumn(): void
    {
        if (!$this->tableExists('person_addresses')) {
            return;
        }

        if (
            !$this->columnExists(
                'person_addresses',
                'geographic_location_id'
            )
        ) {
            $this->db->exec("
                ALTER TABLE person_addresses
                ADD COLUMN geographic_location_id
                    BIGINT UNSIGNED NULL
            ");
        }

        if (
            !$this->indexExists(
                'person_addresses',
                'person_addresses_geographic_location_id_index'
            )
        ) {
            $this->db->exec("
                ALTER TABLE person_addresses
                ADD INDEX
                    person_addresses_geographic_location_id_index
                    (geographic_location_id)
            ");
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

    private function columnExists(
        string $table,
        string $column
    ): bool {
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

    private function indexExists(
        string $table,
        string $index
    ): bool {
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
}
