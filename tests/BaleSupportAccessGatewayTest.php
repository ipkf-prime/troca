<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$target =
    $root
    . '/public_html/app/Services/BaleSupportAccessService.php';

if (!is_file($target)) {
    fwrite(
        STDERR,
        "BaleSupportAccessService missing\n"
    );
    exit(1);
}

$source =
    file_get_contents($target);

if (!is_string($source)) {
    fwrite(
        STDERR,
        "Unable to read gateway source\n"
    );
    exit(1);
}

$requiredFragments = [
    'final class BaleSupportAccessService',
    'SupportProjectRepository',
    'TicketRequesterOnboardingService',
    'TicketService',
    '->hasMembership(',
    '->hasStaffMembership(',
    '->forUser(',
    '->projectForUser(',
    '->serviceForUser(',
    '->detailForUser(',
];

foreach (
    $requiredFragments
    as $fragment
) {
    if (
        strpos(
            $source,
            $fragment
        ) === false
    ) {
        fwrite(
            STDERR,
            'Missing canonical dependency/call: '
            . $fragment
            . PHP_EOL
        );
        exit(1);
    }
}

$forbiddenFragments = [
    'BaleDialogContentService',
    '/admin/',
    'worker.text_',
    'reply.text_',
];

foreach (
    $forbiddenFragments
    as $fragment
) {
    if (
        strpos(
            $source,
            $fragment
        ) !== false
    ) {
        fwrite(
            STDERR,
            'Forbidden hardcoded presentation contract: '
            . $fragment
            . PHP_EOL
        );
        exit(1);
    }
}

if (
    preg_match(
        '/\b(?:SELECT|INSERT|UPDATE|DELETE|REPLACE)\b/i',
        $source
    ) === 1
) {
    fwrite(
        STDERR,
        "Raw SQL is forbidden in BaleSupportAccessService\n"
    );
    exit(1);
}

if (
    preg_match(
        '/[\'"]ticketing\.[A-Za-z0-9_.:-]+[\'"]/',
        $source
    ) === 1
) {
    fwrite(
        STDERR,
        "Hardcoded Ticketing permission/config key detected\n"
    );
    exit(1);
}

if (
    preg_match(
        '/[\'"](?:project|service|customer)[._-][A-Za-z0-9_.:-]+[\'"]/i',
        $source
    ) === 1
) {
    fwrite(
        STDERR,
        "Hardcoded project/service/customer key detected\n"
    );
    exit(1);
}

echo "BALE_SUPPORT_ACCESS_GATEWAY_CANONICAL_DEPENDENCIES=PASS\n";
echo "BALE_SUPPORT_ACCESS_GATEWAY_RAW_SQL_ABSENT=PASS\n";
echo "BALE_SUPPORT_ACCESS_GATEWAY_UI_TEXT_ABSENT=PASS\n";
echo "BALE_SUPPORT_ACCESS_GATEWAY_PERMISSION_KEY_ABSENT=PASS\n";
echo "BALE_SUPPORT_ACCESS_GATEWAY_PROJECT_KEY_ABSENT=PASS\n";
echo "BALE_SUPPORT_ACCESS_GATEWAY_ROUTE_ABSENT=PASS\n";
echo "BALE_SUPPORT_ACCESS_GATEWAY_TEST=PASS\n";
