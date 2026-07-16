<?php

namespace App\Services\Automation;

class AutomationProvisioningReadiness
{
    public function prerequisitesPassed(array $state): bool
    {
        foreach ($this->requiredKeys() as $key) {
            if (($state[$key] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    public function requiredKeys(): array
    {
        return [
            'dedicated_connection_configured',
            'dedicated_connection_available',
            'utf8mb4_ready',
            'utc_timezone_applied',
            'standalone_schema_available',
            'standalone_metadata_available',
            'application_migration_history_available',
            'internal_foreign_keys_preserved',
            'core_foreign_keys_absent',
            'cross_database_sql_absent',
            'schema_parity_contract_passes',
            'legacy_operational_data_absent',
            'rollback_source_available',
        ];
    }

    public function cutoverReady(
        bool $dedicatedConnectionAvailable,
        bool $standaloneSchemaAvailable,
        bool $standaloneMetadataAvailable,
        bool $legacyOperationalDataAbsent,
        bool $internalForeignKeysPreserved,
        bool $coreForeignKeysAbsent,
        bool $crossDatabaseSqlAbsent
    ): bool {
        return $dedicatedConnectionAvailable
            && $standaloneSchemaAvailable
            && $standaloneMetadataAvailable
            && $legacyOperationalDataAbsent
            && $internalForeignKeysPreserved
            && $coreForeignKeysAbsent
            && $crossDatabaseSqlAbsent;
    }
}
