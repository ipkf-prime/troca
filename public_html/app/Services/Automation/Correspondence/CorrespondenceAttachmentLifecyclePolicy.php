<?php

namespace App\Services\Automation\Correspondence;

use IPKF\Support\Env;

/**
 * attachment-lifecycle-policy-v1
 */
class CorrespondenceAttachmentLifecyclePolicy
{
    private const DEFAULT_RETENTION_DAYS = 30;
    private const DEFAULT_BATCH_SIZE = 100;

    public function retentionDays(): int
    {
        return $this->integer(
            'AUTOMATION_ATTACHMENT_RETENTION_DAYS',
            self::DEFAULT_RETENTION_DAYS,
            1,
            3650
        );
    }

    public function batchSize(): int
    {
        return $this->integer(
            'AUTOMATION_ATTACHMENT_PURGE_BATCH_SIZE',
            self::DEFAULT_BATCH_SIZE,
            1,
            1000
        );
    }

    private function integer(
        string $key,
        int $default,
        int $minimum,
        int $maximum
    ): int {
        $value =
            filter_var(
                Env::get(
                    $key,
                    (string) $default
                ),
                FILTER_VALIDATE_INT
            );

        if ($value === false) {
            return $default;
        }

        return max(
            $minimum,
            min(
                $maximum,
                (int) $value
            )
        );
    }
}