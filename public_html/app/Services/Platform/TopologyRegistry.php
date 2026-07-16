<?php

namespace App\Services\Platform;

class TopologyRegistry
{
    public function databasePurposes(): array
    {
        return ['primary', 'read_replica', 'reporting', 'archive'];
    }

    public function secretReferenceColumns(): array
    {
        return [
            'credential_secret_reference',
            'signed_manifest_reference',
            'revocation_reference',
        ];
    }
}
