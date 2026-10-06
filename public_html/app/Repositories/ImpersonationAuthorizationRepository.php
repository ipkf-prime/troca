<?php

declare(strict_types=1);

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

final class ImpersonationAuthorizationRepository
{
    private const GEOGRAPHIC_TYPES = [
        'province',
        'county',
        'district',
        'village',
        'city',
    ];

    private const SCOPE_TABLES = [
        'province' => [
            'provinces',
        ],
        'county' => [
            'counties',
            'shahrestans',
        ],
        'district' => [
            'districts',
            'bakhshs',
        ],
        'village' => [
            'villages',
            'dehestans',
        ],
        'city' => [
            'cities',
        ],
    ];

    private const PARENT_TYPES = [
        'county' => [
            'province' => [
                'province_id',
            ],
        ],
        'district' => [
            'county' => [
                'county_id',
                'shahrestan_id',
            ],
            'province' => [
                'province_id',
            ],
        ],
        'village' => [
            'district' => [
                'district_id',
                'bakhsh_id',
            ],
            'county' => [
                'county_id',
                'shahrestan_id',
            ],
            'province' => [
                'province_id',
            ],
        ],
        'city' => [
            'county' => [
                'county_id',
                'shahrestan_id',
            ],
            'province' => [
                'province_id',
            ],
        ],
    ];

    private PDO $db;


    public function __construct(
        ?PDO $db = null
    ) {
        $this->db =
            $db
            ?? (
                new ConnectionResolver()
            )->resolve(
                'core.primary'
            );
    }


    public function actorAssignment(
        int $userId,
        int $assignmentId
    ): ?array {
        if (
            $userId < 1
            || $assignmentId < 1
        ) {
            return null;
        }

        $lifecycleFilter =
            $this->columnExists(
                'user_role_assignments',
                'lifecycle_status_code'
            )
                ? "
                  AND (
                        assignments.lifecycle_status_code IS NULL
                        OR assignments.lifecycle_status_code = 'active'
                      )"
                : '';

        $statement =
            $this->db->prepare("
                SELECT
                    assignments.id,
                    assignments.user_id,
                    assignments.role_id,
                    roles.priority,
                    roles.can_manage_other_users
                FROM user_role_assignments
                    AS assignments
                INNER JOIN roles
                    ON roles.id =
                        assignments.role_id
                WHERE assignments.id = ?
                  AND assignments.user_id = ?
                  AND assignments.is_active = 1
                  AND roles.is_active = 1
                  {$lifecycleFilter}
                  AND (
                        assignments.starts_at IS NULL
                        OR assignments.starts_at
                            <= CURRENT_TIMESTAMP
                      )
                  AND (
                        assignments.ends_at IS NULL
                        OR assignments.ends_at
                            >= CURRENT_TIMESTAMP
                      )
                LIMIT 1
            ");

        $statement->execute([
            $assignmentId,
            $userId,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        if (!is_array($row)) {
            return null;
        }

        return [
            'id' =>
                (int) $row['id'],

            'user_id' =>
                (int) $row['user_id'],

            'role_id' =>
                (int) $row['role_id'],

            'priority' =>
                (int) $row['priority'],

            'can_manage_other_users' =>
                (bool) $row[
                    'can_manage_other_users'
                ],
        ];
    }


    public function permissionForAssignment(
        int $userId,
        int $assignmentId,
        string $permissionCode
    ): bool {
        if (
            $userId < 1
            || $assignmentId < 1
            || trim($permissionCode) === ''
        ) {
            return false;
        }

        $override =
            $this->permissionOverride(
                $userId,
                $assignmentId,
                $permissionCode
            );

        if ($override !== null) {
            return
                $override === 'allow';
        }

        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM user_role_assignments
                    AS assignments
                INNER JOIN roles
                    ON roles.id =
                        assignments.role_id
                INNER JOIN role_permissions
                    ON role_permissions.role_id =
                        assignments.role_id
                INNER JOIN permissions
                    ON permissions.id =
                        role_permissions.permission_id
                WHERE assignments.id = ?
                  AND assignments.user_id = ?
                  AND assignments.is_active = 1
                  AND roles.is_active = 1
                  AND permissions.is_active = 1
                  AND permissions.code = ?
                  AND (
                        assignments.starts_at IS NULL
                        OR assignments.starts_at
                            <= CURRENT_TIMESTAMP
                      )
                  AND (
                        assignments.ends_at IS NULL
                        OR assignments.ends_at
                            >= CURRENT_TIMESTAMP
                      )
            ");

        $statement->execute([
            $assignmentId,
            $userId,
            $permissionCode,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }


    public function targetUser(
        int $userId
    ): ?array {
        if ($userId < 1) {
            return null;
        }

        $statement =
            $this->db->prepare("
                SELECT
                    id,
                    status,
                    locked_until
                FROM users
                WHERE id = ?
                  AND deleted_at IS NULL
                LIMIT 1
            ");

        $statement->execute([
            $userId,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        if (!is_array($row)) {
            return null;
        }

        $locked =
            !empty(
                $row['locked_until']
            )
            && strtotime(
                (string) $row[
                    'locked_until'
                ]
            ) > time();

        return [
            'id' =>
                (int) $row['id'],

            'eligible' =>
                (string) (
                    $row['status']
                    ?? ''
                ) === 'active'
                && !$locked,
        ];
    }


    public function activeAssignmentsForUser(
        int $userId
    ): array {
        if ($userId < 1) {
            return [];
        }

        $lifecycleFilter =
            $this->columnExists(
                'user_role_assignments',
                'lifecycle_status_code'
            )
                ? "
                  AND (
                        assignments.lifecycle_status_code IS NULL
                        OR assignments.lifecycle_status_code = 'active'
                      )"
                : '';

        $statement =
            $this->db->prepare("
                SELECT
                    assignments.id,
                    assignments.role_id,
                    roles.priority,
                    (
                        SELECT COUNT(*)
                        FROM role_scope_policies
                            AS policies
                        WHERE policies.role_id =
                            roles.id
                          AND policies.is_required = 1
                    ) AS required_scope_count
                FROM user_role_assignments
                    AS assignments
                INNER JOIN roles
                    ON roles.id =
                        assignments.role_id
                WHERE assignments.user_id = ?
                  AND assignments.is_active = 1
                  AND roles.is_active = 1
                  {$lifecycleFilter}
                  AND (
                        assignments.starts_at IS NULL
                        OR assignments.starts_at
                            <= CURRENT_TIMESTAMP
                      )
                  AND (
                        assignments.ends_at IS NULL
                        OR assignments.ends_at
                            >= CURRENT_TIMESTAMP
                      )
                ORDER BY
                    roles.priority DESC,
                    assignments.id
            ");

        $statement->execute([
            $userId,
        ]);

        $result = [];

        foreach (
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: []
            as $row
        ) {
            $assignmentId =
                (int) $row['id'];

            $result[] = [
                'id' =>
                    $assignmentId,

                'role_id' =>
                    (int) $row['role_id'],

                'priority' =>
                    (int) $row['priority'],

                'requires_scope' =>
                    (int) $row[
                        'required_scope_count'
                    ] > 0,

                'scopes' =>
                    $this->scopesForAssignment(
                        $assignmentId
                    ),
            ];
        }

        return $result;
    }


    public function scopesForAssignment(
        int $assignmentId
    ): array {
        if (
            $assignmentId < 1
            || !$this->tableExists(
                'role_assignment_scopes'
            )
        ) {
            return [];
        }

        $statement =
            $this->db->prepare("
                SELECT
                    scope_type_code,
                    scope_reference,
                    effect_code,
                    include_descendants
                FROM role_assignment_scopes
                WHERE role_assignment_id = ?
                ORDER BY
                    CASE effect_code
                        WHEN 'deny' THEN 0
                        ELSE 1
                    END,
                    id
            ");

        $statement->execute([
            $assignmentId,
        ]);

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        $result = [];

        foreach ($rows as $row) {
            $type =
                strtolower(
                    trim(
                        (string) (
                            $row[
                                'scope_type_code'
                            ]
                            ?? ''
                        )
                    )
                );

            $reference =
                trim(
                    (string) (
                        $row[
                            'scope_reference'
                        ]
                        ?? ''
                    )
                );

            $context = [
                'scope_type_code' =>
                    $type,

                'scope_reference' =>
                    $reference,

                'effect_code' =>
                    strtolower(
                        trim(
                            (string) (
                                $row[
                                    'effect_code'
                                ]
                                ?? ''
                            )
                        )
                    ),

                'include_descendants' =>
                    !empty(
                        $row[
                            'include_descendants'
                        ]
                    ),

                'reference_aliases' =>
                    $reference !== ''
                        ? [$reference]
                        : [],

                'ancestors' =>
                    [],
            ];

            if (
                in_array(
                    $type,
                    self::GEOGRAPHIC_TYPES,
                    true
                )
                && $reference !== ''
            ) {
                $resolved =
                    $this->geographicScopeContext(
                        $type,
                        $reference
                    );

                if ($resolved !== null) {
                    $context[
                        'reference_aliases'
                    ] =
                        $resolved[
                            'reference_aliases'
                        ];

                    $context[
                        'ancestors'
                    ] =
                        $resolved[
                            'ancestors'
                        ];
                }
            }

            $result[] =
                $context;
        }

        return $result;
    }


    private function permissionOverride(
        int $userId,
        int $assignmentId,
        string $permissionCode
    ): ?string {
        if (
            !$this->tableExists(
                'user_permission_overrides'
            )
        ) {
            return null;
        }

        $statement =
            $this->db->prepare("
                SELECT
                    overrides.effect_code
                FROM user_permission_overrides
                    AS overrides
                INNER JOIN permissions
                    ON permissions.id =
                        overrides.permission_id
                WHERE overrides.user_id = ?
                  AND permissions.code = ?
                  AND permissions.is_active = 1
                  AND overrides.role_assignment_id
                        IN (0, ?)
                ORDER BY
                    overrides.role_assignment_id
                        DESC
                LIMIT 1
            ");

        $statement->execute([
            $userId,
            $permissionCode,
            $assignmentId,
        ]);

        $effect =
            $statement->fetchColumn();

        return in_array(
            $effect,
            [
                'allow',
                'deny',
            ],
            true
        )
            ? (string) $effect
            : null;
    }


    private function geographicScopeContext(
        string $type,
        string $reference
    ): ?array {
        $entity =
            $this->scopeEntity(
                $type,
                $reference
            );

        if ($entity === null) {
            return null;
        }

        $ancestors = [];
        $visited = [];

        $currentType = $type;
        $currentEntity = $entity;

        for (
            $depth = 0;
            $depth < 8;
            $depth++
        ) {
            $visitKey =
                $currentType
                . ':'
                . (
                    $currentEntity[
                        'table'
                    ]
                    ?? ''
                )
                . ':'
                . (
                    $currentEntity[
                        'id'
                    ]
                    ?? ''
                );

            if (isset($visited[$visitKey])) {
                return null;
            }

            $visited[$visitKey] =
                true;

            $parent =
                $this->parentEntity(
                    $currentType,
                    $currentEntity
                );

            if ($parent === null) {
                break;
            }

            $parentType =
                (string) $parent[
                    'type'
                ];

            $parentEntity =
                $parent[
                    'entity'
                ];

            $ancestors[
                $parentType
            ] =
                $parentEntity[
                    'aliases'
                ];

            $currentType =
                $parentType;

            $currentEntity =
                $parentEntity;
        }

        return [
            'scope_type_code' =>
                $type,

            'scope_reference' =>
                $reference,

            'reference_aliases' =>
                $entity['aliases'],

            'ancestors' =>
                $ancestors,
        ];
    }


    private function parentEntity(
        string $type,
        array $entity
    ): ?array {
        $parents =
            self::PARENT_TYPES[
                $type
            ]
            ?? [];

        if ($parents === []) {
            return null;
        }

        $table =
            (string) (
                $entity['table']
                ?? ''
            );

        $id =
            (int) (
                $entity['id']
                ?? 0
            );

        if (
            $table === ''
            || $id < 1
        ) {
            return null;
        }

        $columns =
            $this->columns(
                $table
            );

        foreach (
            $parents
            as $parentType =>
                $candidateColumns
        ) {
            foreach (
                $candidateColumns
                as $column
            ) {
                if (
                    !isset(
                        $columns[
                            $column
                        ]
                    )
                ) {
                    continue;
                }

                $statement =
                    $this->db->prepare(
                        "SELECT `{$column}`
                         FROM `{$table}`
                         WHERE id = ?
                         LIMIT 1"
                    );

                $statement->execute([
                    $id,
                ]);

                $parentId =
                    (int) (
                        $statement->fetchColumn()
                        ?: 0
                    );

                if ($parentId < 1) {
                    continue;
                }

                $parent =
                    $this->scopeEntityById(
                        (string) $parentType,
                        $parentId
                    );

                if ($parent !== null) {
                    return [
                        'type' =>
                            (string) $parentType,

                        'entity' =>
                            $parent,
                    ];
                }
            }
        }

        return null;
    }


    private function scopeEntity(
        string $type,
        string $reference
    ): ?array {
        $tables =
            self::SCOPE_TABLES[
                $type
            ]
            ?? [];

        foreach ($tables as $table) {
            if (
                !$this->tableExists(
                    $table
                )
            ) {
                continue;
            }

            $columns =
                $this->columns(
                    $table
                );

            $referenceColumns =
                $this->referenceColumns(
                    $columns
                );

            foreach (
                $referenceColumns
                as $column
            ) {
                $statement =
                    $this->db->prepare(
                        "SELECT *
                         FROM `{$table}`
                         WHERE `{$column}` = ?
                         LIMIT 2"
                    );

                $statement->execute([
                    $reference,
                ]);

                $rows =
                    $statement->fetchAll(
                        PDO::FETCH_ASSOC
                    ) ?: [];

                if (count($rows) !== 1) {
                    continue;
                }

                return
                    $this->entityDescriptor(
                        $table,
                        $columns,
                        $rows[0]
                    );
            }
        }

        return null;
    }


    private function scopeEntityById(
        string $type,
        int $id
    ): ?array {
        $tables =
            self::SCOPE_TABLES[
                $type
            ]
            ?? [];

        foreach ($tables as $table) {
            if (
                !$this->tableExists(
                    $table
                )
                || !$this->columnExists(
                    $table,
                    'id'
                )
            ) {
                continue;
            }

            $statement =
                $this->db->prepare(
                    "SELECT *
                     FROM `{$table}`
                     WHERE id = ?
                     LIMIT 1"
                );

            $statement->execute([
                $id,
            ]);

            $row =
                $statement->fetch(
                    PDO::FETCH_ASSOC
                );

            if (!is_array($row)) {
                continue;
            }

            return
                $this->entityDescriptor(
                    $table,
                    $this->columns(
                        $table
                    ),
                    $row
                );
        }

        return null;
    }


    private function entityDescriptor(
        string $table,
        array $columns,
        array $row
    ): ?array {
        $id =
            (int) (
                $row['id']
                ?? 0
            );

        if ($id < 1) {
            return null;
        }

        $aliases = [];

        foreach (
            $this->referenceColumns(
                $columns
            )
            as $column
        ) {
            $value =
                trim(
                    (string) (
                        $row[$column]
                        ?? ''
                    )
                );

            if (
                $value !== ''
                && !in_array(
                    $value,
                    $aliases,
                    true
                )
            ) {
                $aliases[] =
                    $value;
            }
        }

        $idString =
            (string) $id;

        if (
            !in_array(
                $idString,
                $aliases,
                true
            )
        ) {
            $aliases[] =
                $idString;
        }

        return [
            'table' =>
                $table,

            'id' =>
                $id,

            'aliases' =>
                $aliases,
        ];
    }


    private function referenceColumns(
        array $columns
    ): array {
        $result = [];

        foreach (
            [
                'public_reference',
                'code',
                'id',
            ]
            as $column
        ) {
            if (
                isset(
                    $columns[
                        $column
                    ]
                )
            ) {
                $result[] =
                    $column;
            }
        }

        return $result;
    }


    private function columns(
        string $table
    ): array {
        $statement =
            $this->db->prepare("
                SELECT column_name
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = ?
            ");

        $statement->execute([
            $table,
        ]);

        return array_fill_keys(
            $statement->fetchAll(
                PDO::FETCH_COLUMN
            ) ?: [],
            true
        );
    }


    private function tableExists(
        string $table
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = ?
            ");

        $statement->execute([
            $table,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }


    private function columnExists(
        string $table,
        string $column
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                  AND column_name = ?
            ");

        $statement->execute([
            $table,
            $column,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }
}
