<?php

namespace App\Services\Automation\Correspondence;

use IPKF\Support\Clock;
use IPKF\Support\Env;
use RuntimeException;

/**
 * attachment-legacy-scan-service-v1
 *
 * Dry-run performs the malware scan but does not update database
 * state. Execute mode requires an explicit CLI confirmation.
 */
class CorrespondenceAttachmentLegacyScanService
{
    public function __construct(
        private ?CorrespondenceAttachmentRepository $attachments = null,
        private ?CorrespondenceAttachmentLegacyScanPolicy $policy = null,
        private ?CorrespondenceAttachmentMalwareScanner $scanner = null
    ) {
        $this->attachments ??=
            new CorrespondenceAttachmentRepository();

        $this->policy ??=
            new CorrespondenceAttachmentLegacyScanPolicy();

        $this->scanner ??=
            new CorrespondenceAttachmentMalwareScanner();
    }

    public function run(
        bool $execute = false
    ): array {
        $root =
            $this->storageRoot();

        $candidates =
            $this->attachments
                ->legacyScanCandidates(
                    $this->policy->batchSize()
                );

        $result = [
            'mode' =>
                $execute
                    ? 'execute'
                    : 'dry-run',

            'batch_size' =>
                $this->policy->batchSize(),

            'candidate_count' =>
                count($candidates),

            'eligible_count' => 0,
            'clean_count' => 0,
            'infected_count' => 0,
            'error_count' => 0,
            'missing_count' => 0,
            'unsafe_count' => 0,
            'updated_count' => 0,
            'stale_count' => 0,
            'eligible_bytes' => 0,
        ];

        foreach ($candidates as $candidate) {
            $path =
                $this->safePath(
                    $root,
                    (string) (
                        $candidate['storage_key']
                        ?? ''
                    )
                );

            if ($path['missing'] === true) {
                $result['missing_count']++;
                continue;
            }

            if ($path['safe'] !== true) {
                $result['unsafe_count']++;
                continue;
            }

            $result['eligible_count']++;
            $result['eligible_bytes'] +=
                (int) (
                    $candidate['size_bytes']
                    ?? 0
                );

            $scanResult =
                $this->scanner
                    ->scan(
                        (string) $path['path']
                    );

            match ($scanResult) {
                'clean' =>
                    $result['clean_count']++,

                'infected' =>
                    $result['infected_count']++,

                default =>
                    $result['error_count']++,
            };

            if (!$execute) {
                continue;
            }

            $updated =
                $this->attachments
                    ->markLegacyScanResult(
                        (int) (
                            $candidate['id']
                            ?? 0
                        ),
                        $scanResult,
                        Clock::databaseTimestamp()
                    );

            if ($updated) {
                $result['updated_count']++;
            } else {
                $result['stale_count']++;
            }
        }

        return $result;
    }

    private function storageRoot(): string
    {
        $configured =
            trim(
                (string) Env::get(
                    'PRIVATE_FILE_STORAGE_PATH',
                    ''
                )
            );

        $configured =
            rtrim(
                $configured !== ''
                    ? $configured
                    : dirname(BASE_PATH)
                        . '/storage/private/automation',
                '/\\'
            );

        $root =
            realpath($configured);

        if (
            !is_string($root)
            || !is_dir($root)
            || !is_readable($root)
        ) {
            throw new RuntimeException(
                'Private attachment storage root is unavailable.'
            );
        }

        return $root;
    }

    private function safePath(
        string $root,
        string $storageKey
    ): array {
        $storageKey =
            trim($storageKey);

        if (
            $storageKey === ''
            || str_contains(
                $storageKey,
                "\0"
            )
        ) {
            return [
                'safe' => false,
                'missing' => false,
                'path' => null,
            ];
        }

        $path =
            realpath($storageKey);

        if ($path === false) {
            return [
                'safe' => false,
                'missing' => true,
                'path' => null,
            ];
        }

        $safe =
            is_file($path)
            && is_readable($path)
            && str_starts_with(
                $path,
                $root . DIRECTORY_SEPARATOR
            );

        return [
            'safe' => $safe,
            'missing' => false,
            'path' =>
                $safe
                    ? $path
                    : null,
        ];
    }
}