<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;
use RuntimeException;
use Throwable;

final class ExtendAdminUserImpersonationOperationalMode
    extends Migration
{
    private const PERMISSION =
        'users.impersonate.operate';

    private const BASE_PERMISSION =
        'users.impersonate';

    private const SEED =
        'c4-1-operational-impersonation-ui-v1';

    private const MODULE =
        'core';

    private const SURFACE =
        'impersonation';

    private const SOURCE_FILE =
        'platform:admin-user-impersonation-c4.1';

    private const ITEMS = [
        [
            'key' =>
                'core.users.impersonation.mode.label',

            'role' =>
                'field_label',

            'body' =>
                'نوع ورود',
        ],
        [
            'key' =>
                'core.users.impersonation.mode.observe',

            'role' =>
                'option_label',

            'body' =>
                'فقط مشاهده',
        ],
        [
            'key' =>
                'core.users.impersonation.mode.operate',

            'role' =>
                'option_label',

            'body' =>
                'عملیاتی',
        ],
        [
            'key' =>
                'core.users.impersonation.confirm.operate',

            'role' =>
                'confirm_body',

            'body' =>
                'در حالت عملیاتی وارد محیط این کاربر می‌شوید. اقدامات مجاز با سطح دسترسی همین کاربر انجام و هویت مدیر در ممیزی ثبت می‌شود. ادامه می‌دهید؟',
        ],
        [
            'key' =>
                'core.users.impersonation.operate.banner',

            'role' =>
                'banner',

            'body' =>
                'در حال کار در سامانه با هویت کاربر زیر هستید:',
        ],
        [
            'key' =>
                'core.users.impersonation.operate',

            'role' =>
                'notice',

            'body' =>
                'حالت عملیاتی فعال است؛ اقدامات مجاز به نام این کاربر و با ثبت هویت مدیر انجام می‌شود.',
        ],
        [
            'key' =>
                'core.users.impersonation.mutation.blocked',

            'role' =>
                'notice',

            'body' =>
                'شما در حالت مشاهده وارد محیط این کاربر شده‌اید و امکان انجام عملیات تغییردهنده وجود ندارد.',
        ],
        [
            'key' =>
                'core.users.impersonation.sensitive.blocked',

            'role' =>
                'error',

            'body' =>
                'این عملیات امنیتی حتی در حالت عملیاتی ورود مدیریتی مجاز نیست.',
        ],
        [
            'key' =>
                'core.users.impersonation.operate.denied',

            'role' =>
                'error',

            'body' =>
                'مجوز حالت عملیاتی این نشست معتبر نیست؛ عملیات انجام نشد.',
        ],
        [
            'key' =>
                'core.users.impersonation.audit.unavailable',

            'role' =>
                'error',

            'body' =>
                'ثبت ممیزی عملیات ممکن نیست؛ برای حفظ امنیت عملیات انجام نشد.',
        ],
    ];


    public function up(): void
    {
        if (
            !$this->columnExists(
                'permissions',
                'is_sensitive'
            )
        ) {
            throw new RuntimeException(
                'impersonation_operate_permission_foundation_missing'
            );
        }

        $this->db->beginTransaction();

        try {
            $this->seedPermission();
            $this->grantBootstrapPermission();

            foreach (self::ITEMS as $item) {
                $definitionId =
                    $this->ensureDefinition(
                        $item
                    );

                $this->ensureOverride(
                    $definitionId,
                    $item
                );
            }

            $this->db->commit();

        } catch (Throwable $exception) {

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }


    public function down(): void
    {
        /*
         * Managed configuration is intentionally
         * non-destructive.
         */
    }


    private function seedPermission(): void
    {
        $statement =
            $this->db->prepare("
                INSERT INTO permissions
                (
                    code,
                    module,
                    resource,
                    action,
                    title,
                    is_sensitive,
                    is_active,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    'core',
                    'users',
                    'impersonate_operate',
                    'ورود عملیاتی مدیریتی به حساب کاربر',
                    1,
                    1,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
                ON DUPLICATE KEY UPDATE
                    module =
                        VALUES(module),
                    resource =
                        VALUES(resource),
                    action =
                        VALUES(action),
                    title =
                        VALUES(title),
                    is_sensitive = 1,
                    is_active = 1,
                    updated_at =
                        CURRENT_TIMESTAMP
            ");

        $statement->execute([
            self::PERMISSION,
        ]);
    }


    private function grantBootstrapPermission(): void
    {
        /*
         * Dynamic capability bootstrap.
         * No role name/title is used.
         */
        $statement =
            $this->db->prepare("
                INSERT IGNORE INTO role_permissions
                (
                    role_id,
                    permission_id,
                    created_at
                )
                SELECT
                    roles.id,
                    operate_permission.id,
                    CURRENT_TIMESTAMP
                FROM roles

                INNER JOIN role_permissions
                    AS base_grant
                  ON base_grant.role_id =
                        roles.id

                INNER JOIN permissions
                    AS base_permission
                  ON base_permission.id =
                        base_grant.permission_id
                 AND base_permission.code = ?
                 AND base_permission.is_active = 1

                INNER JOIN permissions
                    AS operate_permission
                  ON operate_permission.code = ?
                 AND operate_permission.is_active = 1

                WHERE roles.is_active = 1
                  AND roles.can_manage_other_users = 1
            ");

        $statement->execute([
            self::BASE_PERMISSION,
            self::PERMISSION,
        ]);
    }


    private function ensureDefinition(
        array $item
    ): int {
        $key =
            trim(
                (string) (
                    $item['key']
                    ?? ''
                )
            );

        if ($key === '') {
            throw new RuntimeException(
                'c4_1_ui_key_empty'
            );
        }

        $query =
            $this->db->prepare(
                'SELECT
                    id,
                    content_type,
                    metadata_json
                 FROM ui_content_definitions
                 WHERE content_key = ?
                 LIMIT 1
                 FOR UPDATE'
            );

        $query->execute([
            $key,
        ]);

        $existing =
            $query->fetch(
                PDO::FETCH_ASSOC
            );

        if (is_array($existing)) {
            $metadata =
                $this->metadata(
                    $existing[
                        'metadata_json'
                    ]
                    ?? null
                );

            if (
                (string) (
                    $existing[
                        'content_type'
                    ]
                    ?? ''
                ) !== 'guide'
                ||
                (
                    $metadata['seed']
                    ?? null
                ) !== self::SEED
            ) {
                throw new RuntimeException(
                    'c4_1_definition_ownership_collision:'
                    . $key
                );
            }

            return
                (int) $existing['id'];
        }

        $reference =
            'UICD-'
            . strtoupper(
                substr(
                    hash(
                        'sha256',
                        'definition|'
                        . $key
                    ),
                    0,
                    24
                )
            );

        $metadata =
            $this->metadataFor(
                $item
            );

        $insert =
            $this->db->prepare(
                "INSERT INTO ui_content_definitions
                 (
                    public_reference,
                    content_key,
                    content_type,
                    default_locale,
                    http_status,
                    description,
                    metadata_json,
                    is_active
                 )
                 VALUES
                 (
                    ?,
                    ?,
                    'guide',
                    'fa',
                    NULL,
                    ?,
                    ?,
                    1
                 )"
            );

        $insert->execute([
            $reference,
            $key,
            'Admin impersonation C4.1: '
                . (string) (
                    $item['role']
                    ?? 'text'
                ),
            json_encode(
                $metadata,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
        ]);

        $id =
            (int) $this->db
                ->lastInsertId();

        if ($id < 1) {
            throw new RuntimeException(
                'c4_1_definition_create_failed:'
                . $key
            );
        }

        return $id;
    }


    private function ensureOverride(
        int $definitionId,
        array $item
    ): void {
        $key =
            (string) $item['key'];

        $body =
            trim(
                (string) (
                    $item['body']
                    ?? ''
                )
            );

        if ($body === '') {
            throw new RuntimeException(
                'c4_1_ui_body_empty:'
                . $key
            );
        }

        $scopeKey =
            'scope:'
            . self::MODULE
            . ':surface:'
            . self::SURFACE;

        $query =
            $this->db->prepare(
                "SELECT
                    id,
                    metadata_json
                 FROM ui_content_overrides
                 WHERE definition_id = ?
                   AND scope_key = ?
                   AND locale = 'fa'
                 LIMIT 1
                 FOR UPDATE"
            );

        $query->execute([
            $definitionId,
            $scopeKey,
        ]);

        $existing =
            $query->fetch(
                PDO::FETCH_ASSOC
            );

        if (is_array($existing)) {
            $metadata =
                $this->metadata(
                    $existing[
                        'metadata_json'
                    ]
                    ?? null
                );

            if (
                (
                    $metadata['seed']
                    ?? null
                ) !== self::SEED
            ) {
                throw new RuntimeException(
                    'c4_1_override_ownership_collision:'
                    . $key
                );
            }

            return;
        }

        $reference =
            'UICO-'
            . strtoupper(
                substr(
                    hash(
                        'sha256',
                        'override|'
                        . $key
                        . '|'
                        . $scopeKey
                        . '|fa'
                    ),
                    0,
                    24
                )
            );

        $scopePath = [
            [
                'type' =>
                    'surface',

                'reference' =>
                    self::SURFACE,
            ],
        ];

        $metadata =
            $this->metadataFor(
                $item
            );

        $insert =
            $this->db->prepare(
                "INSERT INTO ui_content_overrides
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
                    is_active
                 )
                 VALUES
                 (
                    ?,
                    ?,
                    'fine',
                    ?,
                    ?,
                    ?,
                    ?,
                    'fa',
                    ?,
                    ?,
                    NULL,
                    NULL,
                    NULL,
                    'show',
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    ?,
                    1
                 )"
            );

        $insert->execute([
            $reference,
            $definitionId,
            $scopeKey,
            self::MODULE,
            self::SURFACE,
            json_encode(
                $scopePath,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
            $body,
            $body,
            json_encode(
                $metadata,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
        ]);
    }


    private function metadataFor(
        array $item
    ): array {
        return [
            'seed' =>
                self::SEED,

            'module' =>
                self::MODULE,

            'surface' =>
                self::SURFACE,

            'source_file' =>
                self::SOURCE_FILE,

            'consumer_bound' =>
                true,

            'render_mode' =>
                'body',

            'ui_role' =>
                (string) (
                    $item['role']
                    ?? 'text'
                ),
        ];
    }


    private function metadata(
        mixed $value
    ): array {
        $decoded =
            json_decode(
                (string) $value,
                true
            );

        return
            is_array($decoded)
                ? $decoded
                : [];
    }


    private function columnExists(
        string $table,
        string $column
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
            ");

        $statement->execute([
            $table,
            $column,
        ]);

        return
            (int) $statement
                ->fetchColumn()
            > 0;
    }
}
