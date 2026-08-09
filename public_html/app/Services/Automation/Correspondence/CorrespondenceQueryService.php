<?php

namespace App\Services\Automation\Correspondence;

use App\Support\PersianDate;
use RuntimeException;
use Throwable;

class CorrespondenceQueryService
{
    private const PER_PAGE = 15;

    public function __construct(
        private ?CorrespondenceRepository $correspondences = null,
        private ?CorrespondenceVersionRepository $versions = null,
        private ?CorrespondencePartyRepository $parties = null,
        private ?CorrespondenceEventRepository $events = null,
        private ?AutomationLookupRepository $lookups = null,
        private ?CorrespondenceDocumentTemplateRepository $documentTemplates = null,
        private ?CoreReferenceOptions $coreReferences = null,
        private ?CorrespondenceViewModelBuilder $viewModels = null,
        private ?CorrespondenceRelationRepository $relations = null,
        private ?CorrespondenceAttachmentRepository $attachments = null,
        private ?EnterpriseAutomationContextService $enterpriseContext = null
    ) {
        $runtime = new AutomationOperationalRuntime();
        $this->correspondences ??= new CorrespondenceRepository($runtime);
        $this->versions ??= new CorrespondenceVersionRepository($runtime);
        $this->parties ??= new CorrespondencePartyRepository($runtime);
        $this->events ??= new CorrespondenceEventRepository($runtime);
        $this->lookups ??= new AutomationLookupRepository($runtime);
        $this->documentTemplates ??= new CorrespondenceDocumentTemplateRepository($runtime);
        $this->coreReferences ??= new CoreReferenceOptions();
        $this->viewModels ??= new CorrespondenceViewModelBuilder($this->lookups);
        $this->relations ??= new CorrespondenceRelationRepository($runtime);
        $this->attachments ??= new CorrespondenceAttachmentRepository($runtime);
        $this->enterpriseContext ??= new EnterpriseAutomationContextService();
    }

    public function dashboard(
        int $userId
    ): array {
        try {
            $actor =
                $this->enterpriseContext
                    ->forUser(
                        $userId
                    );

            return [
                'ok' => true,

                'counts' =>
                    $this->correspondences
                        ->dashboardCounts(
                            $actor[
                                'repository_scope'
                            ]
                        ),

                'enterprise_context' =>
                    $actor,
            ];

        } catch (Throwable) {
            return [
                'ok' => false,
                'counts' => [],
            ];
        }
    }

    public function index(
        array $params,
        int $userId
    ): array {
        $filters =
            $this->filters(
                $params
            );

        $page =
            $this->page(
                $params['page'] ?? 1
            );

        try {
            $actor =
                $this->enterpriseContext
                    ->forUser(
                        $userId
                    );

            $result =
                $this->correspondences
                    ->paginate(
                        $filters,
                        $page,
                        self::PER_PAGE,
                        $actor[
                            'repository_scope'
                        ]
                    );

            $total =
                (int) (
                    $result['total']
                    ?? 0
                );

            return [
                'ok' => true,

                'filters' =>
                    $filters,

                'items' =>
                    array_map(
                        fn (
                            array $row
                        ): array =>
                            $this->viewModels
                                ->listItem(
                                    $row
                                ),

                        $result[
                            'items'
                        ] ?? []
                    ),

                'pagination' =>
                    $this->pagination(
                        $total,
                        $page
                    ),

                'options' =>
                    $this->lookups
                        ->formOptions(),

                'enterprise_context' =>
                    $actor,
            ];

        } catch (Throwable) {
            return [
                'ok' => false,

                'filters' =>
                    $filters,

                'items' => [],

                'pagination' =>
                    $this->pagination(
                        0,
                        $page
                    ),

                'options' =>
                    $this->lookups
                        ->formOptions(),
            ];
        }
    }

    public function form(
        ?string $publicReference = null,
        ?int $userId = null
    ): array {
        if (
            $userId === null
            || $userId < 1
        ) {
            throw new RuntimeException(
                'automation_user_context_required'
            );
        }

        $actor =
            $this->enterpriseContext
                ->forUser(
                    $userId
                );

        $correspondence = null;
        $versions = [];
        $parties = [];
        $relations = [];

        if (
            $publicReference !== null
        ) {
            $correspondence =
                $this->correspondences
                    ->findByPublicReferenceScoped(
                        $publicReference,
                        $actor[
                            'repository_scope'
                        ]
                    );

            if (
                $correspondence !== null
            ) {
                $id =
                    (int) $correspondence[
                        'id'
                    ];

                $versions =
                    $this->versions
                        ->listFor(
                            $id
                        );

                $parties =
                    $this->parties
                        ->listFor(
                            $id
                        );

                $relations =
                    $this->relations
                        ->listFor(
                            $id,
                            $actor[
                                'repository_scope'
                            ]
                        );
            }
        }

        return [
            'ok' =>
                $publicReference === null
                || $correspondence !== null,

            'form' =>
                $this->viewModels
                    ->formData(
                        $correspondence,
                        $versions,
                        $this->formParties(
                            $parties
                        ),
                        $relations
                    ),

            'options' =>
                $this->lookups
                    ->formOptions()
                + [
                    'document_templates' =>
                        $this->documentTemplates
                            ->options(),

                    'related_correspondences' =>
                        $this->relations
                            ->options(
                                $correspondence
                                    !== null
                                        ? (int) $correspondence[
                                            'id'
                                        ]
                                        : null,

                                $actor[
                                    'repository_scope'
                                ]
                            ),
                ],

            'references' =>
                $this->coreReferences
                    ->options(
                        $actor[
                            'repository_scope'
                        ]
                    ),

            'editable' =>
                $correspondence === null
                || (
                    $correspondence[
                        'status_code'
                    ] ?? ''
                ) === 'draft',

            'enterprise_context' =>
                $actor,
        ];
    }

    public function detail(
        string $publicReference,
        string $tab,
        int $userId
    ): ?array {
        $tab =
            in_array(
                $tab,
                [
                    'summary',
                    'content',
                    'parties',
                    'relations',
                    'attachments',
                    'versions',
                    'history',
                ],
                true
            )
                ? $tab
                : 'summary';

        $actor =
            $this->enterpriseContext
                ->forUser(
                    $userId
                );

        $correspondence =
            $this->correspondences
                ->findByPublicReferenceScoped(
                    $publicReference,
                    $actor[
                        'repository_scope'
                    ]
                );

        if (
            $correspondence === null
        ) {
            return null;
        }

        $id =
            (int) $correspondence[
                'id'
            ];

        $detail =
            $this->viewModels
                ->detail(
                    $correspondence,

                    $this->versions
                        ->listFor(
                            $id
                        ),

                    $this->parties
                        ->listFor(
                            $id
                        ),

                    $this->events
                        ->listFor(
                            $id
                        ),

                    $tab,

                    $this->relations
                        ->listFor(
                            $id,
                            $actor[
                                'repository_scope'
                            ]
                        ),

                    $this->attachments
                        ->listFor(
                            $id
                        )
                );

        $detail[
            'enterprise_context'
        ] = $actor;

        return $detail;
    }

    public function templates(): array
    {
        return $this->documentTemplates->options();
    }

    private function formParties(array $parties): array
    {
        foreach ($parties as &$party) {
            $kind = (string) ($party['target_kind_code'] ?? '');
            $id = match ($kind) {
                'person' => (int) ($party['person_id'] ?? 0),
                'organization' => (int) ($party['organization_id'] ?? 0),
                'org_unit' => (int) ($party['org_unit_id'] ?? 0),
                default => 0,
            };
            $party['reference_token'] = $id > 0
                ? (string) ($this->coreReferences->tokenFor($kind, $id) ?? '')
                : '';
        }
        unset($party);

        return $parties;
    }

    private function filters(array $params): array
    {
        return [
            'q' => $this->text($params['q'] ?? '', 80),
            'status' => $this->code($params['status'] ?? ''),
            'direction' => $this->code($params['direction'] ?? ''),
            'priority' => $this->code($params['priority'] ?? ''),
            'date_from' => $this->date($params['date_from'] ?? '', $params['date_from_fa'] ?? ''),
            'date_to' => $this->date($params['date_to'] ?? '', $params['date_to_fa'] ?? ''),
        ];
    }

    private function page(mixed $page): int
    {
        return (int) filter_var($page, FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1, 'max_range' => 10000]]);
    }

    private function pagination(int $total, int $page): array
    {
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $page), $lastPage);

        return ['page' => $page, 'per_page' => self::PER_PAGE, 'total' => $total, 'last_page' => $lastPage, 'has_previous' => $page > 1, 'has_next' => $page < $lastPage, 'previous_page' => max(1, $page - 1), 'next_page' => min($lastPage, $page + 1)];
    }

    private function text(mixed $value, int $max): string
    {
        $value = trim((string) ($value ?? ''));
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }

    private function code(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        return preg_match('/^[a-z0-9_]+$/', $value) === 1 ? $value : '';
    }

    private function date(mixed $gregorianValue, mixed $persianValue = ''): string
    {
        $gregorian = trim((string) ($gregorianValue ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $gregorian) === 1) {
            return $gregorian;
        }

        $persian = trim((string) ($persianValue ?? ''));
        if ($persian === '') {
            return '';
        }

        try {
            return PersianDate::toGregorianDate($persian) ?? '';
        } catch (RuntimeException) {
            return '';
        }
    }
}
