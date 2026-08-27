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
        $q = $this->limitString(
            trim(
                (string) (
                    $filters['q']
                    ?? ''
                )
            ),
            120
        );

        $status = trim(
            (string) (
                $filters['status']
                ?? ''
            )
        );

        $priority = trim(
            (string) (
                $filters['priority']
                ?? ''
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

        $rows =
            $this->tickets->index([
                'q' => $q,
                'status' => $status,
                'priority' => $priority,

                'viewer_user_reference' =>
                    $this->userReference(
                        $userId
                    ),
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


        $created =
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

            'ticket_number' =>
                $this->ticketNumber(
                    (int) $created['id']
                ),
        ];
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
        $ticket['ticket_number'] =
            $this->ticketNumber(
                (int) $ticket['id']
            );

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
