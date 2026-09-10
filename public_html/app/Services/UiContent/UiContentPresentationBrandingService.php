<?php

declare(strict_types=1);

namespace App\Services\UiContent;

use App\Services\AdminThemeService;
use Throwable;


/**
 * Shared platform presentation identity.
 *
 * Native precedence:
 *   Base Theme -> Module -> Fine Scope
 *
 * UiContent metadata is already cascaded field-by-field by
 * UiContentResolver and may override visual presentation.
 */
final class UiContentPresentationBrandingService
{
    private const DEFAULT_FONT =
        '"Vazirmatn", "IRANSans", "Tahoma", "Segoe UI", sans-serif';

    private const DEFAULT_PRIMARY =
        '#1f6f4a';

    private const DEFAULT_PRIMARY_SOFT =
        '#e4f0e8';

    private const DEFAULT_BG =
        '#f4f8f5';

    private const DEFAULT_SURFACE =
        '#ffffff';

    private const DEFAULT_TEXT =
        '#16241d';

    private const DEFAULT_MUTED =
        '#5f7169';

    private const DEFAULT_BORDER =
        '#cadfd2';


    private ?AdminThemeService $themeService;


    public function __construct(
        ?AdminThemeService $themeService = null
    ) {
        $this->themeService =
            $themeService;
    }


    public function resolve(
        array $page,
        array $module,
        array $scopeMetadata
    ): array {

        $envelope =
            $this->themeEnvelope();


        $theme =
            is_array(
                $envelope['theme']
                ?? null
            )
                ? $envelope['theme']
                : [];


        $tokens =
            is_array(
                $theme['tokens']
                ?? null
            )
                ? $theme['tokens']
                : [];


        $assets =
            is_array(
                $envelope['assets']
                ?? null
            )
                ? $envelope['assets']
                : [];


        $metadata =
            is_array(
                $page['metadata']
                ?? null
            )
                ? $page['metadata']
                : [];


        $platformBrand =
            $this->text(
                (string) (
                    $theme['brand_name']
                    ?? 'سامانه'
                ),
                100
            );


        if ($platformBrand === '') {
            $platformBrand = 'سامانه';
        }


        $moduleTitle =
            $this->text(
                (string) (
                    $module['display_name']
                    ?? ''
                ),
                120
            );


        $projectTitle =
            $this->text(
                (string) (
                    $scopeMetadata[
                        'project_title'
                    ]
                    ?? ''
                ),
                160
            );


        $portalTitle =
            $this->text(
                (string) (
                    $scopeMetadata[
                        'portal_title'
                    ]
                    ?? ''
                ),
                160
            );


        $brandTitle =
            $portalTitle !== ''
                ? $portalTitle
                : (
                    $projectTitle !== ''
                        ? $projectTitle
                        : (
                            $moduleTitle !== ''
                                ? $moduleTitle
                                : $platformBrand
                        )
                );


        $metadataTitle =
            $this->text(
                (string) (
                    $metadata[
                        'presentation_brand_title'
                    ]
                    ?? ''
                ),
                160
            );


        if ($metadataTitle !== '') {
            $brandTitle =
                $metadataTitle;
        }


        $brandSubtitle =
            $this->text(
                (string) (
                    $theme['brand_subtitle']
                    ?? ''
                ),
                180
            );


        $metadataSubtitle =
            $this->text(
                (string) (
                    $metadata[
                        'presentation_brand_subtitle'
                    ]
                    ?? ''
                ),
                180
            );


        if ($metadataSubtitle !== '') {
            $brandSubtitle =
                $metadataSubtitle;
        }


        $accent =
            $this->color(
                (string) (
                    $tokens['primary']
                    ?? ''
                )
            )
            ?? self::DEFAULT_PRIMARY;


        $moduleAccent =
            $this->color(
                (string) (
                    $module['color_code']
                    ?? ''
                )
            );


        if ($moduleAccent !== null) {
            $accent =
                $moduleAccent;
        }


        $fineAccent =
            $this->color(
                (string) (
                    $scopeMetadata[
                        'project_color_code'
                    ]
                    ?? ''
                )
            );


        if ($fineAccent !== null) {
            $accent =
                $fineAccent;
        }


        $metadataAccent =
            $this->color(
                (string) (
                    $metadata[
                        'presentation_accent_color'
                    ]
                    ?? ''
                )
            );


        if ($metadataAccent !== null) {
            $accent =
                $metadataAccent;
        }


        $logoUrl =
            $this->safeLogoUrl(
                (string) (
                    $theme['logo_url']
                    ?? ''
                )
            );


        $logoOverride =
            trim(
                (string) (
                    $metadata[
                        'presentation_logo_url'
                    ]
                    ?? ''
                )
            );


        $logoOverrideRejected =
            false;


        if ($logoOverride !== '') {

            $safeOverride =
                $this->safeLogoUrl(
                    $logoOverride
                );


            if ($safeOverride !== null) {
                $logoUrl =
                    $safeOverride;
            } else {
                $logoOverrideRejected =
                    true;
            }
        }


        $logoEnabled =
            $logoUrl !== null;


        if (
            array_key_exists(
                'presentation_logo_enabled',
                $metadata
            )
        ) {
            $logoEnabled =
                $this->boolean(
                    $metadata[
                        'presentation_logo_enabled'
                    ],
                    $logoEnabled
                )
                &&
                $logoUrl !== null;
        }


        $logoAlt =
            $this->text(
                (string) (
                    $metadata[
                        'presentation_logo_alt'
                    ]
                    ?? ''
                ),
                120
            );


        if ($logoAlt === '') {
            $logoAlt = $brandTitle;
        }


        return [
            'platform_brand_name' =>
                $platformBrand,

            'brand_title' =>
                $brandTitle,

            'brand_subtitle' =>
                $brandSubtitle,

            'module_title' =>
                $moduleTitle,

            'project_title' =>
                $projectTitle,

            'portal_title' =>
                $portalTitle,

            'breadcrumbs' =>
                $this->uniqueLabels([
                    $moduleTitle,
                    $projectTitle,
                    $portalTitle,
                ]),

            'logo_enabled' =>
                $logoEnabled,

            'logo_url' =>
                $logoUrl,

            'logo_alt' =>
                $logoAlt,

            'logo_override_rejected' =>
                $logoOverrideRejected,

            'font_family' =>
                $this->safeFontFamily(
                    (string) (
                        $tokens[
                            'font_family'
                        ]
                        ?? ''
                    )
                ),

            'admin_css_url' =>
                $this->safeAdminCssUrl(
                    (string) (
                        $assets[
                            'admin_css'
                        ]
                        ?? ''
                    )
                ),

            'accent' =>
                $accent,

            'accent_soft' =>
                $this->color(
                    (string) (
                        $tokens[
                            'primary_soft'
                        ]
                        ?? ''
                    )
                )
                ?? self::DEFAULT_PRIMARY_SOFT,

            'background' =>
                $this->color(
                    (string) (
                        $tokens['bg']
                        ?? ''
                    )
                )
                ?? self::DEFAULT_BG,

            'surface' =>
                $this->color(
                    (string) (
                        $tokens['surface']
                        ?? ''
                    )
                )
                ?? self::DEFAULT_SURFACE,

            'text' =>
                $this->color(
                    (string) (
                        $tokens['text']
                        ?? ''
                    )
                )
                ?? self::DEFAULT_TEXT,

            'muted' =>
                $this->color(
                    (string) (
                        $tokens[
                            'text_muted'
                        ]
                        ?? ''
                    )
                )
                ?? self::DEFAULT_MUTED,

            'border' =>
                $this->color(
                    (string) (
                        $tokens['border']
                        ?? ''
                    )
                )
                ?? self::DEFAULT_BORDER,

            'theme_source' =>
                (string) (
                    $envelope['source']
                    ?? 'fallback'
                ),
        ];
    }


    private function themeEnvelope(): array
    {
        try {

            $service =
                $this->themeService();


            $theme =
                $service->systemTheme();


            $assets =
                $service->assetUrls();


            return [
                'theme' =>
                    is_array($theme)
                        ? $theme
                        : [],

                'assets' =>
                    is_array($assets)
                        ? $assets
                        : [],

                'source' =>
                    'admin_theme_service',
            ];

        } catch (Throwable) {

            return [
                'theme' => [
                    'brand_name' =>
                        'سامانه',

                    'brand_subtitle' =>
                        '',

                    'logo_url' =>
                        '/assets/admin/images/logos/default-logo.svg',

                    'tokens' => [
                        'font_family' =>
                            self::DEFAULT_FONT,

                        'primary' =>
                            self::DEFAULT_PRIMARY,

                        'primary_soft' =>
                            self::DEFAULT_PRIMARY_SOFT,

                        'bg' =>
                            self::DEFAULT_BG,

                        'surface' =>
                            self::DEFAULT_SURFACE,

                        'text' =>
                            self::DEFAULT_TEXT,

                        'text_muted' =>
                            self::DEFAULT_MUTED,

                        'border' =>
                            self::DEFAULT_BORDER,
                    ],
                ],

                'assets' => [
                    'admin_css' =>
                        '/assets/admin/css/admin.css',
                ],

                'source' =>
                    'safe_fallback',
            ];
        }
    }


    private function safeFontFamily(
        string $font
    ): string {

        $font = trim($font);


        try {

            $allowed =
                array_values(
                    $this->themeService()
                        ->fontOptions()
                );


            if (
                in_array(
                    $font,
                    $allowed,
                    true
                )
            ) {
                return $font;
            }

        } catch (Throwable) {
        }


        return self::DEFAULT_FONT;
    }


    private function safeLogoUrl(
        string $url
    ): ?string {

        $url =
            trim(
                strip_tags(
                    $url
                )
            );


        $lower =
            strtolower($url);


        if (
            $url === ''
            ||
            str_contains($url, '..')
            ||
            str_contains($lower, 'javascript:')
            ||
            str_contains($lower, 'data:')
            ||
            str_contains($lower, 'url(')
            ||
            preg_match(
                '/^https?:\/\//i',
                $url
            )
            === 1
        ) {
            return null;
        }


        if (
            preg_match(
                '#^/(?:'
                . 'assets/admin/images/'
                . '|uploads/admin/logos/'
                . ')'
                . '[A-Za-z0-9_\-/\.]+'
                . '\.(?:svg|png|jpg|jpeg|webp|gif)'
                . '$#i',
                $url
            )
            !== 1
        ) {
            return null;
        }


        if (!defined('BASE_PATH')) {
            return null;
        }


        $physical =
            BASE_PATH
            . '/public'
            . $url;


        return
            is_readable($physical)
                ? $url
                : null;
    }


    private function safeAdminCssUrl(
        string $url
    ): string {

        $url = trim($url);


        if (
            preg_match(
                '#^/assets/admin/css/admin\.css'
                . '(?:\?v=[0-9]+)?$#D',
                $url
            )
            === 1
        ) {
            return $url;
        }


        return '/assets/admin/css/admin.css';
    }


    private function color(
        string $value
    ): ?string {

        $value = trim($value);


        return
            preg_match(
                '/^#[0-9a-fA-F]{6}$/D',
                $value
            )
            === 1
                ? strtolower($value)
                : null;
    }


    private function text(
        string $value,
        int $limit
    ): string {

        $value =
            trim(
                strip_tags($value)
            );


        return
            function_exists('mb_substr')
                ? mb_substr(
                    $value,
                    0,
                    $limit
                )
                : substr(
                    $value,
                    0,
                    $limit * 2
                );
    }


    private function boolean(
        mixed $value,
        bool $default
    ): bool {

        if (is_bool($value)) {
            return $value;
        }


        if (is_int($value)) {
            return $value === 1;
        }


        $value =
            strtolower(
                trim(
                    (string) $value
                )
            );


        if (
            in_array(
                $value,
                [
                    '1',
                    'true',
                    'yes',
                    'on',
                    'show',
                    'enabled',
                ],
                true
            )
        ) {
            return true;
        }


        if (
            in_array(
                $value,
                [
                    '0',
                    'false',
                    'no',
                    'off',
                    'hide',
                    'disabled',
                ],
                true
            )
        ) {
            return false;
        }


        return $default;
    }


    private function uniqueLabels(
        array $labels
    ): array {

        $result = [];


        foreach ($labels as $label) {

            $label =
                $this->text(
                    (string) $label,
                    160
                );


            if (
                $label === ''
                ||
                in_array(
                    $label,
                    $result,
                    true
                )
            ) {
                continue;
            }


            $result[] = $label;
        }


        return $result;
    }


    private function themeService():
        AdminThemeService
    {
        $this->themeService ??=
            new AdminThemeService();


        return $this->themeService;
    }
}
