<?php

namespace IPKF\Database\Migrations;

class CreateNotificationGatewayFoundation extends Migration
{
    public function up(): void
    {
        $this->extendDeliveries();
        $this->extendAttempts();
        $this->backfillDeliveryReferences();
        $this->addIndexes();
        $this->addForeignKeys();
    }

    public function down(): void
    {
    }

    private function extendDeliveries(): void
    {
        if (!$this->tableExists(
            'notification_deliveries'
        )) {
            return;
        }

        $columns = [
            'public_reference' => "
                ADD COLUMN public_reference
                VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                NULL
                AFTER id
            ",
            'purpose_code' => "
                ADD COLUMN purpose_code
                VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                NOT NULL DEFAULT 'general'
                AFTER channel_code
            ",
            'provider_instance_id' => "
                ADD COLUMN provider_instance_id
                BIGINT UNSIGNED NULL
                AFTER provider_code
            ",
            'provider_type_code' => "
                ADD COLUMN provider_type_code
                VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                NULL
                AFTER provider_instance_id
            ",
            'request_reference' => "
                ADD COLUMN request_reference
                VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                NULL
                AFTER provider_message_reference
            ",
            'last_response_code' => "
                ADD COLUMN last_response_code
                VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                NULL
                AFTER last_error
            ",
        ];

        foreach ($columns as $column => $definition) {
            if (!$this->columnExists(
                'notification_deliveries',
                $column
            )) {
                $this->db->exec(
                    "ALTER TABLE notification_deliveries "
                    . $definition
                );
            }
        }
    }

    private function extendAttempts(): void
    {
        if (!$this->tableExists(
            'notification_delivery_attempts'
        )) {
            return;
        }

        $columns = [
            'provider_instance_id' => "
                ADD COLUMN provider_instance_id
                BIGINT UNSIGNED NULL
                AFTER status_code
            ",
            'provider_type_code' => "
                ADD COLUMN provider_type_code
                VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                NULL
                AFTER provider_instance_id
            ",
            'provider_message_reference' => "
                ADD COLUMN provider_message_reference
                VARCHAR(500) NULL
                AFTER provider_type_code
            ",
            'duration_ms' => "
                ADD COLUMN duration_ms
                INT UNSIGNED NOT NULL DEFAULT 0
                AFTER provider_response_message
            ",
            'response_metadata_json' => "
                ADD COLUMN response_metadata_json
                LONGTEXT NULL
                AFTER duration_ms
            ",
        ];

        foreach ($columns as $column => $definition) {
            if (!$this->columnExists(
                'notification_delivery_attempts',
                $column
            )) {
                $this->db->exec(
                    "ALTER TABLE "
                    . "notification_delivery_attempts "
                    . $definition
                );
            }
        }
    }

    private function backfillDeliveryReferences(): void
    {
        if (
            !$this->tableExists(
                'notification_deliveries'
            )
            || !$this->columnExists(
                'notification_deliveries',
                'public_reference'
            )
        ) {
            return;
        }

        $this->db->exec("
            UPDATE notification_deliveries
            SET public_reference = CONCAT(
                'ndl_',
                LOWER(LPAD(HEX(id), 24, '0'))
            )
            WHERE public_reference IS NULL
               OR public_reference = ''
        ");

        $this->db->exec("
            ALTER TABLE notification_deliveries
            MODIFY public_reference
                VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                NOT NULL
        ");
    }

    private function addIndexes(): void
    {
        if (
            $this->tableExists(
                'notification_deliveries'
            )
            && !$this->indexExists(
                'notification_deliveries',
                'notification_deliveries_reference_unique'
            )
        ) {
            $this->db->exec("
                ALTER TABLE notification_deliveries
                ADD UNIQUE KEY
                    notification_deliveries_reference_unique (
                        public_reference
                    )
            ");
        }

        if (
            $this->tableExists(
                'notification_deliveries'
            )
            && !$this->indexExists(
                'notification_deliveries',
                'notification_deliveries_provider_status_index'
            )
        ) {
            $this->db->exec("
                ALTER TABLE notification_deliveries
                ADD INDEX
                    notification_deliveries_provider_status_index (
                        provider_instance_id,
                        status_code,
                        last_attempt_at,
                        id
                    )
            ");
        }

        if (
            $this->tableExists(
                'notification_delivery_attempts'
            )
            && !$this->indexExists(
                'notification_delivery_attempts',
                'notification_attempts_provider_time_index'
            )
        ) {
            $this->db->exec("
                ALTER TABLE notification_delivery_attempts
                ADD INDEX
                    notification_attempts_provider_time_index (
                        provider_instance_id,
                        attempted_at,
                        id
                    )
            ");
        }
    }

    private function addForeignKeys(): void
    {
        foreach ([
            [
                'notification_deliveries',
                'notification_deliveries_provider_instance_fk',
                'provider_instance_id',
                'notification_provider_instances',
                'id',
                'SET NULL',
            ],
            [
                'notification_delivery_attempts',
                'notification_attempts_provider_instance_fk',
                'provider_instance_id',
                'notification_provider_instances',
                'id',
                'SET NULL',
            ],
        ] as $foreignKey) {
            $this->addForeignKeyIfPossible(
                ...$foreignKey
            );
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
        if (
            !$this->tableExists($table)
            || !$this->tableExists($referenceTable)
            || !$this->columnExists($table, $column)
            || !$this->columnExists(
                $referenceTable,
                $referenceColumn
            )
            || $this->foreignKeyExists(
                $table,
                $constraint
            )
            || !$this->supportsForeignKeys($table)
            || !$this->supportsForeignKeys(
                $referenceTable
            )
            || $this->columnType($table, $column)
                !== $this->columnType(
                    $referenceTable,
                    $referenceColumn
                )
        ) {
            return;
        }

        $this->db->exec("
            ALTER TABLE {$table}
            ADD CONSTRAINT {$constraint}
            FOREIGN KEY ({$column})
            REFERENCES {$referenceTable}
                ({$referenceColumn})
            ON UPDATE CASCADE
            ON DELETE {$onDelete}
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
            FROM information_schema.referential_constraints
            WHERE constraint_schema = DATABASE()
              AND table_name = ?
              AND constraint_name = ?
        ");
        $statement->execute([
            $table,
            $constraint,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function supportsForeignKeys(
        string $table
    ): bool {
        $statement = $this->db->prepare("
            SELECT ENGINE
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
            LIMIT 1
        ");
        $statement->execute([$table]);

        return strtolower(
            trim((string) $statement->fetchColumn())
        ) === 'innodb';
    }

    private function columnType(
        string $table,
        string $column
    ): string {
        $statement = $this->db->prepare("
            SELECT COLUMN_TYPE
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

        return strtolower(
            trim((string) $statement->fetchColumn())
        );
    }
}
