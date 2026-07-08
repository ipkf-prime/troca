<?php

namespace App\Services;

use App\Repositories\MfaRepository;
use IPKF\Support\Env;
use IPKF\Support\Session;

class MfaService extends BaseService
{
    protected MfaRepository $mfa;

    protected TotpService $totp;

    public function __construct(?MfaRepository $mfa = null, ?TotpService $totp = null)
    {
        $this->mfa = $mfa ?? new MfaRepository();
        $this->totp = $totp ?? new TotpService();
    }

    public function enabled(): bool
    {
        return filter_var(Env::get('MFA_ENABLED', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function enforcement(): string
    {
        $value = (string) Env::get('MFA_ENFORCEMENT', 'optional');

        return in_array($value, ['optional', 'required'], true) ? $value : 'optional';
    }

    public function methodsForUser(int $userId): array
    {
        return $this->mfa->enabledMethodsForUser($userId);
    }

    public function requiresChallenge(int $userId): bool
    {
        return $this->enabled() && $this->methodsForUser($userId) !== [];
    }

    public function startPending(int $userId): array
    {
        $methods = array_values(array_map(fn (array $method): string => $method['method'], $this->methodsForUser($userId)));

        if ($this->mfa->unusedRecoveryCodeCount($userId) > 0) {
            $methods[] = 'recovery_code';
        }

        Session::put('auth_pending_user_id', $userId);
        Session::put('auth_pending_at', time());
        Session::put('auth_pending_methods', $methods);

        return $methods;
    }

    public function pendingUserId(): ?int
    {
        $userId = Session::get('auth_pending_user_id');
        $pendingAt = (int) Session::get('auth_pending_at', 0);

        if ($userId === null || $pendingAt < (time() - 300)) {
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
    }

    public function setupTotp(int $userId, string $account): array
    {
        $secret = $this->totp->generateSecret();
        $method = $this->mfa->saveTotpSetup($userId, 'plain:' . $secret, 'Authenticator app');

        return [
            'method_id' => (int) ($method['id'] ?? 0),
            'otpauth_uri' => $this->totp->provisioningUri('IPKF', $account, $secret),
        ];
    }

    public function confirmTotp(int $userId, string $code): bool
    {
        $method = $this->mfa->totpMethodForUser($userId);

        if ($method === null) {
            return false;
        }

        $secret = $this->extractSecret((string) $method['secret_encrypted']);

        if (!$this->totp->verify($secret, $code)) {
            return false;
        }

        $this->mfa->enableMethod((int) $method['id']);

        return true;
    }

    public function verifyPendingChallenge(string $method, string $code): ?int
    {
        $userId = $this->pendingUserId();

        if ($userId === null) {
            return null;
        }

        $valid = false;

        if ($method === 'totp') {
            $valid = $this->confirmTotpChallenge($userId, $code);
        } elseif ($method === 'recovery_code') {
            $valid = (new RecoveryCodeService())->consume($userId, $code);
        }

        if (!$valid) {
            return null;
        }

        $this->clearPending();

        return $userId;
    }

    private function confirmTotpChallenge(int $userId, string $code): bool
    {
        $method = $this->mfa->totpMethodForUser($userId);

        if ($method === null || (int) $method['is_enabled'] !== 1 || $method['verified_at'] === null) {
            return false;
        }

        return $this->totp->verify($this->extractSecret((string) $method['secret_encrypted']), $code);
    }

    private function extractSecret(string $stored): string
    {
        return str_starts_with($stored, 'plain:') ? substr($stored, 6) : $stored;
    }
}
