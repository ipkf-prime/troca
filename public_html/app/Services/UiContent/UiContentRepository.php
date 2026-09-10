<?php

declare(strict_types=1);

namespace App\Services\UiContent;

use DateTimeImmutable;
use IPKF\Database\Connections\ConnectionResolver;
use PDO;


final class UiContentRepository
    implements UiContentStoreInterface
{
    private PDO $db;


    public function __construct(
        ?PDO $db = null
    ) {
        $this->db =
            $db
            ?? (
                new ConnectionResolver()
            )->resolve(
                'core.primary'
            );
    }


    public function available(): bool
    {
        $database =
            trim(
                (string) $this->db
                    ->query(
                        'SELECT DATABASE()'
                    )
                    ->fetchColumn()
            );

        if ($database === '') {
            return false;
        }

        $statement =
            $this->db->prepare("
                SELECT COUNT(*)

                FROM information_schema.TABLES

                WHERE TABLE_SCHEMA = ?

                  AND TABLE_NAME IN (
                      'ui_content_definitions',
                      'ui_content_overrides'
                  )
            ");

        $statement->execute([
            $database,
        ]);

        return
            (int) $statement
                ->fetchColumn()
            === 2;
    }


    public function definition(
        string $contentKey
    ): ?array {

        $statement =
            $this->db->prepare("
                SELECT
                    id,
                    public_reference,
                    content_key,
                    content_type,
                    default_locale,
                    http_status,
                    description,
                    metadata_json,
                    is_active

                FROM ui_content_definitions

                WHERE content_key = ?
                  AND is_active = 1

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


    public function activeOverrides(
        int $definitionId,
        array $scopeKeys,
        array $locales,
        DateTimeImmutable $now
    ): array {

        if (
            $definitionId < 1
            ||
            $scopeKeys === []
            ||
            $locales === []
        ) {
            return [];
        }

        $scopePlaceholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($scopeKeys),
                    '?'
                )
            );

        $localePlaceholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($locales),
                    '?'
                )
            );

        $statement =
            $this->db->prepare("
                SELECT
                    id,
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
                    is_active

                FROM ui_content_overrides

                WHERE definition_id = ?

                  AND scope_key IN (
                      {$scopePlaceholders}
                  )

                  AND locale IN (
                      {$localePlaceholders}
                  )

                  AND is_active = 1

                  AND (
                        starts_at IS NULL
                     OR starts_at <= ?
                  )

                  AND (
                        ends_at IS NULL
                     OR ends_at >= ?
                  )

                ORDER BY id
            ");

        $timestamp =
            $now->format(
                'Y-m-d H:i:s'
            );

        $statement->execute(
            array_merge(
                [
                    $definitionId,
                ],
                array_values(
                    $scopeKeys
                ),
                array_values(
                    $locales
                ),
                [
                    $timestamp,
                    $timestamp,
                ]
            )
        );

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }
}
