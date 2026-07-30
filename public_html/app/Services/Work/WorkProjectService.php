<?php

namespace App\Services\Work;

use App\Repositories\WorkProjectRepository;
use App\Services\BaseService;
use DateTimeImmutable;

class WorkProjectService extends BaseService
{
    public function __construct(private ?WorkProjectRepository $projects = null)
    {
        $this->projects ??= new WorkProjectRepository();
    }

    public function index(array $filters = []): array
    {
        $statusOptions = $this->indexStatusOptions();
        $q = trim((string) ($filters['q'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        $q = $this->limit($q, 120);

        if (!array_key_exists($status, $statusOptions)) {
            $status = '';
        }

        $items = $this->projects->index([
            'q' => $q,
            'status' => $status,
        ]);

        foreach ($items as &$item) {
            $item = $this->decorate($item);
        }
        unset($item);

        return [
            'items' => $items,
            'total' => count($items),
            'q' => $q,
            'status' => $status,
            'status_options' => $statusOptions,
        ];
    }

    public function detail(string $publicReference): array
    {
        $project = $this->projects->findByReference(trim($publicReference));

        return $project === null
            ? ['ok' => false]
            : ['ok' => true, 'project' => $this->decorate($project)];
    }

    public function form(?string $publicReference = null): array
    {
        if ($publicReference === null) {
            return [
                'ok' => true,
                'form' => [
                    'public_reference' => '',
                    'code' => '',
                    'title' => '',
                    'description' => '',
                    'organization_reference' => '',
                    'organization_snapshot' => '',
                    'start_date' => '',
                    'target_date' => '',
                    'status_code' => 'active',
                    'visibility_code' => 'private',
                    'archived_at' => null,
                ],
                'options' => $this->formOptions(),
            ];
        }

        $project = $this->projects->findByReference(trim($publicReference));
        if ($project === null) {
            return ['ok' => false];
        }

        return [
            'ok' => true,
            'form' => $project,
            'options' => $this->formOptions(),
        ];
    }

    public function create(array $input, int $userId, array $context = []): array
    {
        $data = $this->normalize($input);
        $errors = $this->validate($data);

        if ($data['code'] !== '' && $this->projects->codeExists($data['code'])) {
            $errors['code'] = 'کد پروژه قبلاً استفاده شده است.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'form' => $data];
        }

        $actorReference = 'user:' . $userId;
        $actorDisplayName = $this->actorDisplayName($context, $userId);
        $data['public_reference'] = 'WRK-PRJ-' . strtoupper(bin2hex(random_bytes(8)));
        $data['owner_user_reference'] = $actorReference;

        try {
            $reference = $this->projects->create($data, $actorReference, $actorDisplayName);
        } catch (\PDOException $exception) {
            return [
                'ok' => false,
                'errors' => ['code' => 'ثبت پروژه انجام نشد. کد پروژه را بررسی کنید.'],
                'form' => $data,
            ];
        }

        return ['ok' => true, 'public_reference' => $reference];
    }

    public function update(string $publicReference, array $input, int $userId, array $context = []): array
    {
        $current = $this->projects->findByReference(trim($publicReference));
        if ($current === null) {
            return ['ok' => false, 'not_found' => true, 'errors' => []];
        }
        if (!empty($current['archived_at'])) {
            return [
                'ok' => false,
                'errors' => ['archived' => 'پروژه بایگانی‌شده قابل ویرایش نیست. ابتدا آن را بازیابی کنید.'],
                'form' => $current,
            ];
        }

        $data = $this->normalize($input);
        $data['public_reference'] = $publicReference;
        $errors = $this->validate($data);

        if ($data['code'] !== '' && $this->projects->codeExists($data['code'], $publicReference)) {
            $errors['code'] = 'کد پروژه قبلاً استفاده شده است.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'form' => $data];
        }

        $actorReference = 'user:' . $userId;
        $updated = $this->projects->update(
            $publicReference,
            $data,
            $actorReference,
            $this->actorDisplayName($context, $userId)
        );

        return $updated
            ? ['ok' => true, 'public_reference' => $publicReference]
            : ['ok' => false, 'errors' => ['save' => 'ذخیره تغییرات پروژه انجام نشد.'], 'form' => $data];
    }

    public function archive(string $publicReference, int $userId, array $context = []): bool
    {
        return $this->projects->archive(
            trim($publicReference),
            'user:' . $userId,
            $this->actorDisplayName($context, $userId)
        );
    }

    public function restore(string $publicReference, int $userId, array $context = []): bool
    {
        return $this->projects->restore(
            trim($publicReference),
            'user:' . $userId,
            $this->actorDisplayName($context, $userId)
        );
    }

    private function normalize(array $input): array
    {
        $code = strtolower(trim((string) ($input['code'] ?? '')));
        $code = preg_replace('/[\s_]+/u', '-', $code) ?? $code;
        $code = preg_replace('/-+/', '-', $code) ?? $code;

        return [
            'public_reference' => trim((string) ($input['public_reference'] ?? '')),
            'code' => trim($code, '-'),
            'title' => trim((string) ($input['title'] ?? '')),
            'description' => trim((string) ($input['description'] ?? '')) ?: null,
            'organization_reference' => trim((string) ($input['organization_reference'] ?? '')) ?: null,
            'organization_snapshot' => trim((string) ($input['organization_snapshot'] ?? '')) ?: null,
            'start_date' => trim((string) ($input['start_date'] ?? '')) ?: null,
            'target_date' => trim((string) ($input['target_date'] ?? '')) ?: null,
            'status_code' => trim((string) ($input['status_code'] ?? 'active')),
            'visibility_code' => trim((string) ($input['visibility_code'] ?? 'private')),
            'archived_at' => $input['archived_at'] ?? null,
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];

        if ($data['title'] === '' || $this->length((string) $data['title']) < 3) {
            $errors['title'] = 'عنوان پروژه باید حداقل ۳ نویسه باشد.';
        } elseif ($this->length((string) $data['title']) > 255) {
            $errors['title'] = 'عنوان پروژه بیش از حد مجاز است.';
        }

        if ($data['code'] === '' || preg_match('/^[a-z0-9][a-z0-9-]{1,78}[a-z0-9]$/', (string) $data['code']) !== 1) {
            $errors['code'] = 'کد پروژه باید ۳ تا ۸۰ نویسه و فقط شامل حروف انگلیسی کوچک، عدد و خط تیره باشد.';
        }

        if ($data['description'] !== null && $this->length((string) $data['description']) > 20000) {
            $errors['description'] = 'شرح پروژه بیش از حد مجاز است.';
        }

        if ($data['organization_snapshot'] !== null && $this->length((string) $data['organization_snapshot']) > 255) {
            $errors['organization_snapshot'] = 'عنوان سازمان بیش از حد مجاز است.';
        }

        foreach (['start_date' => 'تاریخ شروع', 'target_date' => 'تاریخ هدف'] as $field => $label) {
            if ($data[$field] !== null && !$this->validDate((string) $data[$field])) {
                $errors[$field] = $label . ' معتبر نیست.';
            }
        }

        if (
            !isset($errors['start_date'])
            && !isset($errors['target_date'])
            && $data['start_date'] !== null
            && $data['target_date'] !== null
            && $data['target_date'] < $data['start_date']
        ) {
            $errors['target_date'] = 'تاریخ هدف نمی‌تواند پیش از تاریخ شروع باشد.';
        }

        if (!array_key_exists((string) $data['status_code'], $this->projectStatusOptions())) {
            $errors['status_code'] = 'وضعیت پروژه معتبر نیست.';
        }

        if (!array_key_exists((string) $data['visibility_code'], $this->visibilityOptions())) {
            $errors['visibility_code'] = 'سطح دسترسی پروژه معتبر نیست.';
        }

        return $errors;
    }

    private function decorate(array $project): array
    {
        $project['status_title'] = !empty($project['archived_at'])
            ? 'بایگانی‌شده'
            : ($this->projectStatusOptions()[$project['status_code']] ?? $project['status_code']);
        $project['visibility_title'] = $this->visibilityTitle((string) ($project['visibility_code'] ?? ''));

        return $project;
    }

    private function formOptions(): array
    {
        return [
            'statuses' => $this->projectStatusOptions(),
            'visibilities' => $this->visibilityOptions(),
        ];
    }

    private function indexStatusOptions(): array
    {
        return ['' => 'همه وضعیت‌ها'] + $this->projectStatusOptions() + ['archived' => 'بایگانی‌شده'];
    }

    private function projectStatusOptions(): array
    {
        return [
            'active' => 'فعال',
            'paused' => 'متوقف',
            'completed' => 'تکمیل‌شده',
            'cancelled' => 'لغوشده',
        ];
    }

    private function visibilityOptions(): array
    {
        return [
            'private' => 'خصوصی',
            'members' => 'اعضای پروژه',
            'organization' => 'سازمانی',
            'public' => 'عمومی',
        ];
    }

    private function visibilityTitle(string $code): string
    {
        return $this->visibilityOptions()[$code] ?? 'خصوصی';
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
