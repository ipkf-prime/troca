<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;


/**
 * TICKETING_ATTACHMENT_STORAGE_SCOPE_V1
 *
 * Canonical ownership:
 *
 * Ticket -> Project -> Realm -> Realm default Portal
 *
 * Portal identity is never synthesized from a hostname,
 * request value, filename or physical storage location.
 */
final class TicketAttachmentStorageScopeService
{
    private PDO $ticketing;


    public function __construct(
        ?ConnectionResolver $connections = null
    ) {
        $this->ticketing =
            (
                $connections
                ?? new ConnectionResolver()
            )->resolve(
                'ticketing.primary'
            );
    }


    public function forProjectRealm(
        int $projectId,
        int $realmId,
        string $ticketReference
    ): ?array {

        $ticketReference =
            trim(
                $ticketReference
            );


        if (
            $projectId < 1
            ||
            $realmId < 1
            ||
            $ticketReference === ''
        ) {
            return null;
        }


        $statement =
            $this->ticketing
                ->prepare("
                    SELECT
                        p.public_reference
                            AS project_reference,

                        r.public_reference
                            AS realm_reference,

                        po.public_reference
                            AS portal_reference

                    FROM ticketing_support_projects p

                    INNER JOIN ticketing_support_realms r
                      ON r.id = ?
                     AND r.project_id = p.id
                     AND r.status = 'active'
                     AND r.archived_at IS NULL

                    INNER JOIN ticketing_support_portals po
                      ON po.id = r.default_portal_id
                     AND po.project_id = p.id
                     AND po.realm_id = r.id
                     AND po.status = 'active'
                     AND po.archived_at IS NULL

                    WHERE p.id = ?
                      AND p.is_active = 1
                      AND p.archived_at IS NULL

                    LIMIT 1
                ");


        $statement->execute([
            $realmId,
            $projectId,
        ]);


        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        if (!is_array($row)) {
            return null;
        }


        return
            $this->present(
                $row,
                $ticketReference
            );
    }


    public function forTicket(
        string $ticketReference
    ): ?array {

        $ticketReference =
            trim(
                $ticketReference
            );


        if ($ticketReference === '') {
            return null;
        }


        $statement =
            $this->ticketing
                ->prepare("
                    SELECT
                        t.public_reference
                            AS ticket_reference,

                        p.public_reference
                            AS project_reference,

                        r.public_reference
                            AS realm_reference,

                        po.public_reference
                            AS portal_reference

                    FROM ticketing_tickets t

                    INNER JOIN ticketing_support_projects p
                      ON p.id = t.support_project_id

                    INNER JOIN ticketing_support_realms r
                      ON r.id = t.realm_id
                     AND r.project_id =
                            t.support_project_id

                    INNER JOIN ticketing_support_portals po
                      ON po.id = r.default_portal_id
                     AND po.project_id =
                            t.support_project_id
                     AND po.realm_id =
                            t.realm_id
                     AND po.status = 'active'
                     AND po.archived_at IS NULL

                    WHERE t.public_reference = ?
                      AND t.archived_at IS NULL

                    LIMIT 1
                ");


        $statement->execute([
            $ticketReference,
        ]);


        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        if (!is_array($row)) {
            return null;
        }


        return
            $this->present(
                $row,
                $ticketReference
            );
    }


    private function present(
        array $row,
        string $ticketReference
    ): array {

        return [
            'ticket_reference' =>
                trim(
                    (string) (
                        $row['ticket_reference']
                        ?? $ticketReference
                    )
                ),

            'project_reference' =>
                trim(
                    (string) (
                        $row['project_reference']
                        ?? ''
                    )
                ),

            'realm_reference' =>
                trim(
                    (string) (
                        $row['realm_reference']
                        ?? ''
                    )
                ),

            'portal_reference' =>
                trim(
                    (string) (
                        $row['portal_reference']
                        ?? ''
                    )
                ),
        ];
    }
}
