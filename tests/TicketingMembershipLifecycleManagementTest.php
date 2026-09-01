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


$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketRequesterOnboardingService.php'
    );

$requesterRoutes =
    $read(
        'public_html/routes/'
        . 'ticketing-requester.php'
    );

$requesterView =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-requester-onboarding.php'
    );

$managerRoutes =
    $read(
        'public_html/routes/'
        . 'ticketing-project-membership.php'
    );

$partial =
    $read(
        'public_html/resources/views/admin/partials/'
        . 'ticketing-project-membership-config.php'
    );

$managerView =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-project-requester-members.php'
    );


foreach ([
    'REQUESTER_PROJECT_SELF_LEAVE_RUNTIME',
    'public function leave(',
    "!== 'requester'",
    'requesterHasOpenTickets(',
    'statuses.is_closed = 0',
    'FOR UPDATE',
    'left_at = UTC_TIMESTAMP()',
    'requester_open_tickets',
    'requester_left',

    'TICKETING_REQUESTER_MANAGER_REVOKE_RUNTIME',
    'public function requesterMembersForManager(',
    'public function revokeRequester(',
    "role_code = 'requester'",
    'requester_revoked',
] as $marker) {

    $expect(
        str_contains(
            $service,
            $marker
        ),
        'Service contract missing: '
        . $marker
    );
}


$expect(
    !preg_match(
        '/DELETE\s+FROM\s+ticketing_support_project_members/i',
        $service
    ),
    'Membership must never be hard deleted.'
);


foreach ([
    'TICKETING_REQUESTER_SELF_LEAVE_ROUTE',
    '/admin/support/ticketing/leave',
    '->leave(',
    'new \\IPKF\\Security\\Csrf()',
] as $marker) {

    $expect(
        str_contains(
            $requesterRoutes,
            $marker
        ),
        'Self-leave route missing: '
        . $marker
    );
}


foreach ([
    'TICKETING_REQUESTER_SELF_LEAVE_UI',
    '/admin/support/ticketing/leave',
    'project_reference',
    'لغو عضویت',
    'requester_open_tickets',
    'عضویت شما در پروژه با موفقیت لغو شد.',
] as $marker) {

    $expect(
        str_contains(
            $requesterView,
            $marker
        ),
        'Self-leave UI missing: '
        . $marker
    );
}


foreach ([
    'TICKETING_REQUESTER_MANAGER_REVOKE_ROUTES',
    '/admin/ticketing/projects/{public_reference}/requesters',
    '/admin/ticketing/projects/{public_reference}/requesters/{member_id}/revoke',
    'requesterMembersForManager',
    'revokeRequester',
] as $marker) {

    $expect(
        str_contains(
            $managerRoutes,
            $marker
        ),
        'Manager route missing: '
        . $marker
    );
}


foreach ([
    'TICKETING_REQUESTER_MANAGER_MEMBERS_LINK',
    'اعضا و دسترسی‌ها',
    '/members',
] as $marker) {

    $expect(
        str_contains(
            $partial,
            $marker
        ),
        'Manager membership link missing: '
        . $marker
    );
}


foreach ([
    'مدیریت اعضای متقاضی',
    'open_ticket_count',
    'تیکت باز',
    'لغو عضویت',
    'لغو عضویت مسدود',
    'ابتدا تیکت‌های باز بسته شوند.',
    '/revoke',
] as $marker) {

    $expect(
        str_contains(
            $managerView,
            $marker
        ),
        'Manager view missing: '
        . $marker
    );
}


echo "TICKETING_MEMBERSHIP_LIFECYCLE_MANAGEMENT_PASS\n";
