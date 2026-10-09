<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not Found\n");
}

$options = getopt(
    '',
    [
        'mode:',
        'file:',
        'actor-user-id:',
        'project-reference:',
        'invite-expiry-days::',
        'output::',
        'help',
    ]
);

if (isset($options['help'])) {
    echo "Usage:\n";
    echo "  php scripts/ticketing-bulk-onboarding.php --mode=dry-run --file=/path/input.csv --actor-user-id=1 --project-reference=TSP-NEP [--output=/path/result.csv]\n";
    echo "  php scripts/ticketing-bulk-onboarding.php --mode=apply --file=/path/input.csv --actor-user-id=1 --output=/secure/path/result.csv --project-reference=TSP-NEP\n";
    echo "\nCSV headers:\n";
    echo "  mobile,organization_reference,full_name,email,position_reference,is_primary\n";
    exit(0);
}

$mode = strtolower(trim((string)($options['mode'] ?? '')));
$file = trim((string)($options['file'] ?? ''));
$actorUserId = (int)($options['actor-user-id'] ?? 0);
$projectReference = trim((string)($options['project-reference'] ?? ''));
$inviteExpiryDays = (int)($options['invite-expiry-days'] ?? 7);
$output = trim((string)($options['output'] ?? ''));

if (!in_array($mode, ['dry-run', 'apply'], true)) {
    fwrite(STDERR, "ERROR=INVALID_MODE\n");
    exit(2);
}

if (
    $file === ''
    || $actorUserId < 1
    || $projectReference === ''
    || $inviteExpiryDays < 1
    || $inviteExpiryDays > 30
) {
    fwrite(STDERR, "ERROR=MISSING_REQUIRED_ARGUMENT\n");
    exit(2);
}

if ($mode === 'apply' && $output === '') {
    fwrite(STDERR, "ERROR=APPLY_OUTPUT_FILE_REQUIRED\n");
    exit(2);
}

if ($output !== '' && file_exists($output)) {
    fwrite(STDERR, "ERROR=OUTPUT_FILE_ALREADY_EXISTS\n");
    exit(2);
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/app.php';

restore_error_handler();
restore_exception_handler();

\IPKF\Logging\RequestContext::reset();
\IPKF\Logging\RequestContext::setActor(
    $actorUserId,
    'user:' . $actorUserId,
    'user'
);

try {
    $service = new \App\Services\Ticketing\TicketingBulkOnboardingService();

    $result = $service->run(
        $file,
        $mode,
        $actorUserId,
        $projectReference,
        $inviteExpiryDays
    );

    if ($output !== '') {
        $directory = dirname($output);
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException('bulk_onboarding_output_directory_unwritable');
        }

        $handle = fopen($output, 'x');
        if ($handle === false) {
            throw new RuntimeException('bulk_onboarding_output_create_failed');
        }

        try {
            if (!chmod($output, 0600)) {
                throw new RuntimeException('bulk_onboarding_output_chmod_failed');
            }

            fputcsv(
                $handle,
                [
                    'source_row_number',
                    'mobile',
                    'organization_reference',
                    'result_code',
                    'user_id',
                    'organization_membership_reference',
                    'member_id',
                    'invitation_reference',
                    'invitation_url',
                    'error_code',
                ]
            , ',', '"', '\\');

            foreach (($result['results'] ?? []) as $row) {
                fputcsv(
                    $handle,
                    [
                        $row['source_row_number'] ?? '',
                        $row['mobile'] ?? '',
                        $row['organization_reference'] ?? '',
                        $row['result_code'] ?? '',
                        $row['user_id'] ?? '',
                        $row['organization_membership_reference'] ?? '',
                        $row['member_id'] ?? '',
                        $row['invitation_reference'] ?? '',
                        $row['invitation_url'] ?? '',
                        $row['error_code'] ?? '',
                    ]
                , ',', '"', '\\');
            }
        } finally {
            fclose($handle);
        }
    }

    $publicSummary = $result;
    unset($publicSummary['results']);
    $publicSummary['output_file'] = $output !== '' ? $output : null;
    $publicSummary['invitation_urls_in_console'] = false;

    echo json_encode(
        $publicSummary,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_PRETTY_PRINT
        | JSON_THROW_ON_ERROR
    ) . PHP_EOL;

    $failedRows = (int)($result['failed_rows'] ?? 0);
    exit($failedRows > 0 ? 3 : 0);

} catch (Throwable $exception) {
    fwrite(
        STDERR,
        json_encode(
            [
                'ok' => false,
                'error_code' => preg_replace(
                    '/[^A-Za-z0-9._:-]+/',
                    '_',
                    $exception->getMessage()
                ),
                'message' => $exception->getMessage(),
            ],
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_PRETTY_PRINT
        ) . PHP_EOL
    );
    exit(1);
}
