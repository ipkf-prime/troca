<?php

declare(strict_types=1);

namespace App\Services\Work;

use App\Repositories\TicketWorkPolicyAdminRepository;
use RuntimeException;

/**
 * TICKET_WORK_POLICY_ADMIN_SERVICE_V1
 *
 * Schema-driven, non-destructive administration. No default policy rule is
 * created here. Existing policy resolver semantics remain authoritative.
 */
final class TicketWorkPolicyAdminService
{
    public function __construct(
        private ?TicketWorkPolicyAdminRepository $repository = null
    ) {
        $this->repository ??=
            new TicketWorkPolicyAdminRepository();
    }

    public function page(): array
    {
        return [
            'destination' =>
                $this->kindPage('destination'),
            'access' =>
                $this->kindPage('access'),
            'lifecycle' =>
                $this->kindPage('lifecycle'),
        ];
    }

    public function save(
        string $kind,
        ?int $id,
        array $input,
        int $actorUserId
    ): array {
        try {
            $schema = $this->repository->schema($kind);
            $values =
                $this->normalize(
                    $kind,
                    $schema,
                    is_array($input['fields'] ?? null)
                        ? $input['fields']
                        : [],
                    $actorUserId,
                    $id === null
                );

            if ($id === null) {
                $id =
                    $this->repository->insert(
                        $kind,
                        $values
                    );
            } else {
                if ($this->repository->find($kind, $id) === null) {
                    return [
                        'ok' => false,
                        'status' => 'invalid',
                    ];
                }

                $this->repository->update(
                    $kind,
                    $id,
                    $values
                );
            }

            return [
                'ok' => true,
                'status' => 'saved',
                'id' => $id,
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'status' =>
                    $exception instanceof RuntimeException
                        ? 'invalid'
                        : 'failed',
            ];
        }
    }

    public function setActive(
        string $kind,
        int $id,
        bool $active
    ): array {
        try {
            if ($this->repository->find($kind, $id) === null) {
                return [
                    'ok' => false,
                    'status' => 'invalid',
                ];
            }

            $this->repository->setActive(
                $kind,
                $id,
                $active
            );

            return [
                'ok' => true,
                'status' =>
                    $active
                        ? 'restored'
                        : 'deactivated',
            ];
        } catch (\Throwable) {
            return [
                'ok' => false,
                'status' => 'failed',
            ];
        }
    }

    private function kindPage(string $kind): array
    {
        $schema = $this->repository->schema($kind);

        return [
            'schema' => $schema,
            'editable_columns' =>
                $this->editableColumns($schema),
            'field_options' =>
                $this->fieldOptions($kind),
            'rows' =>
                $this->repository->rows($kind),
        ];
    }

    private function fieldOptions(string $kind): array
    {
        if ($kind !== 'lifecycle') {
            return [];
        }

        $projects = [];

        foreach (
            $this->repository->workProjects()
            as $row
        ) {
            $id =
                (int) ($row['id'] ?? 0);

            if ($id < 1) {
                continue;
            }

            $title =
                trim(
                    (string) (
                        $row['title']
                        ?? ''
                    )
                );

            $code =
                trim(
                    (string) (
                        $row['code']
                        ?? ''
                    )
                );

            $projects[(string) $id] =
                $title !== ''
                    ? (
                        $code !== ''
                            ? $title . ' (' . $code . ')'
                            : $title
                    )
                    : (string) $id;
        }

        $statuses = [];

        foreach (
            $this->repository->workStatuses()
            as $row
        ) {
            $id =
                (int) ($row['id'] ?? 0);

            if ($id < 1) {
                continue;
            }

            $title =
                trim(
                    (string) (
                        $row['title']
                        ?? ''
                    )
                );

            $code =
                trim(
                    (string) (
                        $row['code']
                        ?? ''
                    )
                );

            $statuses[(string) $id] =
                $title !== ''
                    ? (
                        $code !== ''
                            ? $title . ' (' . $code . ')'
                            : $title
                    )
                    : (string) $id;
        }

        return [
            'work_project_id' =>
                $projects,
            'work_status_id' =>
                $statuses,
        ];
    }

    private function editableColumns(array $schema): array
    {
        $result = [];

        foreach ($schema as $column) {
            $name =
                trim(
                    (string) ($column['Field'] ?? '')
                );

            if (
                $name === ''
                || $name === 'id'
                || $this->isManagedColumn($name)
                || str_contains(
                    strtolower(
                        (string) ($column['Extra'] ?? '')
                    ),
                    'auto_increment'
                )
            ) {
                continue;
            }

            $result[] = [
                'name' => $name,
                'type' =>
                    (string) ($column['Type'] ?? ''),
                'nullable' =>
                    strtoupper(
                        (string) ($column['Null'] ?? '')
                    ) === 'YES',
                'default' =>
                    $column['Default'] ?? null,
            ];
        }

        return $result;
    }

    private function normalize(
        string $kind,
        array $schema,
        array $posted,
        int $actorUserId,
        bool $creating
    ): array {
        $values = [];
        $actorReference = 'user:' . max(0, $actorUserId);

        foreach ($schema as $column) {
            $name =
                trim(
                    (string) ($column['Field'] ?? '')
                );

            if ($name === '' || $name === 'id') {
                continue;
            }

            $extra =
                strtolower(
                    (string) ($column['Extra'] ?? '')
                );

            if (str_contains($extra, 'auto_increment')) {
                continue;
            }

            if ($this->isCreatedActorColumn($name)) {
                if ($creating) {
                    $values[$name] = $actorReference;
                }
                continue;
            }

            if ($this->isUpdatedActorColumn($name)) {
                $values[$name] = $actorReference;
                continue;
            }

            if (
                in_array(
                    $name,
                    ['created_at', 'updated_at', 'archived_at', 'deleted_at'],
                    true
                )
            ) {
                continue;
            }

            if (
                in_array(
                    $name,
                    ['public_reference', 'rule_reference'],
                    true
                )
            ) {
                if ($creating) {
                    $prefix =
                        match ($kind) {
                            'destination' =>
                                'WTDR-',
                            'access' =>
                                'WTAR-',
                            'lifecycle' =>
                                'TWLSR-',
                            default =>
                                throw new RuntimeException(
                                    'Ticket Work policy kind is invalid.'
                                ),
                        };

                    $values[$name] =
                        $prefix
                        . strtoupper(
                            bin2hex(random_bytes(10))
                        );
                }

                continue;
            }

            if (!array_key_exists($name, $posted)) {
                if (!$creating) {
                    continue;
                }

                $default = $column['Default'] ?? null;
                $nullable =
                    strtoupper(
                        (string) ($column['Null'] ?? '')
                    ) === 'YES';

                if ($default !== null || $nullable) {
                    continue;
                }

                if (in_array($name, ['is_active', 'enabled'], true)) {
                    $values[$name] = 1;
                    continue;
                }

                if (in_array($name, ['status', 'status_code'], true)) {
                    $values[$name] = 'active';
                    continue;
                }

                if (in_array($name, ['priority', 'specificity', 'sort_order'], true)) {
                    $values[$name] = 0;
                    continue;
                }

                throw new RuntimeException(
                    'Required policy field is missing: ' . $name
                );
            }

            $value = $posted[$name];

            if (is_array($value)) {
                throw new RuntimeException(
                    'Policy field must be scalar: ' . $name
                );
            }

            $value = trim((string) $value);

            $nullable =
                strtoupper(
                    (string) ($column['Null'] ?? '')
                ) === 'YES';

            if ($value === '' && $nullable) {
                $values[$name] = null;
                continue;
            }

            if (
                in_array(
                    $name,
                    ['is_active', 'enabled'],
                    true
                )
            ) {
                $values[$name] =
                    in_array(
                        strtolower($value),
                        ['1', 'true', 'yes', 'on', 'active'],
                        true
                    )
                        ? 1
                        : 0;
                continue;
            }

            $type =
                strtolower(
                    (string) ($column['Type'] ?? '')
                );

            if (
                preg_match(
                    '/^(tinyint|smallint|mediumint|int|bigint)/',
                    $type
                ) === 1
                && $value !== ''
            ) {
                if (preg_match('/^-?[0-9]+$/', $value) !== 1) {
                    throw new RuntimeException(
                        'Policy integer field is invalid: ' . $name
                    );
                }

                $values[$name] = (int) $value;
                continue;
            }

            $values[$name] = $value;
        }

        return $values;
    }

    private function isManagedColumn(string $name): bool
    {
        return
            in_array(
                $name,
                [
                    'public_reference',
                    'rule_reference',
                    'created_at',
                    'updated_at',
                    'archived_at',
                    'deleted_at',
                    'created_by_user_reference',
                    'updated_by_user_reference',
                    'created_by_reference',
                    'updated_by_reference',
                ],
                true
            );
    }

    private function isCreatedActorColumn(string $name): bool
    {
        return
            in_array(
                $name,
                [
                    'created_by_user_reference',
                    'created_by_reference',
                ],
                true
            );
    }

    private function isUpdatedActorColumn(string $name): bool
    {
        return
            in_array(
                $name,
                [
                    'updated_by_user_reference',
                    'updated_by_reference',
                ],
                true
            );
    }
}
