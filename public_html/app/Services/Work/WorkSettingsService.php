<?php

namespace App\Services\Work;

use App\Repositories\WorkSettingsRepository;
use App\Services\BaseService;

class WorkSettingsService extends BaseService
{
    private const GROUP_TITLES = [
        'item_status' => 'وضعیت‌های کار',
        'project_status' => 'وضعیت‌های پروژه',
        'project_visibility' => 'سطوح دسترسی پروژه',
        'item_priority' => 'اولویت‌های کار',
        'item_type' => 'انواع آیتم',
    ];

    public function __construct(private ?WorkSettingsRepository $settings = null)
    {
        $this->settings ??= new WorkSettingsRepository();
    }

    public function view(string $requestedGroup = 'item_status'): array
    {
        $groups = [[
            'code' => 'item_status',
            'title' => self::GROUP_TITLES['item_status'],
            'description' => 'وضعیت‌های عملیاتی کارها و تسک‌ها',
            'management_mode' => 'dynamic',
            'sort_order' => 0,
            'item_count' => count($this->settings->workStatuses()),
        ]];

        foreach ($this->settings->referenceGroups() as $group) {
            $groups[] = $group;
        }

        $groupCodes = array_map(
            static fn (array $group): string => (string) $group['code'],
            $groups
        );

        $groupCode = in_array($requestedGroup, $groupCodes, true)
            ? $requestedGroup
            : 'item_status';

        if ($groupCode === 'item_status') {
            $selected = $groups[0];
            $items = $this->settings->workStatuses();
            $selected['can_create'] = true;
        } else {
            $selected = $this->settings->referenceGroup($groupCode);
            if ($selected === null) {
                $selected = $groups[0];
                $groupCode = 'item_status';
                $items = $this->settings->workStatuses();
                $selected['can_create'] = true;
            } else {
                $items = $this->settings->referenceItems((int) $selected['id']);
                foreach ($items as &$item) {
                    $item['usage_count'] = $this->settings->referenceUsageCount(
                        $groupCode,
                        (string) $item['code']
                    );
                }
                unset($item);

                $selected['can_create'] = (string) $selected['management_mode'] === 'dynamic';
            }
        }

        $selected['title'] = self::GROUP_TITLES[$groupCode]
            ?? (string) ($selected['title'] ?? $groupCode);

        return [
            'groups' => $groups,
            'selected_group' => $selected,
            'group_code' => $groupCode,
            'items' => $items,
            'category_options' => [
                'open' => 'باز',
                'blocked' => 'مسدود',
                'closed' => 'بسته',
            ],
        ];
    }

    public function saveWorkStatus(
        ?int $statusId,
        array $input,
        int $userId,
        array $context = []
    ): array {
        $data = [
            'code' => $this->code((string) ($input['code'] ?? '')),
            'title' => $this->text((string) ($input['title'] ?? ''), 190),
            'category' => trim((string) ($input['category'] ?? 'open')),
            'color' => $this->color((string) ($input['color'] ?? '')),
            'sort_order' => $this->integer($input['sort_order'] ?? 0, -100000, 100000),
            'is_closed' => (string) ($input['is_closed'] ?? '0') === '1' ? 1 : 0,
            'is_active' => (string) ($input['is_active'] ?? '0') === '1' ? 1 : 0,
        ];

        $errors = [];
        if ($statusId === null && $data['code'] === '') {
            $errors['code'] = 'کد وضعیت باید با حرف انگلیسی شروع شود و فقط شامل حروف کوچک، عدد و زیرخط باشد.';
        }
        if ($data['title'] === '') {
            $errors['title'] = 'عنوان وضعیت الزامی است.';
        }
        if (!in_array($data['category'], ['open', 'blocked', 'closed'], true)) {
            $errors['category'] = 'دسته وضعیت معتبر نیست.';
        }

        if ($statusId !== null && $data['is_active'] === 0) {
            $current = $this->settings->workStatus($statusId);
            if ($current === null) {
                $errors['status'] = 'وضعیت مورد نظر پیدا نشد.';
            } elseif ($this->settings->workStatusUsageCount($statusId) > 0) {
                $errors['is_active'] = 'وضعیت استفاده‌شده قابل غیرفعال‌سازی نیست.';
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        try {
            $saved = $statusId === null
                ? $this->settings->createWorkStatus(
                    $data,
                    'user:' . $userId,
                    $this->actorDisplayName($context, $userId)
                )
                : $this->settings->updateWorkStatus(
                    $statusId,
                    $data,
                    'user:' . $userId,
                    $this->actorDisplayName($context, $userId)
                );
        } catch (\PDOException) {
            return [
                'ok' => false,
                'errors' => ['save' => 'ثبت وضعیت انجام نشد. کد وضعیت نباید تکراری باشد.'],
            ];
        }

        return $saved
            ? ['ok' => true]
            : ['ok' => false, 'errors' => ['save' => 'ذخیره وضعیت انجام نشد.']];
    }

    public function saveReferenceItem(
        string $groupCode,
        ?int $itemId,
        array $input,
        int $userId,
        array $context = []
    ): array {
        $group = $this->settings->referenceGroup(trim($groupCode));
        if ($group === null) {
            return ['ok' => false, 'errors' => ['group' => 'گروه تنظیمات پیدا نشد.']];
        }

        $mode = (string) $group['management_mode'];
        if ($itemId === null && $mode !== 'dynamic') {
            return ['ok' => false, 'errors' => ['mode' => 'افزودن گزینه جدید برای این گروه مجاز نیست.']];
        }

        $current = $itemId === null
            ? null
            : $this->settings->referenceItem((int) $group['id'], $itemId);

        if ($itemId !== null && $current === null) {
            return ['ok' => false, 'errors' => ['item' => 'گزینه مورد نظر پیدا نشد.']];
        }

        $data = [
            'code' => $itemId === null
                ? $this->code((string) ($input['code'] ?? ''))
                : (string) $current['code'],
            'title_fa' => $this->text((string) ($input['title_fa'] ?? ''), 190),
            'title_en' => $this->nullableText((string) ($input['title_en'] ?? ''), 190),
            'color' => $this->color((string) ($input['color'] ?? '')),
            'sort_order' => $this->integer($input['sort_order'] ?? 0, -100000, 100000),
            'is_active' => (string) ($input['is_active'] ?? '0') === '1' ? 1 : 0,
        ];

        if ($mode === 'structural') {
            $data['is_active'] = 1;
        }

        $errors = [];
        if ($itemId === null && $data['code'] === '') {
            $errors['code'] = 'کد گزینه باید با حرف انگلیسی شروع شود و فقط شامل حروف کوچک، عدد و زیرخط باشد.';
        }
        if ($data['title_fa'] === '') {
            $errors['title_fa'] = 'عنوان فارسی الزامی است.';
        }

        if (
            $current !== null
            && $data['is_active'] === 0
            && $this->settings->referenceUsageCount($groupCode, (string) $current['code']) > 0
        ) {
            $errors['is_active'] = 'گزینه استفاده‌شده قابل غیرفعال‌سازی نیست.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        try {
            $saved = $itemId === null
                ? $this->settings->createReferenceItem(
                    $group,
                    $data,
                    'user:' . $userId,
                    $this->actorDisplayName($context, $userId)
                )
                : $this->settings->updateReferenceItem(
                    $group,
                    $itemId,
                    $data,
                    'user:' . $userId,
                    $this->actorDisplayName($context, $userId)
                );
        } catch (\PDOException) {
            return [
                'ok' => false,
                'errors' => ['save' => 'ثبت گزینه انجام نشد. کد گزینه نباید تکراری باشد.'],
            ];
        }

        return $saved
            ? ['ok' => true]
            : ['ok' => false, 'errors' => ['save' => 'ذخیره گزینه انجام نشد.']];
    }

    public function errorText(array $errors): string
    {
        $message = reset($errors);

        return is_string($message) && $message !== ''
            ? $message
            : 'عملیات انجام نشد.';
    }

    private function code(string $value): string
    {
        $value = strtolower(trim($value));

        return preg_match('/^[a-z][a-z0-9_]{1,78}$/', $value) === 1
            ? $value
            : '';
    }

    private function color(string $value): ?string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return null;
        }

        return preg_match('/^#[0-9a-f]{6}$/', $value) === 1
            ? $value
            : null;
    }

    private function text(string $value, int $length): string
    {
        $value = trim($value);

        return function_exists('mb_substr')
            ? mb_substr($value, 0, $length, 'UTF-8')
            : substr($value, 0, $length);
    }

    private function nullableText(string $value, int $length): ?string
    {
        $value = $this->text($value, $length);

        return $value === '' ? null : $value;
    }

    private function integer(mixed $value, int $minimum, int $maximum): int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT);
        if ($value === false) {
            return 0;
        }

        return max($minimum, min($maximum, (int) $value));
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
}
