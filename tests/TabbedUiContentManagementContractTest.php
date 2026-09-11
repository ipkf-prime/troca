<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$route =
    (string) file_get_contents(
        $root
        . '/public_html/routes/system-help-texts.php'
    );

$service =
    (string) file_get_contents(
        $root
        . '/public_html/app/Services/UiContent/UiContentManagementService.php'
    );

$view =
    (string) file_get_contents(
        $root
        . '/public_html/resources/views/admin/help-texts.php'
    );


$requireMarker =
    static function (
        string $surface,
        string $value,
        string $label
    ): void {

        if (
            !str_contains(
                $surface,
                $value
            )
        ) {
            echo
                'FAILED_MARKER='
                . $label
                . ' | VALUE='
                . $value
                . PHP_EOL;

            throw new RuntimeException(
                'Tabbed UI content contract failed.'
            );
        }

        echo
            'PASS_MARKER='
            . $label
            . PHP_EOL;
    };


foreach (
    [
        "'browse_mode'",
        "'module'",
        "'placement'",
        "'tab'",
    ]
    as $marker
) {
    $requireMarker(
        $route,
        $marker,
        'route:' . $marker
    );
}


foreach (
    [
        '$browseMode',
        '$moduleFilter',
        '$placementFilter',
        '$workspaceTab',
        "'placement_labels'",
        "fm.module_key = ?",
        "fp.scope_type = ?",
        'scope_path_json',

        /*
         * Placement labels belong to the Service catalog.
         * The View intentionally renders $placementLabels
         * rather than duplicating those Persian strings.
         */
        'عمومی سامانه',
        'کل ماژول',
        'پروژه',
        'پرتال',
        'محدوده تخصصی',
    ]
    as $marker
) {
    $requireMarker(
        $service,
        $marker,
        'service:' . $marker
    );
}


foreach (
    [
        'ui-content-workspace-tabs',
        'ui-content-browse-tabs',
        'مرور و جستجو',
        'تعریف اصلی',
        'نمایش و محدوده',
        'بر اساس محتوا',
        'بر اساس ماژول',
        'بر اساس محل نمایش',
        '$placementLabels',
    ]
    as $marker
) {
    $requireMarker(
        $view,
        $marker,
        'view:' . $marker
    );
}


if (
    str_contains(
        $view,
        'type="datetime-local"'
    )
) {
    throw new RuntimeException(
        'Gregorian datetime-local regression.'
    );
}


$requireMarker(
    $view,
    'data-persian-datepicker',
    'view:jalali-datepicker'
);


echo "TABBED_UI_CONTENT_MANAGEMENT_CONTRACT=PASS\n";
echo "WORKSPACE_BROWSER=PASS\n";
echo "WORKSPACE_DEFINITION=PASS\n";
echo "WORKSPACE_SCOPE=PASS\n";
echo "BROWSE_BY_CONTENT=PASS\n";
echo "BROWSE_BY_MODULE=PASS\n";
echo "BROWSE_BY_PLACEMENT=PASS\n";
echo "PERSIAN_JALALI_POLICY=PASS\n";
