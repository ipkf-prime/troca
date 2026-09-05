<?php

declare(strict_types=1);

namespace App\Services;

use IPKF\Support\Session;

/**
 * DYNAMIC_SCOPED_ACCESS_FOUNDATION_V1
 *
 * AuthorizationService remains the atomic permission check.
 * This service adds optional resource-context scope/constraint evaluation.
 *
 * Existing assignments without role_assignment_scopes remain compatible
 * until their owning module is migrated to contextual authorization.
 */
class ScopedAuthorizationService extends BaseService
{
    private AuthorizationService $authorization;
    private DynamicAccessService $dynamicAccess;

    public function __construct(
        ?AuthorizationService $authorization = null,
        ?DynamicAccessService $dynamicAccess = null
    ) {
        $this->authorization =
            $authorization
            ?? new AuthorizationService();

        $this->dynamicAccess =
            $dynamicAccess
            ?? new DynamicAccessService();
    }

    public function decide(
        int $userId,
        string $permissionCode,
        array $context
    ): array {
        if (
            $userId < 1
            || !$this->authorization->hasPermission(
                $userId,
                $permissionCode
            )
        ) {
            return [
                'allowed' => false,
                'reason' => 'permission_denied',
            ];
        }

        $assignmentId = (int) (
            Session::get('active_role_assignment_id')
            ?? 0
        );

        if ($assignmentId < 1) {
            return [
                'allowed' => true,
                'reason' => 'legacy_unscoped_permission',
            ];
        }

        $assignment = $this->dynamicAccess
            ->assignmentForUser(
                $userId,
                $assignmentId
            );

        if ($assignment === null) {
            return [
                'allowed' => false,
                'reason' => 'active_assignment_invalid',
            ];
        }

        $policy = $this->dynamicAccess
            ->assignmentPolicy($assignmentId);

        $scopes = is_array($policy['scopes'] ?? null)
            ? $policy['scopes']
            : [];

        $constraints = is_array(
            $policy['constraints'] ?? null
        )
            ? $policy['constraints']
            : [];

        if ($scopes === []) {
            $roleId =
                (int) (
                    $assignment['role_id']
                    ?? 0
                );

            if (
                $this->dynamicAccess
                    ->roleHasExplicitScopePolicy(
                        $roleId
                    )
            ) {
                /*
                 * Reference-free role policies such as
                 * own/global/assigned are self-contained
                 * and do not require a duplicated
                 * assignment-scope row.
                 *
                 * Any scope requiring a concrete reference
                 * remains fail-closed.
                 */
                $scopes =
                    $this->dynamicAccess
                        ->referenceFreeDefaultScopesForRole(
                            $roleId
                        );

                if ($scopes === []) {
                    return [
                        'allowed' => false,
                        'reason' =>
                            'scope_policy_required',
                    ];
                }
            } else {
                return [
                    'allowed' => true,
                    'reason' =>
                        'legacy_assignment_without_scope_policy',
                ];
            }
        }

        if (!$this->scopeAllowed($scopes, $context)) {
            return [
                'allowed' => false,
                'reason' => 'scope_denied',
            ];
        }

        if (
            !$this->constraintsAllowed(
                $constraints,
                $context
            )
        ) {
            return [
                'allowed' => false,
                'reason' => 'constraint_denied',
            ];
        }

        return [
            'allowed' => true,
            'reason' => 'scoped_permission_allowed',
        ];
    }

    public function hasPermissionInContext(
        int $userId,
        string $permissionCode,
        array $context
    ): bool {
        return !empty(
            $this->decide(
                $userId,
                $permissionCode,
                $context
            )['allowed']
        );
    }

    private function scopeAllowed(
        array $scopes,
        array $context
    ): bool {
        $type = strtolower(trim(
            (string) ($context['scope_type'] ?? '')
        ));

        $reference = trim(
            (string) ($context['scope_reference'] ?? '')
        );

        $ancestors = is_array(
            $context['ancestors'] ?? null
        )
            ? $context['ancestors']
            : [];

        $allowMatched = false;

        foreach ($scopes as $scope) {
            if (
                !$this->scopeMatches(
                    $scope,
                    $type,
                    $reference,
                    $ancestors
                )
            ) {
                continue;
            }

            if (
                (string) (
                    $scope['effect_code']
                    ?? 'allow'
                ) === 'deny'
            ) {
                return false;
            }

            $allowMatched = true;
        }

        return $allowMatched;
    }

    private function scopeMatches(
        array $scope,
        string $type,
        string $reference,
        array $ancestors
    ): bool {
        $scopeType = strtolower(trim(
            (string) (
                $scope['scope_type_code']
                ?? ''
            )
        ));

        $scopeReference = trim(
            (string) (
                $scope['scope_reference']
                ?? ''
            )
        );

        if ($scopeType === 'global') {
            return true;
        }

        if ($scopeType === 'own') {
            return !empty($ancestors['own']);
        }

        if ($scopeType === 'assigned') {
            return !empty($ancestors['assigned']);
        }

        if (
            $scopeType === $type
            && $scopeReference === $reference
        ) {
            return true;
        }

        if (empty($scope['include_descendants'])) {
            return false;
        }

        $candidate = $ancestors[$scopeType] ?? null;

        if (is_array($candidate)) {
            return in_array(
                $scopeReference,
                array_map('strval', $candidate),
                true
            );
        }

        return $candidate !== null
            && (string) $candidate === $scopeReference;
    }

    private function constraintsAllowed(
        array $constraints,
        array $context
    ): bool {
        if ($constraints === []) {
            return true;
        }

        $attributes = is_array(
            $context['attributes'] ?? null
        )
            ? $context['attributes']
            : [];

        foreach ($constraints as $constraint) {
            $effect = (string) (
                $constraint['effect_code']
                ?? 'allow'
            );

            $type = (string) (
                $constraint['constraint_type_code']
                ?? ''
            );

            $actual = $attributes[$type] ?? null;

            $expected = json_decode(
                (string) (
                    $constraint['value_json']
                    ?? 'null'
                ),
                true
            );

            $matched = $this->compare(
                $actual,
                (string) (
                    $constraint['operator_code']
                    ?? 'eq'
                ),
                $expected
            );

            if ($effect === 'deny') {
                if ($matched) {
                    return false;
                }

                continue;
            }

            /*
             * Allow constraints are restrictive and compose with AND.
             * Every allow constraint must match; deny still overrides.
             */
            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    private function compare(
        mixed $actual,
        string $operator,
        mixed $expected
    ): bool {
        return match ($operator) {
            'eq' =>
                (string) $actual
                    === (string) $expected,

            'neq' =>
                (string) $actual
                    !== (string) $expected,

            'in' =>
                is_array($expected)
                && in_array(
                    (string) $actual,
                    array_map('strval', $expected),
                    true
                ),

            'not_in' =>
                is_array($expected)
                && !in_array(
                    (string) $actual,
                    array_map('strval', $expected),
                    true
                ),

            'contains' =>
                str_contains(
                    (string) $actual,
                    (string) $expected
                ),

            'exists' =>
                $actual !== null
                && $actual !== '',

            default => false,
        };
    }
}
