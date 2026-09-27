<?php

declare(strict_types=1);

namespace App\Services;

/**
 * IPKF private queue API for the Bale account-link pilot.
 *
 * No secret or network destination is embedded here.
 * Without explicitly enabled private configuration, the API
 * returns 503 and does not expose queue items.
 */
final class BaleAccountLinkQueueApiService
{
    private const PULL =
        '/integrations/bale/link-queue/v1/next';

    private const ACK =
        '/integrations/bale/link-queue/v1/ack';

    private function result(int $status): array
    {
        return [
            'status' => $status,
            'payload' => ['ok' => false],
        ];
    }

    private function privateDirectory(string $path): bool
    {
        return is_dir($path)
            && !is_link($path)
            && (fileperms($path) & 0777) === 0700;
    }

    private function queueKey(): ?string
    {
        $path = BaleAccountLinkPrivateStorage::rootDirectory() . '/config.json';

        if (
            !is_file($path) ||
            is_link($path) ||
            (fileperms($path) & 0777) !== 0600
        ) {
            return null;
        }

        try {
            $raw = file_get_contents($path);

            if (!is_string($raw) || strlen($raw) > 4096) {
                return null;
            }

            $config = json_decode(
                $raw,
                true,
                8,
                JSON_THROW_ON_ERROR
            );

            if (
                !is_array($config) ||
                ($config['queue_enabled'] ?? null) !== true ||
                !is_string(
                    $config['queue_pull_key_hex'] ?? null
                ) ||
                preg_match(
                    '/^[a-f0-9]{64}$/D',
                    $config['queue_pull_key_hex']
                ) !== 1
            ) {
                return null;
            }

            $key = hex2bin(
                $config['queue_pull_key_hex']
            );

            return is_string($key) && strlen($key) === 32
                ? $key
                : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function handle(
        string $method,
        string $path,
        string $body,
        string $timestampHeader,
        string $nonceHeader,
        string $signatureHeader
    ): array {
        if (
            !in_array(
                [$method, $path],
                [
                    ['GET', self::PULL],
                    ['POST', self::ACK],
                ],
                true
            ) ||
            strlen($body) > 256 ||
            ($method === 'GET' && $body !== '')
        ) {
            return $this->result(400);
        }

        $key = $this->queueKey();

        if ($key === null) {
            return $this->result(503);
        }

        if (
            preg_match(
                '/^[1-9][0-9]{0,11}$/D',
                $timestampHeader
            ) !== 1 ||
            preg_match(
                '/^[a-f0-9]{32}$/D',
                $nonceHeader
            ) !== 1 ||
            preg_match(
                '/^[a-f0-9]{64}$/D',
                $signatureHeader
            ) !== 1
        ) {
            return $this->result(401);
        }

        $now = time();
        $timestamp = (int) $timestampHeader;

        if (
            $timestamp < $now - 30 ||
            $timestamp > $now + 30
        ) {
            return $this->result(401);
        }

        $canonical =
            "ipkf-bale-queue-v1\n"
            . $method . "\n"
            . $path . "\n"
            . $timestampHeader . "\n"
            . $nonceHeader . "\n"
            . hash('sha256', $body);

        $expected = hash_hmac(
            'sha256',
            $canonical,
            $key
        );

        if (!hash_equals($expected, $signatureHeader)) {
            return $this->result(401);
        }

        /*
         * Atomic, persistent nonce reservation.
         * A duplicate signed queue request cannot be reused.
         */
        $nonceDir = BaleAccountLinkPrivateStorage::rootDirectory() . '/queue-nonces';

        if (!$this->privateDirectory($nonceDir)) {
            return $this->result(503);
        }

        $noncePath = $nonceDir . '/' . $nonceHeader;

        $handle = @fopen($noncePath, 'x');

        if ($handle === false) {
            return $this->result(409);
        }

        $permissionOk = @chmod($noncePath, 0600);
        fclose($handle);

        if (!$permissionOk) {
            return $this->result(503);
        }

        return $method === 'GET'
            ? $this->nextItem()
            : $this->acknowledge($body);
    }

    private function lockQueue(): mixed
    {
        $path = BaleAccountLinkPrivateStorage::rootDirectory() . '/queue.lock';

        if (is_link($path)) {
            return false;
        }

        $handle = @fopen($path, 'c');

        if ($handle === false) {
            return false;
        }

        if (
            !@chmod($path, 0600) ||
            !flock($handle, LOCK_EX)
        ) {
            fclose($handle);
            return false;
        }

        return $handle;
    }

    private function unlockQueue(mixed $handle): void
    {
        if (is_resource($handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function nextItem(): array
    {
        $directory = BaleAccountLinkPrivateStorage::rootDirectory() . '/outbox';

        if (!$this->privateDirectory($directory)) {
            return $this->result(503);
        }

        $lock = $this->lockQueue();

        if ($lock === false) {
            return $this->result(503);
        }

        try {
            $files = glob(
                $directory . '/*.json',
                GLOB_NOSORT
            );

            if (!is_array($files)) {
                return $this->result(503);
            }

            sort($files, SORT_STRING);

            foreach ($files as $file) {
                $id = basename($file, '.json');

                if (
                    preg_match(
                        '/^[a-f0-9]{32}$/D',
                        $id
                    ) !== 1 ||
                    is_link($file) ||
                    !is_file($file) ||
                    (fileperms($file) & 0777) !== 0600
                ) {
                    continue;
                }

                $raw = @file_get_contents($file);

                if (
                    !is_string($raw) ||
                    strlen($raw) > 4096
                ) {
                    continue;
                }

                try {
                    $item = json_decode(
                        $raw,
                        true,
                        12,
                        JSON_THROW_ON_ERROR
                    );
                } catch (\Throwable) {
                    continue;
                }

                $claims =
                    $item['envelope']['claims'] ?? null;

                if (
                    !is_array($claims) ||
                    ($claims['connector_code'] ?? null)
                        !== 'IPKF' ||
                    !is_int($claims['expires_at'] ?? null) ||
                    $claims['expires_at'] <= time() + 5
                ) {
                    continue;
                }

                return [
                    'status' => 200,
                    'payload' => [
                        'ok' => true,
                        'item_id' => $id,
                        'envelope' => $item['envelope'],
                    ],
                ];
            }

            return [
                'status' => 200,
                'payload' => [
                    'ok' => true,
                    'item_id' => null,
                    'envelope' => null,
                ],
            ];
        } finally {
            $this->unlockQueue($lock);
        }
    }

    private function acknowledge(string $body): array
    {
        try {
            $data = json_decode(
                $body,
                true,
                4,
                JSON_THROW_ON_ERROR
            );
        } catch (\Throwable) {
            return $this->result(400);
        }

        if (
            !is_array($data) ||
            count($data) !== 2 ||
            !is_string($data['item_id'] ?? null) ||
            preg_match(
                '/^[a-f0-9]{32}$/D',
                $data['item_id']
            ) !== 1 ||
            ($data['outcome'] ?? null) !== 'committed'
        ) {
            return $this->result(400);
        }

        $outbox = BaleAccountLinkPrivateStorage::rootDirectory() . '/outbox';
        $ackDir = BaleAccountLinkPrivateStorage::rootDirectory() . '/acknowledged';

        if (
            !$this->privateDirectory($outbox) ||
            !$this->privateDirectory($ackDir)
        ) {
            return $this->result(503);
        }

        $id = $data['item_id'];
        $source = $outbox . '/' . $id . '.json';
        $destination = $ackDir . '/' . $id . '.json';

        $lock = $this->lockQueue();

        if ($lock === false) {
            return $this->result(503);
        }

        try {
            if (
                is_file($destination) &&
                !is_link($destination)
            ) {
                return [
                    'status' => 200,
                    'payload' => [
                        'ok' => true,
                        'acknowledged' => true,
                    ],
                ];
            }

            if (
                !is_file($source) ||
                is_link($source) ||
                (fileperms($source) & 0777) !== 0600
            ) {
                return $this->result(404);
            }

            if (!@rename($source, $destination)) {
                return $this->result(503);
            }

            return [
                'status' => 200,
                'payload' => [
                    'ok' => true,
                    'acknowledged' => true,
                ],
            ];
        } finally {
            $this->unlockQueue($lock);
        }
    }
}
