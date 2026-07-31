<?php

namespace App\Services;

class BaseService implements ServiceInterface
{
    protected array $data = [];

    private ?UserIdentityLabelService $userIdentityLabels = null;

    public function set(string $key, mixed $value): static
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function all(): array
    {
        return $this->data;
    }

    protected function userIdentityLabel(
        int $userId,
        array $context = [],
        string $fallback = ''
    ): string {
        $this->userIdentityLabels ??= new UserIdentityLabelService();

        return $this->userIdentityLabels->labelForUserId(
            $userId,
            $context,
            $fallback
        );
    }
}
