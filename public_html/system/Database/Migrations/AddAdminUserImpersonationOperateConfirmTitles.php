<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;
use RuntimeException;
use Throwable;

final class AddAdminUserImpersonationOperateConfirmTitles
    extends Migration
{
    private const SEED =
        'c4-1-1-operate-confirm-titles-v1';

    private const MODULE =
        'core';

    private const SURFACE =
        'impersonation';

    private const SOURCE_FILE =
        'platform:admin-user-impersonation-c4.1.1';

    private const ITEMS = [
        [
            'key' =>
                'core.users.impersonation.operate.access.confirm.grant.title',

            'body' =>
                'تأیید اعطای دسترسی عملیاتی',
        ],
        [
            'key' =>
                'core.users.impersonation.operate.access.confirm.revoke.title',

            'body' =>
                'تأیید لغو دسترسی عملیاتی',
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
         * Controlled deployment rollback owns
         * exact preimage restoration.
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
                'operate_confirm_title_key_empty'
            );
        }

        $exists =
            $this->db->prepare(
                'SELECT id
                 FROM ui_content_definitions
                 WHERE content_key = ?
                 LIMIT 1'
            );

        $exists->execute([$key]);

        if (
            $exists->fetchColumn()
            !== false
        ) {
            throw new RuntimeException(
                'operate_confirm_title_collision:'
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

        $metadata = [
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
                'confirm_title',
        ];

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
            'Admin impersonation operate confirm title',
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
                'operate_confirm_title_definition_failed'
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
                'operate_confirm_title_body_empty:'
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

        $metadata = [
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
                'confirm_title',
        ];

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
}
