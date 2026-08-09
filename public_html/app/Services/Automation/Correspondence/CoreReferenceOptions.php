<?php

namespace App\Services\Automation\Correspondence;

use App\Services\Automation\CoreReference;
use App\Services\Automation\CoreReferenceType;
use App\Services\Automation\CoreReferenceValidator;
use IPKF\Database\Database;
use IPKF\Support\Env;
use PDO;

class CoreReferenceOptions
{
    public function options(
        ?array $scope = null
    ): array {
        $organizationIds =
            $this->organizationIds(
                $scope
            );

        return [
            'users' =>
                $this->users(
                    $organizationIds
                ),

            'persons' =>
                $this->persons(
                    $organizationIds
                ),

            'organizations' =>
                $this->organizations(
                    $organizationIds
                ),

            'org_units' =>
                $this->orgUnits(
                    $organizationIds
                ),
        ];
    }

    public function tokenFor(
        string $kind,
        int $id
    ): ?string {
        if (
            $id < 1
            || !in_array(
                $kind,
                [
                    CoreReferenceType::PERSON,
                    CoreReferenceType::USER,
                    CoreReferenceType::ORGANIZATION,
                    CoreReferenceType::ORG_UNIT,
                ],
                true
            )
        ) {
            return null;
        }

        return $this->token(
            $kind,
            $id
        );
    }

    public function decode(
        string $token
    ): ?array {
        $encoded =
            strtr(
                trim($token),
                '-_',
                '+/'
            );

        $encoded .=
            str_repeat(
                '=',
                (
                    4
                    - strlen($encoded) % 4
                ) % 4
            );

        $payload =
            json_decode(
                base64_decode(
                    $encoded,
                    true
                ) ?: '',
                true
            );

        if (
            !is_array($payload)
            || !isset(
                $payload['kind'],
                $payload['id'],
                $payload['sig']
            )
        ) {
            return null;
        }

        $kind =
            (string) $payload['kind'];

        $id =
            (int) $payload['id'];

        $signature =
            (string) $payload['sig'];

        if (
            $id < 1
            || !hash_equals(
                $this->signature(
                    $kind,
                    $id
                ),
                $signature
            )
        ) {
            return null;
        }

        if (
            !(new CoreReferenceValidator())
                ->validate(
                    new CoreReference(
                        $kind,
                        (string) $id
                    )
                )
        ) {
            return null;
        }

        return [
            'kind' => $kind,
            'id' => $id,
        ];
    }

    public function referenceAllowed(
        array $reference,
        array $scope
    ): bool {
        $kind =
            (string) (
                $reference['kind'] ?? ''
            );

        $id =
            (int) (
                $reference['id'] ?? 0
            );

        $organizationIds =
            $this->organizationIds(
                $scope
            );

        if (
            $id < 1
            || $organizationIds === null
        ) {
            return $id > 0;
        }

        if ($organizationIds === []) {
            return false;
        }

        if (
            $kind
            === CoreReferenceType::ORGANIZATION
        ) {
            return in_array(
                $id,
                $organizationIds,
                true
            );
        }

        [$in, $params] =
            $this->inClause(
                $organizationIds
            );

        if (
            $kind
            === CoreReferenceType::ORG_UNIT
        ) {
            $statement =
                $this->core()->prepare("
                    SELECT COUNT(*)
                    FROM org_units
                    WHERE id = ?
                      AND organization_id
                          IN ({$in})
                      AND COALESCE(
                            status,
                            'active'
                          ) = 'active'
                      AND deleted_at IS NULL
                ");

            $statement->execute(
                array_merge(
                    [$id],
                    $params
                )
            );

            return
                (int) $statement
                    ->fetchColumn() > 0;
        }

        if (
            $kind
            === CoreReferenceType::PERSON
        ) {
            $statement =
                $this->core()->prepare("
                    SELECT COUNT(DISTINCT p.id)
                    FROM persons p
                    INNER JOIN organization_appointments a
                        ON a.person_id = p.id
                    WHERE p.id = ?
                      AND a.organization_id
                          IN ({$in})
                      AND p.status = 'active'
                      AND a.status = 'active'
                      AND a.revoked_at IS NULL
                      AND (
                            a.valid_from IS NULL
                            OR a.valid_from
                                <= CURRENT_DATE
                      )
                      AND (
                            a.valid_to IS NULL
                            OR a.valid_to
                                >= CURRENT_DATE
                      )
                ");

            $statement->execute(
                array_merge(
                    [$id],
                    $params
                )
            );

            return
                (int) $statement
                    ->fetchColumn() > 0;
        }

        if (
            $kind
            === CoreReferenceType::USER
        ) {
            $statement =
                $this->core()->prepare("
                    SELECT COUNT(DISTINCT u.id)
                    FROM users u
                    INNER JOIN persons p
                        ON p.id = u.person_id
                    INNER JOIN organization_appointments a
                        ON a.person_id = p.id
                    WHERE u.id = ?
                      AND a.organization_id
                          IN ({$in})
                      AND u.status = 'active'
                      AND p.status = 'active'
                      AND a.status = 'active'
                      AND a.revoked_at IS NULL
                      AND (
                            a.valid_from IS NULL
                            OR a.valid_from
                                <= CURRENT_DATE
                      )
                      AND (
                            a.valid_to IS NULL
                            OR a.valid_to
                                >= CURRENT_DATE
                      )
                ");

            $statement->execute(
                array_merge(
                    [$id],
                    $params
                )
            );

            return
                (int) $statement
                    ->fetchColumn() > 0;
        }

        return false;
    }

    public function organizationIdForContext(
        array $context
    ): ?int {
        /*
         * Kept for compatibility with older callers.
         * Enterprise correspondence write paths no longer
         * use this fallback.
         */
        $active =
            $context[
                'active_assignment'
            ] ?? [];

        if (
            ($active[
                'scope_type'
            ] ?? '') === 'organization'
            && (int) (
                $active[
                    'scope_id'
                ] ?? 0
            ) > 0
        ) {
            return
                (int) $active[
                    'scope_id'
                ];
        }

        return null;
    }

    public function userPersonId(
        int $userId
    ): ?int {
        $statement =
            $this->core()->prepare("
                SELECT person_id
                FROM users
                WHERE id = ?
                LIMIT 1
            ");

        $statement->execute([
            $userId,
        ]);

        $id =
            $statement->fetchColumn();

        return
            $id === false
            || $id === null
                ? null
                : (int) $id;
    }

    private function users(
        ?array $organizationIds
    ): array {
        if (
            $organizationIds === null
        ) {
            $statement =
                $this->core()->query("
                    SELECT
                        users.id,
                        users.username,
                        persons.full_name
                    FROM users
                    LEFT JOIN persons
                        ON persons.id =
                            users.person_id
                    WHERE COALESCE(
                        users.status,
                        'active'
                    ) = 'active'
                    ORDER BY users.id
                    LIMIT 80
                ");
        } else {
            if ($organizationIds === []) {
                return [];
            }

            [$in, $params] =
                $this->inClause(
                    $organizationIds
                );

            $statement =
                $this->core()->prepare("
                    SELECT DISTINCT
                        users.id,
                        users.username,
                        persons.full_name
                    FROM users
                    INNER JOIN persons
                        ON persons.id =
                            users.person_id
                    INNER JOIN organization_appointments a
                        ON a.person_id =
                            persons.id
                    WHERE COALESCE(
                            users.status,
                            'active'
                          ) = 'active'
                      AND persons.status = 'active'
                      AND a.status = 'active'
                      AND a.revoked_at IS NULL
                      AND a.organization_id
                          IN ({$in})
                      AND (
                            a.valid_from IS NULL
                            OR a.valid_from
                                <= CURRENT_DATE
                      )
                      AND (
                            a.valid_to IS NULL
                            OR a.valid_to
                                >= CURRENT_DATE
                      )
                    ORDER BY users.id
                    LIMIT 80
                ");

            $statement->execute(
                $params
            );
        }

        return array_map(
            fn (array $row): array => [
                'token' =>
                    $this->token(
                        CoreReferenceType::USER,
                        (int) $row['id']
                    ),

                'label' =>
                    trim(
                        (string) (
                            $row[
                                'full_name'
                            ] ?? ''
                        )
                    ) !== ''
                        ? (string) $row[
                            'full_name'
                        ]
                        : (string) (
                            $row[
                                'username'
                            ] ?? 'کاربر'
                        ),
            ],
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: []
        );
    }

    private function persons(
        ?array $organizationIds
    ): array {
        if (
            $organizationIds === null
        ) {
            $statement =
                $this->core()->query("
                    SELECT
                        id,
                        full_name
                    FROM persons
                    WHERE COALESCE(
                        status,
                        'active'
                    ) = 'active'
                    ORDER BY id
                    LIMIT 80
                ");
        } else {
            if ($organizationIds === []) {
                return [];
            }

            [$in, $params] =
                $this->inClause(
                    $organizationIds
                );

            $statement =
                $this->core()->prepare("
                    SELECT DISTINCT
                        p.id,
                        p.full_name
                    FROM persons p
                    INNER JOIN organization_appointments a
                        ON a.person_id =
                            p.id
                    WHERE p.status = 'active'
                      AND a.status = 'active'
                      AND a.revoked_at IS NULL
                      AND a.organization_id
                          IN ({$in})
                      AND (
                            a.valid_from IS NULL
                            OR a.valid_from
                                <= CURRENT_DATE
                      )
                      AND (
                            a.valid_to IS NULL
                            OR a.valid_to
                                >= CURRENT_DATE
                      )
                    ORDER BY p.id
                    LIMIT 80
                ");

            $statement->execute(
                $params
            );
        }

        return array_map(
            fn (array $row): array => [
                'token' =>
                    $this->token(
                        CoreReferenceType::PERSON,
                        (int) $row['id']
                    ),

                'label' =>
                    (string) (
                        $row[
                            'full_name'
                        ] ?? 'شخص'
                    ),
            ],
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: []
        );
    }

    private function organizations(
        ?array $organizationIds
    ): array {
        if (
            $organizationIds === null
        ) {
            $statement =
                $this->core()->query("
                    SELECT
                        id,
                        title
                    FROM organizations
                    WHERE COALESCE(
                        is_active,
                        1
                    ) = 1
                    ORDER BY
                        sort_order,
                        id
                    LIMIT 80
                ");
        } else {
            if ($organizationIds === []) {
                return [];
            }

            [$in, $params] =
                $this->inClause(
                    $organizationIds
                );

            $statement =
                $this->core()->prepare("
                    SELECT
                        id,
                        title
                    FROM organizations
                    WHERE COALESCE(
                            is_active,
                            1
                          ) = 1
                      AND id IN ({$in})
                    ORDER BY
                        sort_order,
                        id
                    LIMIT 80
                ");

            $statement->execute(
                $params
            );
        }

        return array_map(
            fn (array $row): array => [
                'token' =>
                    $this->token(
                        CoreReferenceType::ORGANIZATION,
                        (int) $row['id']
                    ),

                'label' =>
                    (string) (
                        $row[
                            'title'
                        ] ?? 'سازمان'
                    ),
            ],
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: []
        );
    }

    private function orgUnits(
        ?array $organizationIds
    ): array {
        if (
            $organizationIds === null
        ) {
            $statement =
                $this->core()->query("
                    SELECT
                        id,
                        title
                    FROM org_units
                    WHERE COALESCE(
                            status,
                            'active'
                          ) = 'active'
                      AND deleted_at IS NULL
                    ORDER BY
                        sort_order,
                        id
                    LIMIT 80
                ");
        } else {
            if ($organizationIds === []) {
                return [];
            }

            [$in, $params] =
                $this->inClause(
                    $organizationIds
                );

            $statement =
                $this->core()->prepare("
                    SELECT
                        id,
                        title
                    FROM org_units
                    WHERE COALESCE(
                            status,
                            'active'
                          ) = 'active'
                      AND deleted_at IS NULL
                      AND organization_id
                          IN ({$in})
                    ORDER BY
                        sort_order,
                        id
                    LIMIT 80
                ");

            $statement->execute(
                $params
            );
        }

        return array_map(
            fn (array $row): array => [
                'token' =>
                    $this->token(
                        CoreReferenceType::ORG_UNIT,
                        (int) $row['id']
                    ),

                'label' =>
                    (string) (
                        $row[
                            'title'
                        ] ?? 'واحد سازمانی'
                    ),
            ],
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: []
        );
    }

    private function organizationIds(
        ?array $scope
    ): ?array {
        if ($scope === null) {
            return null;
        }

        $ids =
            array_values(
                array_unique(
                    array_filter(
                        array_map(
                            'intval',
                            $scope[
                                'organization_ids'
                            ] ?? []
                        ),
                        static fn (
                            int $id
                        ): bool => $id > 0
                    )
                )
            );

        sort(
            $ids,
            SORT_NUMERIC
        );

        return $ids;
    }

    private function inClause(
        array $ids
    ): array {
        return [
            implode(
                ',',
                array_fill(
                    0,
                    count($ids),
                    '?'
                )
            ),

            array_values(
                array_map(
                    'intval',
                    $ids
                )
            ),
        ];
    }

    private function token(
        string $kind,
        int $id
    ): string {
        $payload =
            json_encode(
                [
                    'kind' => $kind,
                    'id' => $id,
                    'sig' =>
                        $this->signature(
                            $kind,
                            $id
                        ),
                ],
                JSON_UNESCAPED_UNICODE
            ) ?: '{}';

        return rtrim(
            strtr(
                base64_encode($payload),
                '+/',
                '-_'
            ),
            '='
        );
    }

    private function signature(
        string $kind,
        int $id
    ): string {
        $secret =
            (string) Env::get(
                'APP_KEY',
                'ipkf-local-key'
            );

        return hash_hmac(
            'sha256',
            $kind . ':' . $id,
            $secret !== ''
                ? $secret
                : 'ipkf-local-key'
        );
    }

    private function core(): PDO
    {
        return Database::connect();
    }
}
