<?php

declare(strict_types=1);

$path =
    __DIR__
    . '/../public_html/public/assets/admin/js/ticketing-cartable.js';

$source =
    file_get_contents(
        $path
    );

if (!is_string($source)) {
    fwrite(
        STDERR,
        "JS_READ_FAILED\n"
    );

    exit(1);
}


$expect =
    static function (
        bool $condition,
        string $message
    ): void {
        if ($condition) {
            return;
        }

        fwrite(
            STDERR,
            "FAIL: "
            . $message
            . PHP_EOL
        );

        exit(1);
    };


$expect(
    strpos(
        $source,
        'TICKETING_COMPACT_FILTER_TOGGLE_T3G'
    ) !== false,
    'base compact toggle marker missing'
);


$expect(
    strpos(
        $source,
        'TICKETING_COMPACT_FILTER_TOGGLE_DELEGATED_T3G'
    ) !== false,
    'delegated compact toggle marker missing'
);


$expect(
    strpos(
        $source,
        "document.addEventListener(\n        'click'"
    ) !== false,
    'delegated document click listener missing'
);


$expect(
    strpos(
        $source,
        "target.closest(\n                    '[data-ticketing-advanced-toggle]'"
    ) !== false,
    'delegated toggle lookup missing'
);


$expect(
    strpos(
        $source,
        "toggle.closest(\n                    'form.ticketing-staff-filter-grid'"
    ) !== false,
    'staff filter form lookup missing'
);


$expect(
    strpos(
        $source,
        "form.querySelector(\n                    '[data-ticketing-advanced-panel]'"
    ) !== false,
    'advanced panel lookup missing'
);


$expect(
    strpos(
        $source,
        "panel.removeAttribute(\n                    'hidden'"
    ) !== false,
    'advanced panel open behavior missing'
);


$expect(
    strpos(
        $source,
        "panel.setAttribute(\n                    'hidden',"
    ) !== false,
    'advanced panel close behavior missing'
);


$expect(
    strpos(
        $source,
        'toggle.addEventListener('
    ) === false,
    'direct toggle binding remains'
);


$expect(
    strpos(
        $source,
        "var form =\n        document.querySelector(\n            'form.ticketing-staff-filter-grid'"
    ) === false,
    'asset-evaluation-time form dependency remains'
);


echo "T3G_C2_R2_DELEGATION_CONTRACT=PASS" . PHP_EOL;
echo "DIRECT_TOGGLE_BINDING=ABSENT" . PHP_EOL;
echo "DELEGATED_CLICK_BINDING=PRESENT" . PHP_EOL;
echo "ADVANCED_PANEL_OPEN_CLOSE=PRESENT" . PHP_EOL;
