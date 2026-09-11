<?php

declare(strict_types=1);

namespace App\Services\UiContent;

use DateTimeImmutable;
use IPKF\Database\Connections\ConnectionResolver;
use JsonException;
use PDO;
use RuntimeException;
use Throwable;


final class UiContentManagementService
{
    private PDO $db;

    private UiContentModuleCatalogService $modules;


    public function __construct(
        ?PDO $db = null,
        ?UiContentModuleCatalogService $modules = null
    ) {
        $this->db =
            $db
            ?? (
                new ConnectionResolver()
            )->resolve(
                'core.primary'
            );

        $this->modules =
            $modules
            ?? new UiContentModuleCatalogService();
    }


    public function page(
        array $filters = []
    ): array {

        $q =
            trim(
                (string) (
                    $filters['q']
                    ?? ''
                )
            );

        $type =
            strtolower(
                trim(
                    (string) (
                        $filters['type']
                        ?? ''
                    )
                )
            );

        if (
            !in_array(
                $type,
                [
                    '',
                    'guide',
                    'notice',
                    'error',
                ],
                true
            )
        ) {
            $type = '';
        }

        $status =
            strtolower(
                trim(
                    (string) (
                        $filters['status']
                        ?? ''
                    )
                )
            );

        if (
            !in_array(
                $status,
                [
                    '',
                    'active',
                    'inactive',
                ],
                true
            )
        ) {
            $status = '';
        }

        $selectedKey =
            strtolower(
                trim(
                    (string) (
                        $filters['key']
                        ?? ''
                    )
                )
            );

        $selectedOverrideReference =
            trim(
                (string) (
                    $filters['override']
                    ?? ''
                )
            );

        $newDefinition =
            (bool) (
                $filters['new_definition']
                ?? false
            );

        $newOverride =
            (bool) (
                $filters['new_override']
                ?? false
            );

        $items =
            $this->definitions(
                $q,
                $type,
                $status
            );

        $selected = null;

        if (
            !$newDefinition
            && $selectedKey !== ''
        ) {
            $selected =
                $this->definitionByKey(
                    $selectedKey
                );
        }

        if (
            !$newDefinition
            && $selected === null
            && $items !== []
        ) {
            $selected =
                $this->definitionByKey(
                    (string) $items[0][
                        'content_key'
                    ]
                );
        }

        $overrides = [];
        $selectedOverride = null;

        if (is_array($selected)) {
            $overrides =
                $this->overridesForDefinition(
                    (int) $selected['id']
                );

            if (
                !$newOverride
                && $selectedOverrideReference !== ''
            ) {
                foreach ($overrides as $override) {
                    if (
                        (string) (
                            $override[
                                'public_reference'
                            ]
                            ?? ''
                        )
                        ===
                        $selectedOverrideReference
                    ) {
                        $selectedOverride =
                            $override;

                        break;
                    }
                }
            }
        }

        try {
            $modules =
                $this->modules
                    ->modules();
        } catch (Throwable) {
            $modules = [];
        }

        return [
            'filters' => [
                'q' => $q,
                'type' => $type,
                'status' => $status,
            ],

            'items' =>
                $items,

            'selected' =>
                $selected,

            'overrides' =>
                $overrides,

            'selected_override' =>
                $selectedOverride,

            'new_definition' =>
                $newDefinition,

            'new_override' =>
                $newOverride,

            'modules' =>
                $modules,

            'content_types' => [
                'guide' =>
                    'راهنما',

                'notice' =>
                    'اعلان / اطلاع‌رسانی',

                'error' =>
                    'خطا',
            ],

            'severity_codes' => [
                'information' =>
                    'اطلاعاتی',

                'success' =>
                    'موفق',

                'warning' =>
                    'هشدار',

                'danger' =>
                    'خطا / بحرانی',
            ],

            'layout_variants' => [
                'system-message' =>
                    'پیام سیستمی',

                'compact' =>
                    'فشرده',

                'wide' =>
                    'عریض',
            ],

            'action_codes' => [
                'back' =>
                    'بازگشت',

                'home' =>
                    'صفحه اصلی',

                'admin_home' =>
                    'داشبورد مدیریت',

                'module_home' =>
                    'صفحه اصلی ماژول',
            ],
        ];
    }


    public function saveDefinition(
        int $actorUserId,
        array $input
    ): array {

        $actor =
            $this->actorReference(
                $actorUserId
            );

        $reference =
            trim(
                (string) (
                    $input[
                        'definition_reference'
                    ]
                    ?? ''
                )
            );

        if (
            $reference !== ''
            && preg_match(
                '/^[A-Za-z0-9][A-Za-z0-9_-]{1,47}$/D',
                $reference
            ) !== 1
        ) {
            throw new RuntimeException(
                'ui_content_definition_reference_invalid'
            );
        }

        $contentKey =
            $this->contentKey(
                (string) (
                    $input['content_key']
                    ?? ''
                )
            );

        $contentType =
            strtolower(
                trim(
                    (string) (
                        $input['content_type']
                        ?? ''
                    )
                )
            );

        if (
            !in_array(
                $contentType,
                [
                    'guide',
                    'notice',
                    'error',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'ui_content_type_invalid'
            );
        }

        $locale =
            $this->locale(
                (string) (
                    $input['default_locale']
                    ?? 'fa'
                )
            );

        $httpStatus = null;

        $httpStatusRaw =
            trim(
                (string) (
                    $input['http_status']
                    ?? ''
                )
            );

        if ($httpStatusRaw !== '') {
            if (!ctype_digit(
                $httpStatusRaw
            )) {
                throw new RuntimeException(
                    'ui_content_http_status_invalid'
                );
            }

            $httpStatus =
                (int) $httpStatusRaw;

            if (
                $httpStatus < 400
                || $httpStatus > 599
            ) {
                throw new RuntimeException(
                    'ui_content_http_status_invalid'
                );
            }
        }

        if ($contentType !== 'error') {
            $httpStatus = null;
        }

        $description =
            $this->nullableText(
                $input['description']
                ?? null,
                1000
            );

        $metadata =
            $this->metadataJson(
                $input['metadata_json']
                ?? null
            );

        $active =
            (string) (
                $input['is_active']
                ?? ''
            ) === '1';

        $lockName =
            'troca.ui-content.definition.'
            . substr(
                hash(
                    'sha256',
                    $reference !== ''
                        ? $reference
                        : $contentKey
                ),
                0,
                40
            );

        if (!$this->acquireLock(
            $lockName
        )) {
            throw new RuntimeException(
                'ui_content_definition_busy'
            );
        }

        try {
            $this->db
                ->beginTransaction();

            if ($reference !== '') {
                $statement =
                    $this->db->prepare("
                        SELECT
                            id,
                            public_reference,
                            content_key

                        FROM ui_content_definitions

                        WHERE public_reference = ?

                        LIMIT 1

                        FOR UPDATE
                    ");

                $statement->execute([
                    $reference,
                ]);

                $current =
                    $statement->fetch(
                        PDO::FETCH_ASSOC
                    );

                if (!is_array($current)) {
                    throw new RuntimeException(
                        'ui_content_definition_missing'
                    );
                }

                if (
                    (string) $current[
                        'content_key'
                    ]
                    !== $contentKey
                ) {
                    throw new RuntimeException(
                        'ui_content_key_immutable'
                    );
                }

                $statement =
                    $this->db->prepare("
                        UPDATE
                            ui_content_definitions

                        SET
                            content_type = ?,
                            default_locale = ?,
                            http_status = ?,
                            description = ?,
                            metadata_json = ?,
                            is_active = ?,
                            updated_by_user_reference = ?,
                            updated_at = CURRENT_TIMESTAMP

                        WHERE id = ?
                    ");

                $statement->execute([
                    $contentType,
                    $locale,
                    $httpStatus,
                    $description,
                    $metadata,
                    $active ? 1 : 0,
                    $actor,
                    (int) $current['id'],
                ]);
            } else {
                $statement =
                    $this->db->prepare("
                        SELECT
                            id

                        FROM ui_content_definitions

                        WHERE content_key = ?

                        LIMIT 1

                        FOR UPDATE
                    ");

                $statement->execute([
                    $contentKey,
                ]);

                if (
                    $statement->fetchColumn()
                    !== false
                ) {
                    throw new RuntimeException(
                        'ui_content_key_exists'
                    );
                }

                $reference =
                    $this->newReference(
                        'UICD'
                    );

                $statement =
                    $this->db->prepare("
                        INSERT INTO
                            ui_content_definitions
                        (
                            public_reference,
                            content_key,
                            content_type,
                            default_locale,
                            http_status,
                            description,
                            metadata_json,
                            is_active,
                            created_by_user_reference,
                            updated_by_user_reference,
                            created_at,
                            updated_at
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            CURRENT_TIMESTAMP,
                            CURRENT_TIMESTAMP
                        )
                    ");

                $statement->execute([
                    $reference,
                    $contentKey,
                    $contentType,
                    $locale,
                    $httpStatus,
                    $description,
                    $metadata,
                    $active ? 1 : 0,
                    $actor,
                    $actor,
                ]);
            }

            $this->db
                ->commit();

            return [
                'ok' => true,
                'public_reference' =>
                    $reference,
                'content_key' =>
                    $contentKey,
            ];
        } catch (Throwable $exception) {
            if (
                $this->db
                    ->inTransaction()
            ) {
                $this->db
                    ->rollBack();
            }

            throw $exception;
        } finally {
            $this->releaseLock(
                $lockName
            );
        }
    }


    public function saveOverride(
        int $actorUserId,
        array $input
    ): array {

        $actor =
            $this->actorReference(
                $actorUserId
            );

        $definitionKey =
            $this->contentKey(
                (string) (
                    $input['definition_key']
                    ?? ''
                )
            );

        $definition =
            $this->definitionByKey(
                $definitionKey
            );

        if (!is_array($definition)) {
            throw new RuntimeException(
                'ui_content_definition_missing'
            );
        }

        $definitionId =
            (int) $definition['id'];

        $locale =
            $this->locale(
                (string) (
                    $input['locale']
                    ?? $definition[
                        'default_locale'
                    ]
                    ?? 'fa'
                )
            );

        $scope =
            $this->scope(
                $input,
                $locale
            );

        $reference =
            trim(
                (string) (
                    $input[
                        'override_reference'
                    ]
                    ?? ''
                )
            );

        if (
            $reference !== ''
            && preg_match(
                '/^[A-Za-z0-9][A-Za-z0-9_-]{1,47}$/D',
                $reference
            ) !== 1
        ) {
            throw new RuntimeException(
                'ui_content_override_reference_invalid'
            );
        }

        $title =
            $this->nullableText(
                $input['title']
                ?? null,
                500
            );

        $body =
            $this->nullableText(
                $input['body']
                ?? null,
                50000
            );

        $icon =
            $this->nullableText(
                $input['icon_code']
                ?? null,
                100
            );

        $severity =
            $this->nullableEnum(
                $input['severity_code']
                ?? null,
                [
                    'information',
                    'success',
                    'warning',
                    'danger',
                ],
                'ui_content_severity_invalid'
            );

        $layout =
            $this->nullableEnum(
                $input['layout_variant']
                ?? null,
                [
                    'system-message',
                    'compact',
                    'wide',
                ],
                'ui_content_layout_invalid'
            );

        $visibility =
            strtolower(
                trim(
                    (string) (
                        $input['visibility_mode']
                        ?? 'inherit'
                    )
                )
            );

        if (
            !in_array(
                $visibility,
                [
                    'inherit',
                    'show',
                    'hide',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'ui_content_visibility_invalid'
            );
        }

        $primaryAction =
            $this->nullableEnum(
                $input[
                    'primary_action_code'
                ]
                ?? null,
                [
                    'back',
                    'home',
                    'admin_home',
                    'module_home',
                ],
                'ui_content_primary_action_invalid'
            );

        $secondaryAction =
            $this->nullableEnum(
                $input[
                    'secondary_action_code'
                ]
                ?? null,
                [
                    'back',
                    'home',
                    'admin_home',
                    'module_home',
                ],
                'ui_content_secondary_action_invalid'
            );

        $primaryLabel =
            $primaryAction !== null
                ? $this->nullableText(
                    $input[
                        'primary_action_label'
                    ]
                    ?? null,
                    255
                )
                : null;

        $secondaryLabel =
            $secondaryAction !== null
                ? $this->nullableText(
                    $input[
                        'secondary_action_label'
                    ]
                    ?? null,
                    255
                )
                : null;

        $metadata =
            $this->metadataJson(
                $input['metadata_json']
                ?? null
            );

        $startsAt =
            $this->dateTime(
                $input['starts_at']
                ?? null
            );

        $endsAt =
            $this->dateTime(
                $input['ends_at']
                ?? null
            );

        if (
            $startsAt !== null
            && $endsAt !== null
            && $startsAt > $endsAt
        ) {
            throw new RuntimeException(
                'ui_content_schedule_invalid'
            );
        }

        $active =
            (string) (
                $input['is_active']
                ?? ''
            ) === '1';

        $lockIdentity =
            $reference !== ''
                ? $reference
                : implode(
                    '|',
                    [
                        $definitionId,
                        $scope['scope_key'],
                        $locale,
                    ]
                );

        $lockName =
            'troca.ui-content.override.'
            . substr(
                hash(
                    'sha256',
                    $lockIdentity
                ),
                0,
                40
            );

        if (!$this->acquireLock(
            $lockName
        )) {
            throw new RuntimeException(
                'ui_content_override_busy'
            );
        }

        try {
            $this->db
                ->beginTransaction();

            $statement =
                $this->db->prepare("
                    SELECT id

                    FROM ui_content_definitions

                    WHERE id = ?

                    LIMIT 1

                    FOR UPDATE
                ");

            $statement->execute([
                $definitionId,
            ]);

            if (
                $statement->fetchColumn()
                === false
            ) {
                throw new RuntimeException(
                    'ui_content_definition_missing'
                );
            }

            $current = null;

            if ($reference !== '') {
                $statement =
                    $this->db->prepare("
                        SELECT
                            id,
                            definition_id,
                            public_reference

                        FROM ui_content_overrides

                        WHERE public_reference = ?

                        LIMIT 1

                        FOR UPDATE
                    ");

                $statement->execute([
                    $reference,
                ]);

                $current =
                    $statement->fetch(
                        PDO::FETCH_ASSOC
                    );

                if (!is_array($current)) {
                    throw new RuntimeException(
                        'ui_content_override_missing'
                    );
                }

                if (
                    (int) $current[
                        'definition_id'
                    ]
                    !== $definitionId
                ) {
                    throw new RuntimeException(
                        'ui_content_override_definition_mismatch'
                    );
                }

                $statement =
                    $this->db->prepare("
                        SELECT id

                        FROM ui_content_overrides

                        WHERE definition_id = ?
                          AND scope_key = ?
                          AND locale = ?
                          AND id <> ?

                        LIMIT 1

                        FOR UPDATE
                    ");

                $statement->execute([
                    $definitionId,
                    $scope['scope_key'],
                    $locale,
                    (int) $current['id'],
                ]);

                if (
                    $statement->fetchColumn()
                    !== false
                ) {
                    throw new RuntimeException(
                        'ui_content_override_identity_exists'
                    );
                }
            } else {
                $statement =
                    $this->db->prepare("
                        SELECT
                            id,
                            definition_id,
                            public_reference

                        FROM ui_content_overrides

                        WHERE definition_id = ?
                          AND scope_key = ?
                          AND locale = ?

                        LIMIT 1

                        FOR UPDATE
                    ");

                $statement->execute([
                    $definitionId,
                    $scope['scope_key'],
                    $locale,
                ]);

                $current =
                    $statement->fetch(
                        PDO::FETCH_ASSOC
                    );

                if (is_array($current)) {
                    $reference =
                        (string) $current[
                            'public_reference'
                        ];
                }
            }

            $values = [
                $scope['scope_type'],
                $scope['scope_key'],
                $scope['module_key'],
                $scope['scope_reference'],
                $scope['scope_path_json'],
                $locale,
                $title,
                $body,
                $icon,
                $severity,
                $layout,
                $visibility,
                $primaryAction,
                $primaryLabel,
                $secondaryAction,
                $secondaryLabel,
                $metadata,
                $startsAt,
                $endsAt,
                $active ? 1 : 0,
                $actor,
            ];

            if (is_array($current)) {
                $statement =
                    $this->db->prepare("
                        UPDATE
                            ui_content_overrides

                        SET
                            scope_type = ?,
                            scope_key = ?,
                            module_key = ?,
                            scope_reference = ?,
                            scope_path_json = ?,
                            locale = ?,
                            title = ?,
                            body = ?,
                            icon_code = ?,
                            severity_code = ?,
                            layout_variant = ?,
                            visibility_mode = ?,
                            primary_action_code = ?,
                            primary_action_label = ?,
                            secondary_action_code = ?,
                            secondary_action_label = ?,
                            metadata_json = ?,
                            starts_at = ?,
                            ends_at = ?,
                            is_active = ?,
                            updated_by_user_reference = ?,
                            updated_at = CURRENT_TIMESTAMP

                        WHERE id = ?
                    ");

                $values[] =
                    (int) $current['id'];

                $statement->execute(
                    $values
                );
            } else {
                $reference =
                    $this->newReference(
                        'UICO'
                    );

                $statement =
                    $this->db->prepare("
                        INSERT INTO
                            ui_content_overrides
                        (
                            public_reference,
                            definition_id,
                            scope_type,
                            scope_key,
                            module_key,
                            scope_reference,
                            scope_path_json,
                            locale,
                            title,
                            body,
                            icon_code,
                            severity_code,
                            layout_variant,
                            visibility_mode,
                            primary_action_code,
                            primary_action_label,
                            secondary_action_code,
                            secondary_action_label,
                            metadata_json,
                            starts_at,
                            ends_at,
                            is_active,
                            created_by_user_reference,
                            updated_by_user_reference,
                            created_at,
                            updated_at
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            CURRENT_TIMESTAMP,
                            CURRENT_TIMESTAMP
                        )
                    ");

                $statement->execute([
                    $reference,
                    $definitionId,
                    ...$values,
                    $actor,
                ]);
            }

            $this->db
                ->commit();

            return [
                'ok' => true,

                'content_key' =>
                    $definitionKey,

                'override_reference' =>
                    $reference,

                'scope_key' =>
                    $scope['scope_key'],

                'locale' =>
                    $locale,
            ];
        } catch (Throwable $exception) {
            if (
                $this->db
                    ->inTransaction()
            ) {
                $this->db
                    ->rollBack();
            }

            throw $exception;
        } finally {
            $this->releaseLock(
                $lockName
            );
        }
    }


    private function definitions(
        string $q,
        string $type,
        string $status
    ): array {

        $where = [
            '1 = 1',
        ];

        $params = [];

        if ($q !== '') {
            $where[] = "(
                d.content_key LIKE ?
                OR d.description LIKE ?
            )";

            $like =
                '%' . $q . '%';

            $params[] = $like;
            $params[] = $like;
        }

        if ($type !== '') {
            $where[] =
                'd.content_type = ?';

            $params[] = $type;
        }

        if ($status === 'active') {
            $where[] =
                'd.is_active = 1';
        }

        if ($status === 'inactive') {
            $where[] =
                'd.is_active = 0';
        }

        $statement =
            $this->db->prepare("
                SELECT
                    d.*,

                    (
                        SELECT COUNT(*)

                        FROM ui_content_overrides o

                        WHERE
                            o.definition_id =
                            d.id
                    ) AS override_count

                FROM
                    ui_content_definitions d

                WHERE "
                . implode(
                    ' AND ',
                    $where
                )
                . "

                ORDER BY
                    d.content_type,
                    d.content_key,
                    d.id
            ");

        $statement->execute(
            $params
        );

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }


    private function definitionByKey(
        string $contentKey
    ): ?array {

        if ($contentKey === '') {
            return null;
        }

        $statement =
            $this->db->prepare("
                SELECT *

                FROM ui_content_definitions

                WHERE content_key = ?

                LIMIT 1
            ");

        $statement->execute([
            $contentKey,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return
            is_array($row)
                ? $row
                : null;
    }


    private function overridesForDefinition(
        int $definitionId
    ): array {

        if ($definitionId < 1) {
            return [];
        }

        $statement =
            $this->db->prepare("
                SELECT *

                FROM ui_content_overrides

                WHERE definition_id = ?

                ORDER BY
                    scope_type,
                    scope_key,
                    locale,
                    id
            ");

        $statement->execute([
            $definitionId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }


    private function scope(
        array $input,
        string $locale
    ): array {

        $mode =
            strtolower(
                trim(
                    (string) (
                        $input['scope_mode']
                        ?? 'global'
                    )
                )
            );

        if ($mode === 'global') {
            return [
                'scope_type' =>
                    'global',

                'scope_key' =>
                    'global',

                'module_key' =>
                    null,

                'scope_reference' =>
                    null,

                'scope_path_json' =>
                    null,
            ];
        }

        $module =
            $this->moduleKey(
                (string) (
                    $input['module_key']
                    ?? ''
                )
            );

        if ($mode === 'module') {
            $context =
                new UiContentContext(
                    $module,
                    $locale
                );

            $descriptor =
                $context
                    ->descriptors()[1];

            return [
                'scope_type' =>
                    'module',

                'scope_key' =>
                    (string) $descriptor[
                        'key'
                    ],

                'module_key' =>
                    $module,

                'scope_reference' =>
                    $module,

                'scope_path_json' =>
                    null,
            ];
        }

        if ($mode !== 'fine') {
            throw new RuntimeException(
                'ui_content_scope_mode_invalid'
            );
        }

        $json =
            trim(
                (string) (
                    $input[
                        'scope_path_json'
                    ]
                    ?? ''
                )
            );

        if ($json === '') {
            throw new RuntimeException(
                'ui_content_scope_path_required'
            );
        }

        try {
            $path =
                json_decode(
                    $json,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'ui_content_scope_path_invalid',
                0,
                $exception
            );
        }

        if (
            !is_array($path)
            || $path === []
        ) {
            throw new RuntimeException(
                'ui_content_scope_path_invalid'
            );
        }

        $context =
            new UiContentContext(
                $module,
                $locale,
                $path
            );

        $descriptors =
            $context
                ->descriptors();

        if (count($descriptors) < 3) {
            throw new RuntimeException(
                'ui_content_fine_scope_invalid'
            );
        }

        $descriptor =
            $descriptors[
                array_key_last(
                    $descriptors
                )
            ];

        $normalizedPath =
            $context
                ->scopePath();

        return [
            'scope_type' =>
                (string) $descriptor[
                    'type'
                ],

            'scope_key' =>
                (string) $descriptor[
                    'key'
                ],

            'module_key' =>
                $module,

            'scope_reference' =>
                (string) $descriptor[
                    'reference'
                ],

            'scope_path_json' =>
                json_encode(
                    $normalizedPath,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                ),
        ];
    }


    private function contentKey(
        string $value
    ): string {

        $value =
            strtolower(
                trim(
                    $value
                )
            );

        if (
            preg_match(
                '/^[a-z0-9][a-z0-9._-]{2,189}$/D',
                $value
            )
            !== 1
        ) {
            throw new RuntimeException(
                'ui_content_key_invalid'
            );
        }

        return $value;
    }


    private function moduleKey(
        string $value
    ): string {

        $value =
            strtolower(
                trim(
                    $value
                )
            );

        if (
            preg_match(
                '/^[a-z][a-z0-9_-]{1,99}$/D',
                $value
            )
            !== 1
        ) {
            throw new RuntimeException(
                'ui_content_module_key_invalid'
            );
        }

        return $value;
    }


    private function locale(
        string $value
    ): string {

        $value =
            strtolower(
                trim(
                    $value
                )
            );

        if (
            preg_match(
                '/^[a-z]{2}(?:-[a-z0-9]{2,8})?$/D',
                $value
            )
            !== 1
        ) {
            throw new RuntimeException(
                'ui_content_locale_invalid'
            );
        }

        return $value;
    }


    private function nullableText(
        mixed $value,
        int $maxLength
    ): ?string {

        $value =
            trim(
                (string) (
                    $value
                    ?? ''
                )
            );

        if ($value === '') {
            return null;
        }

        return
            mb_substr(
                $value,
                0,
                $maxLength
            );
    }


    private function nullableEnum(
        mixed $value,
        array $allowed,
        string $errorCode
    ): ?string {

        $value =
            strtolower(
                trim(
                    (string) (
                        $value
                        ?? ''
                    )
                )
            );

        if ($value === '') {
            return null;
        }

        if (
            !in_array(
                $value,
                $allowed,
                true
            )
        ) {
            throw new RuntimeException(
                $errorCode
            );
        }

        return $value;
    }


    private function metadataJson(
        mixed $value
    ): ?string {

        $value =
            trim(
                (string) (
                    $value
                    ?? ''
                )
            );

        if ($value === '') {
            return null;
        }

        if (
            !str_starts_with(
                $value,
                '{'
            )
        ) {
            throw new RuntimeException(
                'ui_content_metadata_object_required'
            );
        }

        try {
            $decoded =
                json_decode(
                    $value,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'ui_content_metadata_invalid',
                0,
                $exception
            );
        }

        if (!is_array($decoded)) {
            throw new RuntimeException(
                'ui_content_metadata_invalid'
            );
        }

        return
            json_encode(
                $decoded,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );
    }


    private function dateTime(
        mixed $value
    ): ?string {

        $value =
            trim(
                (string) (
                    $value
                    ?? ''
                )
            );

        if ($value === '') {
            return null;
        }

        $value =
            str_replace(
                'T',
                ' ',
                $value
            );

        if (
            preg_match(
                '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/D',
                $value
            ) === 1
        ) {
            $value .= ':00';
        }

        if (
            preg_match(
                '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',
                $value
            ) !== 1
        ) {
            throw new RuntimeException(
                'ui_content_datetime_invalid'
            );
        }

        $date =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i:s',
                $value
            );

        $errors =
            DateTimeImmutable::getLastErrors();

        if (
            !$date
            || (
                is_array($errors)
                && (
                    $errors['warning_count'] > 0
                    || $errors['error_count'] > 0
                )
            )
        ) {
            throw new RuntimeException(
                'ui_content_datetime_invalid'
            );
        }

        return
            $date->format(
                'Y-m-d H:i:s'
            );
    }


    private function actorReference(
        int $actorUserId
    ): string {

        if ($actorUserId < 1) {
            throw new RuntimeException(
                'ui_content_actor_invalid'
            );
        }

        return
            'user:'
            . $actorUserId;
    }


    private function newReference(
        string $prefix
    ): string {

        return
            $prefix
            . '-'
            . strtoupper(
                bin2hex(
                    random_bytes(10)
                )
            );
    }


    private function acquireLock(
        string $name
    ): bool {

        $statement =
            $this->db->prepare(
                'SELECT GET_LOCK(?, 5)'
            );

        $statement->execute([
            $name,
        ]);

        return
            (int) $statement
                ->fetchColumn()
            === 1;
    }


    private function releaseLock(
        string $name
    ): void {

        try {
            $statement =
                $this->db->prepare(
                    'SELECT RELEASE_LOCK(?)'
                );

            $statement->execute([
                $name,
            ]);
        } catch (Throwable) {
        }
    }
}
