<?php

namespace App\Services;

use App\Repositories\NotificationProviderDefaultRepository;
use InvalidArgumentException;

class NotificationProviderResolver extends BaseService
{
    public function __construct(
        private ?NotificationProviderDefaultRepository $repository = null
    ) {
        $this->repository ??=
            new NotificationProviderDefaultRepository();
    }

    public function resolve(
        string $channelCode,
        string $purposeCode = 'general',
        string $scopeType = 'global',
        string $scopeReference = '*'
    ): array {
        $channelCode = strtolower(trim($channelCode));
        $purposeCode = strtolower(trim($purposeCode));
        $scopeType = strtolower(trim($scopeType));
        $scopeReference = trim($scopeReference);

        if (!in_array(
            $channelCode,
            ['email', 'sms', 'messenger'],
            true
        )) {
            throw new InvalidArgumentException(
                'provider_resolver_channel_invalid'
            );
        }

        if (
            preg_match(
                '/^[a-z][a-z0-9._-]{1,59}$/',
                $purposeCode
            ) !== 1
            || preg_match(
                '/^[a-z][a-z0-9._-]{1,29}$/',
                $scopeType
            ) !== 1
            || $scopeReference === ''
            || mb_strlen(
                $scopeReference,
                'UTF-8'
            ) > 190
        ) {
            throw new InvalidArgumentException(
                'provider_resolver_context_invalid'
            );
        }

        $ranked = [];

        foreach (
            $this->repository->candidates($channelCode)
            as $candidate
        ) {
            $rank = $this->rank(
                $candidate,
                $purposeCode,
                $scopeType,
                $scopeReference
            );

            if ($rank === null) {
                continue;
            }

            $candidate['resolution_rank'] = $rank;
            $ranked[] = $candidate;
        }

        usort(
            $ranked,
            static fn (array $a, array $b): int =>
                [
                    (int) $a['resolution_rank'],
                    empty($a['is_default']) ? 1 : 0,
                    (int) $a['fallback_order'],
                    (int) $a['default_priority'],
                    -(int) $a['instance_priority'],
                    (int) $a['default_id'],
                ] <=> [
                    (int) $b['resolution_rank'],
                    empty($b['is_default']) ? 1 : 0,
                    (int) $b['fallback_order'],
                    (int) $b['default_priority'],
                    -(int) $b['instance_priority'],
                    (int) $b['default_id'],
                ]
        );

        $resolved = [];
        $seen = [];

        foreach ($ranked as $candidate) {
            $instanceId = (int) (
                $candidate['provider_instance_id'] ?? 0
            );

            if (
                $instanceId < 1
                || isset($seen[$instanceId])
            ) {
                continue;
            }

            $seen[$instanceId] = true;
            $resolved[] = $candidate;
        }

        return $resolved;
    }

    public function primary(
        string $channelCode,
        string $purposeCode = 'general',
        string $scopeType = 'global',
        string $scopeReference = '*'
    ): ?array {
        return $this->resolve(
            $channelCode,
            $purposeCode,
            $scopeType,
            $scopeReference
        )[0] ?? null;
    }

    private function rank(
        array $candidate,
        string $purposeCode,
        string $scopeType,
        string $scopeReference
    ): ?int {
        $candidatePurpose = (string) (
            $candidate['purpose_code'] ?? ''
        );
        $candidateScopeType = (string) (
            $candidate['scope_type'] ?? ''
        );
        $candidateScopeReference = (string) (
            $candidate['scope_reference'] ?? ''
        );

        $exactScope =
            $candidateScopeType === $scopeType
            && $candidateScopeReference === $scopeReference;

        $globalScope =
            $candidateScopeType === 'global'
            && $candidateScopeReference === '*';

        if (
            $exactScope
            && $candidatePurpose === $purposeCode
        ) {
            return 0;
        }

        if (
            $globalScope
            && $candidatePurpose === $purposeCode
        ) {
            return 1;
        }

        if (
            $purposeCode !== 'general'
            && $exactScope
            && $candidatePurpose === 'general'
        ) {
            return 2;
        }

        if (
            $purposeCode !== 'general'
            && $globalScope
            && $candidatePurpose === 'general'
        ) {
            return 3;
        }

        return null;
    }
}
