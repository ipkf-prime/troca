<?php

declare(strict_types=1);

use App\Services\Automation\Correspondence\CorrespondenceAttachmentLegacyScanService;

/**
 * attachment-legacy-scan-cli-v1
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not Found\n");
}

define(
    'BASE_PATH',
    dirname(__DIR__)
);

require
    BASE_PATH
    . '/bootstrap/app.php';

$options =
    getopt(
        '',
        [
            'execute',
            'confirm:',
        ]
    );

$execute =
    array_key_exists(
        'execute',
        $options
    );

$confirmation =
    (string) (
        $options['confirm']
        ?? ''
    );

if (
    $execute
    && $confirmation
        !== 'SCAN-LEGACY-CORRESPONDENCE-ATTACHMENTS'
) {
    fwrite(
        STDERR,
        "Execution requires:\n"
        . "--execute "
        . "--confirm="
        . "SCAN-LEGACY-CORRESPONDENCE-ATTACHMENTS\n"
    );

    exit(2);
}

try {
    $result =
        (
            new CorrespondenceAttachmentLegacyScanService()
        )->run($execute);

    foreach (
        [
            'mode',
            'batch_size',
            'candidate_count',
            'eligible_count',
            'clean_count',
            'infected_count',
            'error_count',
            'missing_count',
            'unsafe_count',
            'updated_count',
            'stale_count',
            'eligible_bytes',
        ]
        as $key
    ) {
        echo
            strtoupper($key)
            . '='
            . (string) (
                $result[$key]
                ?? ''
            )
            . PHP_EOL;
    }

    if (
        (int) (
            $result['error_count']
            ?? 0
        ) > 0
        || (int) (
            $result['missing_count']
            ?? 0
        ) > 0
        || (int) (
            $result['unsafe_count']
            ?? 0
        ) > 0
        || (int) (
            $result['stale_count']
            ?? 0
        ) > 0
    ) {
        exit(1);
    }

} catch (Throwable $exception) {
    fwrite(
        STDERR,
        'ERROR='
        . get_class($exception)
        . PHP_EOL
    );

    exit(1);
}