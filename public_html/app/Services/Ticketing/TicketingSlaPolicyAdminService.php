<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use App\Repositories\TicketingSlaPolicyAdminRepository;

final class TicketingSlaPolicyAdminService
{
    public function __construct(
        private ?TicketingSlaPolicyAdminRepository $policies = null
    ) {
        $this->policies ??=
            new TicketingSlaPolicyAdminRepository();
    }


    public function page(
        array $form = [],
        string $copyReference = ''
    ): array {
        $data =
            $this->policies
                ->pageData();

        $defaults = [
            'scope_type' => 'global',

            'project_id' => '',
            'service_id' => '',
            'topic_id' => '',
            'queue_id' => '',

            'priority_code' => 'normal',

            'calendar_id' =>
                isset(
                    $data['calendars'][0]['id']
                )
                    ? (int) $data[
                        'calendars'
                    ][0]['id']
                    : '',

            'title' => '',

            'response_minutes' => '',
            'resolution_minutes' => '',

            'pause_statuses' => [
                'waiting_requester',
            ],

            'auto_escalate' => 1,

            'max_auto_escalations' => 3,

            'escalation_repeat_minutes' => 60,

            'sort_order' => 100,
        ];


        $copyReference =
            trim(
                $copyReference
            );


        if ($copyReference !== '') {

            $policy =
                $this->policies
                    ->policyByReference(
                        $copyReference
                    );

            if (is_array($policy)) {

                $pauseStatuses =
                    json_decode(
                        (string) (
                            $policy[
                                'pause_statuses_json'
                            ]
                            ?? '[]'
                        ),
                        true
                    );

                if (
                    !is_array(
                        $pauseStatuses
                    )
                ) {
                    $pauseStatuses = [];
                }


                $defaults = [
                    'scope_type' =>
                        $this->scopeType(
                            $policy
                        ),

                    'project_id' =>
                        $policy[
                            'project_id'
                        ]
                        ?? '',

                    'service_id' =>
                        $policy[
                            'service_id'
                        ]
                        ?? '',

                    'topic_id' =>
                        $policy[
                            'topic_id'
                        ]
                        ?? '',

                    'queue_id' =>
                        $policy[
                            'queue_id'
                        ]
                        ?? '',

                    'priority_code' =>
                        $policy[
                            'priority_code'
                        ]
                        ?? 'normal',

                    'calendar_id' =>
                        $policy[
                            'calendar_id'
                        ]
                        ?? '',

                    'title' =>
                        $policy[
                            'title'
                        ]
                        ?? '',

                    'response_minutes' =>
                        $policy[
                            'response_minutes'
                        ]
                        ?? '',

                    'resolution_minutes' =>
                        $policy[
                            'resolution_minutes'
                        ]
                        ?? '',

                    'pause_statuses' =>
                        array_values(
                            array_filter(
                                $pauseStatuses,
                                'is_string'
                            )
                        ),

                    'auto_escalate' =>
                        (
                            $policy[
                                'breach_action_code'
                            ]
                            ?? ''
                        ) === 'escalate'
                            ? 1
                            : 0,

                    'max_auto_escalations' =>
                        (int) (
                            $policy[
                                'max_auto_escalations'
                            ]
                            ?? 3
                        ),

                    'escalation_repeat_minutes' =>
                        (int) (
                            $policy[
                                'escalation_repeat_minutes'
                            ]
                            ?? 60
                        ),

                    'sort_order' =>
                        (int) (
                            $policy[
                                'sort_order'
                            ]
                            ?? 100
                        ),
                ];
            }
        }


        $data['form'] =
            array_merge(
                $defaults,
                $form
            );


        return $data;
    }


    public function save(
        array $input,
        int $userId
    ): array {
        $form =
            $this->normalize(
                $input
            );

        $errors = [];


        $scope =
            $this->resolveScope(
                $form,
                $errors
            );


        $priority =
            $this->policies
                ->priority(
                    $form[
                        'priority_code'
                    ]
                );

        if (!is_array($priority)) {
            $errors['priority_code'] =
                'اولویت انتخاب‌شده معتبر نیست.';
        }


        $calendar =
            $this->policies
                ->calendar(
                    (int) $form[
                        'calendar_id'
                    ]
                );

        if (!is_array($calendar)) {
            $errors['calendar_id'] =
                'تقویم کاری انتخاب‌شده معتبر نیست.';
        }


        if (
            $this->length(
                $form['title']
            ) < 2
        ) {
            $errors['title'] =
                'عنوان سیاست SLA باید حداقل ۲ نویسه باشد.';
        }


        if (
            $form[
                'response_minutes'
            ] < 1
        ) {
            $errors['response_minutes'] =
                'زمان پاسخ باید حداقل یک دقیقه باشد.';
        }


        if (
            $form[
                'resolution_minutes'
            ] < 1
        ) {
            $errors['resolution_minutes'] =
                'زمان حل باید حداقل یک دقیقه باشد.';
        }


        if (
            $form[
                'response_minutes'
            ] > 0
            &&
            $form[
                'resolution_minutes'
            ]
            <
            $form[
                'response_minutes'
            ]
        ) {
            $errors['resolution_minutes'] =
                'زمان حل نباید از زمان پاسخ کمتر باشد.';
        }


        if (
            $form[
                'max_auto_escalations'
            ] < 0
            ||
            $form[
                'max_auto_escalations'
            ] > 50
        ) {
            $errors[
                'max_auto_escalations'
            ] =
                'تعداد ارجاع خودکار باید بین صفر تا ۵۰ باشد.';
        }


        if (
            $form[
                'escalation_repeat_minutes'
            ] < 1
            ||
            $form[
                'escalation_repeat_minutes'
            ] > 10080
        ) {
            $errors[
                'escalation_repeat_minutes'
            ] =
                'فاصله ارجاع خودکار باید بین ۱ دقیقه تا ۷ روز باشد.';
        }


        $statusRows =
            $this->policies
                ->statuses();

        $validStatuses = [];

        foreach ($statusRows as $row) {

            $code =
                trim(
                    (string) (
                        $row['code']
                        ?? ''
                    )
                );

            if ($code !== '') {
                $validStatuses[
                    $code
                ] = true;
            }
        }


        $pauseStatuses = [];

        foreach (
            $form[
                'pause_statuses'
            ]
            as $status
        ) {
            if (
                isset(
                    $validStatuses[
                        $status
                    ]
                )
            ) {
                $pauseStatuses[] =
                    $status;
            }
        }

        $pauseStatuses =
            array_values(
                array_unique(
                    $pauseStatuses
                )
            );

        $form[
            'pause_statuses'
        ] = $pauseStatuses;


        if ($errors !== []) {

            return [
                'ok' => false,

                'errors' => $errors,

                'form' => $form,
            ];
        }


        $publicReference =
            'TSLP-'
            . strtoupper(
                bin2hex(
                    random_bytes(10)
                )
            );


        $scopeKey =
            $this->scopeKey(
                $scope,
                $form[
                    'priority_code'
                ]
            );


        $metadata = [
            'managed_by' =>
                'ticketing-sla-admin-ui-v1',

            'actor_reference' =>
                'user:'
                . max(
                    0,
                    $userId
                ),

            'scope_type' =>
                $scope[
                    'scope_type'
                ],

            'created_at_utc' =>
                gmdate('c'),
        ];


        $created =
            $this->policies
                ->createVersion([
                    'public_reference' =>
                        $publicReference,

                    'scope_key' =>
                        $scopeKey,

                    'project_id' =>
                        $scope[
                            'project_id'
                        ],

                    'service_id' =>
                        $scope[
                            'service_id'
                        ],

                    'topic_id' =>
                        $scope[
                            'topic_id'
                        ],

                    'queue_id' =>
                        $scope[
                            'queue_id'
                        ],

                    'priority_code' =>
                        $form[
                            'priority_code'
                        ],

                    'calendar_id' =>
                        (int) $form[
                            'calendar_id'
                        ],

                    'title' =>
                        $form['title'],

                    'response_minutes' =>
                        $form[
                            'response_minutes'
                        ],

                    'resolution_minutes' =>
                        $form[
                            'resolution_minutes'
                        ],

                    'pause_statuses_json' =>
                        json_encode(
                            $pauseStatuses,
                            JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES
                            | JSON_THROW_ON_ERROR
                        ),

                    'breach_action_code' =>
                        $form[
                            'auto_escalate'
                        ]
                            ? 'escalate'
                            : 'none',

                    'max_auto_escalations' =>
                        $form[
                            'max_auto_escalations'
                        ],

                    'escalation_repeat_minutes' =>
                        $form[
                            'escalation_repeat_minutes'
                        ],

                    'sort_order' =>
                        $form[
                            'sort_order'
                        ],

                    'metadata_json' =>
                        json_encode(
                            $metadata,
                            JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES
                            | JSON_THROW_ON_ERROR
                        ),
                ]);


        return [
            'ok' => true,

            'public_reference' =>
                $created[
                    'public_reference'
                ],
        ];
    }


    public function disable(
        string $reference
    ): bool {
        return
            $this->policies
                ->disable(
                    trim($reference)
                );
    }


    private function normalize(
        array $input
    ): array {
        $scopeType =
            strtolower(
                trim(
                    (string) (
                        $input[
                            'scope_type'
                        ]
                        ?? 'global'
                    )
                )
            );

        if (
            !in_array(
                $scopeType,
                [
                    'global',
                    'project',
                    'service',
                    'topic',
                    'queue',
                ],
                true
            )
        ) {
            $scopeType =
                'global';
        }


        $pauseStatuses =
            $input[
                'pause_statuses'
            ]
            ?? [];

        if (
            !is_array(
                $pauseStatuses
            )
        ) {
            $pauseStatuses = [];
        }


        return [
            'scope_type' =>
                $scopeType,

            'project_id' =>
                max(
                    0,
                    (int) (
                        $input[
                            'project_id'
                        ]
                        ?? 0
                    )
                ),

            'service_id' =>
                max(
                    0,
                    (int) (
                        $input[
                            'service_id'
                        ]
                        ?? 0
                    )
                ),

            'topic_id' =>
                max(
                    0,
                    (int) (
                        $input[
                            'topic_id'
                        ]
                        ?? 0
                    )
                ),

            'queue_id' =>
                max(
                    0,
                    (int) (
                        $input[
                            'queue_id'
                        ]
                        ?? 0
                    )
                ),

            'priority_code' =>
                strtolower(
                    trim(
                        (string) (
                            $input[
                                'priority_code'
                            ]
                            ?? ''
                        )
                    )
                ),

            'calendar_id' =>
                max(
                    0,
                    (int) (
                        $input[
                            'calendar_id'
                        ]
                        ?? 0
                    )
                ),

            'title' =>
                $this->limit(
                    trim(
                        (string) (
                            $input['title']
                            ?? ''
                        )
                    ),
                    255
                ),

            'response_minutes' =>
                max(
                    0,
                    (int) (
                        $input[
                            'response_minutes'
                        ]
                        ?? 0
                    )
                ),

            'resolution_minutes' =>
                max(
                    0,
                    (int) (
                        $input[
                            'resolution_minutes'
                        ]
                        ?? 0
                    )
                ),

            'pause_statuses' =>
                array_values(
                    array_filter(
                        array_map(
                            static fn (
                                mixed $value
                            ): string =>
                                trim(
                                    (string) $value
                                ),
                            $pauseStatuses
                        ),
                        static fn (
                            string $value
                        ): bool =>
                            $value !== ''
                    )
                ),

            'auto_escalate' =>
                !empty(
                    $input[
                        'auto_escalate'
                    ]
                ),

            'max_auto_escalations' =>
                (int) (
                    $input[
                        'max_auto_escalations'
                    ]
                    ?? 0
                ),

            'escalation_repeat_minutes' =>
                (int) (
                    $input[
                        'escalation_repeat_minutes'
                    ]
                    ?? 0
                ),

            'sort_order' =>
                max(
                    0,
                    min(
                        100000,
                        (int) (
                            $input[
                                'sort_order'
                            ]
                            ?? 0
                        )
                    )
                ),
        ];
    }


    private function resolveScope(
        array $form,
        array &$errors
    ): array {
        $scope = [
            'scope_type' =>
                $form[
                    'scope_type'
                ],

            'project_id' => null,
            'service_id' => null,
            'topic_id' => null,
            'queue_id' => null,
        ];


        if (
            $form[
                'scope_type'
            ] === 'global'
        ) {
            return $scope;
        }


        if (
            $form[
                'scope_type'
            ] === 'project'
        ) {
            $project =
                $this->policies
                    ->project(
                        $form[
                            'project_id'
                        ]
                    );

            if (!is_array($project)) {
                $errors['project_id'] =
                    'پروژه انتخاب‌شده معتبر نیست.';

                return $scope;
            }

            $scope['project_id'] =
                (int) $project['id'];

            return $scope;
        }


        if (
            $form[
                'scope_type'
            ] === 'service'
        ) {
            $service =
                $this->policies
                    ->service(
                        $form[
                            'service_id'
                        ]
                    );

            if (!is_array($service)) {
                $errors['service_id'] =
                    'خدمت انتخاب‌شده معتبر نیست.';

                return $scope;
            }

            $scope['project_id'] =
                (int) $service[
                    'project_id'
                ];

            $scope['service_id'] =
                (int) $service['id'];

            return $scope;
        }


        if (
            $form[
                'scope_type'
            ] === 'topic'
        ) {
            $topic =
                $this->policies
                    ->topic(
                        $form[
                            'topic_id'
                        ]
                    );

            if (!is_array($topic)) {
                $errors['topic_id'] =
                    'موضوع انتخاب‌شده معتبر نیست.';

                return $scope;
            }

            $scope['project_id'] =
                (int) $topic[
                    'project_id'
                ];

            $serviceId =
                (int) (
                    $topic[
                        'service_id'
                    ]
                    ?? 0
                );

            $scope['service_id'] =
                $serviceId > 0
                    ? $serviceId
                    : null;

            $scope['topic_id'] =
                (int) $topic['id'];

            return $scope;
        }


        $queue =
            $this->policies
                ->queue(
                    $form[
                        'queue_id'
                    ]
                );

        if (!is_array($queue)) {
            $errors['queue_id'] =
                'صف انتخاب‌شده معتبر نیست.';

            return $scope;
        }

        $scope['project_id'] =
            (int) $queue[
                'project_id'
            ];

        $scope['queue_id'] =
            (int) $queue['id'];

        return $scope;
    }


    private function scopeType(
        array $policy
    ): string {
        if (
            !empty(
                $policy[
                    'topic_id'
                ]
            )
        ) {
            return 'topic';
        }

        if (
            !empty(
                $policy[
                    'service_id'
                ]
            )
        ) {
            return 'service';
        }

        if (
            !empty(
                $policy[
                    'queue_id'
                ]
            )
        ) {
            return 'queue';
        }

        if (
            !empty(
                $policy[
                    'project_id'
                ]
            )
        ) {
            return 'project';
        }

        return 'global';
    }


    private function scopeKey(
        array $scope,
        string $priority
    ): string {
        $parts = [
            'managed-v1',

            $scope[
                'scope_type'
            ],

            'p:'
            . (
                $scope[
                    'project_id'
                ]
                ?? '*'
            ),

            's:'
            . (
                $scope[
                    'service_id'
                ]
                ?? '*'
            ),

            't:'
            . (
                $scope[
                    'topic_id'
                ]
                ?? '*'
            ),

            'q:'
            . (
                $scope[
                    'queue_id'
                ]
                ?? '*'
            ),

            'priority:'
            . $priority,

            'v:'
            . gmdate(
                'YmdHis'
            )
            . '-'
            . bin2hex(
                random_bytes(5)
            ),
        ];


        return
            substr(
                implode(
                    '|',
                    $parts
                ),
                0,
                190
            );
    }


    private function length(
        string $value
    ): int {
        return
            function_exists(
                'mb_strlen'
            )
                ? mb_strlen(
                    $value,
                    'UTF-8'
                )
                : strlen(
                    $value
                );
    }


    private function limit(
        string $value,
        int $length
    ): string {
        if (
            $this->length(
                $value
            )
            <= $length
        ) {
            return $value;
        }


        return
            function_exists(
                'mb_substr'
            )
                ? mb_substr(
                    $value,
                    0,
                    $length,
                    'UTF-8'
                )
                : substr(
                    $value,
                    0,
                    $length
                );
    }
}
