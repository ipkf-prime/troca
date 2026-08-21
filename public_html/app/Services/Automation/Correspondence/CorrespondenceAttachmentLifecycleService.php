<?php

namespace App\Services\Automation\Correspondence;

use DateTimeImmutable;
use DateTimeZone;
use IPKF\Support\Clock;
use IPKF\Support\Env;
use RuntimeException;

/**
 * attachment-lifecycle-purge-service-v1
 */
class CorrespondenceAttachmentLifecycleService
{
    public function __construct(
        private ?CorrespondenceAttachmentRepository $attachments = null,
        private ?CorrespondenceAttachmentLifecyclePolicy $policy = null
    ) {
        $this->attachments ??=
            new CorrespondenceAttachmentRepository();

        $this->policy ??=
            new CorrespondenceAttachmentLifecyclePolicy();
    }

    public function run(
        bool $execute = false
    ): array {
        $root =
            $this->storageRoot();

        $cutoff =
            (new DateTimeImmutable(
                'now',
                new DateTimeZone('UTC')
            ))
                ->modify(
                    '-'
                    . $this->policy->retentionDays()
                    . ' days'
                )
                ->format('Y-m-d H:i:s');

        $candidates =
            $this->attachments
                ->purgeCandidates(
                    $cutoff,
                    $this->policy->batchSize()
                );

        $result = [
            'mode' =>
                $execute
                    ? 'execute'
                    : 'dry-run',

            'retention_days' =>
                $this->policy->retentionDays(),

            'batch_size' =>
                $this->policy->batchSize(),

            'candidate_count' =>
                count($candidates),

            'eligible_count' => 0,
            'purged_count' => 0,
            'missing_count' => 0,
            'failed_count' => 0,
            'unsafe_count' => 0,
            'eligible_bytes' => 0,
        ];

        foreach ($candidates as $candidate) {
            $path =
                (string) (
                    $candidate['storage_key']
                    ?? ''
                );

            $safety =
                $this->safePath(
                    $root,
                    $path
                );

            if ($safety['safe'] !== true) {
                $result['unsafe_count']++;
                continue;
            }

            $result['eligible_count']++;
            $result['eligible_bytes'] +=
                (int) (
                    $candidate['size_bytes']
                    ?? 0
                );

            if (!$execute) {
                continue;
            }

            $exists =
                $safety['exists']
                === true;

            if (
                $exists
                && !@unlink(
                    (string) $safety['path']
                )
            ) {
                $result['failed_count']++;
                continue;
            }

            if (!$exists) {
                $result['missing_count']++;
            }

            $marked =
                $this->attachments
                    ->markPurged(
                        (int) (
                            $candidate['id']
                            ?? 0
                        ),
                        (string) (
                            $candidate['updated_at']
                            ?? ''
                        ),
                        Clock::databaseTimestamp()
                    );

            if (!$marked) {
                $result['failed_count']++;
                continue;
            }

            $result['purged_count']++;
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
                'exists' => false,
                'path' => null,
            ];
        }

        $path =
            realpath($storageKey);

        if ($path !== false) {
            $safe =
                is_file($path)
                && str_starts_with(
                    $path,
                    $root
                    . DIRECTORY_SEPARATOR
                );

            return [
                'safe' => $safe,
                'exists' => $safe,
                'path' =>
                    $safe
                        ? $path
                        : null,
            ];
        }

        /*
         * attachment-lifecycle-missing-file-safety-v1
         *
         * Missing content may already have been removed by an earlier
         * interrupted run. Only accept it when its real parent remains
         * inside the configured private storage root.
         */
        $parent =
            realpath(
                dirname($storageKey)
            );

        $basename =
            basename($storageKey);

        $safe =
            is_string($parent)
            && (
                $parent === $root
                || str_starts_with(
                    $parent,
                    $root
                    . DIRECTORY_SEPARATOR
                )
            )
            && $basename !== ''
            && $basename !== '.'
            && $basename !== '..'
            && !str_contains(
                $basename,
                DIRECTORY_SEPARATOR
            );

        return [
            'safe' => $safe,
            'exists' => false,
            'path' => null,
        ];
    }
}