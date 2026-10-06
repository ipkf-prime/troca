<?php

namespace App\Services;

use App\Repositories\UserRepository;
use IPKF\Support\Clock;
use IPKF\Support\Env;
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

        /*
         * AUTH_LOGIN_LOCKOUT_A5_R2
         *
         * An expired lock starts a fresh failure window. Without this
         * normalization, the next wrong password would immediately lock
         * the account again because the old failure counter survives.
         */
        $user = $this->normalizeExpiredLoginLock($user);

        if (!$this->canAuthenticate($user)) {
            return null;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            $policy = $this->loginFailurePolicy();

            $this->users->updateLoginFailure(
                (int) $user['id'],
                $policy['max_attempts'],
                $policy['lock_minutes']
            );

            return null;
        }

        // Password verification succeeded. This state belongs to the
        // password credential check, not to completion of authentication.
        $this->users->resetLoginFailures((int) $user['id']);

        $mfa = new MfaService();

        if ($mfa->requiresChallenge((int) $user['id'])) {
            return [
                'authenticated' => false,
                'mfa_required' => true,
                'methods' => $mfa->startPending(
                    (int) $user['id'],
                    'password'
                ),
            ];
        }

        return $this->finalizeLogin(
            (int) $user['id'],
            'password',
            false
        );
    }

    public function completePendingMfa(
        string $challengeMethod,
        string $code
    ): ?array {
        $verified = (new MfaService())->verifyPendingChallenge(
            $challengeMethod,
            $code
        );

        if ($verified === null) {
            return null;
        }

        return $this->finalizeLogin(
            (int) $verified['user_id'],
            (string) ($verified['auth_method'] ?? 'password'),
            true
        );
    }

    public function finalizeLogin(
        int $userId,
        string $method = 'session',
        bool $mfaVerified = false
    ): ?array {
        $user = $this->users->findById($userId);

        // Eligibility is deliberately checked immediately before session
        // mutation so a user disabled or locked during MFA/SSO cannot enter.
        if ($user === null || !$this->canAuthenticate($user)) {
            return null;
        }

        $method = $this->normalizeAuthMethod($method);

        /*
         * PASSWORD_CREDENTIAL_SESSION_FINGERPRINT_V1
         *
         * Store only a one-way, domain-separated fingerprint of the
         * current password credential version in the authenticated
         * session. The raw database password hash is never stored.
         */
        $passwordFingerprint =
            $this->passwordFingerprint(
                $user
            );

        Session::regenerate();

        Session::put(
            'auth_password_fingerprint',
            $passwordFingerprint
        );
        Session::put('auth_user_id', $userId);
        Session::put(
            'auth_login_at',
            Clock::isoUtc(Clock::nowUtc())
        );
        Session::put(
            'auth_mfa_verified',
            $mfaVerified
        );

        // last_login_at means a completed authenticated session, not merely
        // a successfully verified password.
        $this->users->updateLastLogin($userId);

        $access = new AccessService();
        $access->ensureDefaultAssignment($userId);
        $activeAssignment = $access->selectPreferred(
            $userId
        );

        (new LoginHistoryService())->record(
            $userId,
            $activeAssignment,
            $method,
            $mfaVerified
        );

        (new InternalMessageLoginNotifierService())
            ->notify($userId);

        return $this->safeUser($user);
    }

    public function logout(): void
    {
        Session::forget('auth_user_id');
        Session::forget('auth_login_at');
        Session::forget('auth_mfa_verified');
        Session::forget('auth_password_fingerprint');
        Session::forget('active_role_assignment_id');
        Session::forget('auth_pending_user_id');
        Session::forget('auth_pending_at');
        Session::forget('auth_pending_methods');
        Session::forget('auth_pending_auth_method');
        Session::forget('module_sso_return_path');

        /*
         * ADMIN_LOGIN_RETURN_LOGOUT_V1
         *
         * Explicit logout is terminal. Local return
         * destinations must not survive it.
         */
        (new AdminLoginReturnPathService())
            ->forgetPendingIntent();
        Session::forget('messages_unread_on_login');

        /*
         * Logout is a terminal authentication boundary.
         * Do not leave the PHP session identifier alive
         * after merely removing authentication keys.
         */
        Session::destroy();
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

        $expectedPasswordFingerprint =
            $this->passwordFingerprint(
                $user
            );

        $sessionPasswordFingerprint =
            trim(
                (string) Session::get(
                    'auth_password_fingerprint',
                    ''
                )
            );

        if (
            $sessionPasswordFingerprint === ''
            || !hash_equals(
                $expectedPasswordFingerprint,
                $sessionPasswordFingerprint
            )
        ) {
            /*
             * Missing fingerprint means a legacy pre-patch session.
             * Mismatch means the password credential changed after
             * this session was authenticated.
             */
            $this->logout();

            return null;
        }

        return $this->safeUser($user);
    }

    public function authenticated(): bool
    {
        return $this->currentUser() !== null;
    }

    public function changePassword(
        int $userId,
        string $currentPassword,
        string $newPassword
    ): bool {
        $hash = $this->users->passwordHashForUser($userId);

        if (
            $hash === null
            || !password_verify(
                $currentPassword,
                $hash
            )
        ) {
            return false;
        }

        $this->users->updatePasswordHash(
            $userId,
            password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            )
        );

        if (
            $this->currentUserId()
            === $userId
        ) {
            $updatedUser =
                $this->users->findById(
                    $userId
                );

            if (is_array($updatedUser)) {
                Session::put(
                    'auth_password_fingerprint',
                    $this->passwordFingerprint(
                        $updatedUser
                    )
                );
            }
        }

        return true;
    }

    /**
     * Return only authentication state explicitly
     * whitelisted by the impersonation context.
     */
    public function impersonationAuthSnapshot(): array
    {
        $keys = [
            'auth_user_id',
            'auth_password_fingerprint',
            'auth_login_at',
            'auth_mfa_verified',
            'active_role_assignment_id',
        ];

        $snapshot = [];

        foreach ($keys as $key) {
            if (Session::has($key)) {
                $snapshot[$key] =
                    Session::get($key);
            }
        }

        return $snapshot;
    }


    /**
     * Switch authenticated effective identity only
     * after canonical impersonation authorization.
     */
    public function beginImpersonatedIdentity(
        int $userId,
        int $activeRoleAssignmentId
    ): bool {
        if (
            $userId < 1
            || $activeRoleAssignmentId < 1
        ) {
            return false;
        }

        $user =
            $this->users->findById(
                $userId
            );

        if (
            $user === null
            || !$this->canAuthenticate(
                $user
            )
        ) {
            return false;
        }

        $fingerprint =
            $this->passwordFingerprint(
                $user
            );

        if ($fingerprint === '') {
            return false;
        }

        Session::put(
            'auth_user_id',
            $userId
        );

        Session::put(
            'auth_password_fingerprint',
            $fingerprint
        );

        Session::put(
            'active_role_assignment_id',
            $activeRoleAssignmentId
        );

        /*
         * Actor -> Effective User is a privilege
         * boundary.
         */
        Session::regenerate();

        return true;
    }


    /**
     * Restore the exact pre-impersonation actor auth
     * snapshot after revalidating the actor credential.
     */
    public function restoreImpersonationAuthSnapshot(
        array $snapshot
    ): bool {
        $userId =
            (int) (
                $snapshot[
                    'auth_user_id'
                ]
                ?? 0
            );

        $storedFingerprint =
            trim(
                (string) (
                    $snapshot[
                        'auth_password_fingerprint'
                    ]
                    ?? ''
                )
            );

        $activeRoleAssignmentId =
            (int) (
                $snapshot[
                    'active_role_assignment_id'
                ]
                ?? 0
            );

        if (
            $userId < 1
            || $storedFingerprint === ''
            || $activeRoleAssignmentId < 1
        ) {
            return false;
        }

        $user =
            $this->users->findById(
                $userId
            );

        if (
            $user === null
            || !$this->canAuthenticate(
                $user
            )
        ) {
            return false;
        }

        $currentFingerprint =
            $this->passwordFingerprint(
                $user
            );

        if (
            $currentFingerprint === ''
            || !hash_equals(
                $currentFingerprint,
                $storedFingerprint
            )
        ) {
            return false;
        }

        $keys = [
            'auth_user_id',
            'auth_password_fingerprint',
            'auth_login_at',
            'auth_mfa_verified',
            'active_role_assignment_id',
        ];

        foreach ($keys as $key) {
            if (
                array_key_exists(
                    $key,
                    $snapshot
                )
            ) {
                Session::put(
                    $key,
                    $snapshot[$key]
                );
            } else {
                Session::forget(
                    $key
                );
            }
        }

        /*
         * Effective User -> Actor is a privilege
         * boundary.
         */
        Session::regenerate();

        return true;
    }

    public function safeUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'name' => $user['full_name']
                ?? $user['username']
                ?? $user['email']
                ?? '',
            'username' => $user['username'] ?? null,
            'email' => $user['email'] ?? null,
            'mobile' => $user['mobile'] ?? null,
            'status' => $user['status'] ?? null,
        ];
    }

    private function passwordFingerprint(
        array $user
    ): string {
        return hash(
            'sha256',
            'ipkf-auth-password-fingerprint-v1:'
            . (string) (
                $user['password_hash']
                ?? ''
            )
        );
    }

    private function normalizeAuthMethod(string $method): string
    {
        $method = strtolower(trim($method));

        return in_array(
            $method,
            [
                'password',
                'token',
                'sso',
                'session',
            ],
            true
        ) ? $method : 'session';
    }

    private function normalizeExpiredLoginLock(array $user): array
    {
        $lockedUntil = trim((string) ($user['locked_until'] ?? ''));

        if (
            $lockedUntil !== ''
            && strtotime($lockedUntil) !== false
            && strtotime($lockedUntil) <= time()
        ) {
            $this->users->resetLoginFailures((int) $user['id']);
            $user['failed_login_attempts'] = 0;
            $user['locked_until'] = null;
        }

        return $user;
    }

    private function loginFailurePolicy(): array
    {
        $maxAttempts = (int) Env::get(
            'AUTH_LOGIN_MAX_FAILURES',
            5
        );

        $lockMinutes = (int) Env::get(
            'AUTH_LOGIN_LOCK_MINUTES',
            15
        );

        return [
            'max_attempts' => max(1, min(20, $maxAttempts)),
            'lock_minutes' => max(1, min(1440, $lockMinutes)),
        ];
    }

    private function canAuthenticate(array $user): bool
    {
        if (($user['status'] ?? '') !== 'active') {
            return false;
        }

        $lockedUntil = $user['locked_until'] ?? null;

        return $lockedUntil === null
            || strtotime((string) $lockedUntil) <= time();
    }
}
