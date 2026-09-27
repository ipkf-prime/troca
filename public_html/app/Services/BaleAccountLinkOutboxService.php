<?php

declare(strict_types=1);

namespace App\Services;

use JsonException;

/**
 * Private, pilot-only account-link outbox.
 *
 * Enqueueing does NOT mean that the bot linked the account.
 * No network delivery or acknowledgement is implemented here.
 */
final class BaleAccountLinkOutboxService
{
    public function enqueue(array $envelope): bool
    {
        $directory = BaleAccountLinkPrivateStorage::outboxDirectory();

        if (
            !is_dir($directory) ||
            is_link($directory) ||
            (fileperms($directory) & 0777) !== 0700 ||
            !is_writable($directory)
        ) {
            return false;
        }

        $claims = $envelope['claims'] ?? null;
        $signature = $envelope['signature'] ?? null;
        $now = time();

        if (
            !is_array($claims) ||
            ($claims['version'] ?? null) !== 1 ||
            ($claims['connector_code'] ?? null) !== 'IPKF' ||
            !is_int($claims['issued_at'] ?? null) ||
            !is_int($claims['expires_at'] ?? null) ||
            $claims['issued_at'] < $now - 30 ||
            $claims['issued_at'] > $now + 30 ||
            $claims['expires_at'] <= $now ||
            $claims['expires_at'] > $now + 120 ||
            !is_string($signature) ||
            preg_match('/^[a-f0-9]{64}$/D', $signature) !== 1
        ) {
            return false;
        }

        $items = glob($directory . '/*.json', GLOB_NOSORT);

        if (!is_array($items) || count($items) >= 128) {
            return false;
        }

        try {
            $json = json_encode(
                [
                    'queued_at' => $now,
                    'envelope' => $envelope,
                ],
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return false;
        }

        if (!is_string($json) || strlen($json) > 4096) {
            return false;
        }

        $path = $directory . '/' .
            bin2hex(random_bytes(16)) . '.json';

        $handle = @fopen($path, 'x');

        if ($handle === false) {
            return false;
        }

        $complete = false;

        try {
            if (!chmod($path, 0600)) {
                return false;
            }

            $length = strlen($json);
            $written = 0;

            while ($written < $length) {
                $part = fwrite(
                    $handle,
                    substr($json, $written)
                );

                if ($part === false || $part === 0) {
                    return false;
                }

                $written += $part;
            }

            if (!fflush($handle)) {
                return false;
            }

            $complete = true;
            return true;
        } finally {
            fclose($handle);

            if (!$complete) {
                @unlink($path);
            }
        }
    }
}
