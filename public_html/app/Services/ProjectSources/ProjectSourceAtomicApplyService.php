<?php

declare(strict_types=1);

namespace App\Services\ProjectSources;

use App\Repositories\ProjectSourceCanonicalPersistenceRepository;
use DomainException;
use Throwable;

/**
 * PROJECT_SOURCE_ATOMIC_APPLY_SERVICE_V1
 *
 * Executes the A10.1 validation/planning contract inside one trusted
 * repository transaction.
 *
 * No connector/network call occurs here.
 */
final class ProjectSourceAtomicApplyService
{
    public function __construct(
        private ?ProjectSourceCanonicalPersistenceRepositoryInterface
            $repository = null,
        private ?ProjectSourceCanonicalMaterializationPlanner
            $planner = null
    ) {
        $this->repository ??=
            new ProjectSourceCanonicalPersistenceRepository();

        $this->planner ??=
            new ProjectSourceCanonicalMaterializationPlanner();
    }


    public function applyValidatedRun(
        int $projectId,
        string $sourceCode,
        int $runId,
        string $actorReference =
            'system:project-source'
    ): array {
        if (
            $projectId < 1
            || $runId < 1
            || trim($sourceCode) === ''
        ) {
            throw new DomainException(
                'Trusted project/source/run identity is required.'
            );
        }

        try {
            return
                $this->repository
                    ->transaction(
                        function () use (
                            $projectId,
                            $sourceCode,
                            $runId,
                            $actorReference
                        ): array {
                            $context =
                                $this->repository
                                    ->lockApplyContext(
                                        $projectId,
                                        trim(
                                            $sourceCode
                                        ),
                                        $runId
                                    );

                            if (!is_array($context)) {
                                throw new DomainException(
                                    'Trusted project source apply context was not found.'
                                );
                            }

                            $sourceConfig = [
                                'project_id' =>
                                    (int) $context[
                                        'project_id'
                                    ],

                                'code' =>
                                    (string) $context[
                                        'source_code'
                                    ],

                                'dimension_code' =>
                                    (string) $context[
                                        'dimension_code'
                                    ],
                            ];

                            $dimensionContext = [
                                'id' =>
                                    (int) $context[
                                        'dimension_id'
                                    ],

                                'project_id' =>
                                    (int) $context[
                                        'dimension_project_id'
                                    ],

                                'code' =>
                                    (string) $context[
                                        'dimension_code_resolved'
                                    ],

                                'status' =>
                                    (string) $context[
                                        'dimension_status'
                                    ],

                                'source_mode_code' =>
                                    (string) $context[
                                        'source_mode_code'
                                    ],

                                'source_key' =>
                                    (string) (
                                        $context[
                                            'source_key'
                                        ]
                                        ?? ''
                                    ),

                                'value_kind_code' =>
                                    (string) $context[
                                        'value_kind_code'
                                    ],

                                'hierarchy_mode_code' =>
                                    (string) $context[
                                        'hierarchy_mode_code'
                                    ],

                                'supports_descendants' =>
                                    (int) (
                                        $context[
                                            'supports_descendants'
                                        ]
                                        ?? 0
                                    ),
                            ];

                            $batch =
                                $this->repository
                                    ->stagedBatch(
                                        $context
                                    );

                            $existing =
                                $this->repository
                                    ->existingCanonical(
                                        (int) $context[
                                            'dimension_id'
                                        ]
                                    );

                            $plan =
                                $this->planner
                                    ->plan(
                                        $sourceConfig,
                                        $dimensionContext,
                                        $batch,
                                        $existing
                                    );

                            $persistence =
                                $this->repository
                                    ->persistPlan(
                                        $context,
                                        $plan,
                                        $actorReference
                                    );

                            $this->repository
                                ->markRunApplied(
                                    $runId,
                                    (string) $plan[
                                        'snapshot_token'
                                    ],
                                    count(
                                        $plan[
                                            'upserts'
                                        ]
                                    )
                                );

                            return [
                                'ok' => true,
                                'run_id' =>
                                    $runId,

                                'source_code' =>
                                    trim(
                                        $sourceCode
                                    ),

                                'snapshot_token' =>
                                    (string) $plan[
                                        'snapshot_token'
                                    ],

                                'persistence' =>
                                    $persistence,
                            ];
                        }
                    );

        } catch (Throwable $exception) {
            try {
                $this->repository
                    ->recordApplyFailure(
                        $runId,
                        $exception->getMessage()
                    );
            } catch (Throwable) {
                /*
                 * Original failure remains authoritative.
                 */
            }

            throw $exception;
        }
    }
}
