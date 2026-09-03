<?php

declare(strict_types=1);

namespace App\Services;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;

final class DynamicMessageTemplateService extends BaseService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db =
            $db
            ?? (
                new ConnectionResolver()
            )->resolve('core.primary');
    }

    public function render(
        string $code,
        string $channel,
        array $variables = [],
        string $locale = 'fa'
    ): array {
        $code =
            strtolower(trim($code));

        $channel =
            strtolower(trim($channel));

        $locale =
            strtolower(trim($locale));

        $this->assertIdentity(
            $code,
            $channel,
            $locale
        );

        $template =
            $this->template(
                $code,
                $channel,
                $locale
            );

        if ($template === null) {
            throw new RuntimeException(
                'message_template_unavailable'
            );
        }

        $content =
            $this->renderContent(
                [
                    'title_template' =>
                        (string) (
                            $template[
                                'title_template'
                            ]
                            ?? ''
                        ),

                    'body_template' =>
                        (string) (
                            $template[
                                'body_template'
                            ]
                            ?? ''
                        ),

                    'action_url_template' =>
                        (string) (
                            $template[
                                'action_url_template'
                            ]
                            ?? ''
                        ),
                ],
                $variables
            );

        return [
            'code' =>
                (string) $template['code'],

            'channel' =>
                (string) $template[
                    'channel_code'
                ],

            'locale' =>
                (string) $template['locale'],

            'version' =>
                (int) $template['version'],

            'format' =>
                (string) $template[
                    'format_code'
                ],

            'title' =>
                $content['title'],

            'body' =>
                $content['body'],

            'action_url' =>
                $content['action_url'],
        ];
    }

    public function renderContent(
        array $content,
        array $variables = []
    ): array {
        /*
         * Global system identity is authoritative.
         * A caller cannot override brand_name.
         */
        $variables =
            array_merge(
                $variables,
                [
                    'brand_name' =>
                        $this->brandName(),
                ]
            );

        return [
            'title' =>
                $this->expand(
                    (string) (
                        $content[
                            'title_template'
                        ]
                        ?? ''
                    ),
                    $variables
                ),

            'body' =>
                $this->expand(
                    (string) (
                        $content[
                            'body_template'
                        ]
                        ?? ''
                    ),
                    $variables
                ),

            'action_url' =>
                $this->expand(
                    (string) (
                        $content[
                            'action_url_template'
                        ]
                        ?? ''
                    ),
                    $variables
                ),
        ];
    }

    private function assertIdentity(
        string $code,
        string $channel,
        string $locale
    ): void {
        if (
            preg_match(
                '/^[a-z0-9._-]{3,100}$/D',
                $code
            ) !== 1
            || preg_match(
                '/^[a-z0-9._-]{2,40}$/D',
                $channel
            ) !== 1
            || preg_match(
                '/^[a-z]{2}(?:-[a-z0-9]{2,8})?$/D',
                $locale
            ) !== 1
        ) {
            throw new RuntimeException(
                'message_template_identity_invalid'
            );
        }
    }

    private function template(
        string $code,
        string $channel,
        string $locale
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    code,
                    channel_code,
                    locale,
                    title_template,
                    body_template,
                    action_url_template,
                    format_code,
                    version
                FROM notification_templates
                WHERE code = ?
                  AND channel_code = ?
                  AND locale = ?
                  AND is_active = 1
                  AND EXISTS (
                      SELECT 1
                      FROM notification_channels
                      WHERE notification_channels.code =
                          notification_templates.channel_code
                        AND notification_channels.is_active = 1
                  )
                ORDER BY version DESC, id DESC
                LIMIT 1
            ");

        $statement->execute([
            $code,
            $channel,
            $locale,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return is_array($row)
            ? $row
            : null;
    }

    private function brandName(): string
    {
        $statement =
            $this->db->prepare("
                SELECT setting_value
                FROM app_settings
                WHERE user_id = 0
                  AND namespace = 'admin.theme'
                  AND setting_key = 'brand_name'
                LIMIT 1
            ");

        $statement->execute();

        $brand =
            trim(
                (string) (
                    $statement->fetchColumn()
                    ?: ''
                )
            );

        if ($brand === '') {
            throw new RuntimeException(
                'message_template_brand_unavailable'
            );
        }

        return $brand;
    }

    private function expand(
        string $template,
        array $variables
    ): string {
        if ($template === '') {
            return '';
        }

        $result =
            preg_replace_callback(
                '/\{\{\s*([A-Za-z0-9_.-]+)\s*\}\}/',
                function (
                    array $matches
                ) use (
                    $variables
                ): string {
                    $key =
                        (string) (
                            $matches[1]
                            ?? ''
                        );

                    if (
                        $key === ''
                        || !array_key_exists(
                            $key,
                            $variables
                        )
                    ) {
                        throw new RuntimeException(
                            'message_template_variable_missing:'
                            . $key
                        );
                    }

                    $value =
                        $variables[$key];

                    if (
                        is_array($value)
                        || is_object($value)
                        || is_resource($value)
                    ) {
                        throw new RuntimeException(
                            'message_template_variable_invalid:'
                            . $key
                        );
                    }

                    return (string) (
                        $value
                        ?? ''
                    );
                },
                $template
            );

        if (!is_string($result)) {
            throw new RuntimeException(
                'message_template_render_failed'
            );
        }

        return $result;
    }
}
