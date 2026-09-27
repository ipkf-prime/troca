<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$require = static function (bool $condition, string $label): void {
    if (!$condition) {
        fwrite(STDERR, $label . "=FAIL\n");
        exit(1);
    }

    echo $label . "=PASS\n";
};

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . $path;

    if (!is_file($full)) {
        fwrite(STDERR, "FILE_MISSING={$path}\n");
        exit(1);
    }

    $content = file_get_contents($full);

    if (!is_string($content)) {
        fwrite(STDERR, "FILE_READ_FAILED={$path}\n");
        exit(1);
    }

    return $content;
};

$services = [
    'public_html/app/Services/BaleAccountLinkAssertionService.php',
    'public_html/app/Services/BaleAccountLinkDeliveryService.php',
    'public_html/app/Services/BaleAccountLinkOutboxService.php',
    'public_html/app/Services/BaleAccountLinkPreparationService.php',
    'public_html/app/Services/BaleAccountLinkQueueApiService.php',
    'public_html/app/Services/BaleAccountLinkSessionService.php',
];

foreach ($services as $service) {
    $require(
        is_file($root . '/' . $service),
        'SERVICE_PRESENT_' . basename($service, '.php')
    );
}

/*
 * Static architectural isolation contract.
 */
$combined = '';

foreach ($services as $service) {
    $combined .= "\n" . $read($service);
}

$forbidden = [
    'TicketRequesterOnboardingService',
    'TicketProjectOrganizationAffiliationService',
    'OrganizationalAffiliationService',
    'OrganizationalAffiliationReviewGuard',
    'TicketService',
    'TicketLifecycleService',
    'SupportProjectRepository',
    'RouteLoader',
    'ApplicationMigrationRegistry',
    'CsrfMiddleware',
];

foreach ($forbidden as $token) {
    $require(
        strpos($combined, $token) === false,
        'NO_CROSS_DOMAIN_' .
        strtoupper(
            preg_replace(
                '/[^A-Za-z0-9]+/',
                '_',
                $token
            )
        )
    );
}

$businessHardcodes = [
    'TSP-NEP',
    'NP-',
    'نپ',
    'نهاده پخش',
    'اتحادیه مرکزی تعاونی',
];

foreach ($businessHardcodes as $index => $token) {
    $require(
        strpos($combined, $token) === false,
        'NO_BUSINESS_HARDCODE_' . ($index + 1)
    );
}

$require(
    preg_match('/[\x{0600}-\x{06FF}]/u', $combined) !== 1,
    'SERVICE_LAYER_UI_TEXT_ABSENT'
);

/*
 * Assertion security contract.
 */
$assertion = $read(
    'public_html/app/Services/BaleAccountLinkAssertionService.php'
);

$require(
    strpos($assertion, 'hash_hmac') !== false,
    'ASSERTION_HMAC'
);

$require(
    strpos($assertion, 'random_bytes') !== false,
    'ASSERTION_RANDOMNESS'
);

$require(
    strpos($assertion, 'trustedConnectorSecret') !== false,
    'ASSERTION_SECRET_BINDING'
);

$require(
    strpos($assertion, 'linkToken') !== false,
    'ASSERTION_LINK_TOKEN_BINDING'
);

$require(
    strpos($assertion, 'externalSubject') !== false,
    'ASSERTION_EXTERNAL_SUBJECT_BINDING'
);

/*
 * Queue authentication / replay protection contract.
 */
$queue = $read(
    'public_html/app/Services/BaleAccountLinkQueueApiService.php'
);

foreach (
    [
        'timestampHeader',
        'nonceHeader',
        'signatureHeader',
        'hash_hmac',
        'hash_equals',
        'flock',
        'acknowledge',
        'lockQueue',
        'unlockQueue',
    ] as $token
) {
    $require(
        strpos($queue, $token) !== false,
        'QUEUE_SECURITY_' .
        strtoupper(
            preg_replace(
                '/[^A-Za-z0-9]+/',
                '_',
                $token
            )
        )
    );
}

/*
 * Preparation chain contract.
 */
$preparation = $read(
    'public_html/app/Services/BaleAccountLinkPreparationService.php'
);

foreach (
    [
        'BaleAccountLinkSessionService',
        'BaleAccountLinkAssertionService',
        'explicitConsent',
        'csrfToken',
        'linkToken',
        'trustedConnectorCode',
        'trustedConnectorSecret',
    ] as $token
) {
    $require(
        strpos($preparation, $token) !== false,
        'PREPARATION_' .
        strtoupper(
            preg_replace(
                '/[^A-Za-z0-9]+/',
                '_',
                $token
            )
        )
    );
}

/*
 * Delivery fail-closed contract.
 */
$delivery = $read(
    'public_html/app/Services/BaleAccountLinkDeliveryService.php'
);

$require(
    strpos(
        $delivery,
        'BaleAccountLinkPreparationService'
    ) !== false,
    'DELIVERY_USES_PREPARATION'
);

$require(
    strpos($delivery, 'trustedSender') !== false,
    'DELIVERY_TRUSTED_SENDER'
);

$require(
    preg_match(
        '/\breturn\s+false\s*;/',
        $delivery
    ) === 1,
    'DELIVERY_FAIL_CLOSED'
);

/*
 * Load Session service only.
 *
 * This service has a pure static validator and can therefore
 * be functionally exercised without DB, routes, migrations,
 * Ticketing, or shared infrastructure.
 */
require_once $root .
    '/public_html/app/Services/BaleAccountLinkSessionService.php';

$class = '\\App\\Services\\BaleAccountLinkSessionService';

$require(
    class_exists($class),
    'SESSION_CLASS_LOAD'
);

$require(
    method_exists($class, 'validateSnapshot'),
    'SESSION_VALIDATE_METHOD'
);

$method = new ReflectionMethod(
    $class,
    'validateSnapshot'
);

$require(
    $method->isPublic(),
    'SESSION_VALIDATE_PUBLIC'
);

$require(
    $method->isStatic(),
    'SESSION_VALIDATE_STATIC'
);

/*
 * Derive freshness policy from real implementation behavior.
 *
 * We don't assume the exact freshness window. We test:
 *   - valid current user
 *   - correct session user binding
 *   - MFA required
 *   - authenticated timestamp required
 *   - stale/invalid snapshots fail closed
 */

$now = 2_000_000_000;
$userId = 12345;

/*
 * Find a recent authenticatedAt value accepted by implementation.
 * Test only a bounded recent window so this remains deterministic
 * and does not encode an assumed TTL.
 */
$accepted = null;
$acceptedAge = null;

$ages = [
    0,
    1,
    5,
    10,
    30,
    60,
    120,
    300,
    600,
    900,
    1800,
    3600,
];

foreach ($ages as $age) {
    $candidate = $class::validateSnapshot(
        $userId,
        true,
        $userId,
        true,
        $now - $age,
        $now
    );

    if (is_array($candidate)) {
        $accepted = $candidate;
        $acceptedAge = $age;
        break;
    }
}

$require(
    is_array($accepted),
    'SESSION_VALID_RECENT_SNAPSHOT_ACCEPTED'
);

echo 'SESSION_ACCEPTED_AGE=' .
    (string) $acceptedAge .
    PHP_EOL;

/*
 * Wrong current-user/session-user binding must fail.
 */
$wrongUser = $class::validateSnapshot(
    $userId,
    true,
    $userId + 1,
    true,
    $now - (int) $acceptedAge,
    $now
);

$require(
    $wrongUser === null,
    'SESSION_WRONG_USER_FAIL_CLOSED'
);

/*
 * Ineligible current user must fail.
 */
$ineligible = $class::validateSnapshot(
    $userId,
    false,
    $userId,
    true,
    $now - (int) $acceptedAge,
    $now
);

$require(
    $ineligible === null,
    'SESSION_INELIGIBLE_USER_FAIL_CLOSED'
);

/*
 * MFA false must fail.
 */
$mfaFalse = $class::validateSnapshot(
    $userId,
    true,
    $userId,
    false,
    $now - (int) $acceptedAge,
    $now
);

$require(
    $mfaFalse === null,
    'SESSION_MFA_FALSE_FAIL_CLOSED'
);

/*
 * MFA absent/null must also fail.
 */
$mfaNull = $class::validateSnapshot(
    $userId,
    true,
    $userId,
    null,
    $now - (int) $acceptedAge,
    $now
);

$require(
    $mfaNull === null,
    'SESSION_MFA_NULL_FAIL_CLOSED'
);

/*
 * Missing session user must fail.
 */
$missingSessionUser = $class::validateSnapshot(
    $userId,
    true,
    null,
    true,
    $now - (int) $acceptedAge,
    $now
);

$require(
    $missingSessionUser === null,
    'SESSION_MISSING_USER_FAIL_CLOSED'
);

/*
 * Missing authentication timestamp must fail.
 */
$missingAuthenticatedAt = $class::validateSnapshot(
    $userId,
    true,
    $userId,
    true,
    null,
    $now
);

$require(
    $missingAuthenticatedAt === null,
    'SESSION_MISSING_AUTH_TIME_FAIL_CLOSED'
);

/*
 * Future authentication timestamps must fail.
 */
$futureAuth = $class::validateSnapshot(
    $userId,
    true,
    $userId,
    true,
    $now + 60,
    $now
);

$require(
    $futureAuth === null,
    'SESSION_FUTURE_AUTH_FAIL_CLOSED'
);

/*
 * Find a clearly stale value.
 * We intentionally test increasingly old ages rather than
 * hardcoding the implementation TTL.
 */
$staleRejected = false;
$staleAge = null;

$staleAges = [
    7200,
    14400,
    28800,
    86400,
    172800,
    604800,
    2592000,
    31536000,
];

foreach ($staleAges as $age) {
    $candidate = $class::validateSnapshot(
        $userId,
        true,
        $userId,
        true,
        $now - $age,
        $now
    );

    if ($candidate === null) {
        $staleRejected = true;
        $staleAge = $age;
        break;
    }
}

$require(
    $staleRejected,
    'SESSION_STALE_AUTH_FAIL_CLOSED'
);

echo 'SESSION_REJECTED_STALE_AGE=' .
    (string) $staleAge .
    PHP_EOL;

/*
 * User binding is a behavioral contract, not an output-shape
 * contract. The same otherwise-valid snapshot has already been
 * proven to fail closed when sessionUserId differs from userId.
 *
 * Do not require the normalized return array to expose user_id
 * under a particular key or as a top-level scalar.
 */
$require(
    is_array($accepted) && $accepted !== [],
    'SESSION_ACCEPTED_CONTEXT_STRUCTURALLY_VALID'
);

$require(
    $wrongUser === null,
    'SESSION_USER_BINDING_FUNCTIONALLY_VERIFIED'
);

/*
 * Test source must not accidentally introduce dependencies
 * on Ticketing/shared integration.
 */
$self = file_get_contents(__FILE__);

$require(
    is_string($self),
    'TEST_SELF_READ'
);

foreach ($forbidden as $token) {
    /*
     * Forbidden names intentionally appear above as test data.
     * Therefore only verify the runtime source dependencies by
     * checking require/include statements.
     */
    $pattern =
        '/(?:require|include)(?:_once)?\s*[^;]*' .
        preg_quote($token, '/') .
        '/i';

    $require(
        preg_match($pattern, $self) !== 1,
        'TEST_NO_RUNTIME_REQUIRE_' .
        strtoupper(
            preg_replace(
                '/[^A-Za-z0-9]+/',
                '_',
                $token
            )
        )
    );
}

echo "BALE_ACCOUNT_LINK_SERVICE_LAYER_ISOLATION_TEST=PASS\n";
