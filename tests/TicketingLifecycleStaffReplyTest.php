<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

require_once
    $root
    . '/public_html/app/Support/'
    . 'AdminFormat.php';

require_once
    $root
    . '/public_html/app/Support/'
    . 'TicketingDisplay.php';

use App\Support\TicketingDisplay;


$read =
    static function (
        string $path
    ) use ($root): string {

        $value =
            file_get_contents(
                $root
                . '/'
                . $path
            );

        if (!is_string($value)) {
            throw new RuntimeException(
                'Cannot read '
                . $path
            );
        }

        return $value;
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


$repository =
    $read(
        'public_html/app/Repositories/'
        . 'TicketLifecycleRepository.php'
    );

$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketLifecycleService.php'
    );

$routes =
    $read(
        'public_html/routes/'
        . 'ticketing-runtime.php'
    );

$view =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-ticket-detail.php'
    );

$migration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'EnableTicketingLifecycleOperations.php'
    );

$registry =
    $read(
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );


foreach ([
    'beginTransaction()',
    'FOR UPDATE',
    'ticketing_support_project_members',
    'left_at IS NULL',
    'INSERT INTO ticketing_messages',
    "'reply'",
    "'public'",
    "'staff'",
    'COALESCE(',
    'first_response_at',
    "'waiting_requester'",
    "'ticket_staff_replied'",
    'first_response_recorded',
    'ticketing_events',
    'commit()',
    'rollBack()',
] as $marker) {

    $expect(
        str_contains(
            $repository,
            $marker
        ),
        'Lifecycle repository marker missing: '
        . $marker
    );
}


$expect(
    str_contains(
        $repository,
        '$body,'
    ),
    'Reply body is not persisted.'
);


foreach ([
    'public function staffReply(',
    "trim(\$body)",
    'mb_strlen',
    "'user:' . \$userId",
    'reply_empty',
    'reply_too_long',
    'reply_closed',
    'reply_forbidden',
] as $marker) {

    $expect(
        str_contains(
            $service,
            $marker
        ),
        'Lifecycle service marker missing: '
        . $marker
    );
}


$expect(
    !str_contains(
        $service,
        'TicketingSla'
    )
    &&
    !str_contains(
        $repository,
        'TicketingSla'
    ),
    'Ticket lifecycle must not invoke SLA directly.'
);


foreach ([
    'ticketing_lifecycle_a8d1',
    '/admin/ticketing/tickets/{public_reference}/reply',
    '$request->route(',
    'new \\IPKF\\Security\\Csrf()',
    'TicketLifecycleService',
    'reply_sent',
] as $marker) {

    $expect(
        str_contains(
            $routes,
            $marker
        ),
        'Lifecycle route marker missing: '
        . $marker
    );
}


$expect(
    str_contains(
        $routes,
        'new \\App\\Services\\Ticketing\\TicketLifecycleService()'
    ),
    'Lifecycle service FQCN must remain parse-safe.'
);


foreach ([
    'ticketing_lifecycle_a8d1',
    'data-ticketing-staff-reply',
    'data-ticketing-staff-reply-form',
    'name="body"',
    'ticketing.ticket.reply',
    'در انتظار پاسخ درخواست‌کننده',
] as $marker) {

    $expect(
        str_contains(
            $view,
            $marker
        ),
        'Lifecycle view marker missing: '
        . $marker
    );
}


foreach ([
    'ticketing.ticket.reply',
    'ticketing.staff.cartable.view',
    '/admin/ticketing/tickets/',
    '{public_reference}/reply',
    'role_permissions',
    'admin_route_permissions',
] as $marker) {

    $expect(
        str_contains(
            $migration,
            $marker
        ),
        'Lifecycle RBAC marker missing: '
        . $marker
    );
}


$expect(
    str_contains(
        $registry,
        'EnableTicketingLifecycleOperations::class'
    ),
    'Lifecycle migration is not registered.'
);


$expect(
    TicketingDisplay::eventTitle(
        'ticket_staff_replied'
    )
    ===
        'پاسخ کارشناس ثبت شد',
    'Staff reply event is not Persian.'
);


echo
    "TICKETING_LIFECYCLE_STAFF_REPLY_PASS\n";
