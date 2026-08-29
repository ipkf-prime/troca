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


$core =
    $read(
        'public_html/app/Services/'
        . 'CoreFeatureRegistryService.php'
    );

$panel =
    $read(
        'public_html/app/Services/'
        . 'AdminPanelService.php'
    );

$project =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-project-form.php'
    );

$membership =
    $read(
        'public_html/resources/views/admin/partials/'
        . 'ticketing-project-membership-config.php'
    );

$route =
    $read(
        'public_html/routes/'
        . 'ticketing-project-membership.php'
    );


$expect(
    !str_contains(
        $core,
        'REQUESTER_TICKETING_SEPARATE_CARD_RUNTIME'
    )
    &&
    !str_contains(
        $core,
        "'ticketing-entry'"
    ),
    'Synthetic Ticketing dashboard card remains.'
);


foreach ([
    'UNIFIED_TICKETING_DASHBOARD_ENTRY_RUNTIME',
    "'support.view'",
    "'ticketing.ticket.view'",
    'hasStaffMembership',
    '/admin/support/ticketing',
] as $marker) {

    $expect(
        str_contains(
            $panel,
            $marker
        ),
        'Unified module contract missing: '
        . $marker
    );
}


foreach ([
    'ticketing-project-workspace',
    'ticketing-project-tabs',
    'data-project-tab="base"',
    'data-project-tab="membership"',
    'data-project-tab-panel="base"',
    'data-project-tab-panel="membership"',
    'مشخصات پروژه',
    'تنظیمات عضویت',
    'کد پروژه پس از ایجاد قابل تغییر نیست.',
    'ذخیره پروژه',
] as $marker) {

    $expect(
        str_contains(
            $project,
            $marker
        ),
        'Project tab contract missing: '
        . $marker
    );
}


foreach ([
    'عضویت کاربران',
    'نوع عضویت',
    'تأیید عضویت',
    'تأیید مدیر پروژه',
    'تأیید خودکار',
    'کد دعوت',
    'فرم تخصصی عضویت',
    'فیلدهای فرم عضویت',
    'افزودن فیلد',
    'داده / منبع داده',
    'وابسته به فیلد',
    'ذخیره تنظیمات عضویت',
    'ticketing-membership-row',
    'ticketing-switch',
    'data-field-key-input',
    'data-field-dependency',
    'data-field-options',
    'data-field-source',
] as $marker) {

    $expect(
        str_contains(
            $membership,
            $marker
        ),
        'Membership UI missing: '
        . $marker
    );
}


$expect(
    str_contains(
        $membership,
        'type="hidden"'
    )
    &&
    str_contains(
        $membership,
        '[field_key]'
    )
    &&
    !str_contains(
        $membership,
        'placeholder="field_key"'
    ),
    'Technical field key is visible.'
);


$expect(
    !str_contains(
        $membership,
        'class="admin-section ticketing-membership-builder"'
    ),
    'Membership still renders as a second admin card.'
);


foreach ([
    'tab=membership&membership_status=saved',
    'tab=membership&membership_status=invalid',
] as $marker) {

    $expect(
        str_contains(
            $route,
            $marker
        ),
        'Membership redirect missing: '
        . $marker
    );
}


foreach ([
    'TSP-NEP',
    'نهاده پخش',
] as $forbidden) {

    $expect(
        !str_contains(
            $membership,
            $forbidden
        ),
        'Project-specific value leaked into generic UI.'
    );
}


echo
    "TICKETING_PROJECT_MEMBERSHIP_PROFESSIONAL_UI_PASS\n";
