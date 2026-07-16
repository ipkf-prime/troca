<?php

namespace App\Services\Automation\Correspondence;

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
        private ?CoreReferenceOptions $coreReferences = null,
        private ?CorrespondenceViewModelBuilder $viewModels = null
    ) {
        $runtime = new AutomationOperationalRuntime();
        $this->correspondences ??= new CorrespondenceRepository($runtime);
        $this->versions ??= new CorrespondenceVersionRepository($runtime);
        $this->parties ??= new CorrespondencePartyRepository($runtime);
        $this->events ??= new CorrespondenceEventRepository($runtime);
        $this->lookups ??= new AutomationLookupRepository($runtime);
        $this->coreReferences ??= new CoreReferenceOptions();
        $this->viewModels ??= new CorrespondenceViewModelBuilder($this->lookups);
    }

    public function dashboard(): array
    {
        try {
            return ['ok' => true, 'counts' => $this->correspondences->dashboardCounts()];
        } catch (Throwable) {
            return ['ok' => false, 'counts' => []];
        }
    }

    public function index(array $params): array
    {
        $filters = $this->filters($params);
        $page = $this->page($params['page'] ?? 1);

        try {
            $result = $this->correspondences->paginate($filters, $page, self::PER_PAGE);
            $total = (int) ($result['total'] ?? 0);

            return [
                'ok' => true,
                'filters' => $filters,
                'items' => array_map(fn (array $row): array => $this->viewModels->listItem($row), $result['items'] ?? []),
                'pagination' => $this->pagination($total, $page),
                'options' => $this->lookups->formOptions(),
            ];
        } catch (Throwable) {
            return [
                'ok' => false,
                'filters' => $filters,
                'items' => [],
                'pagination' => $this->pagination(0, $page),
                'options' => $this->lookups->formOptions(),
            ];
        }
    }

    public function form(?string $publicReference = null): array
    {
        $correspondence = null;
        $versions = [];
        $parties = [];

        if ($publicReference !== null) {
            $correspondence = $this->correspondences->findByPublicReference($publicReference);

            if ($correspondence !== null) {
                $versions = $this->versions->listFor((int) $correspondence['id']);
                $parties = $this->parties->listFor((int) $correspondence['id']);
            }
        }

        return [
            'ok' => $publicReference === null || $correspondence !== null,
            'form' => $this->viewModels->formData($correspondence, $versions, $parties),
            'options' => $this->lookups->formOptions(),
            'references' => $this->coreReferences->options(),
            'editable' => $correspondence === null || ($correspondence['status_code'] ?? '') === 'draft',
        ];
    }

    public function detail(string $publicReference, string $tab): ?array
    {
        $tab = in_array($tab, ['summary', 'content', 'parties', 'versions', 'history'], true) ? $tab : 'summary';
        $correspondence = $this->correspondences->findByPublicReference($publicReference);

        if ($correspondence === null) {
            return null;
        }

        $id = (int) $correspondence['id'];

        return $this->viewModels->detail(
            $correspondence,
            $this->versions->listFor($id),
            $this->parties->listFor($id),
            $this->events->listFor($id),
            $tab
        );
    }

    private function filters(array $params): array
    {
        return [
            'q' => $this->text($params['q'] ?? '', 80),
            'status' => $this->code($params['status'] ?? ''),
            'direction' => $this->code($params['direction'] ?? ''),
            'priority' => $this->code($params['priority'] ?? ''),
            'date_from' => $this->date($params['date_from'] ?? ''),
            'date_to' => $this->date($params['date_to'] ?? ''),
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

    private function date(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : '';
    }
}
