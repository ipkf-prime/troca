<?php

declare(strict_types=1);

namespace App\Services\ProjectSources;

use Closure;

/**
 * PROJECT_SOURCE_CANONICAL_PERSISTENCE_REPOSITORY_CONTRACT_V1
 *
 * Trusted repository boundary for atomic staged-source materialization.
 */
interface ProjectSourceCanonicalPersistenceRepositoryInterface
{
    public function transaction(
        Closure $operation
    ): mixed;

    public function lockApplyContext(
        int $projectId,
        string $sourceCode,
        int $runId
    ): ?array;

    public function stagedBatch(
        array $context
    ): array;

    public function existingCanonical(
        int $dimensionId
    ): array;

    public function persistPlan(
        array $context,
        array $plan,
        string $actorReference
    ): array;

    public function markRunApplied(
        int $runId,
        string $snapshotToken,
        int $itemCount
    ): void;

    public function recordApplyFailure(
        int $runId,
        string $message
    ): void;
}
