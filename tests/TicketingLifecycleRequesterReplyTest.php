<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

require_once
    $root
    . '/public_html/app/Support/AdminFormat.php';

require_once
    $root
    . '/public_html/app/Support/TicketingDisplay.php';

use App\Support\TicketingDisplay;


$read =
    static function (
        string $path
    ) use ($root): string {

        $content =
            file_get_contents(
                $root
                . '/'
                . $path
            );

        if (!is_string($content)) {
            throw new RuntimeException(
                'Cannot read '
                . $path
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

$display =
    $read(
        'public_html/app/Support/'
        . 'TicketingDisplay.php'
    );

$view =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-ticket-detail.php'
    );

$routes =
    $read(
        'public_html/routes/'
        . 'ticketing-runtime.php'
    );

$migration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'EnableTicketingRequesterReplyOperations.php'
    );

$registry =
    $read(
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );


foreach ([
    'public function requesterReply(',
    'beginTransaction()',
    'FOR UPDATE',
    'requester_user_reference',
    'hash_equals(',
    'requester_reply_forbidden',
    'requester_reply_not_expected',
    "'waiting_requester'",
    "'in_progress'",
    'INSERT INTO ticketing_messages',
    "'requester'",
    "'public'",
    "'ticket_requester_replied'",
    'assignment_preserved',
    'commit()',
    'rollBack()',
] as $marker) {

    $expect(
        str_contains(
            $repository,
            $marker
        ),
        'Requester repository marker missing: '
        . $marker
    );
}


$expect(
    !str_contains(
        $repository,
        'UPDATE ticketing_assignments'
    )
    &&
    !str_contains(
        $repository,
        'INSERT INTO ticketing_assignments'
    ),
    'Requester reply must preserve assignment.'
);


foreach ([
    'public function requesterReply(',
    'requester_reply_empty',
    'requester_reply_too_long',
    "'user:' . \$userId",
] as $marker) {

    $expect(
        str_contains(
            $service,
            $marker
        ),
        'Requester service marker missing: '
        . $marker
    );
}


foreach ([
    'ticketing_lifecycle_a8d2',
    'data-ticketing-requester-reply',
    'data-ticketing-requester-reply-form',
    'پاسخ درخواست‌کننده',
    '$lifecycleRequesterExpected',
    '&& !$lifecycleRequesterExpected',
    '/requester-reply',
] as $marker) {

    $expect(
        str_contains(
            $view,
            $marker
        ),
        'Requester View marker missing: '
        . $marker
    );
}


$expect(
    preg_match_all(
        '/data-ticketing-requester-reply(?!-)/',
        $view
    ) === 1,
    'Requester section must appear exactly once.'
);


$expect(
    substr_count(
        $view,
        'data-ticketing-requester-reply-form'
    ) === 1,
    'Requester form must appear exactly once.'
);


$expect(
    preg_match_all(
        '/data-ticketing-staff-reply(?!-)/',
        $view
    ) === 1,
    'Staff section must remain exactly once.'
);


foreach ([
    'ticketing_lifecycle_a8d2',
    '/admin/ticketing/tickets/{public_reference}/requester-reply',
    'requesterReply(',
    'requester_reply_sent',
    '$request->route(',
] as $marker) {

    $expect(
        str_contains(
            $routes,
            $marker
        ),
        'Requester route marker missing: '
        . $marker
    );
}


$routeMarker =
    strpos(
        $routes,
        'ticketing_lifecycle_a8d2'
    );

$expect(
    $routeMarker !== false,
    'A8D2 route marker missing.'
);

$routeBlock =
    substr(
        $routes,
        (int) $routeMarker
    );

$expect(
    substr_count(
        $routeBlock,
        '$request->route('
    ) === 1,
    'Requester route must read public_reference exactly once from Request::route().'
);


foreach ([
    'EnableTicketingRequesterReplyOperations',
    'ticketing.ticket.view',
    '{public_reference}/requester-reply',
    'admin_route_permissions',
] as $marker) {

    $expect(
        str_contains(
            $migration,
            $marker
        ),
        'Requester migration marker missing: '
        . $marker
    );
}


$expect(
    !str_contains(
        $migration,
        'ticketing.ticket.requester_reply'
    ),
    'Requester ownership must not create a Staff-style permission.'
);


$expect(
    str_contains(
        $registry,
        'EnableTicketingRequesterReplyOperations::class'
    ),
    'Requester migration is not registered.'
);


$expect(
    !str_contains(
        $repository,
        'TicketingSla'
    )
    &&
    !str_contains(
        $service,
        'TicketingSla'
    ),
    'Requester lifecycle must not directly invoke SLA.'
);


$expect(
    str_contains(
        $display,
        "'ticket_requester_replied'"
    ),
    'Requester reply display mapping missing.'
);


$expect(
    TicketingDisplay::eventTitle(
        'ticket_requester_replied'
    )
    ===
        'پاسخ درخواست‌کننده ثبت شد',
    'Requester reply event title is not Persian.'
);


echo
    "TICKETING_LIFECYCLE_REQUESTER_REPLY_PASS\n";
