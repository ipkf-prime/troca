<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (
    string $relative
) use ($root): string {
    $value = file_get_contents(
        $root . '/' . $relative
    );

    if (!is_string($value)) {
        throw new RuntimeException(
            'Missing: ' . $relative
        );
    }

    return $value;
};

$theme = $read(
    'public_html/app/Services/'
    . 'AdminThemeService.php'
);

$landing = $read(
    'public_html/app/Services/'
    . 'PublicLandingService.php'
);

$migration = $read(
    'public_html/system/Database/Migrations/'
    . 'CreatePublicLandingFoundation.php'
);

$publicView = $read(
    'public_html/resources/views/site/'
    . 'landing.php'
);

$layout = $read(
    'public_html/resources/views/admin/'
    . 'layout.php'
);

$themeView = $read(
    'public_html/resources/views/admin/'
    . 'theme.php'
);

$pagesView = $read(
    'public_html/resources/views/admin/'
    . 'public-page.php'
);

$helpRoute = $read(
    'public_html/routes/'
    . 'system-help-texts.php'
);

$helpView = $read(
    'public_html/resources/views/admin/'
    . 'help-texts.php'
);

$panel = $read(
    'public_html/app/Services/'
    . 'AdminPanelService.php'
);

$dynamicNav = $read(
    'public_html/app/Services/'
    . 'DynamicAdminNavigationService.php'
);

$loader = $read(
    'public_html/system/Routing/'
    . 'RouteLoader.php'
);

$expect = static function (
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
        $theme,
        'saveSystemIdentity'
    ),
    'System identity writer missing.'
);

$expect(
    str_contains(
        $theme,
        'brand_subtitle'
    )
    && str_contains(
        $layout,
        "\$theme['brand_subtitle']"
    )
    && str_contains(
        $pagesView,
        'name="brand_subtitle"'
    )
    && str_contains(
        $pagesView,
        'name="brand_logo"'
    )
    && !str_contains(
        $themeView,
        'name="brand_name"'
    )
    && !str_contains(
        $themeView,
        'name="brand_subtitle"'
    )
    && !str_contains(
        $themeView,
        'name="logo_url"'
    )
    && !str_contains(
        $themeView,
        'name="footer_text"'
    )
    && !str_contains(
        $themeView,
        'name="footer_enabled"'
    ),
    'Global identity ownership contract is incomplete.'
);

$expect(
    !str_contains(
        $layout,
        '| IPKF</title>'
    ),
    'Internal page title still exposes IPKF.'
);


$expect(
    str_contains(
        $landing,
        "'system_identity'"
    ),
    'Landing does not expose system identity.'
);

$expect(
    str_contains(
        $landing,
        'saveSystemIdentity'
    ),
    'Landing settings do not save system identity.'
);

$identityMigration = $read(
    'public_html/system/Database/Migrations/'
    . 'PromotePublicLandingIdentityToSystemTheme.php'
);

$expect(
    str_contains(
        $migration,
        "['page_title'"
    ),
    'Applied foundation migration was mutated.'
);

$expect(
    str_contains(
        $migration,
        "['footer_text'"
    ),
    'Applied foundation footer seed was mutated.'
);

$expect(
    str_contains(
        $identityMigration,
        'DELETE FROM public_page_settings'
    ),
    'Identity promotion cleanup is missing.'
);

$expect(
    str_contains(
        $identityMigration,
        "'page_title'"
    )
    && str_contains(
        $identityMigration,
        "'footer_text'"
    ),
    'Identity promotion mapping is incomplete.'
);

$expect(
    str_contains(
        $identityMigration,
        "'پنل مدیریت تروکا'"
    )
    && preg_match(
        "/'promote_default'\\s*=>\\s*true/s",
        $identityMigration
    ) === 1,
    'Legacy panel brand is not safely promoted.'
);

$expect(
    str_contains(
        $publicView,
        "\$theme['footer_text']"
    ),
    'Public footer is not system sourced.'
);

$expect(
    str_contains(
        $publicView,
        'public-card-icon'
    )
    && str_contains(
        $publicView,
        'AdminIcon::html'
    ),
    'Public card icons are not rendered as icons.'
);

$expect(
    str_contains(
        $helpRoute,
        '/admin/system/help-texts'
    ),
    'Help management route missing.'
);

$expect(
    str_contains(
        $helpView,
        'مدیریت راهنماها'
    ),
    'Help management view missing.'
);

$expect(
    str_contains(
        $panel,
        "'key' => 'help-texts'"
    ),
    'System management help card missing.'
);

$expect(
    str_contains(
        $dynamicNav,
        "'item_key' => 'help-texts'"
    ),
    'Help sidebar item missing.'
);

$expect(
    str_contains(
        $loader,
        'routes/system-help-texts.php'
    ),
    'Help route not registered.'
);

echo
    "SYSTEM_IDENTITY_HELP_MANAGEMENT_CONTRACT=PASS\n";
