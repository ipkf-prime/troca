<?php

namespace App\Services;

use App\Repositories\IdentityChangeRepository;
use App\Repositories\UserRepository;
use IPKF\Support\Env;

class IdentityChangeService extends BaseService
{
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
            return ['status' => 'error', 'error' => 'invalid_credentials'];
        }

        $normalized = $this->normalize($field, $value);

        if ($normalized === null || $this->users->identityValueExists($field, $normalized, $userId)) {
            return ['status' => 'error', 'error' => 'identity_change_unavailable'];
        }

        $token = (string) random_int(100000, 999999);
        $id = $this->changes->create([
            'user_id' => $userId,
            'field_name' => $field,
            'old_value' => null,
            'new_value' => $value,
            'normalized_new_value' => $normalized,
            'token_hash' => password_hash($token, PASSWORD_DEFAULT),
            'channel' => $field === 'mobile' ? 'sms' : 'email',
        ]);

        return [
            'status' => 'ok',
            'request_id' => $id,
            'pending_verification' => true,
            'dev_token' => $this->devExposeToken() ? $token : null,
        ];
    }

    public function confirm(int $userId, int $requestId, string $token): array
    {
        $request = $this->changes->findPending($requestId, $userId);

        if ($request === null || (int) $request['attempts'] >= 5) {
            return ['status' => 'error', 'error' => 'invalid_or_expired_token'];
        }

        if (!password_verify($token, (string) $request['token_hash'])) {
            $this->changes->markAttempt($requestId);
            return ['status' => 'error', 'error' => 'invalid_or_expired_token'];
        }

        if ($this->users->identityValueExists((string) $request['field_name'], (string) $request['normalized_new_value'], $userId)) {
            return ['status' => 'error', 'error' => 'identity_change_unavailable'];
        }

        $this->users->applyIdentityChange(
            $userId,
            (string) $request['field_name'],
            (string) $request['new_value'],
            (string) $request['normalized_new_value']
        );
        $this->changes->markApplied($requestId);

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

    private function devExposeToken(): bool
    {
        return Env::isDebug() && filter_var(Env::get('IDENTITY_DEV_EXPOSE_TOKEN', false), FILTER_VALIDATE_BOOLEAN);
    }
}
