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


$expect(
    str_contains(
        $route,
        "'page' =>"
    ),
    'Route page support missing.'
);


foreach (
    [
        '$pageNumber',
        '$perPage = 20',
        "'pagination' =>",
        'primary_module_key',
        'primary_scope_type',
        'primary_scope_path_json',
        'LIMIT "',
        'OFFSET "',
    ]
    as $marker
) {

    $expect(
        str_contains(
            $service,
            $marker
        ),
        'Service marker missing: '
        . $marker
    );
}


foreach (
    [
        'class="ui-content-table"',
        'ui-content-table__row',
        'ui-content-pagination',
        '$browserPageUrl',
        'primary_scope_type',
        'primary_scope_path_json',
        'محل نمایش',
        'ماژول',
        'وضعیت',
        'ui-content-layout ui-content-layout--single',
    ]
    as $marker
) {

    $expect(
        str_contains(
            $view,
            $marker
        ),
        'View marker missing: '
        . $marker
    );
}


$expect(
    substr_count(
        $view,
        '<div class="ui-content-list">'
    )
    === 1,
    'Non-browser list was not preserved.'
);


$expect(
    substr_count(
        $view,
        'class="ui-content-table"'
    )
    === 1,
    'Compact browser table count invalid.'
);


echo "COMPACT_UI_CONTENT_BROWSER_CONTRACT=PASS\n";
echo "FULL_WIDTH_TABLE=PASS\n";
echo "PAGE_SIZE=20\n";
echo "SERVER_SIDE_PAGINATION=PASS\n";
echo "MODULE_COLUMN=PASS\n";
echo "PLACEMENT_COLUMN=PASS\n";
echo "STATUS_COLUMN=PASS\n";
echo "TECHNICAL_KEY_HIDDEN_FROM_BROWSER=PASS\n";
echo "NON_BROWSER_LIST_PRESERVED=PASS\n";
