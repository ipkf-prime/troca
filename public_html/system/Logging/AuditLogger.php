<?php

declare(strict_types=1);

namespace IPKF\Logging;

interface AuditLogger
{
    /**
     * Durable audit records must be implemented by a storage-backed
     * adapter. Operational log files are not a valid implementation.
     */
    public function record(
        string $eventCode,
        array $record
    ): void;
}
