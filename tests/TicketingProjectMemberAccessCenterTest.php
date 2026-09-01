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


$onboarding =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketRequesterOnboardingService.php'
    );

$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketProjectMemberAccessService.php'
    );

$routes =
    $read(
        'public_html/routes/'
        . 'ticketing-project-membership.php'
    );

$view =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-project-members.php'
    );

$partial =
    $read(
        'public_html/resources/views/admin/partials/'
        . 'ticketing-project-membership-config.php'
    );

$projects =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-projects.php'
    );


foreach ([
    'TICKETING_PARTICIPANT_LINKAGE_RUNTIME',
    'ensureParticipantForCoreUser',
    'LAST_INSERT_ID(id)',
    'requester_participant_id = ?',
    'participant_id = ?',
    'requester_ticket_participant_conflict',
] as $marker) {
    $expect(
        str_contains(
            $onboarding,
            $marker
        ),
        'Participant linkage contract missing: '
        . $marker
    );
}


foreach ([
    'TICKETING_PROJECT_MEMBER_ACCESS_CENTER_RUNTIME',
    'public function page(',
    'public function changeRole(',
    'public function revoke(',
    'public function restore(',
    'public function saveTeam(',
    'public function removeTeam(',
    "'requester'",
    "'member'",
    "'manager'",
    "'agent'",
    "'supervisor'",
    'ticketing_statuses',
    'is_closed = 0',
    'member_owned_open_tickets',
    'requester_open_tickets',
    "status = 'inactive'",
    'left_at = UTC_TIMESTAMP()',
] as $marker) {
    $expect(
        str_contains(
            $service,
            $marker
        ),
        'Access service contract missing: '
        . $marker
    );
}


$expect(
    !preg_match(
        '/DELETE\s+FROM\s+ticketing_support_(?:project|team)_members/i',
        $service
    ),
    'Access center must never hard-delete membership.'
);


foreach ([
    'TICKETING_PROJECT_MEMBER_ACCESS_CENTER_ROUTES',
    '/admin/ticketing/projects/{public_reference}/members',
    '/members/{member_id}/role',
    '/members/{member_id}/revoke',
    '/members/{member_id}/restore',
    '/members/{member_id}/team',
    '/members/{member_id}/teams/{team_id}/remove',
    'new \\IPKF\\Security\\Csrf()',
] as $marker) {
    $expect(
        str_contains(
            $routes,
            $marker
        ),
        'Access route contract missing: '
        . $marker
    );
}


foreach ([
    'TICKETING_PROJECT_MEMBER_ACCESS_CENTER_UI',
    'TICKETING_PROJECT_MEMBER_ACCESS_TABLE_UI',
    'TICKETING_PROJECT_MEMBER_ACCESS_NO_TECH_IDENTIFIERS',
    'اعضا و دسترسی‌ها',
    'ticketing-member-table',
    'data-member-row',
    'data-member-detail',
    'data-member-search',
    'data-member-role-filter',
    'data-member-status-filter',
    'تیکت‌ها',
    'باز درخواست‌کننده',
    'در اختیار',
    'تیم فعال',
    'نقش و عضویت',
    'دسترسی‌های تیمی',
    'لغو عضویت',
    'فعال‌سازی عضویت',
    'PAGE_SIZE = 24',
] as $marker) {
    $expect(
        str_contains(
            $view,
            $marker
        ),
        'Final dense table UI marker missing: '
        . $marker
    );
}


/*
 * User explicitly requested that internal identity references never
 * appear in the project-member management UI.
 */
foreach ([
    'user_reference',
    'participant_reference',
    'core_user_reference',
    'TPR-',
    'fixture:',
] as $forbidden) {
    $expect(
        !str_contains(
            $view,
            $forbidden
        ),
        'Technical identity leaked into member UI: '
        . $forbidden
    );
}


$expect(
    str_contains(
        $partial,
        'TICKETING_PROJECT_MEMBER_ACCESS_CENTER_LINK'
    )
    &&
    str_contains(
        $partial,
        '/members'
    )
    &&
    str_contains(
        $partial,
        'اعضا و دسترسی‌ها'
    ),
    'Canonical project access link missing.'
);


foreach ([
    'TICKETING_PROJECT_MEMBER_ACCESS_DIRECT_LINK',
    'TICKETING_PROJECT_ACTIONS_ICON_UNIFICATION',
    'data-project-action-icon="edit"',
    'data-project-action-icon="members"',
    'data-project-action-icon="topology"',
    'data-project-action-icon="routing"',
    'title="ویرایش پروژه"',
    'title="اعضا و دسترسی‌ها"',
    'title="ساختار پشتیبانی"',
    'title="موضوعات و مسیریابی"',
] as $marker) {
    $expect(
        str_contains(
            $projects,
            $marker
        ),
        'Unified project action missing: '
        . $marker
    );
}




/*
 * TICKETING_PROJECT_MEMBER_BULK_SELECTION_REGRESSION
 */
foreach ([
    'TICKETING_PROJECT_MEMBER_BULK_SELECTION_UI',
    'TICKETING_PROJECT_MEMBER_BULK_SELECTION_STYLE',
    'name="member_ids[]"',
    'data-member-select',
    'data-member-select-all',
    'data-member-select-filtered',
    'data-member-clear-selection',
    'data-member-selected-count',
    'selectedIds',
    'currentMatched',
    'انتخاب همه نتایج',
    'لغو انتخاب همه',
] as $marker) {
    $expect(
        str_contains(
            $view,
            $marker
        ),
        'Bulk member selection contract missing: '
        . $marker
    );
}



/*
 * TICKETING_TICKETING_UI_CONSISTENCY_REGRESSION
 */
foreach ([
    'TICKETING_TICKETING_UI_CONSISTENCY_CONTRACT',
    'TICKETING_MEMBER_ACTION_VISUAL_CONTRACT',
    'ticketing-standard-page-head',
    'اعضا و دسترسی‌ها',
] as $marker) {
    $expect(
        str_contains(
            $view,
            $marker
        ),
        'Member UI consistency contract missing: '
        . $marker
    );
}

$expect(
    !str_contains(
        $view,
        'admin-module-hub--green ticketing-member-access-hero'
    ),
    'Member page still uses inconsistent green hero.'
);

foreach ([
    'TICKETING_PROJECT_ACTION_ICON_SIZE_CONTRACT',
    '[data-project-action-icon] svg',
    'width:1rem !important',
    'height:1rem !important',
] as $marker) {
    $expect(
        str_contains(
            $projects,
            $marker
        ),
        'Project icon normalization missing: '
        . $marker
    );
}

echo "TICKETING_PROJECT_MEMBER_ACCESS_CENTER_PASS\n";
