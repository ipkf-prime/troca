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


$migration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'CreateTicketingDynamicMembershipFormFoundation.php'
    );

$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'SupportProjectMembershipConfigurationService.php'
    );

$partial =
    $read(
        'public_html/resources/views/admin/partials/'
        . 'ticketing-project-membership-config.php'
    );

$view =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-project-form.php'
    );

$runtime =
    $read(
        'public_html/routes/'
        . 'ticketing-runtime.php'
    );

$route =
    $read(
        'public_html/routes/'
        . 'ticketing-project-membership.php'
    );

$registry =
    $read(
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );


foreach ([
    'membership_mode',
    'approval_mode',
    'form_enabled',
    'ticketing_support_project_membership_fields',
    'field_key',
    'field_type',
    'data_source_key',
    'options_json',
    'dependency_field_key',
] as $marker) {

    $expect(
        str_contains(
            $migration,
            $marker
        ),
        'Migration missing: '
        . $marker
    );
}


$expect(
    str_contains(
        $registry,
        'CreateTicketingDynamicMembershipFormFoundation::class'
    ),
    'Migration registry missing.'
);


foreach ([
    'public function form(',
    'public function save(',
    'normalizeFields',
    'saveSettings',
    'replaceFields',
    "'lookup'",
    "'multiselect'",
] as $marker) {

    $expect(
        str_contains(
            $service,
            $marker
        ),
        'Service missing: '
        . $marker
    );
}


$expect(
    str_contains(
        $view,
        'ticketing-project-membership-config.php'
    ),
    'Project edit does not load membership partial.'
);


foreach ([
    'عضویت کاربران',
    'نوع عضویت',
    'عمومی',
    'اختصاصی',
    'تأیید عضویت',
    'فرم تخصصی عضویت',
    'فیلدهای فرم عضویت',
    'افزودن فیلد',
    'داده / منبع داده',
    'گزینه‌های ثابت',
    'وابسته به فیلد',
    'ذخیره تنظیمات عضویت',
] as $marker) {

    $expect(
        str_contains(
            $partial,
            $marker
        ),
        'Membership UI missing: '
        . $marker
    );
}


foreach ([
    '/admin/ticketing/projects/{public_reference}/membership',
    'SupportProjectMembershipConfigurationService',
    "'membership_fields'",
] as $marker) {

    $expect(
        str_contains(
            $route,
            $marker
        ),
        'Membership route missing: '
        . $marker
    );
}


$expect(
    str_contains(
        $runtime,
        'ticketing-project-membership.php'
    ),
    'Membership route fragment is not loaded.'
);


$includePosition =
    strpos(
        $runtime,
        'ticketing-project-membership.php'
    );

$lifecyclePosition =
    strpos(
        $runtime,
        'ticketing_lifecycle_a8d2'
    );

$expect(
    $includePosition !== false
    &&
    $lifecyclePosition !== false
    &&
    $includePosition
        < $lifecyclePosition,
    'Membership route fragment must load before requester lifecycle.'
);


$requesterBlock =
    substr(
        $runtime,
        (int) $lifecyclePosition
    );

$expect(
    substr_count(
        $requesterBlock,
        '$request->route('
    ) === 1,
    'Requester lifecycle static contract changed.'
);


/*
 * Generic engine cannot contain concrete
 * business/project identifiers.
 */
foreach ([
    $migration,
    $service,
    $partial,
    $route,
] as $source) {

    foreach ([
        'TSP-NEP',
        'TSVC-NEP',
        'نهاده پخش',
        'geography.province',
        'geography.county',
        'organization.company',
        'company.agent',
    ] as $forbidden) {

        $expect(
            !str_contains(
                $source,
                $forbidden
            ),
            'Business hardcode leaked: '
            . $forbidden
        );
    }
}


echo
    "TICKETING_DYNAMIC_MEMBERSHIP_CONFIGURATION_FOUNDATION_PASS\n";
