<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use IPKF\Database\Connections\ConnectionResolver;
use Throwable;

/**
 * TICKETING_PROJECT_SCOPED_EFFECTIVE_ACCESS_BRIDGE_V1
 *
 * Separation of authority:
 *
 * Core RBAC:
 *   global/system role -> permission -> global surface.
 *
 * Ticketing:
 *   user -> project membership role -> team membership ->
 *   topology -> Dynamic Data Scope -> ticket/resource operations.
 *
 * A local Ticketing project manager is NOT a global
 * ticketing.project.manage administrator.
 *
 * This bridge only answers module-entry / operational-shell questions.
 * Resource visibility remains delegated to TicketStaffOperationsService.
 */
final class TicketingProjectScopedAccessService
{
    private static array $summaryCache = [];


    public function __construct(
        private ?TicketStaffOperationsService $staff = null
    ) {
        $this->staff =
            $this->staff
            ?? new TicketStaffOperationsService();
    }


    public function summary(
        int $userId
    ): array {
        if ($userId < 1) {
            return $this->emptySummary();
        }

        if (
            array_key_exists(
                $userId,
                self::$summaryCache
            )
        ) {
            return
                self::$summaryCache[
                    $userId
                ];
        }

        try {
            $scopes =
                $this->staff
                    ->accessScopes(
                        $userId
                    );
        } catch (Throwable) {
            $scopes = [];
        }

        $hasManagerScope = false;

        foreach (
            $scopes
            as $scope
        ) {
            if (
                !empty(
                    $scope[
                        'is_project_manager'
                    ]
                )
            ) {
                $hasManagerScope = true;
                break;
            }
        }

        $summary = [
            'has_operational_access' =>
                $scopes !== [],

            'has_project_manager_scope' =>
                $hasManagerScope,

            'scope_count' =>
                count($scopes),

            'scopes' =>
                $scopes,

            'resource_scope_authority' =>
                'ticketing_cartable_plus_dynamic_data_scope',
        ];

        self::$summaryCache[
            $userId
        ] = $summary;

        return $summary;
    }


    public function isOperationalStaff(
        int $userId
    ): bool {
        return
            !empty(
                $this->summary(
                    $userId
                )[
                    'has_operational_access'
                ]
            );
    }


    public function projectScope(
        int $userId,
        int $projectId
    ): ?array {
        if (
            $userId < 1
            || $projectId < 1
        ) {
            return null;
        }

        foreach (
            $this->summary(
                $userId
            )[
                'scopes'
            ]
            as $scope
        ) {
            if (
                (int) (
                    $scope[
                        'project_id'
                    ]
                    ?? 0
                )
                === $projectId
            ) {
                return $scope;
            }
        }

        return null;
    }


    public function isProjectManager(
        int $userId,
        int $projectId
    ): bool {
        $scope =
            $this->projectScope(
                $userId,
                $projectId
            );

        return
            is_array($scope)
            &&
            !empty(
                $scope[
                    'is_project_manager'
                ]
            );
    }


    /*
     * T2_PROJECT_MANAGER_MEMBER_ADMIN_BRIDGE_V1
     *
     * A project-local manager may administer members/teams only for the
     * exact project in which that manager membership is active.
     *
     * This does not grant global /admin/ticketing/projects authority.
     */
    public function isProjectManagerReference(
        int $userId,
        string $projectReference
    ): bool {
        $projectId =
            $this->projectIdForReference(
                $projectReference
            );

        return
            $projectId > 0
            &&
            $this->isProjectManager(
                $userId,
                $projectId
            );
    }


    public function navigationItemAllowed(
        int $userId,
        string $itemKey
    ): bool {
        if (
            !$this->isOperationalStaff(
                $userId
            )
        ) {
            return false;
        }

        return
            in_array(
                trim($itemKey),
                [
                    /*
                     * Global Ticketing module entry.
                     */
                    'ticketing',

                    /*
                     * Operational shell surfaces.
                     */
                    'ticketing-dashboard',
                    'ticketing-staff',
                    'ticketing-unread-alert',
                ],
                true
            );
    }


    public function canAccessPath(
        int $userId,
        string $path
    ): bool {
        if (
            !$this->isOperationalStaff(
                $userId
            )
        ) {
            return false;
        }

        $path =
            rtrim(
                (string) (
                    parse_url(
                        $path,
                        PHP_URL_PATH
                    )
                    ?: $path
                ),
                '/'
            )
            ?: '/';

        if (
            in_array(
                $path,
                [
                    '/admin/ticketing',
                    '/admin/ticketing/attention',
                    '/admin/ticketing/staff',
                ],
                true
            )
        ) {
            return true;
        }

        /*
         * T2_PROJECT_MANAGER_MEMBER_ADMIN_PATH_V1
         *
         * Member/team administration is project-local. The public project
         * reference is resolved server-side and then checked against the
         * canonical Ticketing project membership.
         */
        if (
            preg_match(
                '#^/admin/ticketing/projects/([A-Za-z0-9_-]+)/members(?:/.*)?$#',
                $path,
                $matches
            ) === 1
        ) {
            return
                $this->isProjectManagerReference(
                    $userId,
                    (string) ($matches[1] ?? '')
                );
        }


        /*
         * Staff ticket detail/actions must remain resource-scoped.
         * canViewTicket() reaches the canonical cartable, including
         * project topology and Dynamic Data Scope.
         */
        if (
            preg_match(
                '#^/admin/ticketing/staff/([A-Za-z0-9_-]+)(?:/(takeover|transfer|escalate))?$#',
                $path,
                $matches
            ) === 1
        ) {
            try {
                return
                    $this->staff
                        ->canViewTicket(
                            (string) $matches[1],
                            $userId
                        );
            } catch (Throwable) {
                return false;
            }
        }

        /*
         * Deliberately excluded:
         * /projects, /participants, /portals, /statuses, SLA policy
         * administration and every other global administration surface.
         *
         * Local project manager != global project administrator.
         */
        return false;
    }


    private function projectIdForReference(
        string $projectReference
    ): int {
        $projectReference =
            trim(
                $projectReference
            );

        if (
            $projectReference === ''
            ||
            preg_match(
                '/^[A-Za-z0-9_-]{1,100}$/D',
                $projectReference
            ) !== 1
        ) {
            return 0;
        }

        try {
            $db =
                (new ConnectionResolver())
                    ->resolve(
                        'ticketing.primary'
                    );

            $statement =
                $db->prepare("
                    SELECT id

                    FROM
                        ticketing_support_projects

                    WHERE public_reference = ?
                      AND is_active = 1
                      AND archived_at IS NULL

                    LIMIT 1
                ");

            $statement->execute([
                $projectReference,
            ]);

            return
                max(
                    0,
                    (int) $statement
                        ->fetchColumn()
                );

        } catch (Throwable) {
            return 0;
        }
    }


    private function emptySummary(): array
    {
        return [
            'has_operational_access' =>
                false,

            'has_project_manager_scope' =>
                false,

            'scope_count' =>
                0,

            'scopes' =>
                [],

            'resource_scope_authority' =>
                'ticketing_cartable_plus_dynamic_data_scope',
        ];
    }
}
