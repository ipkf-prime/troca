<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use App\Services\AdminThemeService;
use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;


/**
 * TICKETING_PORTAL_DYNAMIC_BRANDING_V1
 *
 * Precedence:
 *
 * Global System Theme
 *      -> Project Brand Override
 *      -> Portal Brand Override
 *
 * Realm identity does not duplicate Theme data.
 *
 * Brand rows are sparse overrides:
 * missing/invalid values inherit from the previous scope.
 */
final class PortalBrandingService
{
    private const ALLOWED_KEYS = [
        'active_preset',
        'brand_name',
        'brand_subtitle',
        'logo_url',
        'footer_text',
        'footer_enabled',
    ];


    private PDO $db;

    private AdminThemeService $globalTheme;


    public function __construct(
        ?PDO $db = null,
        ?AdminThemeService $globalTheme = null
    ) {
        $this->db =
            $db
            ?? (
                new ConnectionResolver()
            )->resolve(
                'ticketing.primary'
            );

        $this->globalTheme =
            $globalTheme
            ?? new AdminThemeService();
    }


    public function forPortal(
        array $portalContext
    ): array {

        $projectId =
            (int) (
                $portalContext[
                    'project_id'
                ]
                ?? 0
            );

        $portalId =
            (int) (
                $portalContext[
                    'portal_id'
                ]
                ?? 0
            );


        if (
            $projectId < 1
            || $portalId < 1
        ) {
            throw new RuntimeException(
                'portal_branding_context_invalid'
            );
        }


        $base =
            $this->globalTheme
                ->systemTheme();


        if (!is_array($base)) {
            throw new RuntimeException(
                'global_theme_invalid'
            );
        }


        $project =
            $this->projectSettings(
                $projectId
            );

        $portal =
            $this->portalSettings(
                $portalId
            );


        $theme =
            $base;


        $projectApplied =
            $this->applySettings(
                $theme,
                $project
            );


        $portalApplied =
            $this->applySettings(
                $theme,
                $portal
            );


        $this->applyPreset(
            $theme,
            $portal[
                'active_preset'
            ]
            ?? $project[
                'active_preset'
            ]
            ?? null
        );


        $scope =
            $portalApplied !== []
                ? 'portal'
                : (
                    $projectApplied !== []
                        ? 'project'
                        : 'global'
                );


        $theme['branding_scope'] =
            $scope;

        $theme['has_project_override'] =
            $projectApplied !== [];

        $theme['has_portal_override'] =
            $portalApplied !== [];

        $theme['project_override_keys'] =
            array_values(
                $projectApplied
            );

        $theme['portal_override_keys'] =
            array_values(
                $portalApplied
            );


        return $theme;
    }


    private function projectSettings(
        int $projectId
    ): array {

        $statement =
            $this->db->prepare("
                SELECT
                    setting_key,
                    setting_value,
                    value_type

                FROM
                    ticketing_support_project_brand_settings

                WHERE
                    project_id = ?

                  AND is_active = 1

                ORDER BY id
            ");


        $statement->execute([
            $projectId,
        ]);


        return
            $this->settingMap(
                $statement->fetchAll(
                    PDO::FETCH_ASSOC
                )
                ?: []
            );
    }


    private function portalSettings(
        int $portalId
    ): array {

        $statement =
            $this->db->prepare("
                SELECT
                    setting_key,
                    setting_value,
                    value_type

                FROM
                    ticketing_support_portal_brand_settings

                WHERE
                    portal_id = ?

                  AND is_active = 1

                ORDER BY id
            ");


        $statement->execute([
            $portalId,
        ]);


        return
            $this->settingMap(
                $statement->fetchAll(
                    PDO::FETCH_ASSOC
                )
                ?: []
            );
    }


    private function settingMap(
        array $rows
    ): array {

        $settings = [];


        foreach ($rows as $row) {

            $key =
                trim(
                    (string) (
                        $row[
                            'setting_key'
                        ]
                        ?? ''
                    )
                );


            if (
                !in_array(
                    $key,
                    self::ALLOWED_KEYS,
                    true
                )
            ) {
                continue;
            }


            $value =
                $this->typedValue(
                    $row[
                        'setting_value'
                    ]
                    ?? null,
                    (string) (
                        $row[
                            'value_type'
                        ]
                        ?? 'string'
                    )
                );


            if ($value === null) {
                continue;
            }


            $settings[$key] =
                $value;
        }


        return $settings;
    }


    private function applySettings(
        array &$theme,
        array $settings
    ): array {

        $applied = [];


        foreach ($settings as $key => $value) {

            if ($key === 'active_preset') {
                continue;
            }


            if (
                in_array(
                    $key,
                    [
                        'brand_name',
                        'brand_subtitle',
                        'footer_text',
                    ],
                    true
                )
            ) {

                $clean =
                    trim(
                        (string) $value
                    );


                if ($clean === '') {
                    continue;
                }


                $limit =
                    $key === 'footer_text'
                        ? 500
                        : 255;


                if (
                    mb_strlen(
                        $clean
                    ) > $limit
                ) {
                    continue;
                }


                $theme[$key] =
                    $clean;

                $applied[] =
                    $key;

                continue;
            }


            if ($key === 'logo_url') {

                $url =
                    $this->cleanAssetUrl(
                        (string) $value
                    );


                if ($url === null) {
                    continue;
                }


                $theme[$key] =
                    $url;

                $applied[] =
                    $key;

                continue;
            }


            if ($key === 'footer_enabled') {

                if (!is_bool($value)) {
                    continue;
                }


                $theme[$key] =
                    $value;

                $applied[] =
                    $key;
            }
        }


        return
            array_values(
                array_unique(
                    $applied
                )
            );
    }


    private function applyPreset(
        array &$theme,
        mixed $requested
    ): void {

        $preset =
            trim(
                (string) (
                    $requested
                    ?? ''
                )
            );


        if ($preset === '') {
            return;
        }


        $presets =
            $this->globalTheme
                ->presets();


        if (
            !isset(
                $presets[$preset]
            )
            || !is_array(
                $presets[$preset]
            )
            || !is_array(
                $presets[$preset][
                    'tokens'
                ]
                ?? null
            )
        ) {
            return;
        }


        $theme['active_preset'] =
            $preset;

        $theme['canonical_preset'] =
            $preset;

        $theme['preset_title'] =
            (string) (
                $presets[$preset][
                    'title'
                ]
                ?? $preset
            );

        $theme['tokens'] =
            $presets[$preset][
                'tokens'
            ];
    }


    private function typedValue(
        mixed $value,
        string $type
    ): mixed {

        if ($value === null) {
            return null;
        }


        $type =
            strtolower(
                trim(
                    $type
                )
            );


        if ($type === 'bool') {

            $normalized =
                strtolower(
                    trim(
                        (string) $value
                    )
                );


            if (
                in_array(
                    $normalized,
                    [
                        '1',
                        'true',
                        'yes',
                        'on',
                    ],
                    true
                )
            ) {
                return true;
            }


            if (
                in_array(
                    $normalized,
                    [
                        '0',
                        'false',
                        'no',
                        'off',
                    ],
                    true
                )
            ) {
                return false;
            }


            return null;
        }


        return
            trim(
                (string) $value
            );
    }


    private function cleanAssetUrl(
        string $url
    ): ?string {

        $url =
            trim(
                $url
            );


        if (
            $url === ''
            || mb_strlen(
                $url
            ) > 500
        ) {
            return null;
        }


        if (
            str_starts_with(
                $url,
                '/'
            )
            && !str_starts_with(
                $url,
                '//'
            )
        ) {
            return $url;
        }


        if (
            str_starts_with(
                strtolower($url),
                'https://'
            )
            && filter_var(
                $url,
                FILTER_VALIDATE_URL
            ) !== false
        ) {
            return $url;
        }


        return null;
    }
}
