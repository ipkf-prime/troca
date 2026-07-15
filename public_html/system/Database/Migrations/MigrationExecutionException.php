<?php

namespace IPKF\Database\Migrations;

use RuntimeException;
use Throwable;

class MigrationExecutionException extends RuntimeException
{
    public function __construct(
        private readonly string $migrationClass,
        Throwable $previous
    ) {
        parent::__construct('Migration execution failed.', 0, $previous);
    }

    public function migrationClass(): string
    {
        return $this->migrationClass;
    }

    public function migrationBasename(): string
    {
        $separator = strrpos($this->migrationClass, '\\');
        $basename = $separator === false
            ? $this->migrationClass
            : substr($this->migrationClass, $separator + 1);

        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $basename) === 1
            ? $basename
            : 'unknown';
    }
}
