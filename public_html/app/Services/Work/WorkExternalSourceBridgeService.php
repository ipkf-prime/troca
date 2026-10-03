<?php

declare(strict_types=1);

namespace App\Services\Work;

use App\Repositories\WorkExternalSourceBridgeRepository;
use App\Services\BaseService;

class WorkExternalSourceBridgeService extends BaseService
{
    public function __construct(
        private ?WorkExternalSourceBridgeRepository $repository = null
    ) {
        $this->repository ??= new WorkExternalSourceBridgeRepository();
    }

    public function bindings(
        string $moduleCode,
        string $resourceType,
        string $sourceReference,
        string $bindingRoleCode = 'default'
    ): array {
        $identity = $this->sourceIdentity(
            $moduleCode,
            $resourceType,
            $sourceReference
        );

        return $this->repository->bindingsForSource(
            $identity['module_code'],
            $identity['resource_type'],
            $identity['source_reference'],
            $this->code($bindingRoleCode, 'binding role')
        );
    }

    public function bindProject(
        string $projectReference,
        string $moduleCode,
        string $resourceType,
        string $sourceReference,
        string $bindingRoleCode = 'default',
        bool $isPrimary = true,
        ?string $actorUserReference = null,
        array $metadata = []
    ): array {
        $projectReference = $this->reference(
            $projectReference,
            'work project reference'
        );
        $identity = $this->sourceIdentity(
            $moduleCode,
            $resourceType,
            $sourceReference
        );
        $bindingRoleCode = $this->code(
            $bindingRoleCode,
            'binding role'
        );

        $project = $this->repository->projectByReference($projectReference);

        if ($project === null || !empty($project['archived_at'])) {
            throw new \RuntimeException('Work project is unavailable.');
        }

        $metadataJson = $this->metadata($metadata);

        return $this->repository->transaction(
            function (WorkExternalSourceBridgeRepository $repository) use (
                $project,
                $identity,
                $bindingRoleCode,
                $isPrimary,
                $actorUserReference,
                $metadataJson
            ): array {
                $reference = $this->newReference('WPSB');

                $id = $repository->createProjectBinding(
                    (int) $project['id'],
                    $reference,
                    $identity['module_code'],
                    $identity['resource_type'],
                    $identity['source_reference'],
                    $bindingRoleCode,
                    $isPrimary,
                    $this->nullableReference($actorUserReference),
                    $metadataJson
                );

                return [
                    'id' => $id,
                    'public_reference' => $reference,
                    'work_project_reference' => $project['public_reference'],
                    'source_module_code' => $identity['module_code'],
                    'source_resource_type' => $identity['resource_type'],
                    'source_reference' => $identity['source_reference'],
                    'binding_role_code' => $bindingRoleCode,
                    'is_primary' => $isPrimary,
                ];
            }
        );
    }

    public function linkItem(
        string $projectReference,
        string $itemReference,
        string $moduleCode,
        string $resourceType,
        string $sourceReference,
        string $relationTypeCode = 'related_to',
        ?string $actorUserReference = null,
        array $metadata = []
    ): array {
        $projectReference = $this->reference(
            $projectReference,
            'work project reference'
        );
        $itemReference = $this->reference(
            $itemReference,
            'work item reference'
        );
        $identity = $this->sourceIdentity(
            $moduleCode,
            $resourceType,
            $sourceReference
        );
        $relationTypeCode = $this->code(
            $relationTypeCode,
            'relation type'
        );

        $project = $this->repository->projectByReference($projectReference);

        if ($project === null || !empty($project['archived_at'])) {
            throw new \RuntimeException('Work project is unavailable.');
        }

        $item = $this->repository->itemByReference(
            (int) $project['id'],
            $itemReference
        );

        if ($item === null || !empty($item['archived_at'])) {
            throw new \RuntimeException('Work item is unavailable.');
        }

        $metadataJson = $this->metadata($metadata);

        return $this->repository->transaction(
            function (WorkExternalSourceBridgeRepository $repository) use (
                $project,
                $item,
                $identity,
                $relationTypeCode,
                $actorUserReference,
                $metadataJson
            ): array {
                $reference = $this->newReference('WISL');

                $id = $repository->createItemSourceLink(
                    (int) $item['id'],
                    $reference,
                    $identity['module_code'],
                    $identity['resource_type'],
                    $identity['source_reference'],
                    $relationTypeCode,
                    $this->nullableReference($actorUserReference),
                    $metadataJson
                );

                return [
                    'id' => $id,
                    'public_reference' => $reference,
                    'work_project_reference' => $project['public_reference'],
                    'work_item_reference' => $item['public_reference'],
                    'source_module_code' => $identity['module_code'],
                    'source_resource_type' => $identity['resource_type'],
                    'source_reference' => $identity['source_reference'],
                    'relation_type_code' => $relationTypeCode,
                ];
            }
        );
    }

    public function linkedItems(
        string $moduleCode,
        string $resourceType,
        string $sourceReference
    ): array {
        $identity = $this->sourceIdentity(
            $moduleCode,
            $resourceType,
            $sourceReference
        );

        return $this->repository->linkedItemsForSource(
            $identity['module_code'],
            $identity['resource_type'],
            $identity['source_reference']
        );
    }

    private function sourceIdentity(
        string $moduleCode,
        string $resourceType,
        string $sourceReference
    ): array {
        return [
            'module_code' => $this->code($moduleCode, 'source module'),
            'resource_type' => $this->code($resourceType, 'source resource type'),
            'source_reference' => $this->reference(
                $sourceReference,
                'source reference',
                191
            ),
        ];
    }

    private function code(string $value, string $field): string
    {
        $value = strtolower(trim($value));

        if (
            $value === ''
            || strlen($value) > 64
            || preg_match('/^[a-z0-9][a-z0-9._-]*$/', $value) !== 1
        ) {
            throw new \InvalidArgumentException(
                'Invalid ' . $field . ' code.'
            );
        }

        return $value;
    }

    private function reference(
        string $value,
        string $field,
        int $maxLength = 128
    ): string {
        $value = trim($value);

        if ($value === '' || strlen($value) > $maxLength) {
            throw new \InvalidArgumentException(
                'Invalid ' . $field . '.'
            );
        }

        return $value;
    }

    private function nullableReference(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $this->reference($value, 'actor user reference');
    }

    private function metadata(array $metadata): ?string
    {
        if ($metadata === []) {
            return null;
        }

        $json = json_encode(
            $metadata,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );

        return $json;
    }

    private function newReference(string $prefix): string
    {
        return $prefix . '-' . strtoupper(bin2hex(random_bytes(12)));
    }
}
