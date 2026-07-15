<?php

namespace IPKF\Database\Migrations;

use IPKF\Support\Clock;
use Throwable;

class MigrationFailureLogger
{
    public function log(string $migrationClass, Throwable $exception): string
    {
        $failureReference = $this->failureReference();

        try {
            $path = BASE_PATH . '/storage/logs/migration-failures.log';
            $directory = dirname($path);

            if (!is_dir($directory)) {
                mkdir($directory, 0750, true);
            }

            $entry = json_encode([
                'timestamp' => Clock::isoUtc(Clock::nowUtc()),
                'failure_reference' => $failureReference,
                'failing_migration_class' => $migrationClass,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
                'exception_code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'previous_exception_chain' => $this->previousExceptionChain($exception->getPrevious()),
                'stack_trace' => $exception->getTraceAsString(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

            if (is_string($entry)) {
                file_put_contents($path, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
                chmod($path, 0640);
            }
        } catch (Throwable) {
            // Private logging must never replace the original migration failure.
        }

        return $failureReference;
    }

    private function previousExceptionChain(?Throwable $exception): array
    {
        $chain = [];

        while ($exception instanceof Throwable) {
            $chain[] = [
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
                'exception_code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'stack_trace' => $exception->getTraceAsString(),
            ];
            $exception = $exception->getPrevious();
        }

        return $chain;
    }

    private function failureReference(): string
    {
        try {
            return 'MIG-' . strtoupper(bin2hex(random_bytes(8)));
        } catch (Throwable) {
            $fallback = hash('sha256', uniqid('', true) . '|' . hrtime(true) . '|' . getmypid());

            return 'MIG-' . strtoupper(substr($fallback, 0, 16));
        }
    }
}
