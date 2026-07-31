<?php

namespace App\Services;

use App\Repositories\UserIdentityLabelRepository;

class UserIdentityLabelService
{
    /** @var array<int,array<string,mixed>|null> */
    private array $cache = [];

    public function __construct(private ?UserIdentityLabelRepository $users = null)
    {
        $this->users ??= new UserIdentityLabelRepository();
    }

    public function labelForUserId(int $userId, array $context = [], string $fallback = ''): string
    {
        $contextUser = is_array($context['user'] ?? null) ? $context['user'] : [];
        $contextLabel = $this->labelFromRow($contextUser + $context);

        return $this->labelForReference(
            'user:' . $userId,
            $contextLabel !== '' ? $contextLabel : $fallback
        );
    }

    public function labelForReference(?string $reference, string $fallback = ''): string
    {
        $reference = trim((string) $reference);
        $userId = $this->userIdFromReference($reference);

        if ($userId !== null) {
            $rows = $this->contacts([$userId]);
            $label = $this->labelFromRow($rows[$userId] ?? []);

            if ($label !== '') {
                return $label;
            }
        }

        $fallback = $this->safeFallback($fallback);

        return $fallback !== '' ? $fallback : 'کاربر';
    }

    /**
     * @param array<string> $references
     * @param array<string,string> $fallbacks
     * @return array<string,string>
     */
    public function labelsForReferences(array $references, array $fallbacks = []): array
    {
        $normalized = [];
        $userIds = [];

        foreach ($references as $reference) {
            $reference = trim((string) $reference);
            if ($reference === '') {
                continue;
            }

            $normalized[$reference] = true;
            $userId = $this->userIdFromReference($reference);
            if ($userId !== null) {
                $userIds[] = $userId;
            }
        }

        $contacts = $this->contacts($userIds);
        $labels = [];

        foreach (array_keys($normalized) as $reference) {
            $userId = $this->userIdFromReference($reference);
            $label = $userId === null
                ? ''
                : $this->labelFromRow($contacts[$userId] ?? []);

            if ($label === '') {
                $label = $this->safeFallback((string) ($fallbacks[$reference] ?? ''));
            }

            $labels[$reference] = $label !== '' ? $label : 'کاربر';
        }

        return $labels;
    }

    public function labelFromRow(array $row): string
    {
        foreach ([
            'full_name',
            'display_name',
            'name',
            'email',
            'user_email',
            'person_email',
            'mobile',
            'user_mobile',
            'person_mobile',
            'username',
        ] as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '' && !$this->looksLikeInternalId($value)) {
                return $value;
            }
        }

        return '';
    }

    public function optionLabelFromRow(array $row): string
    {
        $fullName = $this->firstValue($row, ['full_name', 'display_name', 'name']);
        $email = $this->firstValue($row, ['email', 'user_email', 'person_email']);
        $mobile = $this->firstValue($row, ['mobile', 'user_mobile', 'person_mobile']);

        if ($fullName !== '') {
            $contact = $email !== '' ? $email : $mobile;

            return $contact !== ''
                ? $fullName . ' — ' . $contact
                : $fullName;
        }

        if ($email !== '' && $mobile !== '') {
            return $email . ' — ' . $mobile;
        }

        return $email !== ''
            ? $email
            : ($mobile !== '' ? $mobile : ($this->labelFromRow($row) ?: 'کاربر'));
    }

    /**
     * @param array<int> $userIds
     * @return array<int,array<string,mixed>>
     */
    private function contacts(array $userIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $userIds),
            static fn (int $id): bool => $id > 0
        )));

        $missing = array_values(array_filter(
            $ids,
            fn (int $id): bool => !array_key_exists($id, $this->cache)
        ));

        if ($missing !== []) {
            try {
                $resolved = $this->users->contactsByUserIds($missing);
            } catch (\Throwable) {
                $resolved = [];
            }

            foreach ($missing as $id) {
                $this->cache[$id] = $resolved[$id] ?? null;
            }
        }

        $contacts = [];
        foreach ($ids as $id) {
            if (is_array($this->cache[$id] ?? null)) {
                $contacts[$id] = $this->cache[$id];
            }
        }

        return $contacts;
    }

    private function userIdFromReference(string $reference): ?int
    {
        if (preg_match('/^user:(\d+)$/', $reference, $matches) !== 1) {
            return null;
        }

        $id = (int) $matches[1];

        return $id > 0 ? $id : null;
    }

    private function safeFallback(string $fallback): string
    {
        $fallback = trim($fallback);

        return $this->looksLikeInternalId($fallback) ? '' : $fallback;
    }

    private function looksLikeInternalId(string $value): bool
    {
        return preg_match(
            '/^(?:user\s*:\s*\d+|کاربر\s*(?:#|شماره)?\s*\d+)$/iu',
            trim($value)
        ) === 1;
    }

    private function firstValue(array $row, array $fields): string
    {
        foreach ($fields as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
