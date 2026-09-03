<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (
    string $relative
) use ($root): string {
    $content = file_get_contents(
        $root . '/' . $relative
    );

    if (!is_string($content)) {
        throw new RuntimeException(
            'Unreadable: ' . $relative
        );
    }

    return $content;
};

$themeService = $read(
    'public_html/app/Services/'
    . 'AdminThemeService.php'
);

$themeView = $read(
    'public_html/resources/views/admin/'
    . 'theme.php'
);

$pagesView = $read(
    'public_html/resources/views/admin/'
    . 'public-page.php'
);

$pagesService = $read(
    'public_html/app/Services/'
    . 'PublicLandingService.php'
);

$routes = $read(
    'public_html/routes/'
    . 'public-landing.php'
);

$layout = $read(
    'public_html/resources/views/admin/'
    . 'layout.php'
);

$css = $read(
    'public_html/public/assets/css/'
    . 'public-landing.css'
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

foreach ([
    'brand_name',
    'brand_subtitle',
    'logo_url',
    'footer_text',
    'footer_enabled',
] as $key) {
    $expect(
        str_contains(
            $themeService,
            "unset(\n"
        )
        && str_contains(
            $themeService,
            "'{$key}'"
        ),
        'Identity isolation missing: '
        . $key
    );
}

$expect(
    str_contains(
        $pagesView,
        'name="brand_logo"'
    )
    && str_contains(
        $pagesView,
        'enctype="multipart/form-data"'
    ),
    'Global logo upload UI missing.'
);

$expect(
    str_contains(
        $pagesService,
        '$logoUpload'
    )
    && str_contains(
        $routes,
        "\$_FILES['brand_logo']"
    ),
    'Global logo upload runtime missing.'
);

$expect(
    !str_contains(
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
    'Identity fields still exist in default theme UI.'
);

$expect(
    str_contains(
        $layout,
        'PersianDate::fromGregorianDate'
    )
    && !str_contains(
        $layout,
        "AdminFormat::digits(date('Y'))"
    ),
    'Internal footer year is not Persian/dynamic.'
);

$expect(
    str_contains(
        $css,
        'Vazirmatn-Arabic.woff2'
    )
    && str_contains(
        $css,
        'PUBLIC LANDING POLISH V2'
    ),
    'Public landing typography/polish missing.'
);


$migration =
    file_get_contents(
        $root
        . '/public_html/system/Database/Migrations/'
        . 'PromotePublicLandingIdentityToSystemTheme.php'
    );

if (!is_string($migration)) {
    throw new RuntimeException(
        'Identity promotion migration missing.'
    );
}

$expect(
    str_contains(
        $migration,
        "'system_replaceable'"
    )
    && str_contains(
        $migration,
        "'promote_default'"
    )
    && str_contains(
        $migration,
        'in_array('
    ),
    'Identity promotion replacement policy missing.'
);

$expect(
    !str_contains(
        $migration,
        "\$config['system_default']"
    ),
    'Undefined system_default migration key must not be used.'
);

$expect(
    str_contains(
        $migration,
        '$isLegacyDefault'
    )
    && str_contains(
        $migration,
        "'promote_default'"
    ),
    'Legacy-default promotion policy is not enforced.'
);

$expect(
    str_contains(
        $migration,
        "namespace = 'admin.theme'"
    )
    && !str_contains(
        $migration,
        'setting_namespace'
    ),
    'Identity promotion must use the real app_settings namespace column.'
);

echo "PUBLIC_PAGES_IDENTITY_UI_CONTRACT=PASS\n";
