<?php

namespace App\Services;

use App\Repositories\IdentityChangeRepository;
use App\Repositories\IdentityVerificationRepository;
use App\Repositories\UserRepository;
use IPKF\Database\Database;
use IPKF\Support\Clock;

class SelfIdentityChangeService extends BaseService
{
    private const TTL_SECONDS = 900;
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private ?IdentityChangeRepository $changes = null,
        private ?UserRepository $users = null,
        private ?IdentityNormalizer $normalizer = null,
        private ?IdentityOtpDeliveryService $delivery = null,
        private ?IdentityVerificationRepository $verification = null
    ) {
        $this->changes ??= new IdentityChangeRepository();
        $this->users ??= new UserRepository();
        $this->normalizer ??= new IdentityNormalizer();
        $this->delivery ??=
            new IdentityOtpDeliveryService();
        $this->verification ??=
            new IdentityVerificationRepository();
    }

    public function request(
        int $userId,
        string $field,
        string $value,
        string $password
    ): array {
        $hash = $this->users->passwordHashForUser(
            $userId
        );

        if (
            $hash === null
            || !password_verify($password, $hash)
        ) {
            return $this->error('invalid_credentials');
        }

        $field = strtolower(trim($field));

        if (!in_array(
            $field,
            ['email', 'mobile'],
            true
        )) {
            return $this->error('invalid_field');
        }

        $normalized = $field === 'email'
            ? $this->normalizer->email($value)
            : $this->normalizer->mobile($value);

        if ($normalized === null) {
            return $this->error(
                'invalid_identity_value'
            );
        }

        $current = $this->users->identityValueForUser(
            $userId,
            $field
        );
        $currentNormalized = $current === null
            ? null
            : (
                $field === 'email'
                    ? $this->normalizer->email($current)
                    : $this->normalizer->mobile($current)
            );

        if (
            $currentNormalized !== null
            && hash_equals(
                $currentNormalized,
                $normalized
            )
        ) {
            return $this->error('value_unchanged');
        }

        if ($this->users->identityValueExists(
            $field,
            $normalized,
            $userId
        )) {
            return $this->error('value_not_available');
        }

        $now = Clock::nowUtc();
        $expires = $now->modify(
            '+' . self::TTL_SECONDS . ' seconds'
        );

        if ($this->changes->findActivePending(
            $userId,
            $field,
            $normalized,
            Clock::databaseTimestamp($now)
        ) !== null) {
            return $this->error(
                'change_request_already_pending'
            );
        }

        $code = (string) random_int(100000, 999999);
        $requestId = $this->changes->create([
            'user_id' => $userId,
            'field_name' => $field,
            'old_value' => $current,
            'new_value' => $normalized,
            'normalized_new_value' => $normalized,
            'token_hash' => password_hash(
                $code,
                PASSWORD_DEFAULT
            ),
            'channel' => $field === 'email'
                ? 'email'
                : 'sms',
            'expires_at' => Clock::databaseTimestamp(
                $expires
            ),
        ]);

        $delivered = $this->delivery->deliver(
            $field,
            $normalized,
            $code
        );

        if (($delivered['ok'] ?? false) !== true) {
            $this->changes->markCancelled($requestId);

            return [
                'ok' => false,
                'status' => (string) (
                    $delivered['status']
                    ?? 'delivery_failed'
                ),
            ];
        }

        return [
            'ok' => true,
            'status' => (string) (
                $delivered['status'] ?? 'sent'
            ),
            'request_id' => $requestId,
            'field' => $field,
            'masked_destination' => $this->masked(
                $field,
                $normalized
            ),
            'expires_at' => Clock::isoUtc($expires),
            'dev_token' => $delivered['dev_token']
                ?? null,
        ];
    }

    public function confirm(
        int $userId,
        int $requestId,
        string $code
    ): array {
        $request = $this->changes->findPending(
            $requestId,
            $userId
        );
        $code = preg_replace('/\D+/', '', $code)
            ?: '';

        if (
            $request === null
            || strlen($code) !== 6
            || (int) $request['attempts']
                >= self::MAX_ATTEMPTS
            || $this->expired(
                (string) $request['expires_at']
            )
        ) {
            return $this->error(
                'invalid_or_expired_code'
            );
        }

        if (!password_verify(
            $code,
            (string) $request['token_hash']
        )) {
            $this->changes->markAttempt($requestId);

            return $this->error(
                'invalid_or_expired_code'
            );
        }

        $field = (string) $request['field_name'];
        $newValue = (string) $request['new_value'];

        if (
            !in_array(
                $field,
                ['email', 'mobile'],
                true
            )
            || $this->users->identityValueExists(
                $field,
                $newValue,
                $userId
            )
        ) {
            return $this->error('value_not_available');
        }

        $db =
            Database::connect();

        $ownsTransaction =
            !$db->inTransaction();

        if ($ownsTransaction) {
            $db->beginTransaction();
        }

        try {
            $this->users->applyIdentityChange(
                $userId,
                $field,
                $newValue,
                $newValue
            );

            $this->verification->markVerified(
                $userId,
                $field
            );

            $this->changes->markApplied(
                $requestId
            );

            if (
                Database::columnExists(
                    'user_role_assignments',
                    'lifecycle_status_code'
                )
            ) {
                (
                    new RoleAssignmentLifecycleService(
                        $db
                    )
                )->refreshUser(
                    $userId,
                    $userId
                );
            }

            if ($ownsTransaction) {
                $db->commit();
            }

        } catch (\Throwable $exception) {
            if (
                $ownsTransaction
                && $db->inTransaction()
            ) {
                $db->rollBack();
            }

            throw $exception;
        }

        return [
            'ok' => true,
            'status' => 'applied',
            'field' => $field,
        ];
    }

    private function expired(string $value): bool
    {
        $expires = Clock::parseStoredInstant($value);

        return $expires === null
            || $expires < Clock::nowUtc();
    }

    private function masked(
        string $field,
        string $value
    ): string {
        if ($field === 'email') {
            [$local, $domain] = array_pad(
                explode('@', $value, 2),
                2,
                ''
            );

            return mb_substr(
                $local,
                0,
                2,
                'UTF-8'
            )
                . '***@'
                . $domain;
        }

        return mb_substr($value, 0, 4, 'UTF-8')
            . '***'
            . mb_substr($value, -3, null, 'UTF-8');
    }

    private function error(string $status): array
    {
        return [
            'ok' => false,
            'status' => $status,
        ];
    }
}
