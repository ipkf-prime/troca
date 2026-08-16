<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$service =
    file_get_contents(
        $root
        . '/public_html/app/Services/Automation/'
        . 'Correspondence/'
        . 'CorrespondenceDispatchCompletionService.php'
    );

if (!is_string($service)) {
    throw new RuntimeException(
        'D5B completion service unavailable.'
    );
}


foreach ([
    'class CorrespondenceDispatchCompletionService',
    'public function completeSuccess(',
    'latestAttemptForUpdate',
    "'succeeded'",
    "status_code =\n                            'dispatched'",
    'provider_reference = ?',
    'dispatched_at = ?',
    'FOR UPDATE',
    "'already_completed' =>",
    "'correspondence_status_changed' =>",
    "'provider_invoked' =>",
] as $required) {
    if (!str_contains(
        $service,
        $required
    )) {
        throw new RuntimeException(
            'D5B completion service missing: '
            . $required
        );
    }
}


/*
 * Per-recipient completion must not aggregate the
 * correspondence and must not call a provider.
 */
foreach ([
    'UPDATE correspondences',
    'INSERT INTO correspondence_events',
    'INSERT INTO correspondence_dispatch_followups',
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
            'D5B completion leaked across boundary: '
            . $forbidden
        );
    }
}


echo "Automation correspondence D5B completion checks passed.\n";
