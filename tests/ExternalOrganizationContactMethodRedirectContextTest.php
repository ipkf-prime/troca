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
        "FAIL: Route file unavailable.\n"
    );

    exit(1);
}

$start =
    strpos(
        $route,
        "'/admin/automation/"
        . "external-organizations/"
        . "contact-methods/save'"
    );

if ($start === false) {
    fwrite(
        STDERR,
        "FAIL: Contact-method save route missing.\n"
    );

    exit(1);
}

$end =
    strpos(
        $route,
        "'/admin/automation/"
        . "external-organizations/"
        . "contact-methods/deactivate'",
        $start
    );

if ($end === false) {
    fwrite(
        STDERR,
        "FAIL: Contact-method save route boundary missing.\n"
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
    'contact-method-post-save-context-v11',
    '$contactPointReference',
    "'tab'",
    "'contacts'",
    "'point'",
    "'mode'",
    "'manage-contacts'",
    '$contactMethodRedirectContext',
    '$contactMethodRedirectFragment',
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
            "FAIL: Missing {$marker}\n"
        );

        exit(1);
    }
}

echo "External contact method redirect context checks passed.\n";
