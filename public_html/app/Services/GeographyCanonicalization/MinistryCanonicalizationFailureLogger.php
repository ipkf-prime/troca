<?php

namespace App\Services\GeographyCanonicalization;

use IPKF\Support\Clock;
use Throwable;

class MinistryCanonicalizationFailureLogger
{
    public function log(
        string $failureReference,
        string $stage,
        ?string $level,
        ?int $chunkNumber,
        Throwable $exception
    ): void {
        try {
            $directory = BASE_PATH . '/storage/logs';

            if (!is_dir($directory)) {
                mkdir($directory, 0750, true);
            }

            $chain = [];
            $current = $exception;

            do {
                $chain[] = [
                    'class' => $current::class,
                    'message' => $current->getMessage(),
                    'code' => $current->getCode(),
                    'file' => $current->getFile(),
                    'line' => $current->getLine(),
                    'trace' => $current->getTraceAsString(),
                ];
                $current = $current->getPrevious();
            } while ($current instanceof Throwable);

            $entry = json_encode([
                'timestamp' => Clock::isoUtc(Clock::nowUtc()),
                'failure_reference' => $failureReference,
                'failure_stage' => $stage,
                'failed_level_code' => $level,
                'failed_chunk_number' => $chunkNumber,
                'exception_chain' => $chain,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (is_string($entry)) {
                file_put_contents(
                    $directory . '/ministry-canonicalization.log',
                    $entry . PHP_EOL,
                    FILE_APPEND | LOCK_EX
                );
            }
        } catch (Throwable) {
            // Logging must never replace the original canonicalization failure.
        }
    }
}
