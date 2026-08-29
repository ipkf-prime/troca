<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static function (
        string $relative
    ) use ($root): string {

        $content =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($content)) {
            throw new RuntimeException(
                'Unreadable: '
                . $relative
            );
        }

        return $content;
    };

$expect =
    static function (
        bool $condition,
        string $message
    ): void {

        if (!$condition) {
            throw new RuntimeException(
                $message
            );
        }
    };


$notification =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketNotificationService.php'
    );

$repository =
    $read(
        'public_html/app/Repositories/'
        . 'TicketLifecycleRepository.php'
    );

$lifecycle =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketLifecycleService.php'
    );

$badgeResolver =
    $read(
        'public_html/app/Services/'
        . 'NavigationBadgeResolverService.php'
    );

$navigation =
    $read(
        'public_html/app/Services/'
        . 'DynamicAdminNavigationService.php'
    );

$routes =
    $read(
        'public_html/routes/'
        . 'ticketing-runtime.php'
    );

$settings =
    $read(
        'public_html/resources/views/admin/'
        . 'communication-settings.php'
    );


foreach ([
    "'in_app'",
    "'messenger'",
    "'sms'",
    "'email'",
] as $marker) {

    $expect(
        str_contains(
            $notification,
            $marker
        ),
        'Channel missing: '
        . $marker
    );
}


foreach ([
    'ticketing.ticket.staff_replied',
    'ticketing.ticket.requester_replied',
    'NotificationPublisherService',
    'NotificationOutboxProcessorService',
    "'source_module' =>",
    "'ticketing'",
    "'channels' =>",
    "'in_app'",
    "'idempotency_key'",
    'notification_preferences',
] as $marker) {

    $expect(
        str_contains(
            $notification,
            $marker
        ),
        'Notification contract missing: '
        . $marker
    );
}


foreach ([
    "'event_reference' =>",
    "'requester_user_reference' =>",
    "'assignee_user_reference' =>",
] as $marker) {

    $expect(
        str_contains(
            $repository,
            $marker
        ),
        'Lifecycle context missing: '
        . $marker
    );
}


$expect(
    str_contains(
        $lifecycle,
        '->staffReplied('
    )
    &&
    str_contains(
        $lifecycle,
        '->requesterReplied('
    ),
    'Lifecycle notification connection missing.'
);


$expect(
    str_contains(
        $badgeResolver,
        "'ticketing_unread_count'"
    )
    &&
    str_contains(
        $badgeResolver,
        'TicketNotificationService'
    ),
    'Ticketing badge resolver missing.'
);


foreach ([
    'ticketing-unread-alert',
    'ticketing_unread_count',
    'ModuleRuntimeConfig',
    "'target_application' =>",
    "'ticketing'",
    "'sort_order' =>",
    '13',
] as $marker) {

    $expect(
        str_contains(
            $navigation,
            $marker
        ),
        'Ticketing topbar contract missing: '
        . $marker
    );
}


$expect(
    str_contains(
        $routes,
        'TicketNotificationService'
    )
    &&
    str_contains(
        $routes,
        '->markViewed('
    ),
    'Ticket read hook missing.'
);


foreach ([
    'ticketing-channel-phase',
    'پیام داخلی',
    'پیام‌رسان',
    'پیامک',
    'ایمیل',
    'در دست توسعه',
    'disabled',
] as $marker) {

    $expect(
        str_contains(
            $settings,
            $marker
        ),
        'Channel UI missing: '
        . $marker
    );
}


echo
    "TICKETING_INTERNAL_NOTIFICATION_FOUNDATION_PASS\n";
