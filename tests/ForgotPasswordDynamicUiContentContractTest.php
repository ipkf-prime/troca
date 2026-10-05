<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$view =
    file_get_contents(
        $root
        . '/public_html/resources/views/admin/forgot-password.php'
    );

$migration =
    file_get_contents(
        $root
        . '/public_html/system/Database/Migrations/SeedForgotPasswordDynamicUiContent.php'
    );

$registry =
    file_get_contents(
        $root
        . '/public_html/system/Database/Application/ApplicationMigrationRegistry.php'
    );

if (
    !is_string($view)
    || !is_string($migration)
    || !is_string($registry)
) {
    throw new RuntimeException(
        'B2 source unreadable.'
    );
}

$keys = [
    'core.forgot-password.ui.page-title',
    'core.forgot-password.error.password-confirmation',
    'core.forgot-password.error.password-policy',
    'core.forgot-password.error.password-identity',
    'core.forgot-password.error.same-password',
    'core.forgot-password.error.invalid-or-expired-code',
    'core.forgot-password.error.reset-failed',
    'core.forgot-password.ui.kicker',
    'core.forgot-password.ui.heading',
    'core.forgot-password.ui.identifier-label',
    'core.forgot-password.ui.request-code-action',
    'core.forgot-password.ui.code-label',
    'core.forgot-password.ui.new-password-label',
    'core.forgot-password.ui.password-confirmation-label',
    'core.forgot-password.ui.submit-password-action',
    'core.forgot-password.ui.request-new-code-action',
    'core.forgot-password.ui.login-link',
    'core.forgot-password.ui.home-link',
];

if (count($keys) !== 18) {
    throw new RuntimeException(
        'B2 key target invalid.'
    );
}

foreach ($keys as $key) {
    if (
        substr_count(
            $view,
            "'" . $key . "'"
        ) !== 1
    ) {
        throw new RuntimeException(
            'View key consumer invalid: '
            . $key
        );
    }

    if (
        !str_contains(
            $migration,
            $key
        )
    ) {
        throw new RuntimeException(
            'Migration key missing: '
            . $key
        );
    }
}

if (
    preg_match(
        '/[\x{0600}-\x{06FF}]/u',
        $view
    ) === 1
) {
    throw new RuntimeException(
        'Persian hardcode remains in forgot-password view.'
    );
}

if (
    !str_contains(
        $view,
        'UiContentInlineGuide::bodyText('
    )
) {
    throw new RuntimeException(
        'Dynamic text resolver missing.'
    );
}

if (
    !str_contains(
        $registry,
        'SeedForgotPasswordDynamicUiContent::class'
    )
) {
    throw new RuntimeException(
        'B2 migration registry entry missing.'
    );
}

if (
    str_contains(
        $migration,
        'platform-guides.json'
    )
) {
    throw new RuntimeException(
        'Closed platform guide catalog must remain untouched.'
    );
}

echo "FORGOT_PASSWORD_DYNAMIC_UI_CONTENT_CONTRACT=PASS\n";
echo "DYNAMIC_KEYS=18\n";
echo "VIEW_PERSIAN_HARDCODE=0\n";
echo "PLATFORM_GUIDE_CATALOG_MUTATION=NO\n";
