<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$interface =
    file_get_contents(
        $root
        . '/public_html/app/Services/Automation/'
        . 'Correspondence/'
        . 'CorrespondenceDispatchTransportInterface.php'
    );

$service =
    file_get_contents(
        $root
        . '/public_html/app/Services/Automation/'
        . 'Correspondence/'
        . 'CorrespondenceDispatchAttemptService.php'
    );

if (
    !is_string($interface)
    ||
    !is_string($service)
) {
    throw new RuntimeException(
        'D4C1 attempt sources unavailable.'
    );
}

foreach ([
    'interface CorrespondenceDispatchTransportInterface',
    'public function code(): string;',
    'public function send(',
] as $required) {
    if (!str_contains(
        $interface,
        $required
    )) {
        throw new RuntimeException(
            'Transport interface missing: '
            . $required
        );
    }
}

foreach ([
    'public function attempt(',
    'CorrespondenceDispatchTransportInterface $transport',
    'INSERT INTO correspondence_dispatch_attempts',
    "'processing'",
    "'succeeded'",
    "'failed'",
    "'uncertain'",
    'dispatch_attempt_uncertain_requires_review',
    'dispatch_attempt_already_processing',
    '$transport->send(',
    'FOR UPDATE',
    'MAX(attempt_number)',
    "'transport_invoked' =>",
    "'dispatch_status_changed' =>",
    "'correspondence_status_changed' =>",
] as $required) {
    if (!str_contains(
        $service,
        $required
    )) {
        throw new RuntimeException(
            'Attempt service missing: '
            . $required
        );
    }
}

/*
 * D4C1 must not bind to a real transport.
 */
foreach ([
    'NotificationGatewayService',
    'NotificationSmtpGatewayAdapter',
    'NotificationSmtpTransport',
    '->sendDirect(',
    'curl_',
    'fsockopen',
    'stream_socket_client',
] as $forbidden) {
    if (str_contains(
        $service,
        $forbidden
    )) {
        throw new RuntimeException(
            'Attempt foundation contains real provider binding: '
            . $forbidden
        );
    }
}

/*
 * Attempt execution does not complete Dispatch or
 * mutate the parent correspondence.
 */
foreach ([
    'UPDATE correspondences',
    "status_code = 'dispatched'",
    'INSERT INTO correspondence_dispatch_followups',
] as $forbidden) {
    if (str_contains(
        $service,
        $forbidden
    )) {
        throw new RuntimeException(
            'Attempt service leaks into later lifecycle: '
            . $forbidden
        );
    }
}


if (
    !str_contains(
        $service,
        'dispatch_attempt_already_succeeded'
    )
) {
    throw new RuntimeException(
        'Successful attempt must block automatic retry.'
    );
}

if (
    preg_match(
        "/status_code\s+IN\s*\(\s*"
        . "'processing'\s*,\s*"
        . "'uncertain'\s*,\s*"
        . "'succeeded'\s*"
        . "\)/s",
        $service
    ) !== 1
) {
    throw new RuntimeException(
        'Blocking attempt query must include succeeded.'
    );
}

echo "Automation correspondence dispatch attempt D4C1 checks passed.\n";
