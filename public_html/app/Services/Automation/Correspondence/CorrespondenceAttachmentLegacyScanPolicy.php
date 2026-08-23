<?php

namespace App\Services\Automation\Correspondence;

use IPKF\Support\Env;

/**
 * attachment-legacy-scan-policy-v1
 */
class CorrespondenceAttachmentLegacyScanPolicy
{
    private const DEFAULT_BATCH_SIZE = 10;

    public function batchSize(): int
    {
        $value =
            filter_var(
                Env::get(
                    'AUTOMATION_ATTACHMENT_LEGACY_SCAN_BATCH_SIZE',
                    (string) self::DEFAULT_BATCH_SIZE
                ),
                FILTER_VALIDATE_INT
            );

        if ($value === false) {
            return self::DEFAULT_BATCH_SIZE;
        }

        return max(
            1,
            min(
                100,
                (int) $value
            )
        );
    }
}