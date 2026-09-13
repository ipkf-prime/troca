<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

/**
 * TICKETING_GENERIC_SCOPED_TOPOLOGY_ADMIN_EVALUATOR_V1
 *
 * Pure project-local scope policy for delegated topology administration.
 *
 * Important boundaries:
 * - Project-wide manager authority is NOT implemented here.
 * - Core/global RBAC is NOT consulted or aliased here.
 * - Business-domain level names are deliberately absent.
 * - A scope is identified only by generic type + reference.
 * - Descendant knowledge must be supplied by a trusted resolver.
 * - Multiple active delegated scopes compose with OR.
 * - Malformed/expired/inactive/unknown scopes fail closed.
 *
 * Canonical capabilities_json example:
 *
 * {
 *   "topology.view": true,
 *   "topology.create": true,
 *   "topology.update": true,
 *   "topology.delete": false
 * }
 */
final class TicketingScopedTopologyAdminEvaluator
{
    private const ACTIONS = [
        'topology.view',
        'topology.create',
        'topology.update',
        'topology.delete',
    ];

    private const ACCESS_MODES = [
        'exact',
        'descendants',
    ];

    public function allows(
        array $scopeRows,
        string $action,
        array $resourceScope,
        ?DateTimeInterface $now = null
    ): bool {
        $action =
            strtolower(
                trim($action)
            );

        if (
            !in_array(
                $action,
                self::ACTIONS,
                true
            )
        ) {
            return false;
        }

        $resourceType =
            trim(
                (string) (
                    $resourceScope[
                        'scope_type_code'
                    ]
                    ?? ''
                )
            );

        $resourceReference =
            trim(
                (string) (
                    $resourceScope[
                        'scope_reference'
                    ]
                    ?? ''
                )
            );

        if (
            $resourceType === ''
            || $resourceReference === ''
        ) {
            return false;
        }

        $ancestors =
            $this->normalizeReferences(
                $resourceScope[
                    'ancestor_scope_references'
                ]
                ?? []
            );

        $instant =
            $now instanceof DateTimeInterface
                ? DateTimeImmutable::createFromInterface(
                    $now
                )
                : new DateTimeImmutable('now');

        foreach ($scopeRows as $scope) {
            if (!is_array($scope)) {
                continue;
            }

            if (
                !$this->isEffective(
                    $scope,
                    $instant
                )
            ) {
                continue;
            }

            $scopeType =
                trim(
                    (string) (
                        $scope[
                            'scope_type_code'
                        ]
                        ?? ''
                    )
                );

            $scopeReference =
                trim(
                    (string) (
                        $scope[
                            'scope_reference'
                        ]
                        ?? ''
                    )
                );

            if (
                $scopeType === ''
                || $scopeReference === ''
                || $scopeType !== $resourceType
            ) {
                continue;
            }

            $mode =
                strtolower(
                    trim(
                        (string) (
                            $scope[
                                'access_mode_code'
                            ]
                            ?? ''
                        )
                    )
                );

            if (
                !in_array(
                    $mode,
                    self::ACCESS_MODES,
                    true
                )
            ) {
                continue;
            }

            $capabilities =
                $this->capabilities(
                    $scope
                );

            if (
                $capabilities === null
                || empty(
                    $capabilities[$action]
                )
            ) {
                continue;
            }

            if (
                $mode === 'exact'
                && $scopeReference ===
                    $resourceReference
            ) {
                return true;
            }

            if (
                $mode === 'descendants'
                &&
                (
                    $scopeReference ===
                        $resourceReference
                    ||
                    in_array(
                        $scopeReference,
                        $ancestors,
                        true
                    )
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function isEffective(
        array $scope,
        DateTimeImmutable $now
    ): bool {
        if (
            strtolower(
                trim(
                    (string) (
                        $scope['status']
                        ?? 'active'
                    )
                )
            ) !== 'active'
        ) {
            return false;
        }

        $validFrom =
            $this->dateBoundary(
                $scope['valid_from']
                ?? null
            );

        if (!$validFrom['valid']) {
            return false;
        }

        if (
            $validFrom['date'] !== null
            && $validFrom['date'] > $now
        ) {
            return false;
        }

        $validUntil =
            $this->dateBoundary(
                $scope['valid_until']
                ?? null
            );

        if (!$validUntil['valid']) {
            return false;
        }

        if (
            $validUntil['date'] !== null
            && $validUntil['date'] < $now
        ) {
            return false;
        }

        return true;
    }

    private function capabilities(
        array $scope
    ): ?array {
        $raw =
            $scope[
                'capabilities_json'
            ]
            ?? $scope[
                'capabilities'
            ]
            ?? null;

        if (is_string($raw)) {
            $raw = trim($raw);

            if ($raw === '') {
                return null;
            }

            try {
                $raw =
                    json_decode(
                        $raw,
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );
            } catch (Throwable) {
                return null;
            }
        }

        if (!is_array($raw)) {
            return null;
        }

        $normalized = [];

        foreach (
            self::ACTIONS
            as $action
        ) {
            $value =
                $raw[$action]
                ?? false;

            $normalized[$action] =
                $value === true
                || $value === 1
                || $value === '1';
        }

        return $normalized;
    }

    private function normalizeReferences(
        mixed $raw
    ): array {
        if (!is_array($raw)) {
            return [];
        }

        $references = [];

        foreach ($raw as $row) {
            if (is_scalar($row)) {
                $reference =
                    trim(
                        (string) $row
                    );

            } elseif (is_array($row)) {
                $reference =
                    trim(
                        (string) (
                            $row[
                                'scope_reference'
                            ]
                            ?? $row[
                                'value_reference'
                            ]
                            ?? $row[
                                'resource_reference'
                            ]
                            ?? ''
                        )
                    );

            } else {
                continue;
            }

            if ($reference !== '') {
                $references[$reference] =
                    true;
            }
        }

        return
            array_keys(
                $references
            );
    }

    private function dateBoundary(
        mixed $raw
    ): array {
        if ($raw instanceof DateTimeInterface) {
            return [
                'valid' => true,
                'date' =>
                    DateTimeImmutable::createFromInterface(
                        $raw
                    ),
            ];
        }

        $value =
            trim(
                (string) (
                    $raw
                    ?? ''
                )
            );

        if ($value === '') {
            return [
                'valid' => true,
                'date' => null,
            ];
        }

        try {
            return [
                'valid' => true,
                'date' =>
                    new DateTimeImmutable(
                        $value
                    ),
            ];
        } catch (Throwable) {
            return [
                'valid' => false,
                'date' => null,
            ];
        }
    }
}
