<?php

namespace App\Services\Automation;

class AutomationProvisioningReadiness
{
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
