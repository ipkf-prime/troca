<?php

namespace App\Services;

use App\Repositories\LoginHistoryRepository;
use IPKF\Support\Session;
use Throwable;

class LoginHistoryService extends BaseService
{
    public function __construct(
        private ?LoginHistoryRepository $history = null
    ) {
        $this->history ??= new LoginHistoryRepository();
    }

    public function record(
        int $userId,
        ?array $assignment,
        string $method,
        bool $mfaVerified
    ): void {
        try {
            Session::start();

            $this->history->record([
                'user_id' => $userId,
                'role_assignment_id' => isset(
                    $assignment['id']
                )
                    ? (int) $assignment['id']
                    : null,
                'role_code_snapshot' =>
                    $assignment['role_code'] ?? null,
                'role_title_snapshot' =>
                    $assignment['role_title'] ?? null,
                'auth_method' => $this->method($method),
                'mfa_verified' => $mfaVerified,
                'session_hash' => session_id() !== ''
                    ? hash('sha256', session_id())
                    : null,
                'ip_address' => $this->ipAddress(),
                'user_agent' => $this->userAgent(),
                'browser_label' => $this->browserLabel(
                    $this->userAgent()
                ),
            ]);
        } catch (Throwable) {
            // Authentication must not fail because audit logging is unavailable.
        }
    }

    public function recent(
        int $userId,
        int $limit = 10
    ): array {
        try {
            return array_map(
                fn (array $item): array =>
                    $this->present($item),
                $this->history->recent($userId, $limit)
            );
        } catch (Throwable) {
            return [];
        }
    }

    private function present(array $item): array
    {
        $method = (string) (
            $item['auth_method'] ?? 'session'
        );

        return [
            'id' => (int) ($item['id'] ?? 0),
            'logged_in_at' => (string) (
                $item['logged_in_at'] ?? ''
            ),
            'ip_address' => trim((string) (
                $item['ip_address'] ?? ''
            )),
            'browser_label' => trim((string) (
                $item['browser_label'] ?? ''
            )),
            'role_title' => trim((string) (
                $item['role_title_snapshot'] ?? ''
            )),
            'role_code' => trim((string) (
                $item['role_code_snapshot'] ?? ''
            )),
            'mfa_verified' => !empty(
                $item['mfa_verified']
            ),
            'auth_method' => $method,
            'auth_method_label' => match ($method) {
                'password' => 'رمز عبور',
                'password_mfa' => 'رمز عبور و MFA',
                'token' => 'توکن ورود',
                'sso' => 'ورود یکپارچه',
                'legacy' => 'آخرین ورود ثبت‌شده',
                default => 'ورود سامانه‌ای',
            },
            'is_legacy' => $method === 'legacy',
        ];
    }

    private function method(string $method): string
    {
        $method = strtolower(trim($method));

        return in_array(
            $method,
            [
                'password',
                'password_mfa',
                'token',
                'sso',
                'session',
            ],
            true
        ) ? $method : 'session';
    }

    private function ipAddress(): ?string
    {
        $ip = trim((string) (
            $_SERVER['REMOTE_ADDR'] ?? ''
        ));

        return $ip !== ''
            ? mb_substr($ip, 0, 64, 'UTF-8')
            : null;
    }

    private function userAgent(): ?string
    {
        $agent = trim((string) (
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ));

        return $agent !== ''
            ? mb_substr($agent, 0, 2000, 'UTF-8')
            : null;
    }

    private function browserLabel(?string $agent): string
    {
        $agent = (string) $agent;

        if ($agent === '') {
            return 'مرورگر ناشناس';
        }

        $browser = match (true) {
            str_contains($agent, 'Edg/') =>
                'Microsoft Edge',
            str_contains($agent, 'Firefox/') =>
                'Firefox',
            str_contains($agent, 'Chrome/') =>
                'Google Chrome',
            str_contains($agent, 'Safari/') =>
                'Safari',
            default => 'مرورگر وب',
        };

        $platform = match (true) {
            str_contains($agent, 'Windows') =>
                'Windows',
            str_contains($agent, 'Android') =>
                'Android',
            str_contains($agent, 'iPhone'),
            str_contains($agent, 'iPad') =>
                'iOS',
            str_contains($agent, 'Mac OS') =>
                'macOS',
            str_contains($agent, 'Linux') =>
                'Linux',
            default => '',
        };

        return trim(
            $browser
            . ($platform !== ''
                ? ' روی ' . $platform
                : '')
        );
    }
}
