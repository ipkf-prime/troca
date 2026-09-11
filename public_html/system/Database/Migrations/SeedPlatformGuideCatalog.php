<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use RuntimeException;
use Throwable;


/**
 * G4-C1-A1
 *
 * Seeds the curated platform guide / notice / error catalog.
 *
 * Existing administrator-managed records are never updated.
 * This migration only creates missing catalog entries.
 */
final class SeedPlatformGuideCatalog
    extends Migration
{
    private const SEED =
        'g4-c1-a1';


    public function up(): void
    {
        $catalog =
            $this->catalog();

        if (count($catalog) !== 53) {
            throw new RuntimeException(
                'Platform guide catalog count is invalid.'
            );
        }


        $this->db->beginTransaction();

        try {

            foreach ($catalog as $item) {

                $definitionId =
                    $this->definition(
                        $item
                    );

                $this->override(
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
         * Intentionally non-destructive.
         *
         * These texts become administrator-managed after seeding.
         * Automatic rollback must not delete later administrator edits.
         */
    }


    private function catalog(): array
    {
        $path =
            dirname(
                __DIR__,
                3
            )
            . '/resources/ui-content/platform-guides.json';


        if (!is_readable($path)) {
            throw new RuntimeException(
                'Platform guide catalog unavailable.'
            );
        }


        $decoded =
            json_decode(
                (string) file_get_contents(
                    $path
                ),
                true
            );


        if (!is_array($decoded)) {
            throw new RuntimeException(
                'Platform guide catalog is invalid.'
            );
        }


        return $decoded;
    }


    private function definition(
        array $item
    ): int {

        $key =
            strtolower(
                trim(
                    (string) (
                        $item['key']
                        ?? ''
                    )
                )
            );


        $contentType =
            strtolower(
                trim(
                    (string) (
                        $item[
                            'content_type'
                        ]
                        ?? ''
                    )
                )
            );


        if (
            preg_match(
                '/^[a-z0-9][a-z0-9._-]{2,189}$/D',
                $key
            )
            !== 1
        ) {
            throw new RuntimeException(
                'Invalid platform guide key: '
                . $key
            );
        }


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
                'Invalid platform guide type: '
                . $key
            );
        }


        $existing =
            $this->db->prepare("
                SELECT id

                FROM ui_content_definitions

                WHERE content_key = ?

                LIMIT 1
            ");

        $existing->execute([
            $key,
        ]);

        $existingId =
            (int) $existing
                ->fetchColumn();


        if ($existingId > 0) {
            return $existingId;
        }


        $body =
            trim(
                (string) (
                    $item['body']
                    ?? ''
                )
            );


        if ($body === '') {
            throw new RuntimeException(
                'Empty platform guide body: '
                . $key
            );
        }


        $title =
            $this->itemTitle(
                $item,
                $body
            );


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
            [
                'seed' =>
                    self::SEED,

                'module' =>
                    (string) (
                        $item['module']
                        ?? ''
                    ),

                'surface' =>
                    (string) (
                        $item['surface']
                        ?? ''
                    ),

                'source_file' =>
                    (string) (
                        $item[
                            'source_file'
                        ]
                        ?? ''
                    ),

                'source_line' =>
                    (int) (
                        $item[
                            'source_line'
                        ]
                        ?? 0
                    ),

                'fingerprint' =>
                    (string) (
                        $item[
                            'fingerprint'
                        ]
                        ?? ''
                    ),

                'render_mode' =>
                    (string) (
                        $item[
                            'render_mode'
                        ]
                        ?? 'body'
                    ),

                'consumer_bound' =>
                    false,
            ];


        $insert =
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
                    NULL,
                    ?,
                    ?,
                    1
                )
            ");


        $insert->execute([
            $reference,
            $key,
            $contentType,
            $title,

            json_encode(
                $metadata,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
        ]);


        $existing->execute([
            $key,
        ]);

        $id =
            (int) $existing
                ->fetchColumn();


        if ($id < 1) {
            throw new RuntimeException(
                'Unable to create platform guide definition: '
                . $key
            );
        }


        return $id;
    }


    private function override(
        int $definitionId,
        array $item
    ): void {

        $key =
            (string) $item['key'];

        $module =
            strtolower(
                trim(
                    (string) (
                        $item['module']
                        ?? ''
                    )
                )
            );

        $surface =
            strtolower(
                trim(
                    (string) (
                        $item['surface']
                        ?? ''
                    )
                )
            );

        $body =
            trim(
                (string) (
                    $item['body']
                    ?? ''
                )
            );

        $title =
            $this->itemTitle(
                $item,
                $body
            );


        if (
            preg_match(
                '/^[a-z][a-z0-9_-]{1,99}$/D',
                $module
            )
            !== 1
        ) {
            throw new RuntimeException(
                'Invalid platform guide module: '
                . $key
            );
        }


        if (
            preg_match(
                '/^[a-z0-9][a-z0-9_-]{1,119}$/D',
                $surface
            )
            !== 1
        ) {
            throw new RuntimeException(
                'Invalid platform guide surface: '
                . $key
            );
        }


        $scopeKey =
            'scope:'
            . $module
            . ':surface:'
            . $surface;


        $existing =
            $this->db->prepare("
                SELECT id

                FROM ui_content_overrides

                WHERE definition_id = ?
                  AND scope_key = ?
                  AND locale = 'fa'

                LIMIT 1
            ");

        $existing->execute([
            $definitionId,
            $scopeKey,
        ]);


        if (
            (int) $existing
                ->fetchColumn()
            > 0
        ) {
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


        $scopePath =
            [
                [
                    'type' =>
                        'surface',

                    'reference' =>
                        $surface,
                ],
            ];


        $metadata =
            [
                'seed' =>
                    self::SEED,

                'source_file' =>
                    (string) (
                        $item[
                            'source_file'
                        ]
                        ?? ''
                    ),

                'source_line' =>
                    (int) (
                        $item[
                            'source_line'
                        ]
                        ?? 0
                    ),

                'fingerprint' =>
                    (string) (
                        $item[
                            'fingerprint'
                        ]
                        ?? ''
                    ),

                'render_mode' =>
                    (string) (
                        $item[
                            'render_mode'
                        ]
                        ?? 'body'
                    ),

                'consumer_bound' =>
                    false,
            ];


        $insert =
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
                )
            ");


        $insert->execute([
            $reference,
            $definitionId,

            $scopeKey,
            $module,
            $surface,

            json_encode(
                $scopePath,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),

            $title,

            $body,

            json_encode(
                $metadata,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
        ]);
    }


    private function itemTitle(
        array $item,
        string $body
    ): string {

        $title =
            trim(
                (string) (
                    $item['title']
                    ?? ''
                )
            );


        return
            $title !== ''
                ? $title
                : $this->shortTitle(
                    $body
                );
    }


    private function shortTitle(
        string $body
    ): string {

        $body =
            trim(
                preg_replace(
                    '/\s+/u',
                    ' ',
                    $body
                )
                ?? $body
            );


        $characters =
            preg_split(
                '//u',
                $body,
                -1,
                PREG_SPLIT_NO_EMPTY
            );


        if (
            !is_array($characters)
            || count($characters) <= 72
        ) {
            return $body;
        }


        return
            implode(
                '',
                array_slice(
                    $characters,
                    0,
                    72
                )
            )
            . '…';
    }
}
