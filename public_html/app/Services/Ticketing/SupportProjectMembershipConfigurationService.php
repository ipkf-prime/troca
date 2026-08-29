<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use Throwable;

final class SupportProjectMembershipConfigurationService
{
    private PDO $db;


    public function __construct(
        ?ConnectionResolver $resolver = null
    ) {
        $resolver ??=
            new ConnectionResolver();

        $this->db =
            $resolver->resolve(
                'ticketing.primary'
            );
    }


    public function form(
        string $projectReference
    ): ?array {
        $project =
            $this->project(
                $projectReference
            );

        if ($project === null) {
            return null;
        }

        $settings =
            $this->settings(
                (int) $project['id']
            );

        return [
            'project_reference' =>
                (string) $project[
                    'public_reference'
                ],

            'membership_mode' =>
                $settings[
                    'membership_mode'
                ],

            'approval_mode' =>
                $settings[
                    'approval_mode'
                ],

            'invite_enabled' =>
                $settings[
                    'invite_enabled'
                ],

            'form_enabled' =>
                $settings[
                    'form_enabled'
                ],

            'membership_fields' =>
                $this->fields(
                    (int) $project['id']
                ),
        ];
    }


    public function save(
        string $projectReference,
        array $input,
        int $userId
    ): array {
        $project =
            $this->project(
                $projectReference
            );

        if ($project === null) {
            return [
                'ok' => false,
                'not_found' => true,
                'errors' => [],
            ];
        }

        $form =
            $this->normalize(
                $input
            );

        $errors =
            $this->validate(
                $form
            );

        if ($errors !== []) {
            return [
                'ok' => false,
                'not_found' => false,
                'errors' => $errors,
            ];
        }

        $actor =
            'user:' . $userId;

        $this->db->beginTransaction();

        try {
            $this->saveSettings(
                (int) $project['id'],
                $form,
                $actor
            );

            $this->replaceFields(
                (int) $project['id'],
                $form[
                    'membership_fields'
                ],
                $actor,
                (int) $form[
                    'form_enabled'
                ]
            );

            $this->db->commit();

        } catch (Throwable $exception) {

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }

        return [
            'ok' => true,
            'not_found' => false,
            'errors' => [],
        ];
    }


    private function project(
        string $reference
    ): ?array {
        $q = $this->db->prepare("
            SELECT
                id,
                public_reference
            FROM
                ticketing_support_projects
            WHERE public_reference = ?
              AND archived_at IS NULL
            LIMIT 1
        ");

        $q->execute([
            trim($reference),
        ]);

        $row =
            $q->fetch(
                PDO::FETCH_ASSOC
            );

        return
            is_array($row)
                ? $row
                : null;
    }


    private function settings(
        int $projectId
    ): array {
        $q = $this->db->prepare("
            SELECT
                membership_mode,
                approval_mode,
                invite_join_enabled,
                form_enabled
            FROM
                ticketing_support_project_requester_access
            WHERE project_id = ?
            LIMIT 1
        ");

        $q->execute([
            $projectId,
        ]);

        $row =
            $q->fetch(
                PDO::FETCH_ASSOC
            );

        if (!is_array($row)) {
            return [
                'membership_mode' =>
                    'public',

                'approval_mode' =>
                    'manager',

                'invite_enabled' =>
                    1,

                'form_enabled' =>
                    0,
            ];
        }

        return [
            'membership_mode' =>
                in_array(
                    (string) $row[
                        'membership_mode'
                    ],
                    ['public', 'private'],
                    true
                )
                    ? (string) $row[
                        'membership_mode'
                    ]
                    : 'public',

            'approval_mode' =>
                in_array(
                    (string) $row[
                        'approval_mode'
                    ],
                    ['auto', 'manager'],
                    true
                )
                    ? (string) $row[
                        'approval_mode'
                    ]
                    : 'manager',

            'invite_enabled' =>
                (int) $row[
                    'invite_join_enabled'
                ],

            'form_enabled' =>
                (int) $row[
                    'form_enabled'
                ],
        ];
    }


    private function fields(
        int $projectId
    ): array {
        $q = $this->db->prepare("
            SELECT
                public_reference,
                field_key,
                title,
                field_type,
                data_source_key,
                options_json,
                dependency_field_key,
                is_required,
                sort_order
            FROM
                ticketing_support_project_membership_fields
            WHERE project_id = ?
              AND is_active = 1
            ORDER BY
                sort_order,
                id
        ");

        $q->execute([
            $projectId,
        ]);

        $rows =
            $q->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        foreach ($rows as &$row) {

            $decoded =
                json_decode(
                    (string) (
                        $row[
                            'options_json'
                        ]
                        ?? ''
                    ),
                    true
                );

            $row['options'] =
                is_array($decoded)
                    ? $decoded
                    : [];
        }

        unset($row);

        return $rows;
    }


    private function normalize(
        array $input
    ): array {
        $membershipMode =
            strtolower(
                trim(
                    (string) (
                        $input[
                            'membership_mode'
                        ]
                        ?? 'public'
                    )
                )
            );

        if (
            !in_array(
                $membershipMode,
                ['public', 'private'],
                true
            )
        ) {
            $membershipMode =
                'public';
        }


        $approvalMode =
            strtolower(
                trim(
                    (string) (
                        $input[
                            'approval_mode'
                        ]
                        ?? 'manager'
                    )
                )
            );

        if (
            !in_array(
                $approvalMode,
                ['auto', 'manager'],
                true
            )
        ) {
            $approvalMode =
                'manager';
        }


        return [
            'membership_mode' =>
                $membershipMode,

            'approval_mode' =>
                $approvalMode,

            'invite_enabled' =>
                !empty(
                    $input[
                        'invite_enabled'
                    ]
                )
                    ? 1
                    : 0,

            'form_enabled' =>
                !empty(
                    $input[
                        'form_enabled'
                    ]
                )
                    ? 1
                    : 0,

            'membership_fields' =>
                $this->normalizeFields(
                    $input[
                        'membership_fields'
                    ]
                    ?? []
                ),
        ];
    }


    private function normalizeFields(
        mixed $input
    ): array {
        if (!is_array($input)) {
            return [];
        }

        $allowedTypes = [
            'text',
            'textarea',
            'number',
            'date',
            'select',
            'multiselect',
            'boolean',
            'file',
            'lookup',
        ];

        $result = [];

        foreach (
            $input
            as $index => $row
        ) {
            if (!is_array($row)) {
                continue;
            }

            $title =
                trim(
                    (string) (
                        $row['title']
                        ?? ''
                    )
                );

            $key =
                strtolower(
                    trim(
                        (string) (
                            $row['field_key']
                            ?? ''
                        )
                    )
                );

            if (
                $title === ''
                &&
                $key === ''
            ) {
                continue;
            }

            $type =
                strtolower(
                    trim(
                        (string) (
                            $row['field_type']
                            ?? 'text'
                        )
                    )
                );

            if (
                !in_array(
                    $type,
                    $allowedTypes,
                    true
                )
            ) {
                $type = 'text';
            }

            $optionsText =
                trim(
                    (string) (
                        $row['options_text']
                        ?? ''
                    )
                );

            if (
                $optionsText === ''
                &&
                is_array(
                    $row['options']
                    ?? null
                )
            ) {
                $optionsText =
                    implode(
                        PHP_EOL,
                        array_map(
                            'strval',
                            $row['options']
                        )
                    );
            }

            $options = [];

            foreach (
                preg_split(
                    '/\R/u',
                    $optionsText
                ) ?: []
                as $option
            ) {
                $option =
                    trim(
                        (string) $option
                    );

                if ($option !== '') {
                    $options[] =
                        mb_substr(
                            $option,
                            0,
                            255
                        );
                }
            }

            $result[] = [
                'title' =>
                    mb_substr(
                        $title,
                        0,
                        255
                    ),

                'field_key' =>
                    mb_substr(
                        $key,
                        0,
                        100
                    ),

                'field_type' =>
                    $type,

                'data_source_key' =>
                    mb_substr(
                        trim(
                            (string) (
                                $row[
                                    'data_source_key'
                                ]
                                ?? ''
                            )
                        ),
                        0,
                        190
                    ),

                'options' =>
                    array_values(
                        array_unique(
                            $options
                        )
                    ),

                'dependency_field_key' =>
                    mb_substr(
                        strtolower(
                            trim(
                                (string) (
                                    $row[
                                        'dependency_field_key'
                                    ]
                                    ?? ''
                                )
                            )
                        ),
                        0,
                        100
                    ),

                'is_required' =>
                    !empty(
                        $row[
                            'is_required'
                        ]
                    )
                        ? 1
                        : 0,

                'sort_order' =>
                    max(
                        0,
                        min(
                            100000,
                            (int) (
                                $row[
                                    'sort_order'
                                ]
                                ?? (
                                    (
                                        (int) $index
                                        + 1
                                    )
                                    * 10
                                )
                            )
                        )
                    ),
            ];
        }

        usort(
            $result,
            static fn (
                array $a,
                array $b
            ): int =>
                $a['sort_order']
                <=>
                $b['sort_order']
        );

        return $result;
    }


    private function validate(
        array $form
    ): array {
        $errors = [];

        if (
            (int) $form[
                'form_enabled'
            ] === 1
            &&
            $form[
                'membership_fields'
            ] === []
        ) {
            $errors[
                'membership_fields'
            ] =
                'برای فرم تکمیلی حداقل یک فیلد تعریف کنید.';
        }

        $keys = [];

        foreach (
            $form[
                'membership_fields'
            ]
            as $field
        ) {
            if (
                trim(
                    (string) $field['title']
                ) === ''
            ) {
                $errors[
                    'membership_fields'
                ] =
                    'عنوان تمام فیلدها الزامی است.';

                break;
            }

            if (
                preg_match(
                    '/^[a-z][a-z0-9_.-]{1,99}$/',
                    (string) $field[
                        'field_key'
                    ]
                ) !== 1
            ) {
                $errors[
                    'membership_fields'
                ] =
                    'کلید فیلد باید با حرف انگلیسی شروع شود.';
                break;
            }

            if (
                isset(
                    $keys[
                        $field[
                            'field_key'
                        ]
                    ]
                )
            ) {
                $errors[
                    'membership_fields'
                ] =
                    'کلید فیلدها باید یکتا باشد.';

                break;
            }

            $keys[
                $field[
                    'field_key'
                ]
            ] =
                true;
        }

        return $errors;
    }


    private function saveSettings(
        int $projectId,
        array $form,
        string $actor
    ): void {
        $selfJoinEnabled =
            (
                $form[
                    'membership_mode'
                ] === 'public'
                &&
                $form[
                    'approval_mode'
                ] === 'auto'
            )
                ? 1
                : 0;

        $q = $this->db->prepare("
            INSERT INTO
                ticketing_support_project_requester_access
            (
                project_id,
                self_join_enabled,
                invite_join_enabled,
                membership_mode,
                approval_mode,
                form_enabled,
                created_by_user_reference,
                updated_by_user_reference,
                created_at,
                updated_at
            )
            VALUES
            (
                :project_id,
                :self_join_enabled,
                :invite_enabled,
                :membership_mode,
                :approval_mode,
                :form_enabled,
                :actor,
                :actor,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )

            ON DUPLICATE KEY UPDATE

                self_join_enabled =
                    VALUES(
                        self_join_enabled
                    ),

                invite_join_enabled =
                    VALUES(
                        invite_join_enabled
                    ),

                membership_mode =
                    VALUES(
                        membership_mode
                    ),

                approval_mode =
                    VALUES(
                        approval_mode
                    ),

                form_enabled =
                    VALUES(
                        form_enabled
                    ),

                updated_by_user_reference =
                    VALUES(
                        updated_by_user_reference
                    ),

                updated_at =
                    UTC_TIMESTAMP()
        ");

        $q->execute([
            'project_id' =>
                $projectId,

            'self_join_enabled' =>
                $selfJoinEnabled,

            'invite_enabled' =>
                $form[
                    'invite_enabled'
                ],

            'membership_mode' =>
                $form[
                    'membership_mode'
                ],

            'approval_mode' =>
                $form[
                    'approval_mode'
                ],

            'form_enabled' =>
                $form[
                    'form_enabled'
                ],

            'actor' =>
                $actor,
        ]);
    }


    private function replaceFields(
        int $projectId,
        array $fields,
        string $actor,
        int $formEnabled
    ): void {
        $delete =
            $this->db->prepare("
                DELETE FROM
                    ticketing_support_project_membership_fields
                WHERE project_id = ?
            ");

        $delete->execute([
            $projectId,
        ]);

        if ($formEnabled !== 1) {
            return;
        }

        $insert =
            $this->db->prepare("
                INSERT INTO
                    ticketing_support_project_membership_fields
                (
                    public_reference,
                    project_id,
                    field_key,
                    title,
                    field_type,
                    data_source_key,
                    options_json,
                    dependency_field_key,
                    validation_json,
                    is_required,
                    sort_order,
                    is_active,
                    created_by_user_reference,
                    updated_by_user_reference,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    :public_reference,
                    :project_id,
                    :field_key,
                    :title,
                    :field_type,
                    :data_source_key,
                    :options_json,
                    :dependency_field_key,
                    NULL,
                    :is_required,
                    :sort_order,
                    1,
                    :actor,
                    :actor,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )
            ");

        foreach ($fields as $field) {

            $insert->execute([
                'public_reference' =>
                    'TMF-'
                    . strtoupper(
                        bin2hex(
                            random_bytes(10)
                        )
                    ),

                'project_id' =>
                    $projectId,

                'field_key' =>
                    $field[
                        'field_key'
                    ],

                'title' =>
                    $field[
                        'title'
                    ],

                'field_type' =>
                    $field[
                        'field_type'
                    ],

                'data_source_key' =>
                    $field[
                        'data_source_key'
                    ] !== ''
                        ? $field[
                            'data_source_key'
                        ]
                        : null,

                'options_json' =>
                    $field['options'] !== []
                        ? json_encode(
                            $field['options'],
                            JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES
                        )
                        : null,

                'dependency_field_key' =>
                    $field[
                        'dependency_field_key'
                    ] !== ''
                        ? $field[
                            'dependency_field_key'
                        ]
                        : null,

                'is_required' =>
                    $field[
                        'is_required'
                    ],

                'sort_order' =>
                    $field[
                        'sort_order'
                    ],

                'actor' =>
                    $actor,
            ]);
        }
    }
}
