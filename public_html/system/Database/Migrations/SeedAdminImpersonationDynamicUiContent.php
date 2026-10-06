<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;
use RuntimeException;
use Throwable;

final class SeedAdminImpersonationDynamicUiContent
    extends Migration
{
    private const SEED =
        'c4-s4c-admin-impersonation-ui-v1';

    private const MODULE =
        'core';

    private const SURFACE =
        'impersonation';

    private const SOURCE_FILE =
        'platform:admin-user-impersonation';

    private const ITEMS_JSON =
        '[
            {
                "key":"core.users.impersonation.action",
                "role":"action_label",
                "body":"ورود به حساب کاربر"
            },
            {
                "key":"core.users.impersonation.confirm.title",
                "role":"confirm_title",
                "body":"تأیید ورود موقت"
            },
            {
                "key":"core.users.impersonation.confirm.body",
                "role":"confirm_body",
                "body":"برای مشاهده وضعیت این کاربر، به‌صورت موقت و فقط‌خواندنی وارد محیط او می‌شوید. ادامه می‌دهید؟"
            },
            {
                "key":"core.users.impersonation.banner",
                "role":"banner",
                "body":"در حال مشاهده سامانه با هویت کاربر زیر هستید:"
            },
            {
                "key":"core.users.impersonation.return",
                "role":"action_label",
                "body":"بازگشت به حساب اصلی"
            },
            {
                "key":"core.users.impersonation.readonly",
                "role":"notice",
                "body":"این حالت فقط برای مشاهده است و عملیات تغییردهنده مسدود است."
            },
            {
                "key":"core.users.impersonation.denied",
                "role":"error",
                "body":"ورود به محیط این کاربر مجاز نیست."
            },
            {
                "key":"core.users.impersonation.expired",
                "role":"notice",
                "body":"زمان مشاهده حساب کاربر پایان یافت و حساب اصلی بازیابی شد."
            }
        ]';


    public function up(): void
    {
        $items =
            json_decode(
                self::ITEMS_JSON,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

        if (
            !is_array($items)
            || count($items) !== 8
        ) {
            throw new RuntimeException(
                'admin_impersonation_ui_payload_invalid'
            );
        }

        $this->db->beginTransaction();

        try {
            foreach ($items as $item) {
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
         * Intentionally non-destructive because managed
         * UI content may be edited after deployment.
         */
    }


    private function ensureDefinition(
        array $item
    ): int {
        $key =
            trim(
                (string) (
                    $item[
                        'key'
                    ]
                    ?? ''
                )
            );

        if ($key === '') {
            throw new RuntimeException(
                'admin_impersonation_ui_key_empty'
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
            ) {
                throw new RuntimeException(
                    'admin_impersonation_definition_type_collision:'
                    . $key
                );
            }

            if (
                (
                    $metadata[
                        'seed'
                    ]
                    ?? null
                ) !== self::SEED
            ) {
                throw new RuntimeException(
                    'admin_impersonation_definition_ownership_collision:'
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
                 VALUES (
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
            'Admin impersonation UI: '
                . (string) (
                    $item[
                        'role'
                    ]
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
            $query->execute([
                $key,
            ]);

            $row =
                $query->fetch(
                    PDO::FETCH_ASSOC
                );

            $id =
                (int) (
                    $row[
                        'id'
                    ]
                    ?? 0
                );
        }

        if ($id < 1) {
            throw new RuntimeException(
                'admin_impersonation_definition_create_failed:'
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
                    $item[
                        'body'
                    ]
                    ?? ''
                )
            );

        if ($body === '') {
            throw new RuntimeException(
                'admin_impersonation_ui_body_empty:'
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
                    $metadata[
                        'seed'
                    ]
                    ?? null
                ) !== self::SEED
            ) {
                throw new RuntimeException(
                    'admin_impersonation_override_ownership_collision:'
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
                 VALUES (
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
                    $item[
                        'role'
                    ]
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
}
