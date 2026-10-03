<?php

declare(strict_types=1);

/*
 * TICKET_WORK_CREATE_LINK_FOUNDATION_V1
 * Legacy bridge compatibility contract retained by dynamic-policy orchestrator.
 */

namespace App\Services\Ticketing;

use App\Repositories\TicketWorkPolicyRepository;
use App\Repositories\WorkExternalSourceBridgeRepository;
use App\Repositories\WorkItemRepository;
use App\Services\BaseService;
use App\Services\Work\WorkExternalSourceBridgeService;
use App\Services\Work\WorkItemService;
use App\Services\Work\WorkProjectAccessService;
use IPKF\Database\Connections\ConnectionResolver;
use IPKF\Support\ApplicationUrlRegistry;
use PDO;
use RuntimeException;
use Throwable;

/*
 * TICKET_WORK_DYNAMIC_POLICY_INTEGRATION_V1
 *
 * Ticketing owns ticket visibility and operational context.
 * Work owns execution projects/items and Ticket->Work policy.
 *
 * Effective access always requires BOTH:
 * 1) Ticket->Work dynamic policy ALLOW, and
 * 2) native Work project ACL.
 *
 * No Ticket write is performed here.
 */
final class TicketWorkBridgeService extends BaseService
{
    private ConnectionResolver $connections;
    private PDO $ticketing;
    private PDO $work;

    private TicketStaffOperationsService $ticketAccess;
    private TicketWorkPolicyService $policy;
    private WorkProjectAccessService $workAccess;
    private WorkItemService $workItems;
    private WorkExternalSourceBridgeService $bridge;
    private ApplicationUrlRegistry $urls;

    public function __construct(
        ?ConnectionResolver $connections = null,
        ?TicketStaffOperationsService $ticketAccess = null,
        ?TicketWorkPolicyService $policy = null,
        ?WorkProjectAccessService $workAccess = null,
        ?WorkItemService $workItems = null,
        ?WorkExternalSourceBridgeService $bridge = null,
        ?ApplicationUrlRegistry $urls = null
    ) {
        $this->connections =
            $connections
            ?? new ConnectionResolver();

        $this->ticketing =
            $this->connections->resolve(
                'ticketing.primary'
            );

        $this->work =
            $this->connections->resolve(
                'work.primary'
            );

        $this->ticketAccess =
            $ticketAccess
            ?? new TicketStaffOperationsService();

        $this->policy =
            $policy
            ?? new TicketWorkPolicyService(
                new TicketWorkPolicyRepository(
                    $this->connections
                )
            );

        $this->workAccess =
            $workAccess
            ?? new WorkProjectAccessService();

        $this->workItems =
            $workItems
            ?? new WorkItemService(
                new WorkItemRepository(
                    $this->connections
                )
            );

        $this->bridge =
            $bridge
            ?? new WorkExternalSourceBridgeService(
                new WorkExternalSourceBridgeRepository(
                    $this->connections
                )
            );

        $this->urls =
            $urls
            ?? new ApplicationUrlRegistry();
    }

    public function context(
        string $ticketReference,
        int $userId
    ): array {
        $ticketReference =
            trim(
                $ticketReference
            );

        if (
            $ticketReference === ''
            || $userId < 1
        ) {
            return $this->emptyContext(
                'invalid_request'
            );
        }

        $ticket =
            $this->ticketSource(
                $ticketReference
            );

        if ($ticket === null) {
            return $this->emptyContext(
                'ticket_not_found'
            );
        }

        if (
            !$this->ticketAccess
                ->canViewTicket(
                    $ticketReference,
                    $userId
                )
        ) {
            return array_merge(
                $this->emptyContext(
                    'ticket_forbidden'
                ),
                [
                    'found' => true,
                ]
            );
        }

        $policyContext =
            $this->policy->ticketContext(
                $ticketReference,
                $userId
            );

        if ($policyContext === null) {
            return [
                'found' => true,
                'visible' => true,
                'available' => false,
                'reason' =>
                    'ticket_work_policy_context_unavailable',
                'ticket' => $ticket,
                'ticket_work_context' => [],
                'work_project' => [],
                'policy' =>
                    $this->denyPolicyResult(),
                'permissions' =>
                    $this->denyPermissions(),
                'can_view_work' => false,
                'can_create_item' => false,
                'linked_items' => [],
            ];
        }

        $destination =
            $this->resolveDestination(
                $policyContext,
                (string) $ticket[
                    'support_project_reference'
                ]
            );

        if ($destination === null) {
            return [
                'found' => true,
                'visible' => true,
                'available' => false,
                'reason' =>
                    'work_project_destination_unavailable',
                'ticket' => $ticket,
                'ticket_work_context' =>
                    $policyContext,
                'work_project' => [],
                'policy' =>
                    $this->denyPolicyResult(),
                'permissions' =>
                    $this->denyPermissions(),
                'can_view_work' => false,
                'can_create_item' => false,
                'linked_items' => [],
            ];
        }

        $projectReference =
            trim(
                (string) (
                    $destination[
                        'work_project_reference'
                    ]
                    ?? ''
                )
            );

        $workProjectId =
            (int) (
                $destination[
                    'work_project_id'
                ]
                ?? 0
            );

        if (
            $projectReference === ''
            || $workProjectId < 1
        ) {
            return [
                'found' => true,
                'visible' => true,
                'available' => false,
                'reason' =>
                    'work_project_destination_invalid',
                'ticket' => $ticket,
                'ticket_work_context' =>
                    $policyContext,
                'work_project' => [],
                'policy' =>
                    $this->denyPolicyResult(),
                'permissions' =>
                    $this->denyPermissions(),
                'can_view_work' => false,
                'can_create_item' => false,
                'linked_items' => [],
            ];
        }

        $nativeAccess =
            $this->workAccess
                ->projectAccess(
                    $projectReference,
                    $userId
                );

        $nativeCanView =
            ($nativeAccess['found'] ?? false)
                === true
            && ($nativeAccess['can_view'] ?? false)
                === true;

        $nativeCanCreate =
            $nativeCanView
            && ($nativeAccess['can_create_item'] ?? false)
                === true;

        $policyAccess =
            $this->policy->resolveAccess(
                $policyContext,
                $workProjectId
            );

        $actions =
            is_array(
                $policyAccess['actions']
                ?? null
            )
                ? $policyAccess['actions']
                : [];

        $showTab =
            $nativeCanView
            && ($actions['tab.view'] ?? false)
                === true;

        $canViewLinks =
            $showTab
            && ($actions['links.view'] ?? false)
                === true;

        $canOpenItems =
            $showTab
            && ($actions['item.open'] ?? false)
                === true;

        $canCreateFromTicket =
            $showTab
            && $nativeCanCreate
            && (
                $actions[
                    'item.create_from_ticket'
                ]
                ?? false
            ) === true;

        $canSelectProject =
            $showTab
            && $nativeCanCreate
            && ($actions['project.select'] ?? false)
                === true;

        $permissions = [
            'tab.view' => $showTab,
            'links.view' => $canViewLinks,
            'item.open' => $canOpenItems,
            'item.create_from_ticket' =>
                $canCreateFromTicket,
            'project.select' =>
                $canSelectProject,
        ];

        $linkedItems = [];

        if ($canViewLinks) {
            $linkedItems =
                $this->bridge->linkedItems(
                    'ticketing',
                    'ticket',
                    $ticketReference
                );

            foreach (
                $linkedItems
                as &$item
            ) {
                $item['work_url'] =
                    $canOpenItems
                        ? $this->workItemUrl(
                            (string) (
                                $item[
                                    'work_project_reference'
                                ]
                                ?? ''
                            ),
                            (string) (
                                $item[
                                    'work_item_reference'
                                ]
                                ?? ''
                            )
                        )
                        : '';
            }

            unset($item);
        }

        return [
            'found' => true,
            'visible' => true,
            'available' => true,
            'reason' => '',
            'ticket' => $ticket,
            'ticket_work_context' =>
                $policyContext,
            'work_project' => [
                'id' =>
                    $workProjectId,
                'public_reference' =>
                    $projectReference,
                'title' =>
                    (string) (
                        $destination[
                            'work_project_title'
                        ]
                        ?? ''
                    ),
                'code' =>
                    (string) (
                        $destination[
                            'work_project_code'
                        ]
                        ?? ''
                    ),
                'destination_source' =>
                    (string) (
                        $destination[
                            'destination_source'
                        ]
                        ?? ''
                    ),
                'destination_rule_reference' =>
                    (string) (
                        $destination[
                            'destination_rule_reference'
                        ]
                        ?? ''
                    ),
                'binding_reference' =>
                    (string) (
                        $destination[
                            'binding_reference'
                        ]
                        ?? ''
                    ),
                'defaults' =>
                    is_array(
                        $destination[
                            'defaults'
                        ]
                        ?? null
                    )
                        ? $destination[
                            'defaults'
                        ]
                        : [],
            ],
            'policy' =>
                $policyAccess,
            'native_work_access' =>
                $nativeAccess,
            'permissions' =>
                $permissions,
            'can_view_work' =>
                $showTab,
            'can_create_item' =>
                $canCreateFromTicket,
            'linked_items' =>
                $linkedItems,
        ];
    }

    public function createLinkedItem(
        string $ticketReference,
        array $input,
        int $userId,
        array $context = []
    ): array {
        $state =
            $this->context(
                $ticketReference,
                $userId
            );

        if (
            ($state['found'] ?? false) !== true
            || ($state['visible'] ?? false) !== true
        ) {
            return [
                'ok' => false,
                'forbidden' => true,
                'reason' =>
                    (string) (
                        $state['reason']
                        ?? 'ticket_forbidden'
                    ),
            ];
        }

        if (
            ($state['available'] ?? false) !== true
        ) {
            return [
                'ok' => false,
                'unavailable' => true,
                'reason' =>
                    (string) (
                        $state['reason']
                        ?? 'work_project_unavailable'
                    ),
            ];
        }

        if (
            (
                $state[
                    'permissions'
                ][
                    'item.create_from_ticket'
                ]
                ?? false
            ) !== true
            || ($state['can_create_item'] ?? false)
                !== true
        ) {
            return [
                'ok' => false,
                'forbidden' => true,
                'reason' =>
                    'ticket_work_create_forbidden',
            ];
        }

        $projectReference =
            trim(
                (string) (
                    $state[
                        'work_project'
                    ][
                        'public_reference'
                    ]
                    ?? ''
                )
            );

        if ($projectReference === '') {
            throw new RuntimeException(
                'Resolved Work project reference is empty.'
            );
        }

        $workInput =
            $this->applyDestinationDefaults(
                $input,
                is_array(
                    $state[
                        'work_project'
                    ][
                        'defaults'
                    ]
                    ?? null
                )
                    ? $state[
                        'work_project'
                    ][
                        'defaults'
                    ]
                    : []
            );

        if ($this->work->inTransaction()) {
            throw new RuntimeException(
                'Ticket-to-Work orchestration requires a clean Work transaction boundary.'
            );
        }

        $this->work->beginTransaction();

        try {
            $created =
                $this->workItems->create(
                    $projectReference,
                    $workInput,
                    $userId,
                    $context
                );

            if (
                ($created['ok'] ?? false)
                !== true
            ) {
                $this->work->rollBack();

                return $created;
            }

            $itemReference =
                trim(
                    (string) (
                        $created[
                            'public_reference'
                        ]
                        ?? ''
                    )
                );

            if ($itemReference === '') {
                throw new RuntimeException(
                    'Created Work item reference is empty.'
                );
            }

            $link =
                $this->bridge->linkItem(
                    $projectReference,
                    $itemReference,
                    'ticketing',
                    'ticket',
                    trim($ticketReference),
                    'created_from',
                    'user:' . $userId,
                    [
                        'ticket_number' =>
                            (string) (
                                $state[
                                    'ticket'
                                ][
                                    'ticket_number'
                                ]
                                ?? ''
                            ),
                        'ticket_subject' =>
                            (string) (
                                $state[
                                    'ticket'
                                ][
                                    'subject'
                                ]
                                ?? ''
                            ),
                        'destination_source' =>
                            (string) (
                                $state[
                                    'work_project'
                                ][
                                    'destination_source'
                                ]
                                ?? ''
                            ),
                        'destination_rule_reference' =>
                            (string) (
                                $state[
                                    'work_project'
                                ][
                                    'destination_rule_reference'
                                ]
                                ?? ''
                            ),
                    ]
                );

            $this->work->commit();

            return [
                'ok' => true,
                'work_project_reference' =>
                    $projectReference,
                'work_item_reference' =>
                    $itemReference,
                'source_link_reference' =>
                    (string) (
                        $link[
                            'public_reference'
                        ]
                        ?? ''
                    ),
                'destination_source' =>
                    (string) (
                        $state[
                            'work_project'
                        ][
                            'destination_source'
                        ]
                        ?? ''
                    ),
                'destination_rule_reference' =>
                    (string) (
                        $state[
                            'work_project'
                        ][
                            'destination_rule_reference'
                        ]
                        ?? ''
                    ),
                'work_url' =>
                    $this->workItemUrl(
                        $projectReference,
                        $itemReference
                    ),
            ];
        } catch (Throwable $exception) {
            if ($this->work->inTransaction()) {
                $this->work->rollBack();
            }

            throw $exception;
        }
    }

    private function resolveDestination(
        array $policyContext,
        string $supportProjectReference
    ): ?array {
        $dynamic =
            $this->policy->resolveDestination(
                $policyContext
            );

        if (is_array($dynamic)) {
            return [
                'work_project_id' =>
                    (int) (
                        $dynamic[
                            'work_project_id'
                        ]
                        ?? 0
                    ),
                'work_project_reference' =>
                    (string) (
                        $dynamic[
                            'work_project_reference'
                        ]
                        ?? ''
                    ),
                'work_project_code' =>
                    (string) (
                        $dynamic[
                            'work_project_code'
                        ]
                        ?? ''
                    ),
                'work_project_title' =>
                    (string) (
                        $dynamic[
                            'work_project_title'
                        ]
                        ?? ''
                    ),
                'destination_source' =>
                    'dynamic_rule',
                'destination_rule_reference' =>
                    (string) (
                        $dynamic[
                            'public_reference'
                        ]
                        ?? ''
                    ),
                'binding_reference' => '',
                'defaults' => [
                    'item_type' =>
                        (string) (
                            $dynamic[
                                'default_item_type'
                            ]
                            ?? ''
                        ),
                    'status_code' =>
                        (string) (
                            $dynamic[
                                'default_status_code'
                            ]
                            ?? ''
                        ),
                    'priority_code' =>
                        (string) (
                            $dynamic[
                                'default_priority_code'
                            ]
                            ?? ''
                        ),
                    'assignee_reference' =>
                        (string) (
                            $dynamic[
                                'default_assignee_reference'
                            ]
                            ?? ''
                        ),
                ],
            ];
        }

        $binding =
            $this->primaryProjectBinding(
                $supportProjectReference
            );

        if ($binding === null) {
            return null;
        }

        return [
            'work_project_id' =>
                (int) (
                    $binding[
                        'work_project_id'
                    ]
                    ?? 0
                ),
            'work_project_reference' =>
                (string) (
                    $binding[
                        'work_project_reference'
                    ]
                    ?? ''
                ),
            'work_project_code' =>
                (string) (
                    $binding[
                        'work_project_code'
                    ]
                    ?? ''
                ),
            'work_project_title' =>
                (string) (
                    $binding[
                        'work_project_title'
                    ]
                    ?? ''
                ),
            'destination_source' =>
                'project_binding_fallback',
            'destination_rule_reference' => '',
            'binding_reference' =>
                (string) (
                    $binding[
                        'public_reference'
                    ]
                    ?? ''
                ),
            'defaults' => [],
        ];
    }

    private function applyDestinationDefaults(
        array $input,
        array $defaults
    ): array {
        foreach (
            [
                'item_type',
                'status_code',
                'priority_code',
                'assignee_reference',
            ]
            as $key
        ) {
            $current =
                trim(
                    (string) (
                        $input[$key]
                        ?? ''
                    )
                );

            $default =
                trim(
                    (string) (
                        $defaults[$key]
                        ?? ''
                    )
                );

            if (
                $current === ''
                && $default !== ''
            ) {
                $input[$key] =
                    $default;
            }
        }

        return $input;
    }

    private function ticketSource(
        string $ticketReference
    ): ?array {
        $statement =
            $this->ticketing->prepare(
                "SELECT
                    tickets.id,
                    tickets.public_reference,
                    tickets.ticket_number,
                    tickets.subject,
                    tickets.status_code,
                    projects.public_reference
                        AS support_project_reference,
                    projects.code
                        AS support_project_code,
                    projects.title
                        AS support_project_title
                 FROM ticketing_tickets tickets
                 INNER JOIN ticketing_support_projects projects
                    ON projects.id =
                        tickets.support_project_id
                 WHERE tickets.public_reference = ?
                   AND tickets.archived_at IS NULL
                   AND projects.archived_at IS NULL
                   AND projects.is_active = 1
                 LIMIT 1"
            );

        $statement->execute([
            $ticketReference,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return is_array($row)
            ? $row
            : null;
    }

    private function primaryProjectBinding(
        string $supportProjectReference
    ): ?array {
        $bindings =
            $this->bridge->bindings(
                'ticketing',
                'project',
                trim(
                    $supportProjectReference
                ),
                'default'
            );

        $primary = [];

        foreach ($bindings as $binding) {
            if (
                (int) (
                    $binding['is_primary']
                    ?? 0
                ) === 1
            ) {
                $primary[] =
                    $binding;
            }
        }

        if (count($primary) !== 1) {
            return null;
        }

        return $primary[0];
    }

    private function workItemUrl(
        string $projectReference,
        string $itemReference
    ): string {
        $projectReference =
            trim(
                $projectReference
            );

        $itemReference =
            trim(
                $itemReference
            );

        if (
            $projectReference === ''
            || $itemReference === ''
        ) {
            return '';
        }

        return $this->urls->workLaunch(
            '/admin/work/projects/'
            . rawurlencode(
                $projectReference
            )
            . '/items/'
            . rawurlencode(
                $itemReference
            )
        );
    }

    private function denyPolicyResult(): array
    {
        return [
            'actions' =>
                $this->denyPermissions(),
            'matched_rule_references' => [],
            'default_effect' => 'deny',
        ];
    }

    private function denyPermissions(): array
    {
        return [
            'tab.view' => false,
            'links.view' => false,
            'item.open' => false,
            'item.create_from_ticket' => false,
            'project.select' => false,
        ];
    }

    private function emptyContext(
        string $reason
    ): array {
        return [
            'found' => false,
            'visible' => false,
            'available' => false,
            'reason' => $reason,
            'ticket' => [],
            'ticket_work_context' => [],
            'work_project' => [],
            'policy' =>
                $this->denyPolicyResult(),
            'permissions' =>
                $this->denyPermissions(),
            'can_view_work' => false,
            'can_create_item' => false,
            'linked_items' => [],
        ];
    }
}
