<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;

/**
 * GENERIC_ORGANIZATION_NETWORK_FOUNDATION_V1
 *
 * Makes organization-to-organization relations catalog-aware without
 * replacing the canonical organizations directory or legacy relations.
 *
 * Important:
 * - organizations.parent_id is NOT the authority for network topology.
 * - catalog_id is nullable for backwards compatibility.
 * - no tenant/customer/business-specific hierarchy is hardcoded here.
 * - source synchronization metadata is generic and optional.
 */
final class ExtendOrganizationRelationsForCatalogScopedNetworks
    extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('organization_relations')) {
            throw new \RuntimeException(
                'organization_relations table is missing.'
            );
        }

        if (!$this->tableExists('organization_catalogs')) {
            throw new \RuntimeException(
                'organization_catalogs table is missing.'
            );
        }

        if (!$this->tableExists('organization_relation_types')) {
            throw new \RuntimeException(
                'organization_relation_types table is missing.'
            );
        }

        $this->addColumnIfMissing(
            'organization_relations',
            'catalog_id',
            'BIGINT UNSIGNED NULL AFTER id'
        );

        $this->addColumnIfMissing(
            'organization_relations',
            'source_code',
            "VARCHAR(80)
             CHARACTER SET ascii
             COLLATE ascii_bin
             NULL
             AFTER description"
        );

        $this->addColumnIfMissing(
            'organization_relations',
            'source_reference',
            "VARCHAR(190)
             CHARACTER SET ascii
             COLLATE ascii_bin
             NULL
             AFTER source_code"
        );

        $this->addColumnIfMissing(
            'organization_relations',
            'metadata_json',
            'LONGTEXT NULL AFTER source_reference'
        );

        $this->addColumnIfMissing(
            'organization_relations',
            'last_synced_at',
            'DATETIME NULL AFTER metadata_json'
        );

        $this->addIndexIfMissing(
            'organization_relations',
            'org_relations_catalog_status_index',
            'catalog_id, status'
        );

        $this->addIndexIfMissing(
            'organization_relations',
            'org_relations_catalog_type_source_index',
            'catalog_id, relation_type_id, source_organization_id, status'
        );

        $this->addIndexIfMissing(
            'organization_relations',
            'org_relations_catalog_type_target_index',
            'catalog_id, relation_type_id, target_organization_id, status'
        );

        $this->addIndexIfMissing(
            'organization_relations',
            'org_relations_catalog_edge_index',
            'catalog_id, relation_type_id, source_organization_id, target_organization_id'
        );

        $this->addIndexIfMissing(
            'organization_relations',
            'org_relations_source_reference_index',
            'catalog_id, source_code, source_reference'
        );

        $this->addForeignKeyIfMissing(
            'organization_relations',
            'org_relations_catalog_foreign',
            'catalog_id',
            'organization_catalogs',
            'id',
            'CASCADE'
        );

        $this->ensureGenericHierarchyRelationType();
    }


    public function down(): void
    {
        /*
         * Deliberately non-destructive.
         *
         * Catalog-scoped topology may already be referenced by
         * authorization, membership, tickets, workflow history,
         * integrations, or audit records.
         */
    }


    private function ensureGenericHierarchyRelationType(): void
    {
        $statement = $this->db->prepare("
            SELECT id
            FROM organization_relation_types
            WHERE code = ?
            LIMIT 1
        ");

        $statement->execute([
            'hierarchy_parent',
        ]);

        if ((int) $statement->fetchColumn() > 0) {
            return;
        }

        $insert = $this->db->prepare("
            INSERT INTO organization_relation_types
            (
                code,
                title,
                description,
                is_directional,
                is_hierarchical,
                allows_percentage,
                allows_dates,
                sort_order,
                status,
                created_at,
                updated_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                1,
                1,
                0,
                1,
                10,
                'active',
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ");

        $insert->execute([
            'hierarchy_parent',
            'سلسله‌مراتب سازمانی',
            'رابطه عمومی والد به فرزند در یک شبکه یا کاتالوگ سازمانی',
        ]);
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

        return (int) $statement->fetchColumn() === 1;
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

        $statement->execute([
            $table,
            $column,
        ]);

        return (int) $statement->fetchColumn() === 1;
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

        $statement->execute([
            $table,
            $index,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }


    private function foreignKeyExists(
        string $table,
        string $constraint
    ): bool {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.table_constraints
            WHERE constraint_schema = DATABASE()
              AND table_name = ?
              AND constraint_name = ?
              AND constraint_type = 'FOREIGN KEY'
        ");

        $statement->execute([
            $table,
            $constraint,
        ]);

        return (int) $statement->fetchColumn() === 1;
    }


    private function addColumnIfMissing(
        string $table,
        string $column,
        string $definition
    ): void {
        if ($this->columnExists($table, $column)) {
            return;
        }

        $this->db->exec(
            "ALTER TABLE `{$table}`
             ADD COLUMN `{$column}` {$definition}"
        );
    }


    private function addIndexIfMissing(
        string $table,
        string $index,
        string $columns
    ): void {
        if ($this->indexExists($table, $index)) {
            return;
        }

        $this->db->exec(
            "ALTER TABLE `{$table}`
             ADD INDEX `{$index}` ({$columns})"
        );
    }


    private function addForeignKeyIfMissing(
        string $table,
        string $constraint,
        string $column,
        string $targetTable,
        string $targetColumn,
        string $onDelete
    ): void {
        if ($this->foreignKeyExists($table, $constraint)) {
            return;
        }

        $this->db->exec(
            "ALTER TABLE `{$table}`
             ADD CONSTRAINT `{$constraint}`
             FOREIGN KEY (`{$column}`)
             REFERENCES `{$targetTable}` (`{$targetColumn}`)
             ON DELETE {$onDelete}
             ON UPDATE RESTRICT"
        );
    }
}
