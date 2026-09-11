<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$viewPath =
    $root
    . '/public_html/resources/views/admin/help-texts.php';

$servicePath =
    $root
    . '/public_html/app/Services/UiContent/UiContentManagementService.php';

$adminJsPath =
    $root
    . '/public_html/public/assets/admin/js/admin.js';

$adminCssPath =
    $root
    . '/public_html/public/assets/admin/css/admin.css';

$persianDatePath =
    $root
    . '/public_html/system/Support/PersianDate.php';


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


foreach (
    [
        $viewPath,
        $servicePath,
        $adminJsPath,
        $adminCssPath,
        $persianDatePath,
    ]
    as $path
) {
    $expect(
        is_readable($path),
        'Required file missing: '
        . $path
    );
}


$view =
    (string) file_get_contents(
        $viewPath
    );

$service =
    (string) file_get_contents(
        $servicePath
    );

$adminJs =
    (string) file_get_contents(
        $adminJsPath
    );

$adminCss =
    (string) file_get_contents(
        $adminCssPath
    );

$persianDate =
    (string) file_get_contents(
        $persianDatePath
    );


foreach (
    [
        'type="datetime-local"',
        'HTTP Status',
        'Metadata JSON',
        'Scope Path JSON',
        'Icon Code',
        'عمومی / Base',
    ]
    as $forbidden
) {
    $expect(
        !str_contains(
            $view,
            $forbidden
        ),
        'Forbidden Persian-admin UI marker remains: '
        . $forbidden
    );
}


foreach (
    [
        'کد وضعیت وب',
        'اطلاعات تکمیلی فنی',
        'ساختار فنی محدوده تخصصی',
        'شناسه آیکون',
        'فارسی',
        'شروع نمایش',
        'پایان نمایش',
    ]
    as $required
) {
    $expect(
        str_contains(
            $view,
            $required
        ),
        'Required Persian marker missing: '
        . $required
    );
}


$expect(
    substr_count(
        $view,
        'data-persian-datepicker'
    ) === 2,
    'Persian datepicker count invalid.'
);

$expect(
    substr_count(
        $view,
        'data-persian-date-input'
    ) === 2,
    'Persian visible date-input count invalid.'
);

$expect(
    substr_count(
        $view,
        'data-persian-date-output'
    ) === 2,
    'Gregorian hidden date-output count invalid.'
);

$expect(
    substr_count(
        $view,
        'data-persian-number-input'
    ) >= 3,
    'Persian numeric input localization missing.'
);


/*
 * The raw locale field remains fa in backend,
 * but user-facing controls and cards must say فارسی.
 */
$expect(
    str_contains(
        $view,
        'name="default_locale"'
    )
    && str_contains(
        $view,
        'value="fa"'
    ),
    'Backend locale contract missing.'
);

$expect(
    substr_count(
        $view,
        'value="فارسی"'
    ) >= 2,
    'Persian locale presentation missing.'
);


$expect(
    str_contains(
        $adminJs,
        '[data-persian-datepicker]'
    ),
    'Shared Persian datepicker JS missing.'
);

$expect(
    str_contains(
        $adminJs,
        '[data-persian-number-input]'
    ),
    'Shared Persian number-input JS missing.'
);

$expect(
    str_contains(
        $adminCss,
        '.admin-persian-calendar'
    ),
    'Shared Persian calendar CSS missing.'
);

$expect(
    str_contains(
        $persianDate,
        'function toGregorianDate'
    )
    && str_contains(
        $persianDate,
        'function fromGregorianDate'
    ),
    'Server Persian-date conversion foundation missing.'
);


$expect(
    str_contains(
        $service,
        'scheduledDateTime('
    ),
    'Structured Jalali scheduling parser missing.'
);

$expect(
    str_contains(
        $service,
        'PersianDate::toGregorianDate('
    ),
    'Jalali to Gregorian conversion missing.'
);

$expect(
    str_contains(
        $service,
        'Clock::displayTimezone()'
    ),
    'Display timezone interpretation missing.'
);

$expect(
    str_contains(
        $service,
        'Clock::databaseTimestamp('
    ),
    'UTC storage normalization missing.'
);

$expect(
    str_contains(
        $service,
        'PersianDate::normalizeDigits('
    ),
    'Persian digit normalization missing.'
);


echo "PERSIAN_ADMIN_UI_POLICY_CONTRACT=PASS\n";
echo "VISIBLE_LANGUAGE=FA\n";
echo "DISPLAY_CALENDAR=JALALI\n";
echo "DISPLAY_DIGITS=PERSIAN\n";
echo "STORAGE_CALENDAR=GREGORIAN_UTC\n";
echo "VISIBLE_LOCALE_CODE=FORBIDDEN\n";
echo "TECHNICAL_BACKEND_CODES=PRESERVED\n";
