<?php

declare(strict_types=1);

namespace App\Services;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;
use Throwable;

final class NotificationTemplateManagementService extends BaseService
{
    private PDO $db;

    private DynamicMessageTemplateService $renderer;

    public function __construct(
        ?PDO $db = null,
        ?DynamicMessageTemplateService $renderer = null
    ) {
        $this->db =
            $db
            ?? (
                new ConnectionResolver()
            )->resolve('core.primary');

        $this->renderer =
            $renderer
            ?? new DynamicMessageTemplateService(
                $this->db
            );
    }

    public function page(
        array $filters = []
    ): array {
        $q =
            trim(
                (string) (
                    $filters['q']
                    ?? ''
                )
            );

        $channel =
            strtolower(
                trim(
                    (string) (
                        $filters['channel']
                        ?? ''
                    )
                )
            );

        $status =
            strtolower(
                trim(
                    (string) (
                        $filters['status']
                        ?? ''
                    )
                )
            );

        $selectedCode =
            strtolower(
                trim(
                    (string) (
                        $filters['code']
                        ?? ''
                    )
                )
            );

        $selectedChannel =
            strtolower(
                trim(
                    (string) (
                        $filters[
                            'selected_channel'
                        ]
                        ?? ''
                    )
                )
            );

        $selectedLocale =
            strtolower(
                trim(
                    (string) (
                        $filters['locale']
                        ?? 'fa'
                    )
                )
            );

        $definitions =
            $this->definitions(
                $q,
                $channel,
                $status
            );

        $selected = null;

        if (
            $selectedCode !== ''
            && $selectedChannel !== ''
        ) {
            $selected =
                $this->definition(
                    $selectedCode,
                    $selectedChannel,
                    $selectedLocale
                );
        }

        if (
            $selected === null
            && $definitions !== []
        ) {
            $first =
                $definitions[0];

            $selected =
                $this->definition(
                    (string) $first['code'],
                    (string) $first[
                        'channel_code'
                    ],
                    (string) $first['locale']
                );
        }

        $history = [];
        $audit = [];

        if (is_array($selected)) {
            $history =
                $this->history(
                    (string) $selected['code'],
                    (string) $selected[
                        'channel_code'
                    ],
                    (string) $selected['locale']
                );

            $audit =
                $this->audit(
                    (string) $selected['code'],
                    (string) $selected[
                        'channel_code'
                    ],
                    (string) $selected['locale']
                );
        }

        return [
            'filters' => [
                'q' => $q,
                'channel' => $channel,
                'status' => $status,
            ],

            'channels' =>
                $this->channels(),

            'items' =>
                $definitions,

            'selected' =>
                $selected,

            'history' =>
                $history,

            'audit' =>
                $audit,
        ];
    }

    public function saveVersion(
        int $actorUserId,
        array $input
    ): array {
        if ($actorUserId < 1) {
            throw new RuntimeException(
                'message_template_actor_invalid'
            );
        }

        [
            $code,
            $channel,
            $locale,
        ] = $this->identity($input);

        $definition =
            $this->definition(
                $code,
                $channel,
                $locale
            );

        if ($definition === null) {
            throw new RuntimeException(
                'message_template_definition_missing'
            );
        }

        $content =
            $this->content($input);

        $this->validateContent(
            $definition,
            $content
        );

        $active =
            (string) (
                $input['is_active']
                ?? ''
            ) === '1';

        $lockName =
            'troca.template.'
            . substr(
                hash(
                    'sha256',
                    implode(
                        '|',
                        [
                            $code,
                            $channel,
                            $locale,
                        ]
                    )
                ),
                0,
                40
            );

        if (!$this->acquireLock(
            $lockName
        )) {
            throw new RuntimeException(
                'message_template_busy'
            );
        }

        try {
            $this->db
                ->beginTransaction();

            $current =
                $this->latestTemplate(
                    $code,
                    $channel,
                    $locale,
                    true
                );

            $previousVersion =
                is_array($current)
                    ? (int) $current[
                        'version'
                    ]
                    : null;

            $newVersion =
                ($previousVersion ?? 0)
                + 1;

            $statement =
                $this->db->prepare("
                    UPDATE notification_templates
                    SET is_active = 0,
                        updated_at =
                            CURRENT_TIMESTAMP
                    WHERE code = ?
                      AND channel_code = ?
                      AND locale = ?
                ");

            $statement->execute([
                $code,
                $channel,
                $locale,
            ]);

            $format =
                trim(
                    (string) (
                        $current[
                            'format_code'
                        ]
                        ?? 'plain'
                    )
                )
                ?: 'plain';

            $statement =
                $this->db->prepare("
                    INSERT INTO
                        notification_templates (
                            code,
                            event_type,
                            channel_code,
                            locale,
                            title_template,
                            body_template,
                            action_url_template,
                            format_code,
                            version,
                            is_active,
                            created_at,
                            updated_at
                        )
                    VALUES (
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
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    )
                ");

            $statement->execute([
                $code,
                (string) $definition[
                    'event_type'
                ],
                $channel,
                $locale,
                $content[
                    'title_template'
                ] !== ''
                    ? $content[
                        'title_template'
                    ]
                    : null,
                $content[
                    'body_template'
                ],
                $content[
                    'action_url_template'
                ] !== ''
                    ? $content[
                        'action_url_template'
                    ]
                    : null,
                $format,
                $newVersion,
                $active ? 1 : 0,
            ]);

            $snapshot = [
                'previous' =>
                    $current,

                'new' => [
                    'version' =>
                        $newVersion,

                    'is_active' =>
                        $active,

                    'title_template' =>
                        $content[
                            'title_template'
                        ],

                    'body_template' =>
                        $content[
                            'body_template'
                        ],

                    'action_url_template' =>
                        $content[
                            'action_url_template'
                        ],
                ],
            ];

            $statement =
                $this->db->prepare("
                    INSERT INTO
                        notification_template_change_log (
                            code,
                            channel_code,
                            locale,
                            action_code,
                            previous_version,
                            new_version,
                            actor_user_id,
                            snapshot_json,
                            created_at
                        )
                    VALUES (
                        ?,
                        ?,
                        ?,
                        'version_saved',
                        ?,
                        ?,
                        ?,
                        ?,
                        CURRENT_TIMESTAMP
                    )
                ");

            $statement->execute([
                $code,
                $channel,
                $locale,
                $previousVersion,
                $newVersion,
                $actorUserId,
                json_encode(
                    $snapshot,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                ),
            ]);

            $this->db->commit();

            return [
                'ok' => true,
                'version' =>
                    $newVersion,
                'active' =>
                    $active,
                'code' =>
                    $code,
                'channel' =>
                    $channel,
                'locale' =>
                    $locale,
            ];
        } catch (Throwable $exception) {
            if (
                $this->db
                    ->inTransaction()
            ) {
                $this->db
                    ->rollBack();
            }

            throw $exception;
        } finally {
            $this->releaseLock(
                $lockName
            );
        }
    }

    public function previewDraft(
        array $input
    ): array {
        [
            $code,
            $channel,
            $locale,
        ] = $this->identity($input);

        $definition =
            $this->definition(
                $code,
                $channel,
                $locale
            );

        if ($definition === null) {
            throw new RuntimeException(
                'message_template_definition_missing'
            );
        }

        $content =
            $this->content($input);

        $this->validateContent(
            $definition,
            $content
        );

        $rendered =
            $this->renderer
                ->renderContent(
                    $content,
                    (array) $definition[
                        'sample_variables'
                    ]
                );

        return [
            'definition' =>
                $definition,

            'content' =>
                $content,

            'rendered' =>
                $rendered,
        ];
    }

    public function testSend(
        int $actorUserId,
        array $input
    ): array {
        if ($actorUserId < 1) {
            throw new RuntimeException(
                'message_template_actor_invalid'
            );
        }

        [
            $code,
            $channel,
            $locale,
        ] = $this->identity($input);

        $definition =
            $this->definition(
                $code,
                $channel,
                $locale
            );

        if ($definition === null) {
            throw new RuntimeException(
                'message_template_definition_missing'
            );
        }

        $template =
            $this->latestTemplate(
                $code,
                $channel,
                $locale
            );

        if (
            !is_array($template)
            || (int) (
                $template['is_active']
                ?? 0
            ) !== 1
        ) {
            throw new RuntimeException(
                'message_template_inactive'
            );
        }

        $destination =
            trim(
                (string) (
                    $input['destination']
                    ?? ''
                )
            );

        if ($destination === '') {
            throw new RuntimeException(
                'message_template_test_destination_required'
            );
        }

        $gatewayChannel =
            match ($channel) {
                'sms' => 'sms',
                'email' => 'email',
                'messenger',
                'bale' => 'messenger',
                default => null,
            };

        if ($gatewayChannel === null) {
            throw new RuntimeException(
                'message_template_test_channel_unsupported'
            );
        }

        $content = [
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
        ];

        $this->validateContent(
            $definition,
            $content
        );

        $rendered =
            $this->renderer
                ->renderContent(
                    $content,
                    (array) $definition[
                        'sample_variables'
                    ]
                );

        $result =
            (
                new NotificationGatewayService()
            )->sendDirect(
                $actorUserId,
                [
                    'channel_code' =>
                        $gatewayChannel,

                    'purpose_code' =>
                        'template_test',

                    'scope_type' =>
                        'global',

                    'scope_reference' =>
                        '*',

                    'destination' =>
                        $destination,

                    'subject' =>
                        (string) $rendered[
                            'title'
                        ],

                    'body' =>
                        (string) $rendered[
                            'body'
                        ],

                    'recipient_user_reference' =>
                        'template-test:'
                        . $actorUserId,
                ]
            );

        return [
            'ok' => true,
            'gateway_result' =>
                $result,
        ];
    }

    private function definitions(
        string $q,
        string $channel,
        string $status
    ): array {
        $where = [
            'definitions.is_active = 1',
        ];

        $params = [];

        if ($q !== '') {
            $where[] = "(
                definitions.display_title
                    LIKE ?
                OR definitions.description
                    LIKE ?
                OR definitions.code
                    LIKE ?
                OR definitions.event_type
                    LIKE ?
            )";

            $like =
                '%' . $q . '%';

            array_push(
                $params,
                $like,
                $like,
                $like,
                $like
            );
        }

        if ($channel !== '') {
            $where[] =
                'definitions.channel_code = ?';

            $params[] =
                $channel;
        }

        $statement =
            $this->db->prepare("
                SELECT
                    definitions.*,
                    channels.title
                        AS channel_title,
                    channels.is_active
                        AS channel_active,
                    channels.supports_subject
                        AS channel_supports_subject
                FROM notification_template_definitions
                    AS definitions
                LEFT JOIN notification_channels
                    AS channels
                  ON channels.code =
                      definitions.channel_code
                WHERE "
                . implode(
                    ' AND ',
                    $where
                )
                . "
                ORDER BY
                    definitions.sort_order,
                    definitions.id
            ");

        $statement->execute(
            $params
        );

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            );

        $result = [];

        foreach ($rows as $row) {
            $latest =
                $this->latestTemplate(
                    (string) $row['code'],
                    (string) $row[
                        'channel_code'
                    ],
                    (string) $row['locale']
                );

            $row['latest'] =
                $latest;

            $latestActive =
                is_array($latest)
                && (int) (
                    $latest['is_active']
                    ?? 0
                ) === 1;

            if (
                $status === 'active'
                && !$latestActive
            ) {
                continue;
            }

            if (
                $status === 'inactive'
                && $latestActive
            ) {
                continue;
            }

            $result[] =
                $row;
        }

        return $result;
    }

    private function definition(
        string $code,
        string $channel,
        string $locale
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    definitions.*,
                    channels.title
                        AS channel_title,
                    channels.is_active
                        AS channel_active,
                    channels.supports_subject
                        AS channel_supports_subject
                FROM notification_template_definitions
                    AS definitions
                LEFT JOIN notification_channels
                    AS channels
                  ON channels.code =
                      definitions.channel_code
                WHERE definitions.code = ?
                  AND definitions.channel_code = ?
                  AND definitions.locale = ?
                  AND definitions.is_active = 1
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

        if (!is_array($row)) {
            return null;
        }

        $row['allowed_variables'] =
            $this->decodeList(
                (string) $row[
                    'allowed_variables_json'
                ]
            );

        $row['sample_variables'] =
            $this->decodeMap(
                (string) $row[
                    'sample_variables_json'
                ]
            );

        $row['latest'] =
            $this->latestTemplate(
                $code,
                $channel,
                $locale
            );

        return $row;
    }

    private function latestTemplate(
        string $code,
        string $channel,
        string $locale,
        bool $forUpdate = false
    ): ?array {
        $sql = "
            SELECT
                id,
                code,
                event_type,
                channel_code,
                locale,
                title_template,
                body_template,
                action_url_template,
                format_code,
                version,
                is_active,
                created_at,
                updated_at
            FROM notification_templates
            WHERE code = ?
              AND channel_code = ?
              AND locale = ?
            ORDER BY version DESC, id DESC
            LIMIT 1
        ";

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $statement =
            $this->db->prepare(
                $sql
            );

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

    private function history(
        string $code,
        string $channel,
        string $locale
    ): array {
        $statement =
            $this->db->prepare("
                SELECT
                    version,
                    is_active,
                    title_template,
                    created_at,
                    updated_at
                FROM notification_templates
                WHERE code = ?
                  AND channel_code = ?
                  AND locale = ?
                ORDER BY version DESC, id DESC
                LIMIT 30
            ");

        $statement->execute([
            $code,
            $channel,
            $locale,
        ]);

        return $statement->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    private function audit(
        string $code,
        string $channel,
        string $locale
    ): array {
        $statement =
            $this->db->prepare("
                SELECT
                    action_code,
                    previous_version,
                    new_version,
                    actor_user_id,
                    created_at
                FROM notification_template_change_log
                WHERE code = ?
                  AND channel_code = ?
                  AND locale = ?
                ORDER BY id DESC
                LIMIT 30
            ");

        $statement->execute([
            $code,
            $channel,
            $locale,
        ]);

        return $statement->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    private function channels(): array
    {
        $statement =
            $this->db->query("
                SELECT
                    code,
                    title,
                    is_internal,
                    supports_subject,
                    is_active
                FROM notification_channels
                ORDER BY sort_order, id
            ");

        return $statement->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    private function identity(
        array $input
    ): array {
        $code =
            strtolower(
                trim(
                    (string) (
                        $input['code']
                        ?? ''
                    )
                )
            );

        $channel =
            strtolower(
                trim(
                    (string) (
                        $input['channel_code']
                        ?? ''
                    )
                )
            );

        $locale =
            strtolower(
                trim(
                    (string) (
                        $input['locale']
                        ?? 'fa'
                    )
                )
            );

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

        return [
            $code,
            $channel,
            $locale,
        ];
    }

    private function content(
        array $input
    ): array {
        return [
            'title_template' =>
                trim(
                    (string) (
                        $input[
                            'title_template'
                        ]
                        ?? ''
                    )
                ),

            'body_template' =>
                trim(
                    (string) (
                        $input[
                            'body_template'
                        ]
                        ?? ''
                    )
                ),

            'action_url_template' =>
                trim(
                    (string) (
                        $input[
                            'action_url_template'
                        ]
                        ?? ''
                    )
                ),
        ];
    }

    private function validateContent(
        array $definition,
        array $content
    ): void {
        $body =
            (string) $content[
                'body_template'
            ];

        $title =
            (string) $content[
                'title_template'
            ];

        $action =
            (string) $content[
                'action_url_template'
            ];

        if ($body === '') {
            throw new RuntimeException(
                'message_template_body_required'
            );
        }

        if (
            mb_strlen(
                $body,
                'UTF-8'
            ) > 20000
        ) {
            throw new RuntimeException(
                'message_template_body_too_long'
            );
        }

        if (
            mb_strlen(
                $title,
                'UTF-8'
            ) > 500
        ) {
            throw new RuntimeException(
                'message_template_title_too_long'
            );
        }

        if (
            mb_strlen(
                $action,
                'UTF-8'
            ) > 1000
        ) {
            throw new RuntimeException(
                'message_template_action_url_too_long'
            );
        }

        if (
            (string) $definition[
                'channel_code'
            ] === 'email'
            && $title === ''
        ) {
            throw new RuntimeException(
                'message_template_email_title_required'
            );
        }

        $allowed =
            array_values(
                array_unique(
                    array_map(
                        'strval',
                        (array) (
                            $definition[
                                'allowed_variables'
                            ]
                            ?? []
                        )
                    )
                )
            );

        foreach (
            [
                $title,
                $body,
                $action,
            ] as $value
        ) {
            $variables =
                $this->variables(
                    $value
                );

            foreach (
                $variables
                as $variable
            ) {
                if (!in_array(
                    $variable,
                    $allowed,
                    true
                )) {
                    throw new RuntimeException(
                        'message_template_unknown_variable:'
                        . $variable
                    );
                }
            }
        }
    }

    private function variables(
        string $template
    ): array {
        if ($template === '') {
            return [];
        }

        preg_match_all(
            '/\{\{\s*([A-Za-z0-9_.-]+)\s*\}\}/',
            $template,
            $matches
        );

        $clean =
            preg_replace(
                '/\{\{\s*[A-Za-z0-9_.-]+\s*\}\}/',
                '',
                $template
            );

        if (
            is_string($clean)
            && (
                str_contains(
                    $clean,
                    '{{'
                )
                || str_contains(
                    $clean,
                    '}}'
                )
            )
        ) {
            throw new RuntimeException(
                'message_template_variable_syntax_invalid'
            );
        }

        return array_values(
            array_unique(
                array_map(
                    'strval',
                    $matches[1]
                    ?? []
                )
            )
        );
    }

    private function decodeList(
        string $json
    ): array {
        $value =
            json_decode(
                $json,
                true
            );

        if (!is_array($value)) {
            return [];
        }

        return array_values(
            array_unique(
                array_filter(
                    array_map(
                        'strval',
                        $value
                    ),
                    static fn (
                        string $item
                    ): bool =>
                        trim($item) !== ''
                )
            )
        );
    }

    private function decodeMap(
        string $json
    ): array {
        $value =
            json_decode(
                $json,
                true
            );

        return is_array($value)
            ? $value
            : [];
    }

    private function acquireLock(
        string $name
    ): bool {
        $statement =
            $this->db->prepare(
                'SELECT GET_LOCK(?, 5)'
            );

        $statement->execute([
            $name,
        ]);

        return
            (int) $statement
                ->fetchColumn()
            === 1;
    }

    private function releaseLock(
        string $name
    ): void {
        try {
            $statement =
                $this->db->prepare(
                    'SELECT RELEASE_LOCK(?)'
                );

            $statement->execute([
                $name,
            ]);
        } catch (Throwable) {
        }
    }
}
