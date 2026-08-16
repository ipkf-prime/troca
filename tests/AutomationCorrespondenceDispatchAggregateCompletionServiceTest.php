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

$lookup =
    file_get_contents(
        $root
        . '/public_html/app/Services/Automation/'
        . 'Correspondence/'
        . 'AutomationLookupRepository.php'
    );


if (
    !is_string($service)
    ||
    !is_string($lookup)
) {
    throw new RuntimeException(
        'D5C source unavailable.'
    );
}


foreach ([
    'class CorrespondenceDispatchAggregateCompletionService',
    'public function completeIfReady(',
    "'primary_recipient'",
    "'dispatched'",
    "'delivered'",
    'UPDATE correspondences',
    'lock_version =',
    'lock_version + 1',
    'INSERT INTO correspondence_events',
    "'dispatched'",
    "'registered'",
    'remaining_recipient_count',
    'successful_dispatch_count',
    'each_primary_recipient_has_successful_dispatch',
    "'event_created' =>",
    "'provider_invoked' =>",
] as $required) {
    if (!str_contains(
        $service,
        $required
    )) {
        throw new RuntimeException(
            'D5C service missing: '
            . $required
        );
    }
}


/*
 * Aggregate completion must not send anything and
 * must not mutate recipient-level dispatch state.
 */
foreach ([
    'UPDATE correspondence_dispatches',
    'INSERT INTO correspondence_dispatch_attempts',
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
            'D5C boundary leak: '
            . $forbidden
        );
    }
}


if (
    !str_contains(
        $lookup,
        "'dispatched' => 'ارسال شد'"
    )
) {
    throw new RuntimeException(
        'Dispatched event Persian label missing.'
    );
}


echo "Automation correspondence D5C aggregate checks passed.\n";
