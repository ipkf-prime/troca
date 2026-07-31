<?php

namespace App\Services;

use App\Repositories\UserRepository;
use IPKF\Support\Session;

class AccountSecurityService extends BaseService
{
    public function __construct(
        private ?UserRepository $users = null,
        private ?MfaService $mfa = null
    ) {
        $this->users ??= new UserRepository();
        $this->mfa ??= new MfaService();
    }

    public function page(
        int $userId,
        array $user
    ): array {
        Session::start();

        return [
            'mfa_available' => $this->mfa->enabled(),
            'mfa_enforcement' => $this->mfa->enforcement(),
            'totp_enabled' => $this->mfa->totpEnabled($userId),
            'pending_totp' => $this->mfa
                ->pendingTotpSetup($userId),
            'recovery_code_count' => $this->mfa
                ->recoveryCodeCount($userId),
            'recovery_codes' => $this->pullRecoveryCodes(),
            'session' => [
                'id_short' => substr(session_id(), 0, 10),
                'login_at' => (string) Session::get(
                    'auth_login_at',
                    ''
                ),
                'mfa_verified' => (bool) Session::get(
                    'auth_mfa_verified',
                    false
                ),
                'ip' => (string) (
                    $_SERVER['REMOTE_ADDR'] ?? '—'
                ),
                'browser' => $this->browserLabel(
                    (string) (
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    )
                ),
            ],
            'account_label' => $this->accountLabel($user),
        ];
    }

    public function beginTotp(
        int $userId,
        array $user,
        string $password,
        string $currentTotp
    ): array {
        if (!$this->mfa->enabled()) {
            return $this->error('mfa_unavailable');
        }

        if (!$this->verifyPassword($userId, $password)) {
            return $this->error('invalid_password');
        }

        if (
            $this->mfa->totpEnabled($userId)
            && !$this->mfa->verifyCurrentTotp(
                $userId,
                $currentTotp
            )
        ) {
            return $this->error('invalid_totp');
        }

        $setup = $this->mfa->beginTotpSetup(
            $userId,
            $this->accountLabel($user)
        );

        return [
            'ok' => true,
            'setup' => $setup,
        ];
    }

    public function confirmTotp(
        int $userId,
        string $code
    ): array {
        if (
            !$this->mfa->confirmPendingTotp(
                $userId,
                $code
            )
        ) {
            return $this->error('invalid_setup_code');
        }

        $codes = $this->mfa->ensureRecoveryCodes($userId);

        if ($codes !== []) {
            Session::put(
                'account_recovery_codes_once',
                $codes
            );
        }

        return ['ok' => true];
    }

    public function cancelTotp(): void
    {
        $this->mfa->cancelTotpSetup();
    }

    public function disableTotp(
        int $userId,
        string $password,
        string $totpCode
    ): array {
        if (!$this->verifyPassword($userId, $password)) {
            return $this->error('invalid_password');
        }

        if (
            !$this->mfa->verifyCurrentTotp(
                $userId,
                $totpCode
            )
        ) {
            return $this->error('invalid_totp');
        }

        $this->mfa->disableTotp($userId);

        return ['ok' => true];
    }

    public function regenerateRecoveryCodes(
        int $userId,
        string $password,
        string $totpCode
    ): array {
        if (!$this->verifyPassword($userId, $password)) {
            return $this->error('invalid_password');
        }

        $codes = $this->mfa->regenerateRecoveryCodes(
            $userId,
            $totpCode
        );

        if ($codes === null) {
            return $this->error('invalid_totp');
        }

        Session::put(
            'account_recovery_codes_once',
            $codes
        );

        return ['ok' => true];
    }

    public function changePassword(
        int $userId,
        array $user,
        string $currentPassword,
        string $password,
        string $confirmation
    ): array {
        $errors = [];

        if (!$this->verifyPassword(
            $userId,
            $currentPassword
        )) {
            $errors['current_password'] =
                'رمز عبور فعلی صحیح نیست.';
        }

        if ($password !== $confirmation) {
            $errors['password_confirmation'] =
                'تکرار رمز عبور با رمز جدید یکسان نیست.';
        }

        if (strlen($password) < 12) {
            $errors['password'] =
                'رمز عبور جدید باید حداقل ۱۲ کاراکتر باشد.';
        }

        if (
            $password !== ''
            && password_verify(
                $password,
                $this->users->passwordHashForUser(
                    $userId
                ) ?? ''
            )
        ) {
            $errors['password'] =
                'رمز عبور جدید نباید با رمز فعلی یکسان باشد.';
        }

        if (
            $password !== ''
            && $this->passwordClassCount($password) < 3
        ) {
            $errors['password'] =
                'رمز عبور باید دست‌کم سه گروه از حروف بزرگ، حروف کوچک، عدد و نماد را داشته باشد.';
        }

        $identityTokens = array_filter([
            strtolower((string) ($user['username'] ?? '')),
            strtolower((string) strtok(
                (string) ($user['email'] ?? ''),
                '@'
            )),
        ]);

        foreach ($identityTokens as $token) {
            if (
                strlen($token) >= 4
                && str_contains(
                    strtolower($password),
                    $token
                )
            ) {
                $errors['password'] =
                    'رمز عبور نباید شامل نام کاربری یا بخش اصلی ایمیل باشد.';
                break;
            }
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
            ];
        }

        $this->users->updatePasswordHash(
            $userId,
            password_hash(
                $password,
                PASSWORD_DEFAULT
            )
        );

        Session::regenerate();
        Session::put(
            'auth_password_changed_at',
            gmdate(DATE_ATOM)
        );

        return ['ok' => true];
    }

    public function statusMessage(string $status): ?array
    {
        return match ($status) {
            'mfa_setup_started' => [
                'type' => 'info',
                'text' => 'اتصال جدید ایجاد شد. کد برنامه Authenticator را برای تأیید وارد کنید.',
            ],
            'mfa_enabled' => [
                'type' => 'success',
                'text' => 'تأیید دومرحله‌ای با موفقیت فعال شد.',
            ],
            'mfa_disabled' => [
                'type' => 'success',
                'text' => 'تأیید دومرحله‌ای غیرفعال و کدهای بازیابی قبلی باطل شدند.',
            ],
            'mfa_cancelled' => [
                'type' => 'info',
                'text' => 'راه‌اندازی تأیید دومرحله‌ای لغو شد.',
            ],
            'recovery_regenerated' => [
                'type' => 'success',
                'text' => 'کدهای بازیابی جدید ساخته شدند. آن‌ها را در محل امن نگهداری کنید.',
            ],
            'invalid_password' => [
                'type' => 'danger',
                'text' => 'رمز عبور فعلی صحیح نیست.',
            ],
            'invalid_totp' => [
                'type' => 'danger',
                'text' => 'کد شش‌رقمی Authenticator صحیح نیست.',
            ],
            'invalid_setup_code' => [
                'type' => 'danger',
                'text' => 'کد تأیید اتصال جدید معتبر نیست یا زمان آن پایان یافته است.',
            ],
            'mfa_unavailable' => [
                'type' => 'danger',
                'text' => 'زیرساخت MFA در تنظیمات سامانه غیرفعال است.',
            ],
            default => null,
        };
    }

    private function verifyPassword(
        int $userId,
        string $password
    ): bool {
        $hash = $this->users->passwordHashForUser(
            $userId
        );

        return $hash !== null
            && $password !== ''
            && password_verify($password, $hash);
    }

    private function accountLabel(array $user): string
    {
        foreach (['email', 'username', 'mobile'] as $field) {
            $value = trim((string) ($user[$field] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return 'user';
    }

    private function pullRecoveryCodes(): array
    {
        $codes = Session::get(
            'account_recovery_codes_once',
            []
        );
        Session::forget('account_recovery_codes_once');

        return is_array($codes)
            ? array_values($codes)
            : [];
    }

    private function passwordClassCount(
        string $password
    ): int {
        $classes = 0;
        $classes += preg_match('/[a-z]/', $password) ? 1 : 0;
        $classes += preg_match('/[A-Z]/', $password) ? 1 : 0;
        $classes += preg_match('/[0-9]/', $password) ? 1 : 0;
        $classes += preg_match(
            '/[^a-zA-Z0-9]/',
            $password
        ) ? 1 : 0;

        return $classes;
    }

    private function browserLabel(string $agent): string
    {
        if ($agent === '') {
            return 'مرورگر ناشناس';
        }

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Microsoft Edge',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Google Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'مرورگر وب',
        };

        $platform = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'),
            str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => '',
        };

        return trim($browser . ($platform !== ''
            ? ' روی ' . $platform
            : ''));
    }

    private function error(string $code): array
    {
        return [
            'ok' => false,
            'error' => $code,
        ];
    }
}
