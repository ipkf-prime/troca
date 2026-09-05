<?php

namespace App\Services;

use App\Repositories\IdentityChangeRepository;
use App\Repositories\UserRepository;
use IPKF\Database\Database;
use IPKF\Support\Clock;
use IPKF\Support\Env;

class IdentityChangeService extends BaseService
{
    private const TTL_SECONDS = 900;
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        protected ?IdentityChangeRepository $changes = null,
        protected ?UserRepository $users = null,
        protected ?IdentityNormalizer $normalizer = null
    ) {
        $this->changes ??= new IdentityChangeRepository();
        $this->users ??= new UserRepository();
        $this->normalizer ??= new IdentityNormalizer();
    }

    public function request(int $userId, string $field, string $value, string $password): array
    {
        $hash = $this->users->passwordHashForUser($userId);

        if ($hash === null || !password_verify($password, $hash)) {
            return $this->error('invalid_credentials');
        }

        $field = strtolower(trim($field));
        $normalized = $this->normalize($field, $value);

        if (!$this->validNormalizedValue($field, $value, $normalized)) {
            return $this->error('invalid_identity_value');
        }

        $currentValue = $this->users->identityValueForUser($userId, $field);
        $currentNormalized = $currentValue === null ? null : $this->normalize($field, $currentValue);

        if ($currentNormalized !== null && hash_equals($currentNormalized, $normalized)) {
            return $this->error('value_unchanged');
        }

        if ($this->users->identityValueExists($field, $normalized, $userId)) {
            return $this->error('value_not_available');
        }

        $now = $this->nowUtc();
        $expiresAt = $now->modify('+' . self::TTL_SECONDS . ' seconds');

        if ($this->changes->findActivePending($userId, $field, $normalized, $this->databaseTimestamp($now)) !== null) {
            return $this->error('change_request_already_pending');
        }

        $token = (string) random_int(100000, 999999);
        $id = $this->changes->create([
            'user_id' => $userId,
            'field_name' => $field,
            'old_value' => $currentValue,
            'new_value' => $this->storedValue($field, $value, $normalized),
            'normalized_new_value' => $normalized,
            'token_hash' => password_hash($token, PASSWORD_DEFAULT),
            'channel' => $this->channel($field),
            'expires_at' => $this->databaseTimestamp($expiresAt),
        ]);

        $result = [
            'status' => 'ok',
            'request_id' => $id,
            'pending_verification' => true,
            'delivery_status' => $this->deliveryStatus($field),
            'dev_token' => $this->devExposeToken() ? $token : null,
            'expires_at' => Clock::isoUtc($expiresAt),
            'expires_at_utc' => Clock::isoUtc($expiresAt),
            'expires_at_local' => Clock::convertToDisplayTimezone($expiresAt)->format(DATE_ATOM),
            'timezone' => $this->timezone(),
            'ttl_seconds' => self::TTL_SECONDS,
        ];

        if ($result['dev_token'] !== null) {
            $result['delivery_status'] = 'dev_token_exposed';
        }

        return $result;
    }

    public function confirm(int $userId, int $requestId, string $token): array
    {
        $request = $this->changes->findPending($requestId, $userId);
        $token = trim($token);

        if ($request === null || $token === '' || (int) $request['attempts'] >= self::MAX_ATTEMPTS || $this->expired((string) $request['expires_at'])) {
            return $this->error('invalid_or_expired_token');
        }

        if (!password_verify($token, (string) $request['token_hash'])) {
            $this->changes->markAttempt($requestId);
            return $this->error('invalid_or_expired_token');
        }

        $field = (string) $request['field_name'];
        $newValue = (string) $request['new_value'];
        $normalized = $this->normalize($field, $newValue);

        if (!$this->validNormalizedValue($field, $newValue, $normalized)
            || !hash_equals($normalized, (string) $request['normalized_new_value'])) {
            $this->changes->markCancelled($requestId);
            return $this->error('invalid_identity_value');
        }

        if ($this->users->identityValueExists($field, $normalized, $userId)) {
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
                $normalized,
                $normalized
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

        return ['status' => 'ok', 'applied' => true];
    }

    private function normalize(string $field, string $value): ?string
    {
        return match ($field) {
            'username' => $this->normalizer->username($value),
            'email' => $this->normalizer->email($value),
            'mobile' => $this->normalizer->mobile($value),
            default => null,
        };
    }

    private function validNormalizedValue(string $field, string $value, ?string $normalized): bool
    {
        if ($normalized === null) {
            return false;
        }

        if ($field === 'username') {
            return $this->normalizer->username($value) === $normalized;
        }

        return in_array($field, ['email', 'mobile'], true);
    }

    private function devExposeToken(): bool
    {
        return Env::get('APP_ENV', 'production') === 'development'
            && Env::isDebug()
            && filter_var(Env::get('IDENTITY_DEV_EXPOSE_TOKEN', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function error(string $message): array
    {
        return [
            'status' => 'error',
            'error' => $message,
            'message' => $message,
        ];
    }

    private function storedValue(string $field, string $value, string $normalized): string
    {
        return match ($field) {
            'username', 'email', 'mobile' => $normalized,
            default => trim($value),
        };
    }

    private function channel(string $field): string
    {
        return match ($field) {
            'email' => 'email',
            'mobile' => 'sms',
            default => 'session',
        };
    }

    private function deliveryStatus(string $field): string
    {
        if ($this->devExposeToken()) {
            return 'dev_token_exposed';
        }

        return match ($field) {
            'email' => $this->emailProviderConfigured() ? 'provider_configured' : 'not_configured',
            'mobile' => $this->mobileProviderConfigured() ? 'provider_configured' : 'not_configured',
            default => 'not_configured',
        };
    }

    private function emailProviderConfigured(): bool
    {
        return trim((string) Env::get('MAIL_HOST', '')) !== ''
            && trim((string) Env::get('MAIL_FROM_ADDRESS', '')) !== '';
    }

    private function mobileProviderConfigured(): bool
    {
        $smsEnabled = filter_var(Env::get('MFA_SMS_ENABLED', false), FILTER_VALIDATE_BOOLEAN)
            && trim((string) Env::get('KAVENEGAR_API_KEY', '')) !== '';
        $botEnabled = filter_var(Env::get('MFA_BOT_ENABLED', false), FILTER_VALIDATE_BOOLEAN)
            && trim((string) Env::get('BALE_BOT_TOKEN', '')) !== '';

        return $smsEnabled || $botEnabled;
    }

    private function expired(string $expiresAt): bool
    {
        $expires = Clock::parseStoredInstant($expiresAt);

        return $expires === null || $expires < $this->nowUtc();
    }

    private function nowUtc(): \DateTimeImmutable
    {
        return Clock::nowUtc();
    }

    private function databaseTimestamp(\DateTimeImmutable $time): string
    {
        return Clock::databaseTimestamp($time);
    }

    private function timezone(): string
    {
        return Clock::displayTimezoneName();
    }
}
