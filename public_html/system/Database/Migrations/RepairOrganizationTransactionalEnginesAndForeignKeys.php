<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

/**
 * Repairs the historical storage-engine/FK gap in the organization foundation.
 *
 * Historical organization tables were created without an explicit ENGINE clause
 * on environments whose default engine was MyISAM. Foreign-key helpers correctly
 * refused to create FKs against those tables.
 *
 * This migration:
 *  1. performs a complete structural/data preflight before the first DDL;
 *  2. converts only the required legacy tables to InnoDB;
 *  3. creates the exact FK contracts already declared by existing migrations;
 *  4. is safely rerunnable after a partial DDL failure.
 *
 * No business/customer rows are inserted, updated or deleted.
 *
 * MariaDB ALTER TABLE statements cause implicit commits, therefore this migration
 * deliberately relies on full preflight + idempotency rather than a false
 * transaction/rollback guarantee.
 */
final class RepairOrganizationTransactionalEnginesAndForeignKeys
    extends Migration
{
    /**
     * Tables that must support real foreign keys.
     */
    private const ENGINE_TABLES = [
        'organizations',
        'positions',
        'org_units',

        'organization_classification_schemes',
        'organization_classification_terms',
        'organization_classifications',

        'organization_relation_types',
        'organization_relations',

        'organization_unit_types',
        'organization_positions',
    ];


    /**
     * Exact FK contracts already defined throughout the existing platform.
     *
     * table, constraint, column, target_table, target_column, on_delete
     */
    private const FOREIGN_KEYS = [

        /*
         * Dynamic organization core.
         */
        [
            'organization_classification_terms',
            'org_class_terms_scheme_foreign',
            'scheme_id',
            'organization_classification_schemes',
            'id',
            'RESTRICT',
        ],
        [
            'organization_classification_terms',
            'org_class_terms_parent_foreign',
            'parent_id',
            'organization_classification_terms',
            'id',
            'SET NULL',
        ],
        [
            'organization_classifications',
            'org_classes_organization_foreign',
            'organization_id',
            'organizations',
            'id',
            'RESTRICT',
        ],
        [
            'organization_classifications',
            'org_classes_term_foreign',
            'classification_term_id',
            'organization_classification_terms',
            'id',
            'RESTRICT',
        ],
        [
            'organization_relations',
            'org_relations_source_foreign',
            'source_organization_id',
            'organizations',
            'id',
            'RESTRICT',
        ],
        [
            'organization_relations',
            'org_relations_target_foreign',
            'target_organization_id',
            'organizations',
            'id',
            'RESTRICT',
        ],
        [
            'organization_relations',
            'org_relations_type_foreign',
            'relation_type_id',
            'organization_relation_types',
            'id',
            'RESTRICT',
        ],
        [
            'org_units',
            'org_units_organization_foreign',
            'organization_id',
            'organizations',
            'id',
            'SET NULL',
        ],
        [
            'org_units',
            'org_units_unit_type_foreign',
            'unit_type_id',
            'organization_unit_types',
            'id',
            'SET NULL',
        ],
        [
            'organization_positions',
            'org_positions_organization_foreign',
            'organization_id',
            'organizations',
            'id',
            'RESTRICT',
        ],
        [
            'organization_positions',
            'org_positions_unit_foreign',
            'org_unit_id',
            'org_units',
            'id',
            'SET NULL',
        ],
        [
            'organization_positions',
            'org_positions_position_foreign',
            'position_id',
            'positions',
            'id',
            'RESTRICT',
        ],
        [
            'organization_positions',
            'org_positions_parent_foreign',
            'parent_position_id',
            'organization_positions',
            'id',
            'SET NULL',
        ],
        [
            'organization_appointments',
            'org_appointments_organization_foreign',
            'organization_id',
            'organizations',
            'id',
            'RESTRICT',
        ],
        [
            'organization_appointments',
            'org_appointments_person_foreign',
            'person_id',
            'persons',
            'id',
            'RESTRICT',
        ],
        [
            'organization_appointments',
            'org_appointments_position_foreign',
            'organization_position_id',
            'organization_positions',
            'id',
            'RESTRICT',
        ],

        /*
         * Catalog-scoped organization topology.
         */
        [
            'organization_relations',
            'org_relations_catalog_foreign',
            'catalog_id',
            'organization_catalogs',
            'id',
            'CASCADE',
        ],

        /*
         * Automation -> Core organization references.
         */
        [
            'correspondences',
            'corr_organization_fk',
            'organization_id',
            'organizations',
            'id',
            'RESTRICT',
        ],
        [
            'correspondences',
            'corr_org_unit_fk',
            'org_unit_id',
            'org_units',
            'id',
            'RESTRICT',
        ],
        [
            'correspondence_parties',
            'corr_parties_org_fk',
            'organization_id',
            'organizations',
            'id',
            'RESTRICT',
        ],
        [
            'correspondence_parties',
            'corr_parties_unit_fk',
            'org_unit_id',
            'org_units',
            'id',
            'RESTRICT',
        ],
        [
            'registry_books',
            'registry_books_org_fk',
            'organization_id',
            'organizations',
            'id',
            'RESTRICT',
        ],
        [
            'registry_books',
            'registry_books_unit_fk',
            'org_unit_id',
            'org_units',
            'id',
            'RESTRICT',
        ],
        [
            'correspondence_referrals',
            'corr_ref_source_unit_fk',
            'source_org_unit_id',
            'org_units',
            'id',
            'RESTRICT',
        ],
        [
            'correspondence_referrals',
            'corr_ref_target_unit_fk',
            'target_org_unit_id',
            'org_units',
            'id',
            'RESTRICT',
        ],
        [
            'correspondence_referrals',
            'corr_ref_target_pos_fk',
            'target_position_id',
            'positions',
            'id',
            'RESTRICT',
        ],
        [
            'correspondence_events',
            'corr_events_unit_fk',
            'actor_org_unit_id',
            'org_units',
            'id',
            'RESTRICT',
        ],

        /*
         * Platform commercial installation owner.
         */
        [
            'platform_installations',
            'platform_installations_owner_org_fk',
            'owner_organization_id',
            'organizations',
            'id',
            'RESTRICT',
        ],
    ];


    public function up(): void
    {
        /*
         * Absolutely no DDL before the complete preflight succeeds.
         */
        $this->preflight();

        /*
         * Convert all required legacy engines first.
         */
        foreach (self::ENGINE_TABLES as $table) {
            $this->ensureInnoDb($table);
        }

        /*
         * Only after every parent/child engine is suitable do we create FKs.
         */
        foreach (self::FOREIGN_KEYS as $foreignKey) {
            $this->ensureForeignKey(...$foreignKey);
        }

        /*
         * Hard postcondition.
         */
        $this->verifyPostconditions();
    }


    public function down(): void
    {
        /*
         * Deliberately non-destructive.
         *
         * Reverting back to MyISAM or removing referential integrity could
         * invalidate live platform data and is not a safe rollback strategy.
         */
    }


    private function preflight(): void
    {
        foreach (self::ENGINE_TABLES as $table) {
            if (!$this->tableExists($table)) {
                throw new \RuntimeException(
                    'Required engine table is missing: ' . $table
                );
            }
        }

        foreach (self::FOREIGN_KEYS as $foreignKey) {
            [
                $table,
                $constraint,
                $column,
                $targetTable,
                $targetColumn,
                $onDelete,
            ] = $foreignKey;

            $this->assertIdentifier($table);
            $this->assertIdentifier($constraint);
            $this->assertIdentifier($column);
            $this->assertIdentifier($targetTable);
            $this->assertIdentifier($targetColumn);
            $this->assertDeleteRule($onDelete);

            if (!$this->tableExists($table)) {
                throw new \RuntimeException(
                    'FK child table is missing: ' . $table
                );
            }

            if (!$this->tableExists($targetTable)) {
                throw new \RuntimeException(
                    'FK parent table is missing: ' . $targetTable
                );
            }

            if (!$this->columnExists($table, $column)) {
                throw new \RuntimeException(
                    'FK child column is missing: '
                    . $table
                    . '.'
                    . $column
                );
            }

            if (!$this->columnExists($targetTable, $targetColumn)) {
                throw new \RuntimeException(
                    'FK parent column is missing: '
                    . $targetTable
                    . '.'
                    . $targetColumn
                );
            }

            $childType =
                $this->columnType(
                    $table,
                    $column
                );

            $parentType =
                $this->columnType(
                    $targetTable,
                    $targetColumn
                );

            if ($childType !== $parentType) {
                throw new \RuntimeException(
                    'FK column type mismatch for '
                    . $constraint
                    . ': '
                    . $childType
                    . ' != '
                    . $parentType
                );
            }

            if (
                $onDelete === 'SET NULL'
                && !$this->columnNullable(
                    $table,
                    $column
                )
            ) {
                throw new \RuntimeException(
                    'SET NULL FK requires nullable column: '
                    . $table
                    . '.'
                    . $column
                );
            }

            $orphanCount =
                $this->orphanCount(
                    $table,
                    $column,
                    $targetTable,
                    $targetColumn
                );

            if ($orphanCount !== 0) {
                throw new \RuntimeException(
                    'Orphan data blocks FK '
                    . $constraint
                    . ': '
                    . $orphanCount
                );
            }

            $existing =
                $this->foreignKeyDefinition(
                    $table,
                    $constraint
                );

            if ($existing !== null) {
                $this->assertForeignKeyDefinition(
                    $existing,
                    $constraint,
                    $column,
                    $targetTable,
                    $targetColumn,
                    $onDelete
                );

                continue;
            }

            /*
             * Do not silently create a duplicate relationship under
             * a second constraint name.
             */
            $equivalent =
                $this->equivalentForeignKey(
                    $table,
                    $column,
                    $targetTable,
                    $targetColumn
                );

            if ($equivalent !== null) {
                throw new \RuntimeException(
                    'Equivalent FK exists under unexpected name: '
                    . $table
                    . '.'
                    . $column
                    . ' -> '
                    . $targetTable
                    . '.'
                    . $targetColumn
                    . ' as '
                    . ($equivalent['constraint_name'] ?? 'unknown')
                );
            }
        }
    }


    private function verifyPostconditions(): void
    {
        foreach (self::ENGINE_TABLES as $table) {
            if ($this->tableEngine($table) !== 'innodb') {
                throw new \RuntimeException(
                    'Postcondition failed: table is not InnoDB: '
                    . $table
                );
            }
        }

        foreach (self::FOREIGN_KEYS as $foreignKey) {
            [
                $table,
                $constraint,
                $column,
                $targetTable,
                $targetColumn,
                $onDelete,
            ] = $foreignKey;

            $existing =
                $this->foreignKeyDefinition(
                    $table,
                    $constraint
                );

            if ($existing === null) {
                throw new \RuntimeException(
                    'Postcondition failed: FK missing: '
                    . $constraint
                );
            }

            $this->assertForeignKeyDefinition(
                $existing,
                $constraint,
                $column,
                $targetTable,
                $targetColumn,
                $onDelete
            );
        }
    }


    private function ensureInnoDb(string $table): void
    {
        $this->assertIdentifier($table);

        if ($this->tableEngine($table) === 'innodb') {
            return;
        }

        $this->db->exec(
            'ALTER TABLE '
            . $this->quoteIdentifier($table)
            . ' ENGINE=InnoDB'
        );

        if ($this->tableEngine($table) !== 'innodb') {
            throw new \RuntimeException(
                'Engine conversion failed: '
                . $table
            );
        }
    }


    private function ensureForeignKey(
        string $table,
        string $constraint,
        string $column,
        string $targetTable,
        string $targetColumn,
        string $onDelete
    ): void {
        $existing =
            $this->foreignKeyDefinition(
                $table,
                $constraint
            );

        if ($existing !== null) {
            $this->assertForeignKeyDefinition(
                $existing,
                $constraint,
                $column,
                $targetTable,
                $targetColumn,
                $onDelete
            );

            return;
        }

        if (
            $this->tableEngine($table) !== 'innodb'
            || $this->tableEngine($targetTable) !== 'innodb'
        ) {
            throw new \RuntimeException(
                'FK engine requirement failed: '
                . $constraint
            );
        }

        if (
            $this->orphanCount(
                $table,
                $column,
                $targetTable,
                $targetColumn
            ) !== 0
        ) {
            throw new \RuntimeException(
                'FK orphan guard failed immediately before DDL: '
                . $constraint
            );
        }

        $sql =
            'ALTER TABLE '
            . $this->quoteIdentifier($table)
            . ' ADD CONSTRAINT '
            . $this->quoteIdentifier($constraint)
            . ' FOREIGN KEY ('
            . $this->quoteIdentifier($column)
            . ') REFERENCES '
            . $this->quoteIdentifier($targetTable)
            . ' ('
            . $this->quoteIdentifier($targetColumn)
            . ') ON UPDATE RESTRICT ON DELETE '
            . $onDelete;

        $this->db->exec($sql);
    }


    private function tableExists(string $table): bool
    {
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
            === 1;
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
            === 1;
    }


    private function columnType(
        string $table,
        string $column
    ): string {
        $statement =
            $this->db->prepare("
                SELECT LOWER(column_type)
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                  AND column_name = ?
                LIMIT 1
            ");

        $statement->execute([
            $table,
            $column,
        ]);

        $type =
            $statement->fetchColumn();

        if (!is_string($type) || $type === '') {
            throw new \RuntimeException(
                'Column type unavailable: '
                . $table
                . '.'
                . $column
            );
        }

        return trim($type);
    }


    private function columnNullable(
        string $table,
        string $column
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT is_nullable
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                  AND column_name = ?
                LIMIT 1
            ");

        $statement->execute([
            $table,
            $column,
        ]);

        return
            strtoupper(
                (string) $statement->fetchColumn()
            )
            === 'YES';
    }


    private function tableEngine(string $table): string
    {
        $statement =
            $this->db->prepare("
                SELECT engine
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                LIMIT 1
            ");

        $statement->execute([
            $table,
        ]);

        $engine =
            strtolower(
                trim(
                    (string) $statement->fetchColumn()
                )
            );

        if ($engine === '') {
            throw new \RuntimeException(
                'Table engine unavailable: '
                . $table
            );
        }

        return $engine;
    }


    private function orphanCount(
        string $table,
        string $column,
        string $targetTable,
        string $targetColumn
    ): int {
        $sql =
            'SELECT COUNT(*)'
            . ' FROM '
            . $this->quoteIdentifier($table)
            . ' child'
            . ' LEFT JOIN '
            . $this->quoteIdentifier($targetTable)
            . ' parent'
            . ' ON parent.'
            . $this->quoteIdentifier($targetColumn)
            . ' = child.'
            . $this->quoteIdentifier($column)
            . ' WHERE child.'
            . $this->quoteIdentifier($column)
            . ' IS NOT NULL'
            . ' AND parent.'
            . $this->quoteIdentifier($targetColumn)
            . ' IS NULL';

        return
            (int) $this->db
                ->query($sql)
                ->fetchColumn();
    }


    private function foreignKeyDefinition(
        string $table,
        string $constraint
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    kcu.constraint_name,
                    kcu.column_name,
                    kcu.referenced_table_name,
                    kcu.referenced_column_name,
                    UPPER(rc.update_rule) AS update_rule,
                    UPPER(rc.delete_rule) AS delete_rule

                FROM information_schema.key_column_usage kcu

                JOIN information_schema.referential_constraints rc
                  ON rc.constraint_schema =
                     kcu.constraint_schema
                 AND rc.table_name =
                     kcu.table_name
                 AND rc.constraint_name =
                     kcu.constraint_name

                WHERE kcu.constraint_schema = DATABASE()
                  AND kcu.table_name = ?
                  AND kcu.constraint_name = ?
                  AND kcu.referenced_table_name IS NOT NULL

                LIMIT 1
            ");

        $statement->execute([
            $table,
            $constraint,
        ]);

        $row =
            $statement->fetch(
                \PDO::FETCH_ASSOC
            );

        return
            is_array($row)
                ? $row
                : null;
    }


    private function equivalentForeignKey(
        string $table,
        string $column,
        string $targetTable,
        string $targetColumn
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    constraint_name,
                    column_name,
                    referenced_table_name,
                    referenced_column_name

                FROM information_schema.key_column_usage

                WHERE constraint_schema = DATABASE()
                  AND table_name = ?
                  AND column_name = ?
                  AND referenced_table_name = ?
                  AND referenced_column_name = ?

                LIMIT 1
            ");

        $statement->execute([
            $table,
            $column,
            $targetTable,
            $targetColumn,
        ]);

        $row =
            $statement->fetch(
                \PDO::FETCH_ASSOC
            );

        return
            is_array($row)
                ? $row
                : null;
    }


    private function assertForeignKeyDefinition(
        array $actual,
        string $constraint,
        string $column,
        string $targetTable,
        string $targetColumn,
        string $onDelete
    ): void {
        $updateRule =
            $this->normalizeRestrictRule(
                (string) (
                    $actual['update_rule']
                    ?? ''
                )
            );

        $deleteRule =
            $this->normalizeRestrictRule(
                (string) (
                    $actual['delete_rule']
                    ?? ''
                )
            );

        if (
            (string) (
                $actual['column_name']
                ?? ''
            ) !== $column
            ||
            (string) (
                $actual['referenced_table_name']
                ?? ''
            ) !== $targetTable
            ||
            (string) (
                $actual['referenced_column_name']
                ?? ''
            ) !== $targetColumn
            ||
            $updateRule !== 'RESTRICT'
            ||
            $deleteRule !== $onDelete
        ) {
            throw new \RuntimeException(
                'Existing FK definition conflicts with contract: '
                . $constraint
            );
        }
    }


    private function normalizeRestrictRule(
        string $rule
    ): string {
        $rule =
            strtoupper(
                trim($rule)
            );

        return
            $rule === 'NO ACTION'
                ? 'RESTRICT'
                : $rule;
    }


    private function assertDeleteRule(
        string $rule
    ): void {
        if (
            !in_array(
                $rule,
                [
                    'RESTRICT',
                    'CASCADE',
                    'SET NULL',
                ],
                true
            )
        ) {
            throw new \RuntimeException(
                'Unsupported delete rule: '
                . $rule
            );
        }
    }


    private function quoteIdentifier(
        string $identifier
    ): string {
        $this->assertIdentifier(
            $identifier
        );

        return
            '`'
            . $identifier
            . '`';
    }


    private function assertIdentifier(
        string $identifier
    ): void {
        if (
            !preg_match(
                '/^[A-Za-z0-9_]+$/',
                $identifier
            )
        ) {
            throw new \RuntimeException(
                'Unsafe SQL identifier: '
                . $identifier
            );
        }
    }
}
