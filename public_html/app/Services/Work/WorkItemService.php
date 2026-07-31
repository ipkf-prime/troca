<?php

namespace App\Services\Work;

use App\Repositories\WorkItemRepository;
use App\Services\BaseService;
use DateTimeImmutable;

class WorkItemService extends BaseService
{
    public function __construct(private ?WorkItemRepository $items = null)
    {
        $this->items ??= new WorkItemRepository();
    }

    public function index(string $projectReference, array $filters = []): array
    {
        $project = $this->items->project(trim($projectReference));
        if ($project === null) {
            return ['ok' => false];
        }

        $options = $this->options((int) $project['id']);
        $q = $this->limit(trim((string) ($filters['q'] ?? '')), 120);
        $type = trim((string) ($filters['type'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        if ($type !== '' && !array_key_exists($type, $options['types'])) {
            $type = '';
        }
        if ($status !== '' && !array_key_exists($status, $options['statuses'])) {
            $status = '';
        }

        $rows = $this->items->index((int) $project['id'], [
            'q' => $q,
            'type' => $type,
            'status' => $status,
        ]);

        return [
            'ok' => true,
            'project' => $project,
            'items' => $this->tree($rows),
            'total' => count($rows),
            'filters' => ['q' => $q, 'type' => $type, 'status' => $status],
            'options' => $options,
        ];
    }

    public function form(string $projectReference, ?string $itemReference = null): array
    {
        $project = $this->items->project(trim($projectReference));
        if ($project === null) {
            return ['ok' => false];
        }

        $item = null;
        if ($itemReference !== null) {
            $item = $this->items->findByReference((int) $project['id'], trim($itemReference));
            if ($item === null) {
                return ['ok' => false];
            }
        }

        $options = $this->options((int) $project['id'], $item === null ? null : (int) $item['id']);
        $form = $item === null ? $this->emptyForm() : $this->itemToForm($item);

        return [
            'ok' => true,
            'project' => $project,
            'form' => $form,
            'options' => $options,
        ];
    }

    public function create(
        string $projectReference,
        array $input,
        int $userId,
        array $context = []
    ): array {
        $project = $this->items->project(trim($projectReference));
        if ($project === null) {
            return ['ok' => false, 'not_found' => true];
        }
        if (!empty($project['archived_at'])) {
            return ['ok' => false, 'errors' => ['archived' => 'پروژه بایگانی‌شده قابل تغییر نیست.']];
        }

        $data = $this->normalize($input);
        $resolved = $this->resolveAndValidate((int) $project['id'], $data, null);
        if ($resolved['errors'] !== []) {
            return ['ok' => false, 'errors' => $resolved['errors'], 'form' => $data];
        }

        $data = $resolved['data'];
        $data['public_reference'] = 'WRK-ITEM-' . strtoupper(bin2hex(random_bytes(8)));
        $actorReference = 'user:' . $userId;
        $reference = $this->items->create(
            (int) $project['id'],
            $data,
            $actorReference,
            $this->actorDisplayName($context, $userId)
        );

        return ['ok' => true, 'public_reference' => $reference];
    }

    public function update(
        string $projectReference,
        string $itemReference,
        array $input,
        int $userId,
        array $context = []
    ): array {
        $project = $this->items->project(trim($projectReference));
        if ($project === null) {
            return ['ok' => false, 'not_found' => true];
        }

        $current = $this->items->findByReference((int) $project['id'], trim($itemReference));
        if ($current === null || !empty($current['archived_at'])) {
            return ['ok' => false, 'not_found' => true];
        }

        $data = $this->normalize($input);
        $data['item_type'] = (string) $current['item_type'];
        $resolved = $this->resolveAndValidate((int) $project['id'], $data, (int) $current['id']);
        if ($resolved['errors'] !== []) {
            return ['ok' => false, 'errors' => $resolved['errors'], 'form' => $data];
        }

        $actorReference = 'user:' . $userId;
        $updated = $this->items->update(
            (int) $project['id'],
            (int) $current['id'],
            $resolved['data'],
            $actorReference,
            $this->actorDisplayName($context, $userId)
        );

        return $updated
            ? ['ok' => true, 'public_reference' => $itemReference]
            : ['ok' => false, 'errors' => ['save' => 'ذخیره تغییرات انجام نشد.'], 'form' => $data];
    }

    public function archive(
        string $projectReference,
        string $itemReference,
        int $userId,
        array $context = []
    ): bool {
        $project = $this->items->project(trim($projectReference));
        if ($project === null) {
            return false;
        }
        $item = $this->items->findByReference((int) $project['id'], trim($itemReference));
        if ($item === null || !empty($item['archived_at'])) {
            return false;
        }

        return $this->items->archive(
            (int) $project['id'],
            (int) $item['id'],
            'user:' . $userId,
            $this->actorDisplayName($context, $userId)
        );
    }

    private function resolveAndValidate(int $projectId, array $data, ?int $currentId): array
    {
        $errors = [];
        $statusMap = [];
        foreach ($this->items->statuses() as $status) {
            $statusMap[(string) $status['code']] = $status;
        }
        $members = [];
        foreach ($this->items->members($projectId) as $member) {
            $members[(string) $member['user_reference']] = $member;
        }

        if (!array_key_exists($data['item_type'], $this->typeOptions())) {
            $errors['item_type'] = 'نوع آیتم معتبر نیست.';
        }
        if ($data['title'] === '' || $this->length($data['title']) < 3) {
            $errors['title'] = 'عنوان باید حداقل ۳ نویسه باشد.';
        } elseif ($this->length($data['title']) > 500) {
            $errors['title'] = 'عنوان بیش از حد مجاز است.';
        }
        if ($data['description'] !== null && $this->length($data['description']) > 20000) {
            $errors['description'] = 'شرح بیش از حد مجاز است.';
        }
        if (!array_key_exists($data['priority_code'], $this->priorityOptions())) {
            $errors['priority_code'] = 'اولویت معتبر نیست.';
        }
        if (!isset($statusMap[$data['status_code']])) {
            $errors['status_code'] = 'وضعیت معتبر نیست.';
        }
        if ($data['progress_percent'] < 0 || $data['progress_percent'] > 100) {
            $errors['progress_percent'] = 'درصد پیشرفت باید بین صفر تا صد باشد.';
        }
        if ($data['estimate_minutes'] !== null && ($data['estimate_minutes'] < 0 || $data['estimate_minutes'] > 1000000)) {
            $errors['estimate_minutes'] = 'برآورد زمان معتبر نیست.';
        }

        foreach (['start_date' => 'تاریخ شروع', 'due_date' => 'تاریخ سررسید'] as $field => $label) {
            if ($data[$field] !== null && !$this->validDate((string) $data[$field])) {
                $errors[$field] = $label . ' معتبر نیست.';
            }
        }
        if (
            !isset($errors['start_date'])
            && !isset($errors['due_date'])
            && $data['start_date'] !== null
            && $data['due_date'] !== null
            && $data['due_date'] < $data['start_date']
        ) {
            $errors['due_date'] = 'تاریخ سررسید نمی‌تواند پیش از تاریخ شروع باشد.';
        }

        $parent = null;
        if ($data['parent_reference'] !== '') {
            $parent = $this->items->findByReference($projectId, $data['parent_reference']);
            if ($parent === null || !empty($parent['archived_at'])) {
                $errors['parent_reference'] = 'والد انتخاب‌شده معتبر نیست.';
            } elseif ($currentId !== null && (int) $parent['id'] === $currentId) {
                $errors['parent_reference'] = 'یک آیتم نمی‌تواند والد خودش باشد.';
            } elseif ($currentId !== null && $this->wouldCreateCycle($projectId, $currentId, (int) $parent['id'])) {
                $errors['parent_reference'] = 'ساختار والد و فرزند باعث ایجاد حلقه می‌شود.';
            }
        }

        if (!isset($errors['item_type'], $errors['parent_reference'])) {
            $parentType = $parent === null ? null : (string) $parent['item_type'];
            if (!$this->validParent($data['item_type'], $parentType)) {
                $errors['parent_reference'] = $this->parentRuleMessage($data['item_type']);
            }
        }

        if ($data['assignee_reference'] !== '' && !isset($members[$data['assignee_reference']])) {
            $errors['assignee_reference'] = 'مسئول باید یکی از اعضای فعال پروژه باشد.';
        }

        $status = $statusMap[$data['status_code']] ?? null;
        if ($status !== null && (int) $status['is_closed'] === 1) {
            $data['progress_percent'] = 100;
            $data['completed_at'] = gmdate('Y-m-d H:i:s');
        } else {
            $data['completed_at'] = null;
        }
        $data['status_id'] = $status === null ? 0 : (int) $status['id'];
        $data['parent_id'] = $parent === null ? null : (int) $parent['id'];
        if ($data['assignee_reference'] === '') {
            $data['assignee_reference'] = null;
            $data['assignee_name'] = null;
        } else {
            $data['assignee_name'] = (string) $members[$data['assignee_reference']]['display_name_snapshot'];
        }

        return ['errors' => $errors, 'data' => $data];
    }

    private function options(int $projectId, ?int $exceptId = null): array
    {
        $statuses = [];
        foreach ($this->items->statuses() as $status) {
            $statuses[(string) $status['code']] = (string) $status['title'];
        }

        return [
            'types' => $this->typeOptions(),
            'priorities' => $this->priorityOptions(),
            'statuses' => $statuses,
            'members' => $this->items->members($projectId),
            'parents' => $this->items->parentCandidates($projectId, $exceptId),
        ];
    }

    private function emptyForm(): array
    {
        return [
            'public_reference' => '',
            'item_type' => 'task',
            'parent_reference' => '',
            'title' => '',
            'description' => '',
            'status_code' => 'planned',
            'priority_code' => 'normal',
            'progress_percent' => 0,
            'start_date' => '',
            'due_date' => '',
            'estimate_minutes' => '',
            'assignee_reference' => '',
        ];
    }

    private function itemToForm(array $item): array
    {
        return [
            'public_reference' => (string) $item['public_reference'],
            'item_type' => (string) $item['item_type'],
            'parent_reference' => (string) ($item['parent_reference'] ?? ''),
            'title' => (string) $item['title'],
            'description' => (string) ($item['description'] ?? ''),
            'status_code' => (string) $item['status_code'],
            'priority_code' => (string) $item['priority_code'],
            'progress_percent' => (int) $item['progress_percent'],
            'start_date' => $this->datePart($item['start_at'] ?? null),
            'due_date' => $this->datePart($item['due_at'] ?? null),
            'estimate_minutes' => $item['estimate_minutes'] ?? '',
            'assignee_reference' => (string) ($item['assignee_reference'] ?? ''),
        ];
    }

    private function normalize(array $input): array
    {
        $progress = filter_var($input['progress_percent'] ?? 0, FILTER_VALIDATE_INT);
        $estimate = trim((string) ($input['estimate_minutes'] ?? ''));

        return [
            'item_type' => trim((string) ($input['item_type'] ?? 'task')),
            'parent_reference' => trim((string) ($input['parent_reference'] ?? '')),
            'title' => trim((string) ($input['title'] ?? '')),
            'description' => trim((string) ($input['description'] ?? '')) ?: null,
            'status_code' => trim((string) ($input['status_code'] ?? 'planned')),
            'priority_code' => trim((string) ($input['priority_code'] ?? 'normal')),
            'progress_percent' => $progress === false ? -1 : (int) $progress,
            'start_date' => trim((string) ($input['start_date'] ?? '')) ?: null,
            'due_date' => trim((string) ($input['due_date'] ?? '')) ?: null,
            'estimate_minutes' => $estimate === '' ? null : (int) $estimate,
            'assignee_reference' => trim((string) ($input['assignee_reference'] ?? '')),
        ];
    }

    private function tree(array $rows): array
    {
        $byParent = [];
        $knownIds = [];
        foreach ($rows as $row) {
            $knownIds[(int) $row['id']] = true;
        }
        foreach ($rows as $row) {
            $parentId = $row['parent_id'] === null ? 0 : (int) $row['parent_id'];
            if ($parentId !== 0 && !isset($knownIds[$parentId])) {
                $parentId = 0;
            }
            $byParent[$parentId][] = $row;
        }

        $flat = [];
        $append = function (int $parentId, int $depth) use (&$append, &$flat, $byParent): void {
            foreach ($byParent[$parentId] ?? [] as $row) {
                $row['depth'] = min($depth, 8);
                $row['type_title'] = $this->typeOptions()[$row['item_type']] ?? $row['item_type'];
                $row['priority_title'] = $this->priorityOptions()[$row['priority_code']] ?? $row['priority_code'];
                $flat[] = $row;
                $append((int) $row['id'], $depth + 1);
            }
        };
        $append(0, 0);

        return $flat;
    }

    private function wouldCreateCycle(int $projectId, int $currentId, int $parentId): bool
    {
        $visited = [];
        $cursor = $parentId;

        for ($i = 0; $i < 100 && $cursor > 0; $i++) {
            if ($cursor === $currentId || isset($visited[$cursor])) {
                return true;
            }
            $visited[$cursor] = true;
            $item = $this->items->findById($projectId, $cursor);
            if ($item === null || $item['parent_id'] === null) {
                return false;
            }
            $cursor = (int) $item['parent_id'];
        }

        return $cursor > 0;
    }

    private function validParent(string $itemType, ?string $parentType): bool
    {
        return match ($itemType) {
            'work' => $parentType === null,
            'milestone' => $parentType === 'work',
            'task' => in_array($parentType, ['work', 'milestone'], true),
            'subtask' => $parentType === 'task',
            default => false,
        };
    }

    private function parentRuleMessage(string $itemType): string
    {
        return match ($itemType) {
            'work' => 'کار اصلی نباید والد داشته باشد.',
            'milestone' => 'والد نقطه عطف باید یک کار باشد.',
            'task' => 'والد تسک باید کار یا نقطه عطف باشد.',
            'subtask' => 'والد زیرتسک باید یک تسک باشد.',
            default => 'ساختار والد معتبر نیست.',
        };
    }

    private function typeOptions(): array
    {
        return [
            'work' => 'کار',
            'milestone' => 'نقطه عطف',
            'task' => 'تسک',
            'subtask' => 'زیرتسک',
        ];
    }

    private function priorityOptions(): array
    {
        return [
            'low' => 'کم',
            'normal' => 'عادی',
            'high' => 'زیاد',
            'urgent' => 'فوری',
        ];
    }

    private function actorDisplayName(array $context, int $userId): string
    {
        foreach (['display_name', 'full_name', 'username'] as $field) {
            $value = trim((string) ($context[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return 'کاربر #' . $userId;
    }

    private function validDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private function datePart(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? '' : substr($value, 0, 10);
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private function limit(string $value, int $length): string
    {
        return function_exists('mb_substr')
            ? mb_substr($value, 0, $length, 'UTF-8')
            : substr($value, 0, $length);
    }
}