<?php

declare(strict_types=1);

$route =
    file_get_contents(
        dirname(__DIR__)
        . '/public_html/routes/web.php'
    );

if (!is_string($route)) {
    fwrite(
        STDERR,
        "FAIL: route file unavailable.\n"
    );

    exit(1);
}

$start =
    strpos(
        $route,
        "'/admin/automation/"
        . "external-organizations/"
        . "contact-methods/deactivate'"
    );

$end =
    strpos(
        $route,
        "'/admin/automation/"
        . "external-organizations/"
        . "addresses/save'",
        $start
    );

if (
    $start === false
    || $end === false
) {
    fwrite(
        STDERR,
        "FAIL: deactivate route boundary unavailable.\n"
    );

    exit(1);
}

$segment =
    substr(
        $route,
        $start,
        $end - $start
    );

foreach ([
    'contact-method-deactivate-context-v12',
    '$contactPointReference',
    '$contactMethodRedirectContext',
    '$contactMethodRedirectFragment',
    "'tab'",
    "'contacts'",
    "'point'",
    "'mode'",
    "'manage-contacts'",
    "'method_deactivated'",
    "'destination-'",
] as $marker) {
    if (
        !str_contains(
            $segment,
            $marker
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: missing {$marker}\n"
        );

        exit(1);
    }
}

echo "External contact-method deactivate redirect checks passed.\n";
