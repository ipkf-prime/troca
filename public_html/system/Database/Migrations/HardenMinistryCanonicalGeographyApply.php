<?php

namespace IPKF\Database\Migrations;

class HardenMinistryCanonicalGeographyApply extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('geographic_canonicalization_runs')) {
            return;
        }

        $columns = [
            'failure_reference' => 'VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL',
            'failure_stage' => 'VARCHAR(80) NULL',
            'failure_exception_class' => 'VARCHAR(191) NULL',
            'failure_sqlstate' => 'VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL',
            'failure_driver_code' => 'VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL',
            'failure_message_hash' => 'CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL',
            'failed_level_code' => 'VARCHAR(60) NULL',
            'failed_chunk_number' => 'BIGINT UNSIGNED NULL',
            'failed_at' => 'TIMESTAMP NULL',
            'private_failure_context_json' => 'LONGTEXT NULL',
        ];

        foreach ($columns as $column => $definition) {
            if (!$this->columnExists('geographic_canonicalization_runs', $column)) {
                $this->db->exec(
                    "ALTER TABLE geographic_canonicalization_runs ADD COLUMN {$column} {$definition}"
                );
            }
        }

        if (!$this->indexExists('geographic_canonicalization_runs', 'geo_canonical_runs_failure_reference_index')) {
            $this->db->exec("
                ALTER TABLE geographic_canonicalization_runs
                ADD INDEX geo_canonical_runs_failure_reference_index (failure_reference)
            ");
        }
    }

    public function down(): void
    {
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = ?
        ");
        $statement->execute([$table]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
        ");
        $statement->execute([$table, $column]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function indexExists(string $table, string $index): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
        ");
        $statement->execute([$table, $index]);

        return (int) $statement->fetchColumn() > 0;
    }
}
