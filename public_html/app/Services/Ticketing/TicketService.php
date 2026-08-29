<?php

namespace App\Services\Ticketing;

use App\Repositories\TicketRepository;
use App\Repositories\TicketCreateRoutingRepository;
use App\Services\BaseService;

class TicketService extends BaseService
{
    public function __construct(
        private ?TicketRepository $tickets = null,
        private ?TicketCreateRoutingRepository $creation = null
    ) {
        $this->tickets ??=
            new TicketRepository();

        $this->creation ??=
            new TicketCreateRoutingRepository();
    }


    public function dashboard(): array
    {
        $dashboard =
            $this->tickets->dashboard();

        $dashboard['recent'] =
            array_map(
                fn (array $ticket): array =>
                    $this->present($ticket),
                $dashboard['recent']
            );

        return $dashboard;
    }


    public function dashboardForUser(
        int $userId
    ): array {
        $dashboard =
            $this->tickets->dashboard(
                $this->userReference(
                    $userId
                )
            );

        $dashboard['recent'] =
            array_map(
                fn (array $ticket): array =>
                    $this->present($ticket),
                $dashboard['recent']
            );

        return $dashboard;
    }


    public function index(
        array $filters = []
    ): array {
        $q = trim(
            (string) ($filters['q'] ?? '')
        );

        $q = $this->limitString(
            $q,
            120
        );

        $status = trim(
            (string) ($filters['status'] ?? '')
        );

        $priority = trim(
            (string) ($filters['priority'] ?? '')
        );

        $statuses =
            $this->statusMap();

        $priorities =
            $this->priorityMap();

        if (
            $status !== ''
            && !isset($statuses[$status])
        ) {
            $status = '';
        }

        if (
            $priority !== ''
            && !isset($priorities[$priority])
        ) {
            $priority = '';
        }

        $rows =
            $this->tickets->index([
                'q' => $q,
                'status' => $status,
                'priority' => $priority,
            ]);

        return [
            'items' =>
                array_map(
                    fn (array $ticket): array =>
                        $this->present($ticket),
                    $rows
                ),

            'total' =>
                count($rows),

            'q' => $q,

            'status' => $status,

            'priority' => $priority,

            'status_options' =>
                $statuses,

            'priority_options' =>
                $priorities,
        ];
    }


    public function myTickets(
        int $userId,
        array $filters = []
    ): array {
        $viewer =
            $this->userReference(
                $userId
            );

        $q =
            $this->limitString(
                trim(
                    (string) (
                        $filters['q']
                        ?? ''
                    )
                ),
                120
            );

        $status =
            trim(
                (string) (
                    $filters['status']
                    ?? ''
                )
            );

        $priority =
            trim(
                (string) (
                    $filters['priority']
                    ?? ''
                )
            );

        $projectReference =
            trim(
                (string) (
                    $filters[
                        'project_reference'
                    ]
                    ?? ''
                )
            );

        $layerId =
            max(
                0,
                (int) (
                    $filters['layer_id']
                    ?? 0
                )
            );

        $assigneeId =
            max(
                0,
                (int) (
                    $filters['assignee_id']
                    ?? 0
                )
            );


        $statuses =
            $this->statusMap();

        $priorities =
            $this->priorityMap();


        if (
            $status !== ''
            && !isset(
                $statuses[$status]
            )
        ) {
            $status = '';
        }


        if (
            $priority !== ''
            && !isset(
                $priorities[$priority]
            )
        ) {
            $priority = '';
        }


        $projectTabs =
            $this->tickets
                ->viewerProjectTabs(
                    $viewer
                );

        $projectMap = [];

        foreach (
            $projectTabs
            as $project
        ) {
            $projectMap[
                (string) (
                    $project[
                        'public_reference'
                    ]
                    ?? ''
                )
            ] =
                $project;
        }


        if (
            $projectReference !== ''
            && !isset(
                $projectMap[
                    $projectReference
                ]
            )
        ) {
            $projectReference = '';
        }


        $layerRows =
            $this->tickets
                ->viewerLayers(
                    $viewer
                );

        $layerOptions = [];

        foreach ($layerRows as $layer) {
            $id =
                (int) (
                    $layer['id']
                    ?? 0
                );

            if ($id > 0) {
                $layerOptions[$id] =
                    (string) (
                        $layer['title']
                        ?? ''
                    );
            }
        }


        if (
            $layerId > 0
            && !isset(
                $layerOptions[$layerId]
            )
        ) {
            $layerId = 0;
        }


        $assigneeRows =
            $this->tickets
                ->viewerAssignees(
                    $viewer
                );

        $assigneeOptions = [];

        foreach (
            $assigneeRows
            as $assignee
        ) {
            $id =
                (int) (
                    $assignee['id']
                    ?? 0
                );

            if ($id > 0) {
                $assigneeOptions[$id] =
                    (string) (
                        $assignee[
                            'display_name_snapshot'
                        ]
                        ?? ''
                    );
            }
        }


        if (
            $assigneeId > 0
            && !isset(
                $assigneeOptions[
                    $assigneeId
                ]
            )
        ) {
            $assigneeId = 0;
        }


        $sortOptions = [
            'last_activity' =>
                'آخرین فعالیت',

            'created_at' =>
                'تاریخ ثبت',

            'priority' =>
                'اولویت',

            'status' =>
                'وضعیت',

            'project' =>
                'پروژه',

            'stage' =>
                'مرحله جاری',

            'assignee' =>
                'کارشناس جاری',

            'subject' =>
                'عنوان',
        ];


        $sort1 =
            trim(
                (string) (
                    $filters['sort1']
                    ?? 'last_activity'
                )
            );

        $sort2 =
            trim(
                (string) (
                    $filters['sort2']
                    ?? 'created_at'
                )
            );

        if (!isset(
            $sortOptions[$sort1]
        )) {
            $sort1 =
                'last_activity';
        }

        if (!isset(
            $sortOptions[$sort2]
        )) {
            $sort2 =
                'created_at';
        }

        if ($sort2 === $sort1) {
            $sort2 =
                $sort1 === 'created_at'
                    ? 'last_activity'
                    : 'created_at';
        }


        $dir1 =
            strtolower(
                trim(
                    (string) (
                        $filters['dir1']
                        ?? 'desc'
                    )
                )
            );

        $dir2 =
            strtolower(
                trim(
                    (string) (
                        $filters['dir2']
                        ?? 'desc'
                    )
                )
            );

        if (!in_array(
            $dir1,
            ['asc', 'desc'],
            true
        )) {
            $dir1 =
                'desc';
        }

        if (!in_array(
            $dir2,
            ['asc', 'desc'],
            true
        )) {
            $dir2 =
                'desc';
        }


        $rows =
            $this->tickets->index([
                'q' =>
                    $q,

                'status' =>
                    $status,

                'priority' =>
                    $priority,

                'project_reference' =>
                    $projectReference,

                'layer_id' =>
                    $layerId,

                'assignee_id' =>
                    $assigneeId,

                'sort1' =>
                    $sort1,

                'dir1' =>
                    $dir1,

                'sort2' =>
                    $sort2,

                'dir2' =>
                    $dir2,

                'viewer_user_reference' =>
                    $viewer,
            ]);


        return [
            'items' =>
                array_map(
                    fn (array $ticket): array =>
                        array_merge(
                            $ticket,
                            $this->present(
                                $ticket
                            )
                        ),
                    $rows
                ),

            'total' =>
                count(
                    $rows
                ),

            'q' =>
                $q,

            'status' =>
                $status,

            'priority' =>
                $priority,

            'project_reference' =>
                $projectReference,

            'layer_id' =>
                $layerId,

            'assignee_id' =>
                $assigneeId,

            'sort1' =>
                $sort1,

            'dir1' =>
                $dir1,

            'sort2' =>
                $sort2,

            'dir2' =>
                $dir2,

            'status_options' =>
                $statuses,

            'priority_options' =>
                $priorities,

            'project_tabs' =>
                $projectTabs,

            'layer_options' =>
                $layerOptions,

            'assignee_options' =>
                $assigneeOptions,

            'sort_options' =>
                $sortOptions,
        ];
    }


    public function form(
        array $form = [],
        ?int $userId = null
    ): array {
        $priorities =
            $this->tickets->priorities();

        $categories =
            $this->tickets->categories();

        $priorityOptions = [];

        foreach ($priorities as $priority) {
            $priorityOptions[
                (string) $priority['code']
            ] =
                (string) $priority['title'];
        }

        $categoryOptions = [];

        $defaultCategoryId = null;

        foreach ($categories as $category) {
            $id =
                (int) $category['id'];

            $categoryOptions[$id] =
                (string) $category['title'];

            if (
                (string) $category['code']
                === 'general'
            ) {
                $defaultCategoryId = $id;
            }
        }

        if (
            $defaultCategoryId === null
            && $categoryOptions !== []
        ) {
            $defaultCategoryId =
                (int) array_key_first(
                    $categoryOptions
                );
        }

        $projectOptions = [];
        $serviceOptions = [];
        $topicOptions = [];

        if (
            $userId !== null
            && $userId > 0
        ) {
            $createOptions =
                $this->creation->optionsForUser(
                    $this->userReference(
                        $userId
                    )
                );

            $projectOptions =
                $createOptions['projects']
                ?? [];

            $serviceOptions =
                $createOptions['services']
                ?? [];

            $topicOptions =
                $createOptions['topics']
                ?? [];
        }

        $defaultProjectId =
            $projectOptions !== []
                ? (int) array_key_first(
                    $projectOptions
                )
                : null;

        $defaultServiceId = null;

        if ($defaultProjectId !== null) {
            foreach (
                $serviceOptions
                as $serviceId => $service
            ) {
                if (
                    (int) (
                        $service['project_id']
                        ?? 0
                    ) !== $defaultProjectId
                ) {
                    continue;
                }

                if (
                    $defaultServiceId === null
                    || !empty(
                        $service['is_default']
                    )
                ) {
                    $defaultServiceId =
                        (int) $serviceId;
                }

                if (
                    !empty(
                        $service['is_default']
                    )
                ) {
                    break;
                }
            }
        }

        $defaultTopicId =
            null;

        if (
            $defaultProjectId !== null
            && $defaultServiceId !== null
        ) {
            foreach (
                $topicOptions
                as $topicId => $topic
            ) {
                if (
                    (int) (
                        $topic['project_id']
                        ?? 0
                    ) !== $defaultProjectId
                ) {
                    continue;
                }

                $topicServiceId =
                    $topic['service_id']
                    ?? null;

                if (
                    $topicServiceId !== null
                    && (int) $topicServiceId
                        !== $defaultServiceId
                ) {
                    continue;
                }

                if (
                    $defaultTopicId === null
                    || !empty(
                        $topic['is_default']
                    )
                ) {
                    $defaultTopicId =
                        (int) $topicId;
                }

                if (
                    !empty(
                        $topic['is_default']
                    )
                ) {
                    break;
                }
            }
        }


        return [
            'form' =>
                array_merge(
                    [
                        'subject' => '',
                        'body' => '',
                        'priority_code' =>
                            'normal',
                        'category_id' =>
                            $defaultCategoryId
                            ?? '',

                        'support_project_id' =>
                            $defaultProjectId
                            ?? '',

                        'support_service_id' =>
                            $defaultServiceId
                            ?? '',

                        'support_topic_id' =>
                            $defaultTopicId
                            ?? '',
                    ],
                    $form
                ),

            'options' => [
                'priorities' =>
                    $priorityOptions,

                'categories' =>
                    $categoryOptions,

                'projects' =>
                    $projectOptions,

                'services' =>
                    $serviceOptions,

                'topics' =>
                    $topicOptions,
            ],
        ];
    }


    public function create(
        array $input,
        int $userId,
        array $context = [],
        array $files = []): array {
        $form = [
            'subject' =>
                trim(
                    (string) (
                        $input['subject']
                        ?? ''
                    )
                ),

            'body' =>
                trim(
                    (string) (
                        $input['body']
                        ?? ''
                    )
                ),

            'priority_code' =>
                trim(
                    (string) (
                        $input['priority_code']
                        ?? 'normal'
                    )
                ),

            'category_id' =>
                (int) (
                    $input['category_id']
                    ?? 0
                ),

            'support_project_id' =>
                (int) (
                    $input['support_project_id']
                    ?? 0
                ),

            'support_service_id' =>
                (int) (
                    $input['support_service_id']
                    ?? 0
                ),

            'support_topic_id' =>
                (int) (
                    $input['support_topic_id']
                    ?? 0
                ),
        ];

        $errors = [];

        if (
            $this->length(
                $form['subject']
            ) < 3
        ) {
            $errors['subject'] =
                'عنوان تیکت باید حداقل ۳ نویسه باشد.';
        } elseif (
            $this->length(
                $form['subject']
            ) > 500
        ) {
            $errors['subject'] =
                'عنوان تیکت بیش از حد مجاز است.';
        }

        if (
            $this->length(
                $form['body']
            ) < 3
        ) {
            $errors['body'] =
                'شرح درخواست باید حداقل ۳ نویسه باشد.';
        } elseif (
            $this->length(
                $form['body']
            ) > 20000
        ) {
            $errors['body'] =
                'شرح درخواست بیش از حد مجاز است.';
        }


        $priorityMap = [];

        foreach (
            $this->tickets->priorities()
            as $priority
        ) {
            $priorityMap[
                (string) $priority['code']
            ] = $priority;
        }

        if (
            !isset(
                $priorityMap[
                    $form['priority_code']
                ]
            )
        ) {
            $errors['priority_code'] =
                'اولویت انتخاب‌شده معتبر نیست.';
        }


        $categoryMap = [];

        foreach (
            $this->tickets->categories()
            as $category
        ) {
            $categoryMap[
                (int) $category['id']
            ] = $category;
        }

        if (
            $form['category_id'] <= 0
            || !isset(
                $categoryMap[
                    $form['category_id']
                ]
            )
        ) {
            $errors['category_id'] =
                'دسته‌بندی انتخاب‌شده معتبر نیست.';
        }


        $actorReference =
            $this->userReference(
                $userId
            );

        $selection =
            null;

        if (
            $form['support_project_id'] <= 0
            || $form['support_service_id'] <= 0
        ) {
            $errors['support_project_id'] =
                'پروژه و سرویس پشتیبانی الزامی است.';
        } else {
            $selection =
                $this->creation->selectionForUser(
                    $actorReference,
                    $form['support_project_id'],
                    $form['support_service_id']
                );

            if ($selection === null) {
                $errors['support_project_id'] =
                    'پروژه یا سرویس برای حساب شما مجاز نیست.';
            }
        }


        if (
            $form['support_project_id'] > 0
            && $form['support_service_id'] > 0
            && $selection !== null
        ) {
            $topicRequired =
                $this->creation
                    ->hasSelectableTopics(
                        $actorReference,
                        $form[
                            'support_project_id'
                        ],
                        $form[
                            'support_service_id'
                        ]
                    );

            if (
                $topicRequired
                && $form[
                    'support_topic_id'
                ] <= 0
            ) {
                $errors[
                    'support_topic_id'
                ] =
                    'موضوع پشتیبانی را انتخاب کنید.';

            } elseif (
                $form[
                    'support_topic_id'
                ] > 0
                && $this->creation
                    ->topicForSelection(
                        $actorReference,
                        $form[
                            'support_project_id'
                        ],
                        $form[
                            'support_service_id'
                        ],
                        $form[
                            'support_topic_id'
                        ]
                    ) === null
            ) {
                $errors[
                    'support_topic_id'
                ] =
                    'موضوع پشتیبانی انتخاب‌شده معتبر نیست.';
            }
        }


        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
                'form' => $form,
            ];
        }


        $actorName =
            $this->actorDisplayName(
                $context,
                $userId
            );


        $attachmentUpload =
            new TicketAttachmentUploadService();

        try {
            $preparedAttachments =
                $attachmentUpload->prepare(
                    is_array($files)
                        ? $files
                        : [],

                    'user:' . $userId
                );

        } catch (\InvalidArgumentException $exception) {

            return [
                'ok' =>
                    false,

                'errors' => [
                    'attachments' =>
                        $attachmentUpload->errorMessage(
                            $exception->getMessage()
                        ),
                ],

                'form' =>
                    $form,
            ];
        }

        $created =
            $this->creation->create([
                'public_reference' =>
                    $this->reference('TKT'),

                'message_reference' =>
                    $this->reference('TMSG'),

                'created_event_reference' =>
                    $this->reference('TEVT'),

                'routing_event_reference' =>
                    $this->reference('TEVT'),

                'assignment_event_reference' =>
                    $this->reference('TEVT'),

                'priority_code' =>
                    $form['priority_code'],

                'category_id' =>
                    $form['category_id'],

                'support_project_id' =>
                    $form['support_project_id'],

                'support_service_id' =>
                    $form['support_service_id'],

                'support_topic_id' =>
                    $form['support_topic_id'],

                'subject' =>
                    $form['subject'],

                'body' =>
                    $form['body'],

                'requester_user_reference' =>
                    $actorReference,

                'requester_email_snapshot' =>
                    null,

                'requester_mobile_snapshot' =>
                    null,

                'actor_user_reference' =>
                    $actorReference,
            ],
                $preparedAttachments);

        return [
            'ok' => true,

            'public_reference' =>
                (string) $created[
                    'public_reference'
                ],
        ];
    }


    /*
     * Attachment access deliberately reuses the exact Ticket Detail
     * authorization contract. A user who cannot obtain the Detail
     * cannot obtain its attachment.
     */
    public function attachmentForUser(
        string $publicReference,
        int $attachmentId,
        int $userId
    ): ?array {
        if (
            trim($publicReference) === ''
            ||
            $attachmentId < 1
            ||
            $userId < 1
        ) {
            return null;
        }

        $detail =
            $this->detailForUser(
                trim(
                    $publicReference
                ),
                $userId
            );

        if (!is_array($detail)) {
            return null;
        }

        $ticket =
            is_array(
                $detail['ticket']
                ?? null
            )
                ? $detail['ticket']
                : [];

        $ticketId =
            (int) (
                $ticket['id']
                ?? 0
            );

        if ($ticketId < 1) {
            return null;
        }

        return
            $this->tickets
                ->attachmentForTicket(
                    $ticketId,
                    $attachmentId
                );
    }


    public function detailForUser(
        string $publicReference,
        int $userId
    ): ?array {
        $ticket =
            $this->tickets->findByReference(
                trim($publicReference),
                $this->userReference(
                    $userId
                )
            );

        if ($ticket === null) {
            return null;
        }

        $ticket =
            $this->present($ticket);

        return [
            'ticket' => $ticket,

            'messages' =>
                $this->tickets->messages(
                    (int) $ticket['id']
                ),

            'attachments' =>
                $this->tickets->attachments(
                    (int) $ticket['id']
                ),

            'events' =>
                $this->tickets->events(
                    (int) $ticket['id']
                ),
        ];
    }


    public function detail(
        string $publicReference
    ): ?array {
        $ticket =
            $this->tickets->findByReference(
                trim($publicReference)
            );

        if ($ticket === null) {
            return null;
        }

        $ticket =
            $this->present($ticket);

        return [
            'ticket' => $ticket,

            'messages' =>
                $this->tickets->messages(
                    (int) $ticket['id']
                ),

            'attachments' =>
                $this->tickets->attachments(
                    (int) $ticket['id']
                ),

            'events' =>
                $this->tickets->events(
                    (int) $ticket['id']
                ),
        ];
    }


    private function statusMap(): array
    {
        $map = [];

        foreach (
            $this->tickets->statuses()
            as $status
        ) {
            $map[
                (string) $status['code']
            ] =
                (string) $status['title'];
        }

        return $map;
    }


    private function priorityMap(): array
    {
        $map = [];

        foreach (
            $this->tickets->priorities()
            as $priority
        ) {
            $map[
                (string) $priority['code']
            ] =
                (string) $priority['title'];
        }

        return $map;
    }


    private function present(
        array $ticket
    ): array {
        if (
            trim(
                (string) (
                    $ticket['ticket_number']
                    ?? ''
                )
            ) === ''
        ) {
            $ticket['ticket_number'] =
                $this->ticketNumber(
                    (int) $ticket['id']
                );
        }

        return $ticket;
    }


    private function ticketNumber(
        int $id
    ): string {
        return
            'TKT-'
            . str_pad(
                (string) $id,
                7,
                '0',
                STR_PAD_LEFT
            );
    }


    private function reference(
        string $prefix
    ): string {
        return
            $prefix
            . '-'
            . strtoupper(
                bin2hex(
                    random_bytes(10)
                )
            );
    }


    private function userReference(
        int $userId
    ): string {
        return
            'user:'
            . $userId;
    }


    private function actorDisplayName(
        array $context,
        int $userId
    ): string {
        foreach ([
            'display_name',
            'user_display_name',
            'full_name',
            'name',
        ] as $key) {

            $value = trim(
                (string) (
                    $context[$key]
                    ?? ''
                )
            );

            if ($value !== '') {
                return $value;
            }
        }

        if (
            isset($context['user'])
            && is_array($context['user'])
        ) {
            foreach ([
                'display_name',
                'full_name',
                'name',
            ] as $key) {
                $value = trim(
                    (string) (
                        $context['user'][$key]
                        ?? ''
                    )
                );

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return
            'کاربر '
            . $userId;
    }


    private function length(
        string $value
    ): int {
        return function_exists(
            'mb_strlen'
        )
            ? mb_strlen(
                $value,
                'UTF-8'
            )
            : strlen($value);
    }


    private function limitString(
        string $value,
        int $limit
    ): string {
        if (
            $this->length($value)
            <= $limit
        ) {
            return $value;
        }

        return function_exists(
            'mb_substr'
        )
            ? mb_substr(
                $value,
                0,
                $limit,
                'UTF-8'
            )
            : substr(
                $value,
                0,
                $limit
            );
    }
}
