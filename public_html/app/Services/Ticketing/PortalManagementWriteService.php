<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use App\Services\AdminNavigationRbacService;
use App\Services\AdminThemeService;
use App\Support\JalaliDateInput;
use DateTimeImmutable;
use DateTimeZone;
use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;
use Throwable;


/**
 * TICKETING_PORTAL_ADMIN_WRITER_V1
 *
 * Writer domains:
 *
 * - Host
 * - Project Brand
 * - Portal Brand
 * - Landing Settings
 * - Landing Items
 *
 * The service can join a caller-owned transaction so
 * integration proofs can execute real writes and rollback.
 */
final class PortalManagementWriteService
{
    private const BRAND_KEYS = [
        'active_preset',
        'brand_name',
        'brand_subtitle',
        'logo_url',
        'footer_text',
        'footer_enabled',
    ];


    private const LANDING_SETTING_KEYS = [
        'meta_description',
        'status_text',
        'show_status',
        'show_version',
        'show_deploy_date',
        'show_register',
        'login_label',
        'register_label',
        'register_title',
        'register_url',
        'runtime_status_position',
        'runtime_online_position',
        'runtime_datetime_position',
        'runtime_version_position',
        'runtime_deploy_position',
    ];


    private const ITEM_TYPES = [
        'nav',
        'slide',
        'announcement',
        'card',
        'footer_link',
    ];


    private PDO $ticketing;


    public function __construct(
        ?PDO $ticketing = null,
        private ?PortalMediaUploadService $uploader = null
    ) {
        $this->ticketing =
            $ticketing
            ??
            (
                new ConnectionResolver()
            )->resolve(
                'ticketing.primary'
            );


        $this->ticketing
            ->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );


        $this->ticketing
            ->setAttribute(
                PDO::ATTR_DEFAULT_FETCH_MODE,
                PDO::FETCH_ASSOC
            );


        $this->uploader ??=
            new PortalMediaUploadService();
    }


    public function saveHost(
        array $input,
        int $userId
    ): int {

        $this->authorize(
            $userId
        );


        $portal =
            $this->portal(
                (string) (
                    $input[
                        'portal_reference'
                    ]
                    ?? ''
                )
            );


        $portalId =
            (int) $portal[
                'portal_id'
            ];


        $hostId =
            max(
                0,
                (int) (
                    $input[
                        'host_id'
                    ]
                    ?? 0
                )
            );


        $hostname =
            $this->hostname(
                (string) (
                    $input[
                        'hostname'
                    ]
                    ?? ''
                )
            );


        $status =
            strtolower(
                trim(
                    (string) (
                        $input[
                            'status'
                        ]
                        ?? 'active'
                    )
                )
            );


        if (
            !in_array(
                $status,
                [
                    'active',
                    'inactive',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'portal_host_status_invalid'
            );
        }


        $requiresHttps =
            $this->truthy(
                $input[
                    'requires_https'
                ]
                ?? false
            )
                ? 1
                : 0;


        /*
         * TICKETING_PORTAL_ADMIN_JALALI_INPUT_V1
         *
         * Visible numeric inputs may contain Persian/Arabic
         * digits. Normalize before numeric validation/storage.
         */
        $slotText =
            JalaliDateInput::englishDigits(
                trim(
                    (string) (
                        $input[
                            'canonical_host_slot'
                        ]
                        ?? ''
                    )
                )
            );


        $slot =
            $slotText === ''
                ? null
                : max(
                    1,
                    min(
                        255,
                        (int) $slotText
                    )
                );


        $actor =
            $this->actor(
                $userId
            );


        return
            $this->transactional(
                function () use (
                    $portalId,
                    $hostId,
                    $hostname,
                    $status,
                    $requiresHttps,
                    $slot,
                    $actor
                ): int {

                    if ($hostId > 0) {

                        $check =
                            $this->ticketing
                                ->prepare("
                                    SELECT COUNT(*)

                                    FROM
                                        ticketing_support_portal_hosts

                                    WHERE
                                        id = ?
                                      AND portal_id = ?
                                ");


                        $check->execute([
                            $hostId,
                            $portalId,
                        ]);


                        if (
                            (int) $check->fetchColumn()
                            !== 1
                        ) {
                            throw new RuntimeException(
                                'portal_host_not_found'
                            );
                        }


                        $statement =
                            $this->ticketing
                                ->prepare("
                                    UPDATE
                                        ticketing_support_portal_hosts

                                    SET
                                        hostname = ?,
                                        requires_https = ?,
                                        canonical_host_slot = ?,
                                        status = ?,
                                        updated_by_user_reference = ?,
                                        updated_at = CURRENT_TIMESTAMP

                                    WHERE
                                        id = ?
                                      AND portal_id = ?
                                ");


                        $statement->execute([
                            $hostname,
                            $requiresHttps,
                            $slot,
                            $status,
                            $actor,
                            $hostId,
                            $portalId,
                        ]);


                        return $hostId;
                    }


                    $statement =
                        $this->ticketing
                            ->prepare("
                                INSERT INTO
                                    ticketing_support_portal_hosts
                                    (
                                        public_reference,
                                        portal_id,
                                        hostname,
                                        requires_https,
                                        canonical_host_slot,
                                        status,
                                        created_by_user_reference,
                                        updated_by_user_reference
                                    )

                                VALUES
                                    (?, ?, ?, ?, ?, ?, ?, ?)
                            ");


                    $statement->execute([
                        $this->publicReference(
                            'TSPH-ADM-'
                        ),
                        $portalId,
                        $hostname,
                        $requiresHttps,
                        $slot,
                        $status,
                        $actor,
                        $actor,
                    ]);


                    return
                        (int) $this->ticketing
                            ->lastInsertId();
                }
            );
    }


    public function saveBrand(
        array $input,
        int $userId,
        ?array $logoUpload = null
    ): void {

        $this->authorize(
            $userId
        );


        $portal =
            $this->portal(
                (string) (
                    $input[
                        'portal_reference'
                    ]
                    ?? ''
                )
            );


        $scope =
            strtolower(
                trim(
                    (string) (
                        $input[
                            'brand_scope'
                        ]
                        ?? 'portal'
                    )
                )
            );


        if (
            !in_array(
                $scope,
                [
                    'project',
                    'portal',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'portal_brand_scope_invalid'
            );
        }


        $table =
            $scope === 'project'
                ? 'ticketing_support_project_brand_settings'
                : 'ticketing_support_portal_brand_settings';


        $scopeColumn =
            $scope === 'project'
                ? 'project_id'
                : 'portal_id';


        $scopeId =
            $scope === 'project'
                ? (int) $portal[
                    'project_id'
                ]
                : (int) $portal[
                    'portal_id'
                ];


        $overrideKeys =
            $this->stringList(
                $input[
                    'override_keys'
                ]
                ?? []
            );


        $uploadedLogo =
            $this->uploader
                ->store(
                    $logoUpload,
                    (string) $portal[
                        'portal_reference'
                    ],
                    'brand'
                );


        if ($uploadedLogo !== null) {

            $input[
                'logo_url'
            ] =
                $uploadedLogo;


            if (
                !in_array(
                    'logo_url',
                    $overrideKeys,
                    true
                )
            ) {
                $overrideKeys[] =
                    'logo_url';
            }
        }


        try {

            $actor =
                $this->actor(
                    $userId
                );


            $this->transactional(
                function () use (
                    $table,
                    $scopeColumn,
                    $scopeId,
                    $overrideKeys,
                    $input,
                    $actor
                ): void {

                    foreach (
                        self::BRAND_KEYS
                        as $key
                    ) {

                        $enabled =
                            in_array(
                                $key,
                                $overrideKeys,
                                true
                            );


                        /*
                         * Disabled override means inheritance.
                         *
                         * Do not validate an absent value such as
                         * active_preset when it is intentionally
                         * being inherited.
                         */
                        $value =
                            $enabled
                                ? $this->brandValue(
                                    $key,
                                    $input
                                )
                                : '';


                        $this->syncSetting(
                            $table,
                            $scopeColumn,
                            $scopeId,
                            $key,
                            $value,
                            $enabled,
                            $actor
                        );
                    }
                }
            );


        } catch (Throwable $exception) {

            if ($uploadedLogo !== null) {

                $this->uploader
                    ->remove(
                        $uploadedLogo
                    );
            }


            throw $exception;
        }
    }


    public function saveLandingSettings(
        array $input,
        int $userId
    ): void {

        $this->authorize(
            $userId
        );


        $portal =
            $this->portal(
                (string) (
                    $input[
                        'portal_reference'
                    ]
                    ?? ''
                )
            );


        $portalId =
            (int) $portal[
                'portal_id'
            ];


        $overrideKeys =
            $this->stringList(
                $input[
                    'override_keys'
                ]
                ?? []
            );


        $actor =
            $this->actor(
                $userId
            );


        $this->transactional(
            function () use (
                $portalId,
                $overrideKeys,
                $input,
                $actor
            ): void {

                foreach (
                    self::LANDING_SETTING_KEYS
                    as $key
                ) {

                    $enabled =
                        in_array(
                            $key,
                            $overrideKeys,
                            true
                        );


                    $value =
                        $enabled
                            ? $this->landingValue(
                                $key,
                                $input
                            )
                            : '';


                    $this->syncSetting(
                        'ticketing_support_portal_landing_settings',
                        'portal_id',
                        $portalId,
                        $key,
                        $value,
                        $enabled,
                        $actor
                    );
                }
            }
        );
    }


    public function saveItem(
        array $input,
        int $userId,
        ?array $imageUpload = null,
        ?array $mobileImageUpload = null
    ): int {

        $this->authorize(
            $userId
        );


        $portal =
            $this->portal(
                (string) (
                    $input[
                        'portal_reference'
                    ]
                    ?? ''
                )
            );


        $portalId =
            (int) $portal[
                'portal_id'
            ];


        $id =
            max(
                0,
                (int) (
                    $input[
                        'id'
                    ]
                    ?? 0
                )
            );


        $type =
            strtolower(
                trim(
                    (string) (
                        $input[
                            'item_type'
                        ]
                        ?? ''
                    )
                )
            );


        $code =
            strtolower(
                trim(
                    (string) (
                        $input[
                            'code'
                        ]
                        ?? ''
                    )
                )
            );


        $mode =
            strtolower(
                trim(
                    (string) (
                        $input[
                            'override_mode'
                        ]
                        ?? 'upsert'
                    )
                )
            );


        if (
            !in_array(
                $type,
                self::ITEM_TYPES,
                true
            )
        ) {
            throw new RuntimeException(
                'portal_item_type_invalid'
            );
        }


        if (
            preg_match(
                '/^[a-z][a-z0-9_-]{1,79}$/',
                $code
            ) !== 1
        ) {
            throw new RuntimeException(
                'portal_item_code_invalid'
            );
        }


        if (
            !in_array(
                $mode,
                [
                    'upsert',
                    'hide',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'portal_item_override_mode_invalid'
            );
        }


        $current =
            null;


        if ($id > 0) {

            $statement =
                $this->ticketing
                    ->prepare("
                        SELECT *

                        FROM
                            ticketing_support_portal_landing_items

                        WHERE
                            id = ?
                          AND portal_id = ?

                        LIMIT 1
                    ");


            $statement->execute([
                $id,
                $portalId,
            ]);


            $row =
                $statement->fetch();


            if (!is_array($row)) {
                throw new RuntimeException(
                    'portal_item_not_found'
                );
            }


            $current =
                $row;
        }


        $storedImage =
            $this->uploader
                ->store(
                    $imageUpload,
                    (string) $portal[
                        'portal_reference'
                    ],
                    $type
                );


        $storedMobile =
            $this->uploader
                ->store(
                    $mobileImageUpload,
                    (string) $portal[
                        'portal_reference'
                    ],
                    $type
                    . '-mobile'
                );


        try {

            $title =
                trim(
                    (string) (
                        $input[
                            'title'
                        ]
                        ?? ''
                    )
                );


            if (
                $mode === 'upsert'
                &&
                (
                    $title === ''
                    ||
                    mb_strlen(
                        $title
                    ) > 255
                )
            ) {
                throw new RuntimeException(
                    'portal_item_title_invalid'
                );
            }


            $image =
                $this->truthy(
                    $input[
                        'clear_image'
                    ]
                    ?? false
                )
                    ? ''
                    : (
                        $storedImage
                        ??
                        (string) (
                            $current[
                                'image_url'
                            ]
                            ?? ''
                        )
                    );


            $mobile =
                $this->truthy(
                    $input[
                        'clear_mobile_image'
                    ]
                    ?? false
                )
                    ? ''
                    : (
                        $storedMobile
                        ??
                        (string) (
                            $current[
                                'mobile_image_url'
                            ]
                            ?? ''
                        )
                    );


            $actor =
                $this->actor(
                    $userId
                );


            return
                $this->transactional(
                    function () use (
                        $id,
                        $portalId,
                        $type,
                        $code,
                        $mode,
                        $input,
                        $title,
                        $image,
                        $mobile,
                        $actor
                    ): int {

                        $values = [
                            $type,
                            $code,
                            $mode,

                            mb_substr(
                                trim(
                                    (string) (
                                        $input[
                                            'eyebrow'
                                        ]
                                        ?? ''
                                    )
                                ),
                                0,
                                255
                            ),

                            $title,

                            trim(
                                (string) (
                                    $input[
                                        'body'
                                    ]
                                    ?? ''
                                )
                            ),

                            $image,
                            $mobile,

                            mb_substr(
                                trim(
                                    (string) (
                                        $input[
                                            'action_text'
                                        ]
                                        ?? ''
                                    )
                                ),
                                0,
                                255
                            ),

                            $this->cleanUrl(
                                (string) (
                                    $input[
                                        'action_url'
                                    ]
                                    ?? ''
                                )
                            ),

                            (
                                (
                                    $input[
                                        'action_target'
                                    ]
                                    ?? '_self'
                                )
                                === '_blank'
                            )
                                ? '_blank'
                                : '_self',

                            mb_substr(
                                trim(
                                    (string) (
                                        $input[
                                            'icon'
                                        ]
                                        ?? ''
                                    )
                                ),
                                0,
                                100
                            ),

                            max(
                                0,
                                min(
                                    9999,
                                    $this->integerInput(
                                        $input[
                                            'sort_order'
                                        ]
                                        ?? 100,
                                        100
                                    )
                                )
                            ),

                            $this->truthy(
                                $input[
                                    'is_active'
                                ]
                                ?? false
                            )
                                ? 1
                                : 0,

                            $this->dateTime(
                                $input[
                                    'starts_at'
                                ]
                                ?? null
                            ),

                            $this->dateTime(
                                $input[
                                    'ends_at'
                                ]
                                ?? null
                            ),

                            $actor,
                        ];


                        if ($id > 0) {

                            $statement =
                                $this->ticketing
                                    ->prepare("
                                        UPDATE
                                            ticketing_support_portal_landing_items

                                        SET
                                            item_type = ?,
                                            code = ?,
                                            override_mode = ?,
                                            eyebrow = ?,
                                            title = ?,
                                            body = ?,
                                            image_url = ?,
                                            mobile_image_url = ?,
                                            action_text = ?,
                                            action_url = ?,
                                            action_target = ?,
                                            icon = ?,
                                            sort_order = ?,
                                            is_active = ?,
                                            starts_at = ?,
                                            ends_at = ?,
                                            updated_by_user_reference = ?,
                                            updated_at = CURRENT_TIMESTAMP

                                        WHERE
                                            id = ?
                                          AND portal_id = ?
                                    ");


                            $statement->execute([
                                ...$values,
                                $id,
                                $portalId,
                            ]);


                            return $id;
                        }


                        $statement =
                            $this->ticketing
                                ->prepare("
                                    INSERT INTO
                                        ticketing_support_portal_landing_items
                                        (
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
                                            created_by_user_reference,
                                            updated_by_user_reference
                                        )

                                    VALUES
                                        (
                                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                                        )
                                ");


                        $statement->execute([
                            $this->publicReference(
                                'TSPLI-ADM-'
                            ),
                            $portalId,
                            ...$values,
                            $actor,
                        ]);


                        return
                            (int) $this->ticketing
                                ->lastInsertId();
                    }
                );


        } catch (Throwable $exception) {

            if ($storedImage !== null) {

                $this->uploader
                    ->remove(
                        $storedImage
                    );
            }


            if ($storedMobile !== null) {

                $this->uploader
                    ->remove(
                        $storedMobile
                    );
            }


            throw $exception;
        }
    }


    public function deleteItem(
        array $input,
        int $userId
    ): void {

        $this->authorize(
            $userId
        );


        $portal =
            $this->portal(
                (string) (
                    $input[
                        'portal_reference'
                    ]
                    ?? ''
                )
            );


        $id =
            max(
                0,
                (int) (
                    $input[
                        'id'
                    ]
                    ?? 0
                )
            );


        if ($id < 1) {
            throw new RuntimeException(
                'portal_item_not_found'
            );
        }


        $this->transactional(
            function () use (
                $id,
                $portal
            ): void {

                $statement =
                    $this->ticketing
                        ->prepare("
                            DELETE FROM
                                ticketing_support_portal_landing_items

                            WHERE
                                id = ?
                              AND portal_id = ?
                        ");


                $statement->execute([
                    $id,
                    (int) $portal[
                        'portal_id'
                    ],
                ]);


                if (
                    $statement->rowCount()
                    !== 1
                ) {
                    throw new RuntimeException(
                        'portal_item_not_found'
                    );
                }
            }
        );
    }


    private function portal(
        string $reference
    ): array {

        $reference =
            trim(
                $reference
            );


        if ($reference === '') {
            throw new RuntimeException(
                'portal_reference_required'
            );
        }


        $statement =
            $this->ticketing
                ->prepare("
                    SELECT
                        p.id AS portal_id,
                        p.public_reference AS portal_reference,
                        p.project_id,
                        p.realm_id,

                        r.public_reference AS realm_reference,

                        sp.public_reference AS project_reference

                    FROM
                        ticketing_support_portals p

                    INNER JOIN
                        ticketing_support_realms r

                      ON r.id = p.realm_id
                     AND r.project_id = p.project_id

                    INNER JOIN
                        ticketing_support_projects sp

                      ON sp.id = p.project_id

                    WHERE
                        p.public_reference = ?

                      AND p.archived_at IS NULL
                      AND r.archived_at IS NULL
                      AND sp.archived_at IS NULL

                    LIMIT 1
                ");


        $statement->execute([
            $reference,
        ]);


        $row =
            $statement->fetch();


        if (!is_array($row)) {
            throw new RuntimeException(
                'portal_not_found'
            );
        }


        return $row;
    }


    private function syncSetting(
        string $table,
        string $scopeColumn,
        int $scopeId,
        string $key,
        string $value,
        bool $enabled,
        string $actor
    ): void {

        $allowed = [
            'ticketing_support_project_brand_settings'
                => 'project_id',

            'ticketing_support_portal_brand_settings'
                => 'portal_id',

            'ticketing_support_portal_landing_settings'
                => 'portal_id',
        ];


        if (
            ($allowed[$table] ?? null)
            !== $scopeColumn
        ) {
            throw new RuntimeException(
                'portal_setting_scope_invalid'
            );
        }


        if (!$enabled) {

            $statement =
                $this->ticketing
                    ->prepare("
                        DELETE FROM `{$table}`

                        WHERE
                            `{$scopeColumn}` = ?
                          AND setting_key = ?
                    ");


            $statement->execute([
                $scopeId,
                $key,
            ]);


            return;
        }


        $statement =
            $this->ticketing
                ->prepare("
                    INSERT INTO
                        `{$table}`
                        (
                            `{$scopeColumn}`,
                            setting_key,
                            setting_value,
                            value_type,
                            is_active,
                            created_by_user_reference,
                            updated_by_user_reference
                        )

                    VALUES
                        (?, ?, ?, 'string', 1, ?, ?)

                    ON DUPLICATE KEY UPDATE
                        setting_value =
                            VALUES(setting_value),

                        value_type =
                            VALUES(value_type),

                        is_active = 1,

                        updated_by_user_reference =
                            VALUES(updated_by_user_reference),

                        updated_at =
                            CURRENT_TIMESTAMP
                ");


        $statement->execute([
            $scopeId,
            $key,
            $value,
            $actor,
            $actor,
        ]);
    }


    private function brandValue(
        string $key,
        array $input
    ): string {

        $value =
            trim(
                (string) (
                    $input[$key]
                    ?? ''
                )
            );


        return match ($key) {

            'active_preset' =>
                $this->preset(
                    $value
                ),

            'brand_name',
            'brand_subtitle' =>
                mb_substr(
                    $value,
                    0,
                    255
                ),

            'logo_url' =>
                $this->cleanUrl(
                    $value
                ),

            'footer_text' =>
                mb_substr(
                    $value,
                    0,
                    1000
                ),

            'footer_enabled' =>
                $this->truthy(
                    $input[
                        'footer_enabled'
                    ]
                    ?? false
                )
                    ? '1'
                    : '0',

            default =>
                '',
        };
    }


    private function landingValue(
        string $key,
        array $input
    ): string {

        $value =
            trim(
                (string) (
                    $input[$key]
                    ?? ''
                )
            );


        if (
            str_starts_with(
                $key,
                'show_'
            )
        ) {
            return
                $this->truthy(
                    $input[$key]
                    ?? false
                )
                    ? '1'
                    : '0';
        }


        if (
            str_starts_with(
                $key,
                'runtime_'
            )
            &&
            str_ends_with(
                $key,
                '_position'
            )
        ) {
            return
                in_array(
                    $value,
                    [
                        'right',
                        'center',
                        'left',
                        'hidden',
                    ],
                    true
                )
                    ? $value
                    : 'hidden';
        }


        if (
            $key === 'register_url'
        ) {
            return
                $this->cleanUrl(
                    $value
                );
        }


        return
            mb_substr(
                $value,
                0,
                2000
            );
    }


    private function preset(
        string $value
    ): string {

        $presets =
            (
                new AdminThemeService()
            )->presets();


        if (
            $value === ''
            || !isset(
                $presets[$value]
            )
        ) {
            throw new RuntimeException(
                'portal_theme_preset_invalid'
            );
        }


        return $value;
    }


    private function hostname(
        string $value
    ): string {

        $value =
            strtolower(
                trim(
                    $value
                )
            );


        if (
            $value === ''
            || strlen($value) > 253
            || str_contains(
                $value,
                '://'
            )
            || str_contains(
                $value,
                '/'
            )
            || str_contains(
                $value,
                ':'
            )
            ||
            preg_match(
                '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/',
                $value
            ) !== 1
        ) {
            throw new RuntimeException(
                'portal_hostname_invalid'
            );
        }


        return $value;
    }


    private function cleanUrl(
        string $value
    ): string {

        $value =
            trim(
                $value
            );


        if ($value === '') {
            return '';
        }


        if (
            str_starts_with(
                $value,
                '/'
            )
            ||
            str_starts_with(
                $value,
                '#'
            )
        ) {
            return $value;
        }


        if (
            filter_var(
                $value,
                FILTER_VALIDATE_URL
            )
            &&
            in_array(
                strtolower(
                    (string) parse_url(
                        $value,
                        PHP_URL_SCHEME
                    )
                ),
                [
                    'http',
                    'https',
                ],
                true
            )
        ) {
            return $value;
        }


        throw new RuntimeException(
            'portal_url_invalid'
        );
    }


    private function dateTime(
        mixed $value
    ): ?string {

        $value =
            JalaliDateInput::englishDigits(
                trim(
                    (string) $value
                )
            );


        if ($value === '') {
            return null;
        }


        /*
         * Persian/Jalali admin input:
         *
         * 1405/06/17
         * 1405/06/17 14:30
         */
        if (
            preg_match(
                '/^(\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2})(?:[ T](\d{1,2}):(\d{2}))?$/',
                $value,
                $matches
            ) === 1
            &&
            (int) substr(
                $matches[1],
                0,
                4
            ) >= 1200
            &&
            (int) substr(
                $matches[1],
                0,
                4
            ) <= 1600
        ) {

            $gregorian =
                JalaliDateInput::toGregorian(
                    $matches[1]
                );


            if ($gregorian === null) {
                throw new RuntimeException(
                    'portal_datetime_invalid'
                );
            }


            $hour =
                isset(
                    $matches[2]
                )
                    ? (int) $matches[2]
                    : 0;


            $minute =
                isset(
                    $matches[3]
                )
                    ? (int) $matches[3]
                    : 0;


            if (
                $hour < 0
                || $hour > 23
                || $minute < 0
                || $minute > 59
            ) {
                throw new RuntimeException(
                    'portal_datetime_invalid'
                );
            }


            $value =
                sprintf(
                    '%s %02d:%02d:00',
                    $gregorian,
                    $hour,
                    $minute
                );
        }


        try {

            return
                (
                    new DateTimeImmutable(
                        $value
                    )
                )
                ->setTimezone(
                    new DateTimeZone(
                        'UTC'
                    )
                )
                ->format(
                    'Y-m-d H:i:s'
                );


        } catch (Throwable) {

            throw new RuntimeException(
                'portal_datetime_invalid'
            );
        }
    }


    private function integerInput(
        mixed $value,
        int $default = 0
    ): int {

        $normalized =
            JalaliDateInput::englishDigits(
                trim(
                    (string) $value
                )
            );


        if (
            $normalized === ''
            ||
            preg_match(
                '/^-?\d+$/',
                $normalized
            ) !== 1
        ) {
            return $default;
        }


        return
            (int) $normalized;
    }


    private function stringList(
        mixed $value
    ): array {

        if (!is_array($value)) {
            return [];
        }


        return
            array_values(
                array_unique(
                    array_map(
                        static fn (
                            mixed $item
                        ): string =>
                            trim(
                                (string) $item
                            ),
                        $value
                    )
                )
            );
    }


    private function truthy(
        mixed $value
    ): bool {

        return
            in_array(
                strtolower(
                    trim(
                        (string) $value
                    )
                ),
                [
                    '1',
                    'true',
                    'yes',
                    'on',
                ],
                true
            );
    }


    private function publicReference(
        string $prefix
    ): string {

        return
            $prefix
            . strtoupper(
                bin2hex(
                    random_bytes(10)
                )
            );
    }


    private function actor(
        int $userId
    ): string {

        return
            'user:'
            . $userId;
    }


    private function transactional(
        callable $callback
    ): mixed {

        /*
         * TICKETING_PORTAL_WRITER_CALLER_TRANSACTION_V1
         *
         * Join caller transaction when present.
         */
        $ownsTransaction =
            !$this->ticketing
                ->inTransaction();


        if ($ownsTransaction) {

            $this->ticketing
                ->beginTransaction();
        }


        try {

            $result =
                $callback();


            if ($ownsTransaction) {

                $this->ticketing
                    ->commit();
            }


            return $result;


        } catch (Throwable $exception) {

            if (
                $ownsTransaction
                &&
                $this->ticketing
                    ->inTransaction()
            ) {
                $this->ticketing
                    ->rollBack();
            }


            throw $exception;
        }
    }


    private function authorize(
        int $userId
    ): void {

        if (
            $userId < 1
            ||
            !(
                new AdminNavigationRbacService()
            )->can(
                $userId,
                'ticketing.project.manage'
            )
        ) {
            throw new RuntimeException(
                'portal_management_forbidden'
            );
        }
    }
}
