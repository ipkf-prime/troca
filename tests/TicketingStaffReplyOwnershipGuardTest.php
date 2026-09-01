<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static function (
        string $relative
    ) use ($root): string {

        $text =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($text)) {
            throw new RuntimeException(
                'Unreadable: '
                . $relative
            );
        }

        return $text;
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


$access =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketStaffReplyAccessService.php'
    );

$lifecycleService =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketLifecycleService.php'
    );

$runtime =
    $read(
        'public_html/routes/'
        . 'ticketing-runtime.php'
    );

$detail =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-ticket-detail.php'
    );


foreach ([
    'TICKETING_STAFF_REPLY_OWNERSHIP_GUARD',
    'TICKETING_MULTI_PROJECT_REPLY_ISOLATION',
    'tickets.support_project_id',
    'assignee.project_id',
    'tickets.current_assignee_project_member_id',
    'assignee.user_reference',
    'assignee.left_at IS NULL',
    'reply_waiting_requester',
    'reply_takeover_required',
    'reply_not_assignee',
    'reply_assignment_invalid',
    'reply_owned',
] as $marker) {

    $expect(
        str_contains(
            $access,
            $marker
        ),
        'Access contract missing: '
        . $marker
    );
}


foreach ([
    'TICKETING_STAFF_REPLY_DOMAIN_OWNERSHIP_GUARD',
    'TicketStaffReplyAccessService',
    'reply_waiting_requester',
    'reply_takeover_required',
    'reply_not_assignee',
    'reply_assignment_invalid',
] as $marker) {

    $expect(
        str_contains(
            $lifecycleService,
            $marker
        ),
        'Domain ownership guard missing: '
        . $marker
    );
}

foreach ([
    'TICKETING_STAFF_REPLY_POST_OWNERSHIP_GUARD',
    'TicketStaffReplyAccessService',
    'reply_waiting_requester',
    'reply_takeover_required',
    'reply_not_assignee',
    'reply_assignment_invalid',
] as $marker) {

    $expect(
        str_contains(
            $runtime,
            $marker
        ),
        'POST guard missing: '
        . $marker
    );
}


foreach ([
    'TICKETING_STAFF_REPLY_UI_OWNERSHIP_GUARD',
    '$lifecycleStaffOwnsReply',
    'TicketStaffReplyAccessService',
    'data-ticketing-staff-reply-ownership',
    'تیکت در انتظار پاسخ درخواست‌کننده است.',
    'این تیکت در اختیار شما نیست.',
    'رفتن به کارتابل پشتیبانی',
] as $marker) {

    $expect(
        str_contains(
            $detail,
            $marker
        ),
        'Detail ownership contract missing: '
        . $marker
    );
}


$expect(
    !preg_match(
        '/\$lifecycleCanReply\s*'
        . '&&\s*!\$lifecycleRequesterExpected\s*'
        . '&&\s*!\$lifecycleClosed/s',
        $detail
    ),
    'Legacy permission-only staff form condition remains.'
);


echo
    "TICKETING_PROJECT_SCOPED_STAFF_REPLY_OWNERSHIP_PASS\n";
