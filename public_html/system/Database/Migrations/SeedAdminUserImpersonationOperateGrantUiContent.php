<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;
use RuntimeException;
use Throwable;

final class SeedAdminUserImpersonationOperateGrantUiContent
    extends Migration
{
    private const SEED =
        'c4-1-1-operate-person-grant-ui-v1';

    private const MODULE =
        'core';

    private const SURFACE =
        'impersonation';

    private const SOURCE_FILE =
        'platform:admin-user-impersonation-c4.1.1';

    private const ITEMS = [
        [
            'key' =>
                'core.users.impersonation.operate.access.title',

            'role' =>
                'section_title',

            'body' =>
                'دسترسی عملیاتی ورود مدیریتی',
        ],
        [
            'key' =>
                'core.users.impersonation.operate.access.description',

            'role' =>
                'description',

            'body' =>
                'این مجوز به‌صورت فردی اعطا می‌شود و فقط در کنار دسترسی معتبر ورود مدیریتی و محدوده مجاز شخص قابل استفاده است.',
        ],
        [
            'key' =>
                'core.users.impersonation.operate.access.status.allowed',

            'role' =>
                'status',

            'body' =>
                'دسترسی عملیاتی فعال است',
        ],
        [
            'key' =>
                'core.users.impersonation.operate.access.status.denied',

            'role' =>
                'status',

            'body' =>
                'دسترسی عملیاتی غیرفعال است',
        ],
        [
            'key' =>
                'core.users.impersonation.operate.access.status.ineligible',

            'role' =>
                'status',

            'body' =>
                'این شخص در حال حاضر شرایط دریافت دسترسی عملیاتی را ندارد',
        ],
        [
            'key' =>
                'core.users.impersonation.operate.access.grant',

            'role' =>
                'action_label',

            'body' =>
                'اعطای دسترسی عملیاتی',
        ],
        [
            'key' =>
                'core.users.impersonation.operate.access.revoke',

            'role' =>
                'action_label',

            'body' =>
                'لغو دسترسی عملیاتی',
        ],
        [
            'key' =>
                'core.users.impersonation.operate.access.reason.label',

            'role' =>
                'field_label',

            'body' =>
                'دلیل تغییر دسترسی',
        ],
        [
            'key' =>
                'core.users.impersonation.operate.access.reason.placeholder',

            'role' =>
                'field_placeholder',

            'body' =>
                'دلیل اعطا یا لغو این دسترسی را وارد کنید',
        ],
        [
            'key' =>
                'core.users.impersonation.operate.access.confirm.grant',

            'role' =>
                'confirm_body',

            'body' =>
                'دسترسی عملیاتی ورود مدیریتی برای این شخص فعال شود؟',
        ],
        [
            'key' =>
                'core.users.impersonation.operate.access.confirm.revoke',

            'role' =>
                'confirm_body',

            'body' =>
                'دسترسی عملیاتی ورود مدیریتی این شخص لغو شود؟',
        ],
        [
            'key' =>
                'core.users.impersonation.operate.access.feedback.updated',

            'role' =>
                'notice',

            'body' =>
                'دسترسی عملیاتی کاربر با موفقیت به‌روزرسانی شد.',
        ],
        [
            'key' =>
                'core.users.impersonation.operate.access.feedback.denied',

            'role' =>
                'error',

            'body' =>
                'امکان انجام این تغییر برای شما وجود ندارد یا شرایط امنیتی آن معتبر نیست.',
        ],
    ];


    public function up(): void
    {
        $this->db->beginTransaction();

        try {
            foreach (
                self::ITEMS
                as $item
            ) {
                $definitionId =
                    $this->createDefinition(
                        $item
                    );

                $this->createOverride(
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
         * Managed UI content remains non-destructive.
         */
    }


    private function createDefinition(
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
                'c4_1_1_ui_key_empty'
            );
        }

        $existing =
            $this->db->prepare(
                'SELECT id
                 FROM ui_content_definitions
                 WHERE content_key = ?
                 LIMIT 1'
            );

        $existing->execute([
            $key,
        ]);

        if (
            $existing->fetchColumn()
            !== false
        ) {
            throw new RuntimeException(
                'c4_1_1_ui_definition_collision:'
                . $key
            );
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
            'Admin impersonation person grant C4.1.1: '
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
                'c4_1_1_ui_definition_create_failed:'
                . $key
            );
        }

        return $id;
    }


    private function createOverride(
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
                'c4_1_1_ui_body_empty:'
                . $key
            );
        }

        $scopeKey =
            'scope:'
            . self::MODULE
            . ':surface:'
            . self::SURFACE;

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
}
