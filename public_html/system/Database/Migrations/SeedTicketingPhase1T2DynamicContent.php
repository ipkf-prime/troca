<?php
declare(strict_types=1);

namespace IPKF\Database\Migrations;

use RuntimeException;


/**
 * T2_A2B_DYNAMIC_CONTENT_CLOSURE_V2
 *
 * UiContentInlineGuide::bodyText() resolves only "guide" definitions and
 * requires the exact surface override to exist. This migration therefore
 * seeds each T2 visible label as a managed guide at its own surface scope.
 *
 * Existing administrator-managed values are never overwritten.
 */
final class SeedTicketingPhase1T2DynamicContent
    extends Migration
{
    public function up(): void
    {
        $catalogPath =
            BASE_PATH
            . '/resources/ui-content/'
            . 'ticketing-t2-dynamic-content.json';

        $raw=file_get_contents($catalogPath);

        if (!is_string($raw)) {
            throw new RuntimeException(
                'T2 dynamic content catalog unavailable.'
            );
        }

        $catalog=json_decode($raw,true);

        if (!is_array($catalog)) {
            throw new RuntimeException(
                'T2 dynamic content catalog invalid.'
            );
        }

        foreach ($catalog as $item) {

            if (!is_array($item)) {
                throw new RuntimeException(
                    'T2 dynamic content item invalid.'
                );
            }

            $key=trim((string)($item['key']??''));
            $surface=trim((string)($item['surface']??''));
            $body=(string)($item['body']??'');

            if (
                $key===''
                ||
                $surface===''
                ||
                $body===''
            ) {
                throw new RuntimeException(
                    'T2 dynamic content item incomplete.'
                );
            }

            $definitionId=
                $this->definition(
                    $key,
                    $surface
                );

            $this->surfaceOverride(
                $definitionId,
                $key,
                $surface,
                $body
            );
        }
    }


    public function down(): void
    {
        /*
         * Deliberately non-destructive.
         * Dynamic UI content is durable operational configuration.
         */
    }


    private function definition(
        string $key,
        string $surface
    ): int {

        $reference=
            'UICD-'
            .strtoupper(
                substr(
                    hash(
                        'sha256',
                        'definition|'.$key
                    ),
                    0,
                    24
                )
            );

        $statement=$this->db->prepare("
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
                'guide',
                'fa',
                NULL,
                ?,
                ?,
                1
            )
        ");

        $statement->execute([
            $reference,
            $key,
            'Ticketing T2 managed UI text: '.$surface,
            json_encode(
                [
                    'seed'=>'ticketing-phase1-t2-a2b-v2',
                    'surface'=>$surface,
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
        ]);

        $select=$this->db->prepare("
            SELECT
                id,
                content_type
            FROM ui_content_definitions
            WHERE content_key=?
            LIMIT 1
        ");

        $select->execute([$key]);

        $row=$select->fetch(\PDO::FETCH_ASSOC);

        if (
            !is_array($row)
            ||
            (int)($row['id']??0)<1
            ||
            (string)($row['content_type']??'')!=='guide'
        ) {
            throw new RuntimeException(
                'T2 dynamic guide definition unavailable: '.$key
            );
        }

        return (int)$row['id'];
    }


    private function surfaceOverride(
        int $definitionId,
        string $key,
        string $surface,
        string $body
    ): void {

        $scopeKey=
            'scope:ticketing:surface:'
            .$surface;

        $locale='fa';

        $reference=
            'UICO-'
            .strtoupper(
                substr(
                    hash(
                        'sha256',
                        'override|'
                        .$definitionId
                        .'|'
                        .$scopeKey
                        .'|'
                        .$locale
                    ),
                    0,
                    24
                )
            );

        $scopePath=
            json_encode(
                [[
                    'type'=>'surface',
                    'reference'=>$surface,
                ]],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            );

        $statement=$this->db->prepare("
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

                'surface',
                ?,
                'ticketing',
                ?,
                ?,

                ?,

                NULL,
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

        $statement->execute([
            $reference,
            $definitionId,

            $scopeKey,
            $surface,
            $scopePath,

            $locale,
            $body,

            json_encode(
                [
                    'seed'=>'ticketing-phase1-t2-a2b-v2',
                    'content_key'=>$key,
                    'surface'=>$surface,
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
        ]);
    }
}
