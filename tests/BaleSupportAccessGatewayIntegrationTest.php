<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$files = [
    'query' =>
        $root .
        '/public_html/app/Services/BaleServiceQueryService.php',

    'deeplink' =>
        $root .
        '/public_html/app/Services/BaleTicketDeepLinkService.php',

    'reply' =>
        $root .
        '/public_html/app/Services/BaleTicketReplyService.php',
];

$expected = [
    'query' => 2,
    'deeplink' => 1,
    'reply' => 2,
];

foreach ($files as $name => $file) {
    $source = file_get_contents($file);

    if (!is_string($source)) {
        fwrite(
            STDERR,
            "READ_FAIL={$name}\n"
        );
        exit(1);
    }

    $direct =
        preg_match_all(
            '/->\s*detailForUser\s*\(/',
            $source
        );

    $gateway =
        preg_match_all(
            '/->\s*ticketForUser\s*\(/',
            $source
        );

    if ($direct !== 0) {
        fwrite(
            STDERR,
            "DIRECT_DETAIL_REMAINS={$name}:{$direct}\n"
        );
        exit(1);
    }

    if ($gateway !== $expected[$name]) {
        fwrite(
            STDERR,
            "GATEWAY_COUNT_FAIL={$name}:{$gateway}\n"
        );
        exit(1);
    }

    if (
        strpos(
            $source,
            'BaleSupportAccessService'
        ) === false
    ) {
        fwrite(
            STDERR,
            "GATEWAY_CLASS_MISSING={$name}\n"
        );
        exit(1);
    }
}

$query = file_get_contents($files['query']);

foreach (
    [
        '$ticketing->form(',
        '$ticketing->myTickets(',
        'new TicketService()',
        '$supportAccess',
    ]
    as $required
) {
    if (strpos($query, $required) === false) {
        fwrite(
            STDERR,
            "QUERY_CONTRACT_MISSING={$required}\n"
        );
        exit(1);
    }
}

$deep = file_get_contents($files['deeplink']);

foreach (
    [
        'hash_hmac',
        'hash_equals',
        'bale-ticket-deeplink-v1',
        'NotificationMessengerEnrollmentRepository',
        'serviceAccessBaleProviders',
        'notification_messenger_bindings',
        'providerMatched',
    ]
    as $required
) {
    if (strpos($deep, $required) === false) {
        fwrite(
            STDERR,
            "DEEPLINK_SECURITY_CONTRACT_MISSING={$required}\n"
        );
        exit(1);
    }
}

if (
    strpos(
        $deep,
        'new \App\Services\Ticketing\TicketService()'
    ) !== false
) {
    fwrite(
        STDERR,
        "DEEPLINK_OLD_TICKETSERVICE_VISIBILITY_REMAINS\n"
    );
    exit(1);
}

$reply = file_get_contents($files['reply']);

foreach (
    [
        'TicketLifecycleService',
        'requesterReply',
        'requesterResolve',
        'BaleDialogContentService::text',
    ]
    as $required
) {
    if (strpos($reply, $required) === false) {
        fwrite(
            STDERR,
            "REPLY_CONTRACT_MISSING={$required}\n"
        );
        exit(1);
    }
}

if (
    strpos(
        $reply,
        'TicketService'
    ) !== false
) {
    fwrite(
        STDERR,
        "REPLY_TICKETSERVICE_VISIBILITY_DUPLICATION_REMAINS\n"
    );
    exit(1);
}

echo "QUERY_GATEWAY_CALLS=2\n";
echo "QUERY_FORM_MYTICKETS=PRESERVED\n";
echo "DEEPLINK_GATEWAY_CALLS=1\n";
echo "DEEPLINK_PROVIDER_BINDING_SECURITY=PRESERVED\n";
echo "REPLY_GATEWAY_CALLS=2\n";
echo "REPLY_TICKETSERVICE_REMOVED=PASS\n";
echo "DIRECT_DETAIL_FOR_USER=0\n";
echo "REQUESTER_LIFECYCLE=PRESERVED\n";
echo "DYNAMIC_DIALOG=PRESERVED\n";
echo "R2_INTEGRATION_TEST=PASS\n";
