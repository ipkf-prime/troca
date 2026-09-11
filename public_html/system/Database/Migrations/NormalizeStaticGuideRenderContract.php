<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;
use RuntimeException;
use Throwable;


/**
 * G4-C1-A2.1-S1
 *
 * Normalizes static guide title/body storage to match the real
 * consumer render shape before those consumers are dynamically bound.
 *
 * Administrator-edited content is never overwritten because each
 * record must still equal the original g4-c1-a1 seed baseline.
 */
final class NormalizeStaticGuideRenderContract
    extends Migration
{
    private const SEED =
        'g4-c1-a1';


    public function up(): void
    {
        $this->apply(
            true
        );
    }


    public function down(): void
    {
        $this->apply(
            false
        );
    }


    private function apply(
        bool $forward
    ): void {

        $guides =
            $this->guides();


        if (count($guides) !== 47) {
            throw new RuntimeException(
                'Static guide render-contract count invalid.'
            );
        }


        $this->db->beginTransaction();

        try {

            foreach ($guides as $item) {

                $this->applyItem(
                    $item,
                    $forward
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


    private function applyItem(
        array $item,
        bool $forward
    ): void {

        $key =
            trim(
                (string) (
                    $item['key']
                    ?? ''
                )
            );


        $mode =
            trim(
                (string) (
                    $item[
                        'render_mode'
                    ]
                    ?? 'body'
                )
            );


        if (
            !in_array(
                $mode,
                [
                    'body',
                    'title_body',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Invalid static guide render mode: '
                . $key
            );
        }


        $newBody =
            trim(
                (string) (
                    $item['body']
                    ?? ''
                )
            );


        $legacyBody =
            trim(
                (string) (
                    $item[
                        'legacy_body'
                    ]
                    ?? $newBody
                )
            );


        $newTitle =
            trim(
                (string) (
                    $item['title']
                    ?? ''
                )
            );


        if ($newTitle === '') {

            $newTitle =
                $this->shortTitle(
                    $newBody
                );
        }


        $legacyTitle =
            $this->shortTitle(
                $legacyBody
            );


        $definitionStatement =
            $this->db->prepare("
                SELECT
                    id,
                    description,
                    metadata_json

                FROM ui_content_definitions

                WHERE content_key = ?

                LIMIT 1

                FOR UPDATE
            ");


        $definitionStatement->execute([
            $key,
        ]);


        $definition =
            $definitionStatement->fetch(
                PDO::FETCH_ASSOC
            );


        if (!is_array($definition)) {
            throw new RuntimeException(
                'Static guide definition missing: '
                . $key
            );
        }


        $definitionMetadata =
            $this->metadata(
                $definition[
                    'metadata_json'
                ]
                ?? null
            );


        if (
            (
                $definitionMetadata['seed']
                ?? null
            )
            !== self::SEED
        ) {
            throw new RuntimeException(
                'Static guide definition ownership mismatch: '
                . $key
            );
        }


        $scopeKey =
            'scope:'
            . (string) (
                $item['module']
                ?? ''
            )
            . ':surface:'
            . (string) (
                $item['surface']
                ?? ''
            );


        $overrideStatement =
            $this->db->prepare("
                SELECT
                    id,
                    title,
                    body,
                    metadata_json

                FROM ui_content_overrides

                WHERE definition_id = ?

                  AND scope_key = ?

                  AND locale = 'fa'

                LIMIT 1

                FOR UPDATE
            ");


        $overrideStatement->execute([
            (int) $definition['id'],
            $scopeKey,
        ]);


        $override =
            $overrideStatement->fetch(
                PDO::FETCH_ASSOC
            );


        if (!is_array($override)) {
            throw new RuntimeException(
                'Static guide override missing: '
                . $key
            );
        }


        $overrideMetadata =
            $this->metadata(
                $override[
                    'metadata_json'
                ]
                ?? null
            );


        if (
            (
                $overrideMetadata['seed']
                ?? null
            )
            !== self::SEED
        ) {
            throw new RuntimeException(
                'Static guide override ownership mismatch: '
                . $key
            );
        }


        if ($forward) {

            $expectedDescription =
                $legacyTitle;

            $expectedTitle =
                $legacyTitle;

            $expectedBody =
                $legacyBody;

            $targetDescription =
                $newTitle;

            $targetTitle =
                $newTitle;

            $targetBody =
                $newBody;

            $definitionMetadata[
                'render_mode'
            ] = $mode;

            $overrideMetadata[
                'render_mode'
            ] = $mode;

        } else {

            $expectedDescription =
                $newTitle;

            $expectedTitle =
                $newTitle;

            $expectedBody =
                $newBody;

            $targetDescription =
                $legacyTitle;

            $targetTitle =
                $legacyTitle;

            $targetBody =
                $legacyBody;

            unset(
                $definitionMetadata[
                    'render_mode'
                ]
            );

            unset(
                $overrideMetadata[
                    'render_mode'
                ]
            );
        }


        if (
            (string) (
                $definition[
                    'description'
                ]
                ?? ''
            )
            !== $expectedDescription
        ) {
            throw new RuntimeException(
                'Static guide definition changed outside expected baseline: '
                . $key
            );
        }


        if (
            (string) (
                $override['title']
                ?? ''
            )
            !== $expectedTitle
            ||
            (string) (
                $override['body']
                ?? ''
            )
            !== $expectedBody
        ) {
            throw new RuntimeException(
                'Static guide override changed outside expected baseline: '
                . $key
            );
        }


        $updateDefinition =
            $this->db->prepare("
                UPDATE ui_content_definitions

                SET
                    description = ?,
                    metadata_json = ?

                WHERE id = ?
            ");


        $updateDefinition->execute([
            $targetDescription,

            json_encode(
                $definitionMetadata,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),

            (int) $definition['id'],
        ]);


        $updateOverride =
            $this->db->prepare("
                UPDATE ui_content_overrides

                SET
                    title = ?,
                    body = ?,
                    metadata_json = ?

                WHERE id = ?
            ");


        $updateOverride->execute([
            $targetTitle,
            $targetBody,

            json_encode(
                $overrideMetadata,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),

            (int) $override['id'],
        ]);
    }


    private function guides(): array
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


        $catalog =
            json_decode(
                (string) file_get_contents(
                    $path
                ),
                true
            );


        if (!is_array($catalog)) {
            throw new RuntimeException(
                'Platform guide catalog invalid.'
            );
        }


        return
            array_values(
                array_filter(
                    $catalog,
                    static fn (
                        mixed $item
                    ): bool =>
                        is_array($item)
                        &&
                        (
                            $item[
                                'content_type'
                            ]
                            ?? null
                        )
                        === 'guide'
                )
            );
    }


    private function metadata(
        mixed $value
    ): array {

        if (
            $value === null
            ||
            trim(
                (string) $value
            )
            === ''
        ) {
            return [];
        }


        $decoded =
            json_decode(
                (string) $value,
                true
            );


        if (!is_array($decoded)) {
            throw new RuntimeException(
                'Static guide metadata invalid.'
            );
        }


        return $decoded;
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
            ||
            count($characters) <= 72
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
