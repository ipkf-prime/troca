<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use RuntimeException;


/**
 * Seed defaults only.
 *
 * Existing administrator-managed content is never overwritten.
 */
final class SeedInitialDynamicUiContent
    extends Migration
{
    public function up(): void
    {
        $http404 =
            $this->definition(
                'http.404',
                'error',
                404,
                'صفحه عمومی یافت نشد'
            );


        $this->override(
            $http404,
            'global',
            'global',
            null,
            null,
            'fa',
            [
                'title' =>
                    'صفحه موردنظر پیدا نشد',

                'body' =>
                    'نشانی موردنظر وجود ندارد، جابه‌جا شده است '
                    . 'یا در این محدوده در دسترس نیست.',

                'icon_code' =>
                    'circle-alert',

                'severity_code' =>
                    'information',

                'layout_variant' =>
                    'system-message',

                'visibility_mode' =>
                    'show',

                'primary_action_code' =>
                    'back',

                'primary_action_label' =>
                    'بازگشت',

                'secondary_action_code' =>
                    'home',

                'secondary_action_label' =>
                    'صفحه اصلی',
            ]
        );


        $attachment404 =
            $this->definition(
                'ticketing.attachment.not_found',
                'error',
                404,
                'پیوست تیکت یافت نشد یا مجاز نیست'
            );


        $this->override(
            $attachment404,
            'module',
            'module:ticketing',
            'ticketing',
            'ticketing',
            'fa',
            [
                'title' =>
                    'پیوست موردنظر در دسترس نیست',

                'body' =>
                    'این پیوست وجود ندارد یا دسترسی به آن '
                    . 'از طریق این تیکت برای شما مجاز نیست.',

                'icon_code' =>
                    'file-x',

                'severity_code' =>
                    'information',

                'layout_variant' =>
                    'system-message',

                'visibility_mode' =>
                    'show',

                'primary_action_code' =>
                    'back',

                'primary_action_label' =>
                    'بازگشت',

                'secondary_action_code' =>
                    'module_home',

                'secondary_action_label' =>
                    'بازگشت به تیکتینگ',
            ]
        );
    }


    public function down(): void
    {
    }


    private function definition(
        string $key,
        string $type,
        int $httpStatus,
        string $description
    ): int {

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


        $statement =
            $this->db->prepare("
                INSERT IGNORE INTO
                    ui_content_definitions
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
                    ?,
                    'fa',
                    ?,
                    ?,
                    ?,
                    1
                )
            ");


        $statement->execute([
            $reference,
            $key,
            $type,
            $httpStatus,
            $description,

            json_encode(
                [
                    'seed' =>
                        't3f-c1-r4b1',
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
        ]);


        $select =
            $this->db->prepare("
                SELECT id

                FROM ui_content_definitions

                WHERE content_key = ?

                LIMIT 1
            ");


        $select->execute([
            $key,
        ]);


        $id =
            (int) $select
                ->fetchColumn();


        if ($id < 1) {
            throw new RuntimeException(
                'Definition unavailable: '
                . $key
            );
        }


        return $id;
    }


    private function override(
        int $definitionId,
        string $scopeType,
        string $scopeKey,
        ?string $moduleKey,
        ?string $scopeReference,
        string $locale,
        array $values
    ): void {

        $reference =
            'UICO-'
            . strtoupper(
                substr(
                    hash(
                        'sha256',
                        'override|'
                        . $definitionId
                        . '|'
                        . $scopeKey
                        . '|'
                        . $locale
                    ),
                    0,
                    24
                )
            );


        $statement =
            $this->db->prepare("
                INSERT IGNORE INTO
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
                    is_active
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
                    1
                )
            ");


        $statement->execute([
            $reference,
            $definitionId,

            $scopeType,
            $scopeKey,
            $moduleKey,
            $scopeReference,

            json_encode(
                [],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),

            $locale,

            $values['title']
            ?? null,

            $values['body']
            ?? null,

            $values['icon_code']
            ?? null,

            $values['severity_code']
            ?? null,

            $values['layout_variant']
            ?? null,

            $values['visibility_mode']
            ?? 'inherit',

            $values['primary_action_code']
            ?? null,

            $values['primary_action_label']
            ?? null,

            $values['secondary_action_code']
            ?? null,

            $values['secondary_action_label']
            ?? null,

            json_encode(
                [
                    'seed' =>
                        't3f-c1-r4b1',
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
        ]);
    }
}
