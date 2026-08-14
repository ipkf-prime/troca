<?php

declare(strict_types=1);

$view =
    file_get_contents(
        dirname(__DIR__)
        . '/public_html/resources/views/admin/'
        . 'automation-external-organizations.php'
    );

if (!is_string($view)) {
    fwrite(
        STDERR,
        "FAIL: View unavailable.\n"
    );

    exit(1);
}

foreach ([
    'external-contact-method-compact-ui-v4',
    'external-contact-phone-group-v6',
    'external-contact-phone-alignment-v7',
    'external-contact-options-group-v8',
    'external-contact-action-alignment-v9',
    'contact-method-persian-numeric-v10',
    'external-contact-method-form',
    'data-contact-method-form',
    ':has([name="area_code"])',
    ':has([name="value"])',
    ':has([name="extension"])',
    'عنوان / توضیح',
    'ترتیب نمایش',
    'directDispatchTypes',
    'form.classList.toggle(',
    'dispatchField.hidden',
] as $marker) {
    if (
        !str_contains(
            $view,
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

if (
    substr_count(
        $view,
        'class="admin-form external-contact-method-form" data-contact-method-form'
    ) !== 2
) {
    fwrite(
        STDERR,
        "FAIL: Expected exactly two real scoped contact forms.\n"
    );

    exit(1);
}


if (
    substr_count(
        $view,
        'data-contact-phone-group'
    ) !== 2
) {
    fwrite(
        STDERR,
        "FAIL: Expected exactly two real phone groups.\n"
    );

    exit(1);
}


if (
    substr_count(
        $view,
        'data-contact-options-group'
    ) !== 2
) {
    fwrite(
        STDERR,
        "FAIL: Expected exactly two real option groups.\n"
    );

    exit(1);
}

echo "External contact method compact visual UI checks passed.\n";
