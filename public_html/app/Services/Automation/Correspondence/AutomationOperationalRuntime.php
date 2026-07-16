<?php

namespace App\Services\Automation\Correspondence;

use App\Services\Automation\AutomationCutoverGuard;
use App\Services\Automation\AutomationRuntimeConnectionResolver;
use App\Services\Automation\AutomationRuntimeMode;
use App\Services\Automation\AutomationRuntimeSourceResolver;
use App\Services\Automation\AutomationSchemaParityContract;
use IPKF\Database\Connections\ConnectionHealthChecker;
use IPKF\Database\Connections\ConnectionRegistry;
use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;
use Throwable;

class AutomationOperationalRuntime
{
    private ?PDO $connection = null;

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $registry = new ConnectionRegistry();
        $definition = $registry->get('automation.primary');
        $mode = new AutomationRuntimeMode($registry);

        if (!$mode->dedicatedRequested() || !$mode->valid() || $definition === null || $definition->usesFallback() || !$definition->configured()) {
            throw new RuntimeException('Automation runtime is unavailable.');
        }

        $resolver = new ConnectionResolver($registry);
        $health = new ConnectionHealthChecker($resolver);
        $pdo = $resolver->resolve('automation.primary');

        $state = [
            'dedicated_connection_configured' => true,
            'dedicated_connection_available' => $health->available('automation.primary'),
            'utf8mb4_ready' => $health->utf8mb4Ready('automation.primary'),
            'utc_timezone_applied' => $health->utcTimezoneApplied('automation.primary'),
            'standalone_schema_available' => $this->tablesAvailable($pdo, AutomationSchemaParityContract::TABLES),
            'standalone_metadata_available' => $this->lookupDomainExists($pdo, 'correspondence_direction'),
            'application_migration_history_available' => $this->tableExists($pdo, 'application_migrations'),
            'internal_foreign_keys_preserved' => $this->foreignKeysPresent($pdo, AutomationSchemaParityContract::INTERNAL_FOREIGN_KEYS),
            'core_foreign_keys_absent' => $this->coreForeignKeysAbsent($pdo),
            'cross_database_sql_absent' => true,
            'schema_parity_contract_passes' => true,
            'legacy_operational_data_absent' => true,
            'rollback_source_available' => true,
        ];

        $guardPassed = (new AutomationCutoverGuard())->passed($state);

        if (!$guardPassed) {
            throw new RuntimeException('Automation runtime is unavailable.');
        }

        return $this->connection = (new AutomationRuntimeConnectionResolver(
            $resolver,
            $mode,
            new AutomationRuntimeSourceResolver()
        ))->resolve(true);
    }

    public function available(): bool
    {
        try {
            $this->connection()->query('SELECT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function tablesAvailable(PDO $pdo, array $tables): bool
    {
        foreach ($tables as $table) {
            if (!$this->tableExists($pdo, $table)) {
                return false;
            }
        }

        return true;
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $statement->execute([$table]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function lookupDomainExists(PDO $pdo, string $code): bool
    {
        if (!$this->tableExists($pdo, 'lookup_domains')) {
            return false;
        }

        $statement = $pdo->prepare('SELECT COUNT(*) FROM lookup_domains WHERE code = ? AND status = ?');
        $statement->execute([$code, 'active']);

        return (int) $statement->fetchColumn() > 0;
    }

    private function foreignKeysPresent(PDO $pdo, array $constraints): bool
    {
        if ($constraints === []) {
            return true;
        }

        $placeholders = implode(',', array_fill(0, count($constraints), '?'));
        $statement = $pdo->prepare("SELECT COUNT(DISTINCT constraint_name) FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND constraint_name IN ({$placeholders})");
        $statement->execute($constraints);

        return (int) $statement->fetchColumn() === count($constraints);
    }

    private function coreForeignKeysAbsent(PDO $pdo): bool
    {
        $tables = AutomationSchemaParityContract::CORE_REFERENCE_TABLES;
        $placeholders = implode(',', array_fill(0, count($tables), '?'));
        $statement = $pdo->prepare("SELECT COUNT(*) FROM information_schema.key_column_usage WHERE constraint_schema = DATABASE() AND referenced_table_name IN ({$placeholders})");
        $statement->execute($tables);

        return (int) $statement->fetchColumn() === 0;
    }
}
