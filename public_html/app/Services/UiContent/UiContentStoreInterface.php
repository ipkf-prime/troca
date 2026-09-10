<?php

declare(strict_types=1);

namespace App\Services\UiContent;

use DateTimeImmutable;


interface UiContentStoreInterface
{
    public function available(): bool;


    public function definition(
        string $contentKey
    ): ?array;


    public function activeOverrides(
        int $definitionId,
        array $scopeKeys,
        array $locales,
        DateTimeImmutable $now
    ): array;
}
