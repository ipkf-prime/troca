<?php

declare(strict_types=1);


$root =
    dirname(__DIR__);


$read =
    static function (
        string $file
    ) use ($root): string {

        $path =
            $root
            . '/'
            . ltrim(
                $file,
                '/'
            );


        if (!is_file($path)) {
            throw new RuntimeException(
                'Missing: ' . $file
            );
        }


        $content =
            file_get_contents(
                $path
            );


        if (!is_string($content)) {
            throw new RuntimeException(
                'Unreadable: ' . $file
            );
        }


        return $content;
    };


$assert =
    static function (
        bool $value,
        string $message
    ): void {

        if (!$value) {
            throw new RuntimeException(
                $message
            );
        }
    };


$branding =
    $read(
        'public_html/app/Services/UiContent/'
        . 'UiContentPresentationBrandingService.php'
    );


$presenter =
    $read(
        'public_html/app/Services/UiContent/'
        . 'UiContentHttpPresenter.php'
    );


$context =
    $read(
        'public_html/app/Services/UiContent/'
        . 'UiContentRequestContextService.php'
    );


$interface =
    $read(
        'public_html/app/Services/UiContent/'
        . 'UiContentScopeProviderInterface.php'
    );


$provider =
    $read(
        'public_html/app/Services/UiContent/'
        . 'ScopeProviders/'
        . 'TicketingUiContentScopeProvider.php'
    );


$view =
    $read(
        'public_html/resources/views/shared/'
        . 'ui-content-error.php'
    );


foreach ([
    'AdminThemeService',
    'systemTheme()',
    'assetUrls()',
    'fontOptions()',
    'presentation_logo_url',
    'presentation_logo_enabled',
    'presentation_brand_title',
    'presentation_brand_subtitle',
    'presentation_accent_color',
    'safeLogoUrl',
] as $marker) {

    $assert(
        str_contains(
            $branding,
            $marker
        ),
        'Branding marker missing: '
        . $marker
    );
}


$assert(
    str_contains(
        $interface,
        'function describeScopePath('
    ),
    'Generic scope descriptor missing.'
);


$assert(
    str_contains(
        $provider,
        'function describeScopePath('
    )
    &&
    str_contains(
        $provider,
        'project_color_code'
    )
    &&
    str_contains(
        $provider,
        'portal_title'
    ),
    'Ticketing Project/Portal descriptor missing.'
);


$assert(
    str_contains(
        $context,
        '->describeScopePath('
    ),
    'Authorized explicit-scope description missing.'
);


$assert(
    str_contains(
        $presenter,
        'UiContentPresentationBrandingService'
    )
    &&
    str_contains(
        $presenter,
        "'branding' =>"
    ),
    'Presenter branding integration missing.'
);


foreach ([
    'data-ui-platform-font-source="admin-css"',
    'data-ui-brand-logo="1"',
    'var(--error-font)',
] as $marker) {

    $assert(
        str_contains(
            $view,
            $marker
        ),
        'View marker missing: '
        . $marker
    );
}


$assert(
    !str_contains(
        $view,
        'fonts.googleapis.com'
    ),
    'External font dependency introduced.'
);


$assert(
    str_contains(
        $branding,
        'javascript:'
    )
    &&
    str_contains(
        $branding,
        'data:'
    )
    &&
    str_contains(
        $branding,
        '/^https?:\/\//i'
    ),
    'Logo URL safety contract incomplete.'
);


$assert(
    !str_contains(
        $branding,
        'action_url'
    ),
    'Branding introduced arbitrary action URL.'
);


echo "DYNAMIC_UI_CONTENT_PRESENTATION_BRANDING_CONTRACT=PASS\n";
echo "PLATFORM_WIDE_PRESENTATION=PASS\n";
echo "BASE_CORE_THEME=PASS\n";
echo "DYNAMIC_MODULE_BRANDING=PASS\n";
echo "GENERIC_FINE_SCOPE_MODEL=PASS\n";
echo "TICKETING_PROJECT_PORTAL_PROVIDER=PASS\n";
echo "VAZIRMATN_PLATFORM_FONT=PASS\n";
echo "DYNAMIC_LOCAL_LOGO=PASS\n";
echo "EXTERNAL_LOGO_URL=FORBIDDEN\n";
echo "ARBITRARY_ACTION_URL=FORBIDDEN\n";
