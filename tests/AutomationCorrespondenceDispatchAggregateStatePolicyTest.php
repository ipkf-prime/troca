<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$service =
    file_get_contents(
        $root
        . '/public_html/app/Services/Automation/'
        . 'Correspondence/'
        . 'CorrespondenceDispatchAggregateCompletionService.php'
    );

if (!is_string($service)) {
    throw new RuntimeException(
        'D5D aggregate service unavailable.'
    );
}


foreach ([
    'latest_attempt_status_code',
    'recipient_failed_count',
    'recipient_uncertain_count',
    'recipient_processing_count',
    'recipient_pending_count',
    'aggregate_state_code',
    "'retryable_failure'",
    "'uncertain'",
    "'processing'",
    "'pending'",
    "'dispatched'",
] as $required) {
    if (!str_contains(
        $service,
        $required
    )) {
        throw new RuntimeException(
            'D5D aggregate policy missing: '
            . $required
        );
    }
}


/*
 * D5D is observability/policy only.
 * It must not mark correspondence failed or call transport.
 */
foreach ([
    "status_code =\n                            'failed'",
    'UPDATE correspondence_dispatches',
    'NotificationGatewayService',
    'NotificationSmtp',
    'sendDirect',
    'curl_',
    'fsockopen',
    'stream_socket_client',
] as $forbidden) {
    if (str_contains(
        $service,
        $forbidden
    )) {
        throw new RuntimeException(
            'D5D crossed lifecycle boundary: '
            . $forbidden
        );
    }
}


echo "Automation correspondence D5D aggregate policy checks passed.\n";
