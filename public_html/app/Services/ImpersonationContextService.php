<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class ImpersonationContextService
{
    public const SESSION_KEY =
        'auth_impersonation';

    public const DEFAULT_TTL_MINUTES =
        30;

    private const MAX_TTL_MINUTES =
        120;

    public const MODE_OBSERVE =
        'observe';

    public const MODE_OPERATE =
        'operate';

    private const MODES = [
        self::MODE_OBSERVE,
        self::MODE_OPERATE,
    ];

    private const SNAPSHOT_KEYS = [
        'auth_user_id',
        'auth_password_fingerprint',
        'auth_login_at',
        'auth_mfa_verified',
        'active_role_assignment_id',
    ];


    public function create(
        int $actorUserId,
        int $effectiveUserId,
        array $actorAuthSnapshot,
        string $nonce,
        ?string $returnPath = null,
        ?DateTimeImmutable $startedAt = null,
        ?int $ttlMinutes = null,
        string $mode = self::MODE_OBSERVE
    ): array {
        if (
            $actorUserId < 1
            || $effectiveUserId < 1
            || $actorUserId === $effectiveUserId
        ) {
            throw new InvalidArgumentException(
                'invalid_impersonation_identity_pair'
            );
        }

        $nonce =
            trim(
                $nonce
            );

        if (
            preg_match(
                '/^[A-Za-z0-9_-]{16,128}$/',
                $nonce
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'invalid_impersonation_nonce'
            );
        }

        $mode =
            strtolower(
                trim(
                    $mode
                )
            );

        if (
            !in_array(
                $mode,
                self::MODES,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'invalid_impersonation_mode'
            );
        }

        $startedAt =
            (
                $startedAt
                ?? new DateTimeImmutable(
                    'now',
                    new DateTimeZone(
                        'UTC'
                    )
                )
            )->setTimezone(
                new DateTimeZone(
                    'UTC'
                )
            );

        $ttl =
            $ttlMinutes
            ?? $this->configuredTtlMinutes();

        if (
            $ttl < 1
            || $ttl > self::MAX_TTL_MINUTES
        ) {
            throw new InvalidArgumentException(
                'invalid_impersonation_ttl'
            );
        }

        $snapshot =
            $this->filterSnapshot(
                $actorAuthSnapshot
            );

        if (
            (int) (
                $snapshot[
                    'auth_user_id'
                ]
                ?? 0
            ) !== $actorUserId
        ) {
            throw new InvalidArgumentException(
                'impersonation_actor_snapshot_mismatch'
            );
        }

        $expiresAt =
            $startedAt->modify(
                '+' . $ttl . ' minutes'
            );

        return [
            'actor_user_id' =>
                $actorUserId,

            'effective_user_id' =>
                $effectiveUserId,

            'mode' =>
                $mode,

            'started_at' =>
                $startedAt->format(
                    'Y-m-d H:i:s'
                ),

            'expires_at' =>
                $expiresAt->format(
                    'Y-m-d H:i:s'
                ),

            'nonce' =>
                $nonce,

            'actor_auth_snapshot' =>
                $snapshot,

            'return_path' =>
                $this->localReturnPath(
                    $returnPath
                ),
        ];
    }


    public function inspect(
        mixed $context,
        ?DateTimeImmutable $now = null
    ): array {
        if (!is_array($context)) {
            return $this->invalidState();
        }

        $actorUserId =
            (int) (
                $context[
                    'actor_user_id'
                ]
                ?? 0
            );

        $effectiveUserId =
            (int) (
                $context[
                    'effective_user_id'
                ]
                ?? 0
            );

        $mode =
            strtolower(
                trim(
                    (string) (
                        $context[
                            'mode'
                        ]
                        ?? self::MODE_OBSERVE
                    )
                )
            );

        $nonce =
            trim(
                (string) (
                    $context[
                        'nonce'
                    ]
                    ?? ''
                )
            );

        $startedAt =
            $this->utcDate(
                $context[
                    'started_at'
                ]
                ?? null
            );

        $expiresAt =
            $this->utcDate(
                $context[
                    'expires_at'
                ]
                ?? null
            );

        $snapshot =
            is_array(
                $context[
                    'actor_auth_snapshot'
                ]
                ?? null
            )
                ? $this->filterSnapshot(
                    $context[
                        'actor_auth_snapshot'
                    ]
                )
                : [];

        if (
            $actorUserId < 1
            || $effectiveUserId < 1
            || $actorUserId ===
                $effectiveUserId
            || !in_array(
                $mode,
                self::MODES,
                true
            )
            || preg_match(
                '/^[A-Za-z0-9_-]{16,128}$/',
                $nonce
            ) !== 1
            || $startedAt === null
            || $expiresAt === null
            || $expiresAt <= $startedAt
            || (int) (
                $snapshot[
                    'auth_user_id'
                ]
                ?? 0
            ) !== $actorUserId
        ) {
            return $this->invalidState();
        }

        $now =
            (
                $now
                ?? new DateTimeImmutable(
                    'now',
                    new DateTimeZone(
                        'UTC'
                    )
                )
            )->setTimezone(
                new DateTimeZone(
                    'UTC'
                )
            );

        $expired =
            $now >= $expiresAt;

        return [
            'valid' =>
                true,

            'active' =>
                !$expired,

            'expired' =>
                $expired,

            'actor_user_id' =>
                $actorUserId,

            'effective_user_id' =>
                $effectiveUserId,

            'mode' =>
                $mode,

            'started_at' =>
                $startedAt->format(
                    'Y-m-d H:i:s'
                ),

            'expires_at' =>
                $expiresAt->format(
                    'Y-m-d H:i:s'
                ),

            'nonce' =>
                $nonce,

            'actor_auth_snapshot' =>
                $snapshot,

            'return_path' =>
                $this->localReturnPath(
                    $context[
                        'return_path'
                    ]
                    ?? null
                ),
        ];
    }


    public function isActive(
        mixed $context,
        ?DateTimeImmutable $now = null
    ): bool {
        return
            (bool) (
                $this->inspect(
                    $context,
                    $now
                )['active']
                ?? false
            );
    }


    private function filterSnapshot(
        array $snapshot
    ): array {
        $filtered = [];

        foreach (
            self::SNAPSHOT_KEYS
            as $key
        ) {
            if (
                array_key_exists(
                    $key,
                    $snapshot
                )
            ) {
                $filtered[$key] =
                    $snapshot[$key];
            }
        }

        return $filtered;
    }


    private function configuredTtlMinutes(): int
    {
        $raw =
            trim(
                (string) getenv(
                    'IMPERSONATION_TTL_MINUTES'
                )
            );

        if (
            $raw === ''
            || preg_match(
                '/^[0-9]{1,3}$/',
                $raw
            ) !== 1
        ) {
            return
                self::DEFAULT_TTL_MINUTES;
        }

        $ttl =
            (int) $raw;

        return
            $ttl >= 1
            && $ttl <=
                self::MAX_TTL_MINUTES
                ? $ttl
                : self::DEFAULT_TTL_MINUTES;
    }


    private function localReturnPath(
        mixed $value
    ): string {
        $value =
            trim(
                (string) (
                    $value
                    ?? ''
                )
            );

        if (
            $value === ''
            || !str_starts_with(
                $value,
                '/'
            )
            || str_starts_with(
                $value,
                '//'
            )
            || str_contains(
                $value,
                "\r"
            )
            || str_contains(
                $value,
                "\n"
            )
        ) {
            return
                '/admin/users';
        }

        return $value;
    }


    private function utcDate(
        mixed $value
    ): ?DateTimeImmutable {
        $value =
            trim(
                (string) (
                    $value
                    ?? ''
                )
            );

        if ($value === '') {
            return null;
        }

        $date =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i:s',
                $value,
                new DateTimeZone(
                    'UTC'
                )
            );

        $errors =
            DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || (
                is_array($errors)
                && (
                    (int) (
                        $errors[
                            'warning_count'
                        ]
                        ?? 0
                    ) > 0
                    || (int) (
                        $errors[
                            'error_count'
                        ]
                        ?? 0
                    ) > 0
                )
            )
        ) {
            return null;
        }

        return $date;
    }


    private function invalidState(): array
    {
        return [
            'valid' =>
                false,

            'active' =>
                false,

            'expired' =>
                false,

            'actor_user_id' =>
                null,

            'effective_user_id' =>
                null,
        ];
    }
}
