<?php

namespace App\Services;

use App\Repositories\UserRepository;
use IPKF\Support\Session;

class AuthService extends BaseService
{
    protected UserRepository $users;

    public function __construct(?UserRepository $users = null)
    {
        $this->users = $users ?? new UserRepository();
    }

    public function attempt(string $login, string $password): ?array
    {
        $user = $this->users->findByLoginIdentifier($login);

        if ($user === null) {
            return null;
        }

        if (!$this->canAuthenticate($user)) {
            return null;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            $this->users->updateLoginFailure((int) $user['id']);
            return null;
        }

        $this->users->updateLoginSuccess((int) $user['id']);

        $mfa = new MfaService();

        if ($mfa->requiresChallenge((int) $user['id'])) {
            return [
                'authenticated' => false,
                'mfa_required' => true,
                'methods' => $mfa->startPending((int) $user['id']),
            ];
        }

        $this->login((int) $user['id']);

        return $this->currentUser();
    }

    public function login(int $userId): void
    {
        Session::regenerate();
        Session::put('auth_user_id', $userId);
        Session::put('auth_login_at', date(DATE_ATOM));
        Session::put('auth_mfa_verified', false);
        (new AccessService())->ensureDefaultAssignment($userId);
        (new AccessService())->selectLowest($userId);
    }

    public function logout(): void
    {
        Session::forget('auth_user_id');
        Session::forget('auth_login_at');
        Session::forget('auth_mfa_verified');
        Session::forget('active_role_assignment_id');
        Session::forget('auth_pending_user_id');
        Session::forget('auth_pending_at');
        Session::forget('auth_pending_methods');
    }

    public function completeMfaLogin(int $userId): ?array
    {
        $this->login($userId);
        Session::put('auth_mfa_verified', true);

        return $this->currentUser();
    }

    public function currentUserId(): ?int
    {
        $userId = Session::get('auth_user_id');

        return $userId === null ? null : (int) $userId;
    }

    public function currentUser(): ?array
    {
        $userId = $this->currentUserId();

        if ($userId === null) {
            return null;
        }

        $user = $this->users->findById($userId);

        if ($user === null || !$this->canAuthenticate($user)) {
            return null;
        }

        return $this->safeUser($user);
    }

    public function authenticated(): bool
    {
        return $this->currentUser() !== null;
    }

    public function safeUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'name' => $user['full_name'] ?? $user['username'] ?? $user['email'] ?? '',
            'email' => $user['email'] ?? null,
            'mobile' => $user['mobile'] ?? null,
            'status' => $user['status'] ?? null,
        ];
    }

    private function canAuthenticate(array $user): bool
    {
        if (($user['status'] ?? '') !== 'active') {
            return false;
        }

        $lockedUntil = $user['locked_until'] ?? null;

        return $lockedUntil === null || strtotime((string) $lockedUntil) <= time();
    }
}
