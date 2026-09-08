<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use App\Services\AdminThemeService;
use IPKF\Database\Connections\ConnectionResolver;
use IPKF\Support\ApplicationUrlRegistry;
use IPKF\Support\Clock;
use IPKF\Support\PersianDate;
use IPKF\Support\Version;
use PDO;
use RuntimeException;
use Throwable;


/**
 * TICKETING_PORTAL_LANDING_RUNTIME_V1
 *
 * Read-only Portal Landing projection.
 *
 * Data ownership:
 *
 * - Global Landing content -> core.primary
 * - Global Theme           -> AdminThemeService / core.primary
 * - Project/Portal Brand   -> ticketing.primary
 * - Portal Context         -> ticketing.primary
 *
 * This service deliberately does not activate PublicLandingService
 * inside Ticketing runtime. Therefore the existing non-Portal root
 * behaviour remains unchanged.
 */
final class TicketingPortalLandingService
{
    private const ITEM_TYPES = [
        'nav',
        'slide',
        'announcement',
        'card',
        'footer_link',
    ];


    private PDO $coreDb;

    private PDO $ticketingDb;

    private PortalBrandingService $branding;


    public function __construct(
        ?PDO $coreDb = null,
        ?PDO $ticketingDb = null,
        ?PortalBrandingService $branding = null
    ) {
        $resolver =
            new ConnectionResolver();


        $this->coreDb =
            $coreDb
            ?? $resolver->resolve(
                'core.primary'
            );


        $this->ticketingDb =
            $ticketingDb
            ?? $resolver->resolve(
                'ticketing.primary'
            );


        $this->branding =
            $branding
            ?? new PortalBrandingService(
                $this->ticketingDb,
                new AdminThemeService()
            );
    }


    public function page(
        array $portalContext
    ): array {

        $projectId =
            (int) (
                $portalContext[
                    'project_id'
                ]
                ?? 0
            );

        $realmId =
            (int) (
                $portalContext[
                    'realm_id'
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
            || $realmId < 1
            || $portalId < 1
        ) {
            throw new RuntimeException(
                'portal_landing_context_invalid'
            );
        }


        /*
         * TICKETING_PORTAL_LANDING_CONTENT_OVERLAY_V1
         *
         * Global Landing settings are the baseline.
         * Sparse active Portal settings override only matching keys.
         */
        $settings =
            $this->settings(
                $portalId
            );


        $groups =
            array_fill_keys(
                self::ITEM_TYPES,
                []
            );


        foreach (
            $this->runtimeItems(
                $portalId
            )
            as $item
        ) {
            $type =
                (string) (
                    $item[
                        'item_type'
                    ]
                    ?? ''
                );


            if (
                isset(
                    $groups[$type]
                )
            ) {
                /*
                 * TICKETING_PORTAL_ITEM_MEDIA_SCOPE_V1
                 *
                 * runtimeItems() already normalizes Core-owned media.
                 * Portal overlay media remains Portal-local and must
                 * not be rewritten into the Core asset namespace.
                 */
                $groups[$type][] =
                    $item;
            }
        }


        $theme =
            $this->branding
                ->forPortal(
                    $portalContext
                );


        /*
         * A globally inherited relative logo belongs to the
         * Core public asset namespace.
         *
         * Explicit Project/Portal logo overrides remain untouched
         * because they may belong to Portal-local storage.
         */
        $overrideLogo =
            in_array(
                'logo_url',
                $theme[
                    'project_override_keys'
                ]
                ?? [],
                true
            )
            ||
            in_array(
                'logo_url',
                $theme[
                    'portal_override_keys'
                ]
                ?? [],
                true
            );


        if (
            !$overrideLogo
            && isset(
                $theme['logo_url']
            )
        ) {
            $theme['logo_url'] =
                $this->globalAssetUrl(
                    (string) $theme[
                        'logo_url'
                    ]
                );
        }


        $runtime =
            $this->runtimeMetadata();


        return [
            'settings' =>
                $settings,

            'theme' =>
                $theme,

            'navigation' =>
                $groups['nav'],

            'slides' =>
                $groups['slide'],

            'announcements' =>
                $groups['announcement'],

            'cards' =>
                $groups['card'],

            'footer_links' =>
                $groups['footer_link'],

            'runtime' =>
                $runtime,

            'runtime_slots' =>
                $this->runtimeSlots(
                    $settings,
                    $runtime
                ),

            'portal_context' => [
                'project_id' =>
                    $projectId,

                'realm_id' =>
                    $realmId,

                'portal_id' =>
                    $portalId,

                'hostname' =>
                    $portalContext[
                        'hostname'
                    ]
                    ?? null,

                'project_title' =>
                    $portalContext[
                        'project_title'
                    ]
                    ?? null,

                'realm_title' =>
                    $portalContext[
                        'realm_title'
                    ]
                    ?? null,

                'portal_title' =>
                    $portalContext[
                        'portal_title'
                    ]
                    ?? null,
            ],
        ];
    }


    private function settings(
        int $portalId
    ): array
    {
        $statement =
            $this->coreDb->query("
                SELECT
                    setting_key,
                    setting_value

                FROM
                    public_page_settings

                WHERE
                    is_active = 1
            ");


        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];


        $map = [];


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


            if ($key === '') {
                continue;
            }


            $map[$key] =
                (string) (
                    $row[
                        'setting_value'
                    ]
                    ?? ''
                );
        }


        /*
         * Sparse Portal-level override.
         *
         * Missing/inactive Portal keys keep the Core value.
         */
        $portal =
            $this->ticketingDb
                ->prepare("
                    SELECT
                        setting_key,
                        setting_value

                    FROM
                        ticketing_support_portal_landing_settings

                    WHERE
                        portal_id = ?

                      AND is_active = 1

                    ORDER BY
                        id
                ");


        $portal->execute([
            $portalId,
        ]);


        foreach (
            $portal->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: []
            as $row
        ) {
            $key =
                trim(
                    (string) (
                        $row[
                            'setting_key'
                        ]
                        ?? ''
                    )
                );


            if ($key === '') {
                continue;
            }


            $map[$key] =
                (string) (
                    $row[
                        'setting_value'
                    ]
                    ?? ''
                );
        }


        return
            $map
            + [
                'runtime_status_position' =>
                    'right',

                'runtime_online_position' =>
                    'right',

                'runtime_datetime_position' =>
                    'center',

                'runtime_version_position' =>
                    'left',

                'runtime_deploy_position' =>
                    'left',
            ];
    }

    private function runtimeItems(
        int $portalId
    ): array
    {
        /*
         * Global items are keyed by type+code.
         */
        $globalRows =
            $this->coreDb
                ->query("
                    SELECT *

                    FROM
                        public_page_items

                    WHERE
                        is_active = 1

                      AND
                        (
                            starts_at IS NULL
                            OR starts_at <= UTC_TIMESTAMP()
                        )

                      AND
                        (
                            ends_at IS NULL
                            OR ends_at >= UTC_TIMESTAMP()
                        )

                    ORDER BY
                        item_type,
                        sort_order,
                        id
                ")
                ->fetchAll(
                    PDO::FETCH_ASSOC
                )
            ?: [];


        $merged = [];


        foreach ($globalRows as $row) {

            $type =
                trim(
                    (string) (
                        $row[
                            'item_type'
                        ]
                        ?? ''
                    )
                );

            $code =
                trim(
                    (string) (
                        $row[
                            'code'
                        ]
                        ?? ''
                    )
                );


            if (
                $type === ''
                || $code === ''
            ) {
                continue;
            }


            $row =
                $this->normalizeGlobalItemMedia(
                    $row
                );


            $row['_portal_override'] =
                false;

            $row['_overlay_identity'] =
                $type
                . ':'
                . $code;


            $merged[
                $row['_overlay_identity']
            ] =
                $row;
        }


        /*
         * Portal overlay:
         *
         * upsert -> replace same global identity or add a new item.
         * hide   -> remove the same global/Portal identity.
         *
         * inactive override rows are ignored, therefore global
         * content automatically becomes visible again.
         */
        $statement =
            $this->ticketingDb
                ->prepare("
                    SELECT
                        id,
                        public_reference,
                        portal_id,
                        item_type,
                        code,
                        override_mode,
                        eyebrow,
                        title,
                        body,
                        image_url,
                        mobile_image_url,
                        action_text,
                        action_url,
                        action_target,
                        icon,
                        sort_order,
                        is_active,
                        starts_at,
                        ends_at,
                        metadata_json,
                        created_at,
                        updated_at

                    FROM
                        ticketing_support_portal_landing_items

                    WHERE
                        portal_id = ?

                      AND is_active = 1

                      AND
                        (
                            starts_at IS NULL
                            OR starts_at <= UTC_TIMESTAMP()
                        )

                      AND
                        (
                            ends_at IS NULL
                            OR ends_at >= UTC_TIMESTAMP()
                        )

                    ORDER BY
                        item_type,
                        sort_order,
                        id
                ");


        $statement->execute([
            $portalId,
        ]);


        foreach (
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: []
            as $row
        ) {

            $type =
                trim(
                    (string) (
                        $row[
                            'item_type'
                        ]
                        ?? ''
                    )
                );

            $code =
                trim(
                    (string) (
                        $row[
                            'code'
                        ]
                        ?? ''
                    )
                );

            $mode =
                strtolower(
                    trim(
                        (string) (
                            $row[
                                'override_mode'
                            ]
                            ?? 'upsert'
                        )
                    )
                );


            if (
                $type === ''
                || $code === ''
            ) {
                continue;
            }


            $identity =
                $type
                . ':'
                . $code;


            if ($mode === 'hide') {

                unset(
                    $merged[
                        $identity
                    ]
                );

                continue;
            }


            if ($mode !== 'upsert') {
                continue;
            }


            $row['_portal_override'] =
                true;

            $row['_overlay_identity'] =
                $identity;


            $merged[$identity] =
                $row;
        }


        $rows =
            array_values(
                $merged
            );


        usort(
            $rows,
            static function (
                array $left,
                array $right
            ): int {

                $typeCompare =
                    strcmp(
                        (string) (
                            $left[
                                'item_type'
                            ]
                            ?? ''
                        ),
                        (string) (
                            $right[
                                'item_type'
                            ]
                            ?? ''
                        )
                    );


                if ($typeCompare !== 0) {
                    return $typeCompare;
                }


                $sortCompare =
                    (
                        (int) (
                            $left[
                                'sort_order'
                            ]
                            ?? 100
                        )
                    )
                    <=>
                    (
                        (int) (
                            $right[
                                'sort_order'
                            ]
                            ?? 100
                        )
                    );


                if ($sortCompare !== 0) {
                    return $sortCompare;
                }


                return
                    strcmp(
                        (string) (
                            $left[
                                'code'
                            ]
                            ?? ''
                        ),
                        (string) (
                            $right[
                                'code'
                            ]
                            ?? ''
                        )
                    );
            }
        );


        return $rows;
    }

    private function runtimeMetadata(): array
    {
        $nowUtc =
            Clock::nowUtc();

        $now =
            Clock::convertToDisplayTimezone(
                $nowUtc
            );


        return [
            'version' =>
                $this->persianDigits(
                    Version::current()
                ),

            'persian_date' =>
                PersianDate::fromGregorianDate(
                    $now->format(
                        'Y-m-d'
                    )
                ),

            'time' =>
                $this->persianDigits(
                    $now->format(
                        'H:i:s'
                    )
                ),

            'utc_iso' =>
                Clock::isoUtc(
                    $nowUtc
                ),

            'timezone' =>
                Clock::displayTimezoneName(),

            'deployment_at' =>
                $this->deploymentTime(),

            'online_users' =>
                $this->onlineCount(),
        ];
    }


    private function runtimeSlots(
        array $settings,
        array $runtime
    ): array {

        $slots = [
            'right' => [],
            'center' => [],
            'left' => [],
        ];


        $push =
            static function (
                array &$slots,
                string $position,
                array $item
            ): void {

                if (
                    isset(
                        $slots[$position]
                    )
                ) {
                    $slots[$position][] =
                        $item;
                }
            };


        if (
            (
                $settings[
                    'show_status'
                ]
                ?? '1'
            ) === '1'
        ) {
            $push(
                $slots,
                (string) (
                    $settings[
                        'runtime_status_position'
                    ]
                    ?? 'right'
                ),
                [
                    'key' =>
                        'status',

                    'kind' =>
                        'status',

                    'text' =>
                        (string) (
                            $settings[
                                'status_text'
                            ]
                            ?? 'سامانه فعال است'
                        ),
                ]
            );
        }


        if (
            $runtime[
                'online_users'
            ] !== null
        ) {
            $push(
                $slots,
                (string) (
                    $settings[
                        'runtime_online_position'
                    ]
                    ?? 'right'
                ),
                [
                    'key' =>
                        'online',

                    'kind' =>
                        'online',

                    'text' =>
                        'کاربران آنلاین: '
                        . $this->persianDigits(
                            (string) $runtime[
                                'online_users'
                            ]
                        ),
                ]
            );
        }


        $push(
            $slots,
            (string) (
                $settings[
                    'runtime_datetime_position'
                ]
                ?? 'center'
            ),
            [
                'key' =>
                    'datetime',

                'kind' =>
                    'datetime',

                'text' =>
                    trim(
                        (string) (
                            $runtime[
                                'persian_date'
                            ]
                            ?? ''
                        )
                        . ' | '
                        . (string) (
                            $runtime[
                                'time'
                            ]
                            ?? ''
                        )
                    ),
            ]
        );


        if (
            (
                $settings[
                    'show_version'
                ]
                ?? '1'
            ) === '1'
        ) {
            $push(
                $slots,
                (string) (
                    $settings[
                        'runtime_version_position'
                    ]
                    ?? 'left'
                ),
                [
                    'key' =>
                        'version',

                    'kind' =>
                        'version',

                    'text' =>
                        'نسخه '
                        . (string) (
                            $runtime[
                                'version'
                            ]
                            ?? ''
                        ),
                ]
            );
        }


        if (
            (
                $settings[
                    'show_deploy_date'
                ]
                ?? '1'
            ) === '1'
            &&
            !empty(
                $runtime[
                    'deployment_at'
                ]
            )
        ) {
            $push(
                $slots,
                (string) (
                    $settings[
                        'runtime_deploy_position'
                    ]
                    ?? 'left'
                ),
                [
                    'key' =>
                        'deploy',

                    'kind' =>
                        'deploy',

                    'text' =>
                        'استقرار: '
                        . (string) $runtime[
                            'deployment_at'
                        ],
                ]
            );
        }


        return $slots;
    }


    private function onlineCount(): ?int
    {
        try {

            $available =
                $this->coreDb
                    ->query("
                        SELECT COUNT(*)

                        FROM
                            information_schema.tables

                        WHERE
                            table_schema = DATABASE()

                          AND table_name =
                                'online_user_presence'
                    ")
                    ->fetchColumn();


            if (
                (int) $available
                !== 1
            ) {
                return null;
            }


            return
                (int) (
                    $this->coreDb
                        ->query("
                            SELECT COUNT(*)

                            FROM
                                online_user_presence

                            WHERE
                                last_seen_at >=
                                    DATE_SUB(
                                        UTC_TIMESTAMP(),
                                        INTERVAL 5 MINUTE
                                    )
                        ")
                        ->fetchColumn()
                    ?: 0
                );

        } catch (Throwable) {

            return null;
        }
    }


    private function normalizeGlobalItemMedia(
        array $item
    ): array {

        foreach ([
            'image_url',
            'mobile_image_url',
        ] as $key) {

            if (
                isset(
                    $item[$key]
                )
            ) {
                $item[$key] =
                    $this->globalAssetUrl(
                        (string) $item[$key]
                    );
            }
        }


        return $item;
    }


    private function globalAssetUrl(
        string $url
    ): string {

        $url =
            trim(
                $url
            );


        if (
            $url === ''
            || !str_starts_with(
                $url,
                '/'
            )
            || str_starts_with(
                $url,
                '//'
            )
        ) {
            return $url;
        }


        $base =
            rtrim(
                (
                    new ApplicationUrlRegistry()
                )->core(),
                '/'
            );


        if (
            $base === ''
            || $base === '/'
            || filter_var(
                $base,
                FILTER_VALIDATE_URL
            ) === false
        ) {
            return $url;
        }


        return
            $base
            . $url;
    }


    private function persianDigits(
        string $value
    ): string {

        return strtr(
            $value,
            [
                '0' => '۰',
                '1' => '۱',
                '2' => '۲',
                '3' => '۳',
                '4' => '۴',
                '5' => '۵',
                '6' => '۶',
                '7' => '۷',
                '8' => '۸',
                '9' => '۹',
            ]
        );
    }


    private function deploymentTime(): ?string
    {
        $file =
            BASE_PATH
            . '/storage/runtime-build.json';


        if (!is_readable($file)) {
            return null;
        }


        $data =
            json_decode(
                (string) file_get_contents(
                    $file
                ),
                true
            );


        if (!is_array($data)) {
            return null;
        }


        foreach ([
            'deployed_at',
            'generated_at',
            'built_at',
            'created_at',
        ] as $key) {

            $instant =
                Clock::parseStoredInstant(
                    $data[$key]
                    ?? null
                );


            if ($instant === null) {
                continue;
            }


            $local =
                Clock::convertToDisplayTimezone(
                    $instant
                );


            return
                PersianDate::fromGregorianDate(
                    $local->format(
                        'Y-m-d'
                    )
                )
                . ' '
                . $this->persianDigits(
                    $local->format(
                        'H:i'
                    )
                );
        }


        return null;
    }
}
