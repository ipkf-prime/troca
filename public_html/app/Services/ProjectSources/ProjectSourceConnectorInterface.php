<?php

declare(strict_types=1);

namespace App\Services\ProjectSources;

/**
 * PROJECT_SOURCE_CONNECTOR_CONTRACT_V1
 *
 * Generic, transport-agnostic project source contract.
 *
 * Catalog page shape:
 * [
 *   'items' => [
 *     [
 *       'source_reference' => '...',
 *       'title' => '...',
 *       'parent_source_reference' => null,
 *       'status' => 'active',
 *       'attributes' => [],
 *     ],
 *   ],
 *   'next_cursor' => null,
 *   'snapshot_token' => null,
 * ]
 */
interface ProjectSourceConnectorInterface
{
    public function driverCode(): string;

    public function capabilities(): array;

    public function health(): array;

    public function catalog(
        string $catalogCode,
        ?string $cursor = null,
        int $limit = 200
    ): array;
}
