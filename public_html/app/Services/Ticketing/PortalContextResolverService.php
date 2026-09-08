<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;


/**
 * TICKETING_PORTAL_CONTEXT_RESOLVER_V1
 *
 * Runtime contract:
 *
 * - Host resolves only by an explicit active Portal Host row.
 * - Unknown Host never infers a Project or Realm.
 * - Portal, Realm and Project must all be active.
 * - Default Portal resolution is explicit Realm configuration.
 * - Host normalization is deterministic and port-insensitive.
 * - This service is read-only.
 */
final class PortalContextResolverService
{
    private PDO $db;


    public function __construct(
        ?PDO $db = null
    ) {
        $this->db =
            $db
            ?? (
                new ConnectionResolver()
            )->resolve(
                'ticketing.primary'
            );
    }


    public function resolveByHost(
        string $requestHost
    ): ?array {

        $hostname =
            $this->normalizeHost(
                $requestHost
            );


        if ($hostname === '') {
            return null;
        }


        $statement =
            $this->db->prepare("
                SELECT
                    h.id
                        AS host_id,

                    h.public_reference
                        AS host_public_reference,

                    h.hostname,

                    h.requires_https,

                    h.canonical_host_slot,

                    p.id
                        AS portal_id,

                    p.public_reference
                        AS portal_public_reference,

                    p.code
                        AS portal_code,

                    p.title
                        AS portal_title,

                    p.project_id,

                    p.realm_id,

                    r.public_reference
                        AS realm_public_reference,

                    r.code
                        AS realm_code,

                    r.title
                        AS realm_title,

                    r.default_portal_id,

                    sp.public_reference
                        AS project_public_reference,

                    sp.title
                        AS project_title

                FROM
                    ticketing_support_portal_hosts h

                INNER JOIN
                    ticketing_support_portals p

                    ON p.id =
                        h.portal_id

                   AND p.status =
                        'active'

                   AND p.archived_at
                        IS NULL

                INNER JOIN
                    ticketing_support_realms r

                    ON r.id =
                        p.realm_id

                   AND r.project_id =
                        p.project_id

                   AND r.status =
                        'active'

                   AND r.archived_at
                        IS NULL

                INNER JOIN
                    ticketing_support_projects sp

                    ON sp.id =
                        p.project_id

                   AND sp.is_active = 1

                   AND sp.archived_at
                        IS NULL

                WHERE
                    h.hostname = ?

                  AND h.status =
                        'active'

                LIMIT 1
            ");


        $statement->execute([
            $hostname,
        ]);


        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        return
            is_array($row)
                ? $this->normalizeContext($row)
                : null;
    }


    public function resolveDefaultForRealm(
        int $projectId,
        int $realmId
    ): ?array {

        if (
            $projectId < 1
            || $realmId < 1
        ) {
            return null;
        }


        $statement =
            $this->db->prepare("
                SELECT
                    NULL
                        AS host_id,

                    NULL
                        AS host_public_reference,

                    NULL
                        AS hostname,

                    NULL
                        AS requires_https,

                    NULL
                        AS canonical_host_slot,

                    p.id
                        AS portal_id,

                    p.public_reference
                        AS portal_public_reference,

                    p.code
                        AS portal_code,

                    p.title
                        AS portal_title,

                    p.project_id,

                    p.realm_id,

                    r.public_reference
                        AS realm_public_reference,

                    r.code
                        AS realm_code,

                    r.title
                        AS realm_title,

                    r.default_portal_id,

                    sp.public_reference
                        AS project_public_reference,

                    sp.title
                        AS project_title

                FROM
                    ticketing_support_realms r

                INNER JOIN
                    ticketing_support_projects sp

                    ON sp.id =
                        r.project_id

                   AND sp.is_active = 1

                   AND sp.archived_at
                        IS NULL

                INNER JOIN
                    ticketing_support_portals p

                    ON p.id =
                        r.default_portal_id

                   AND p.project_id =
                        r.project_id

                   AND p.realm_id =
                        r.id

                   AND p.status =
                        'active'

                   AND p.archived_at
                        IS NULL

                WHERE
                    r.project_id = ?

                  AND r.id = ?

                  AND r.status =
                        'active'

                  AND r.archived_at
                        IS NULL

                LIMIT 1
            ");


        $statement->execute([
            $projectId,
            $realmId,
        ]);


        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        return
            is_array($row)
                ? $this->normalizeContext($row)
                : null;
    }


    public function isPortalHost(
        string $requestHost
    ): bool {

        return
            $this->resolveByHost(
                $requestHost
            ) !== null;
    }


    public function normalizeHost(
        string $host
    ): string {

        $host =
            strtolower(
                trim(
                    $host
                )
            );


        if (
            $host === ''
            || str_contains(
                $host,
                '://'
            )
            || str_contains(
                $host,
                '/'
            )
            || str_contains(
                $host,
                '\\'
            )
            || str_starts_with(
                $host,
                '['
            )
        ) {
            return '';
        }


        $host =
            preg_replace(
                '/:\d+$/D',
                '',
                $host
            )
            ?? '';


        $host =
            rtrim(
                $host,
                '.'
            );


        if (
            $host === ''
            || strlen($host) > 253
        ) {
            return '';
        }


        if (
            preg_match(
                '/^(?:'
                . '[a-z0-9]'
                . '(?:[a-z0-9-]{0,61}[a-z0-9])?'
                . '\.)*'
                . '[a-z0-9]'
                . '(?:[a-z0-9-]{0,61}[a-z0-9])?'
                . '$/D',
                $host
            ) !== 1
        ) {
            return '';
        }


        return $host;
    }


    private function normalizeContext(
        array $row
    ): array {

        foreach ([
            'host_id',
            'portal_id',
            'project_id',
            'realm_id',
            'default_portal_id',
            'canonical_host_slot',
        ] as $key) {

            $row[$key] =
                $row[$key] === null
                    ? null
                    : (int) $row[$key];
        }


        $row['requires_https'] =
            $row['requires_https'] === null
                ? null
                : (
                    (int) $row[
                        'requires_https'
                    ] === 1
                );


        return $row;
    }
}
