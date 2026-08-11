<?php

namespace App\Services;

use App\Repositories\MfaRepository;
use IPKF\Support\Env;
use IPKF\Support\Session;

class MfaService extends BaseService
{
    private const SETUP_TTL_SECONDS = 600;

    protected MfaRepository $mfa;

    protected TotpService $totp;

    public function __construct(
        ?MfaRepository $mfa = null,
        ?TotpService $totp = null
    ) {
        $this->mfa = $mfa ?? new MfaRepository();
        $this->totp = $totp ?? new TotpService();
    }

    public function enabled(): bool
    {
        return filter_var(
            Env::get('MFA_ENABLED', true),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function enforcement(): string
    {
        $value = (string) Env::get(
            'MFA_ENFORCEMENT',
            'optional'
        );

        return in_array(
            $value,
            ['optional', 'required'],
            true
        ) ? $value : 'optional';
    }

    public function methodsForUser(int $userId): array
    {
        return $this->mfa->enabledMethodsForUser($userId);
    }

    public function allMethodsForUser(int $userId): array
    {
        return $this->mfa->allMethodsForUser($userId);
    }

    public function requiresChallenge(int $userId): bool
    {
        return $this->enabled()
            && $this->methodsForUser($userId) !== [];
    }

    public function startPending(
        int $userId,
        string $authMethod = 'password'
    ): array {
        $methods = array_values(array_map(
            static fn (array $method): string =>
                (string) $method['method'],
            $this->methodsForUser($userId)
        ));

        if ($this->mfa->unusedRecoveryCodeCount($userId) > 0) {
            $methods[] = 'recovery_code';
        }

        $methods = array_values(array_unique($methods));

        Session::put('auth_pending_user_id', $userId);
        Session::put('auth_pending_at', time());
        Session::put('auth_pending_methods', $methods);
        Session::put(
            'auth_pending_auth_method',
            $this->pendingAuthMethodValue($authMethod)
        );

        return $methods;
    }

    public function pendingUserId(): ?int
    {
        $userId = Session::get('auth_pending_user_id');
        $pendingAt = (int) Session::get(
            'auth_pending_at',
            0
        );

        if (
            $userId === null
            || $pendingAt < (time() - 300)
        ) {
            $this->clearPending();
            return null;
        }

        return (int) $userId;
    }

    public function clearPending(): void
    {
        Session::forget('auth_pending_user_id');
        Session::forget('auth_pending_at');
        Session::forget('auth_pending_methods');
        Session::forget('auth_pending_auth_method');
    }

    public function beginTotpSetup(
        int $userId,
        string $account
    ): array {
        $secret = $this->totp->generateSecret();
        $uri = $this->totp->provisioningUri(
            'IPKF',
            $account,
            $secret
        );

        $payload = [
            'user_id' => $userId,
            'secret' => $secret,
            'otpauth_uri' => $uri,
            'created_at' => time(),
        ];

        Session::put('account_totp_setup', $payload);

        return $this->publicSetupPayload($payload);
    }

    public function pendingTotpSetup(
        int $userId
    ): ?array {
        $payload = Session::get('account_totp_setup');

        if (
            !is_array($payload)
            || (int) ($payload['user_id'] ?? 0) !== $userId
            || (int) ($payload['created_at'] ?? 0)
                < time() - self::SETUP_TTL_SECONDS
        ) {
            $this->cancelTotpSetup();
            return null;
        }

        return $this->publicSetupPayload($payload);
    }

    public function confirmPendingTotp(
        int $userId,
        string $code
    ): bool {
        $payload = Session::get('account_totp_setup');

        if (
            !is_array($payload)
            || (int) ($payload['user_id'] ?? 0) !== $userId
            || (int) ($payload['created_at'] ?? 0)
                < time() - self::SETUP_TTL_SECONDS
        ) {
            $this->cancelTotpSetup();
            return false;
        }

        $secret = (string) ($payload['secret'] ?? '');

        if (
            $secret === ''
            || !$this->totp->verify($secret, $code)
        ) {
            return false;
        }

        $this->mfa->saveVerifiedTotp(
            $userId,
            $secret,
            'Authenticator'
        );
        $this->cancelTotpSetup();

        return true;
    }

    public function cancelTotpSetup(): void
    {
        Session::forget('account_totp_setup');
    }

    public function setupTotp(
        int $userId,
        string $account
    ): array {
        $secret = $this->totp->generateSecret();
        $method = $this->mfa->saveTotpSetup(
            $userId,
            'plain:' . $secret,
            'Authenticator app'
        );

        return [
            'method_id' => (int) ($method['id'] ?? 0),
            'otpauth_uri' => $this->totp->provisioningUri(
                'IPKF',
                $account,
                $secret
            ),
        ];
    }

    public function confirmTotp(
        int $userId,
        string $code
    ): bool {
        $method = $this->mfa->totpMethodForUser($userId);

        if ($method === null) {
            return false;
        }

        $secret = $this->extractSecret(
            (string) $method['secret_encrypted']
        );

        if (!$this->totp->verify($secret, $code)) {
            return false;
        }

        $this->mfa->enableMethod((int) $method['id']);

        return true;
    }

    public function totpEnabled(int $userId): bool
    {
        $method = $this->mfa->totpMethodForUser($userId);

        return $method !== null
            && (int) ($method['is_enabled'] ?? 0) === 1
            && ($method['verified_at'] ?? null) !== null;
    }

    public function disableTotp(int $userId): void
    {
        $this->mfa->disableTotpForUser($userId);
        $this->mfa->revokeRecoveryCodes($userId);
        $this->cancelTotpSetup();
        Session::put('auth_mfa_verified', false);
    }

    public function recoveryCodeCount(int $userId): int
    {
        return $this->mfa->unusedRecoveryCodeCount($userId);
    }

    public function recoveryCodesAvailable(int $userId): bool
    {
        return $this->recoveryCodeCount($userId) > 0;
    }

    public function ensureRecoveryCodes(int $userId): array
    {
        return (new RecoveryCodeService())
            ->ensureForUser($userId);
    }

    public function regenerateRecoveryCodes(
        int $userId,
        string $totpCode
    ): ?array {
        if (
            !$this->verifyCurrentTotp(
                $userId,
                $totpCode
            )
        ) {
            return null;
        }

        return (new RecoveryCodeService())
            ->regenerate($userId);
    }

    public function verifyCurrentTotp(
        int $userId,
        string $code
    ): bool {
        return $this->confirmTotpChallenge(
            $userId,
            $code
        );
    }

    public function verifyPendingChallenge(
        string $method,
        string $code
    ): ?array {
        $userId = $this->pendingUserId();

        if ($userId === null) {
            return null;
        }

        // Defaulting to password preserves already-open pending sessions
        // created before authentication provenance was stored.
        $authMethod = $this->pendingAuthMethodValue(
            (string) Session::get(
                'auth_pending_auth_method',
                'password'
            )
        );

        $valid = false;

        if ($method === 'totp') {
            $valid = $this->confirmTotpChallenge(
                $userId,
                $code
            );
        } elseif ($method === 'recovery_code') {
            $valid = (new RecoveryCodeService())
                ->consume($userId, $code);
        }

        if (!$valid) {
            return null;
        }

        $this->clearPending();

        return [
            'user_id' => $userId,
            'auth_method' => $authMethod,
        ];
    }

    private function confirmTotpChallenge(
        int $userId,
        string $code
    ): bool {
        $method = $this->mfa->totpMethodForUser($userId);

        if (
            $method === null
            || (int) ($method['is_enabled'] ?? 0) !== 1
            || ($method['verified_at'] ?? null) === null
        ) {
            return false;
        }

        return $this->totp->verify(
            $this->extractSecret(
                (string) $method['secret_encrypted']
            ),
            $code
        );
    }

    private function pendingAuthMethodValue(
        string $method
    ): string {
        $method = strtolower(trim($method));

        return in_array(
            $method,
            ['password', 'token'],
            true
        ) ? $method : 'password';
    }

    private function extractSecret(string $stored): string
    {
        return str_starts_with($stored, 'plain:')
            ? substr($stored, 6)
            : $stored;
    }

    private function publicSetupPayload(
        array $payload
    ): array {
        $createdAt = (int) ($payload['created_at'] ?? time());

        return [
            'secret' => (string) ($payload['secret'] ?? ''),
            'otpauth_uri' => (string) (
                $payload['otpauth_uri'] ?? ''
            ),
            'expires_in' => max(
                0,
                ($createdAt + self::SETUP_TTL_SECONDS)
                    - time()
            ),
        ];
    }
}
