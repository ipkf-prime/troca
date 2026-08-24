<?php

namespace App\Services;

use IPKF\Support\ApplicationUrlRegistry;
use IPKF\Support\Session;

class ModuleSsoService extends BaseService
{
    private const PURPOSE = 'module_sso';
    private const SOURCE = 'core_panel';
    private const INTENT_KEY = 'module_sso_return_path';

    public function __construct(
        private ?LoginTokenService $tokens = null,
        private ?AuthorizationService $authorization = null,
        private ?ApplicationUrlRegistry $urls = null
    ) {
        $this->tokens ??= new LoginTokenService();
        $this->authorization ??= new AuthorizationService();
        $this->urls ??= new ApplicationUrlRegistry();
    }

    public function remember(string $returnPath): void
    {
        Session::put(self::INTENT_KEY, $this->returnPath($returnPath));
    }

    public function pendingResumeUrl(): ?string
    {
        return Session::has(self::INTENT_KEY) ? $this->urls->core('/auth/module-sso/resume') : null;
    }

    public function forgetPendingIntent(): void
    {
        Session::forget(self::INTENT_KEY);
    }

    public function issueFor(int $userId, string $returnPath): array
    {
        $module = $this->moduleForPath($returnPath);
        $permission = match ($module) {
            'work' => 'work.project.view',
            'ticketing' => 'ticketing.ticket.view',
            default => 'automation.correspondence.view',
        };
        if (!$this->authorization->hasPermission($userId, $permission)) {
            return ['ok' => false, 'error' => 'forbidden'];
        }

        $returnPath = $this->returnPath($returnPath);
        $issued = $this->tokens->issue(
            $userId,
            self::PURPOSE,
            self::SOURCE,
            $returnPath,
            $userId,
            60,
            [
                'audience' => $module,
                'active_role_assignment_id' => (int) Session::get('active_role_assignment_id', 0),
                'active_organizational_appointment' => (string) Session::get('active_organizational_appointment', ''),
                'mfa_verified' => (bool) Session::get('auth_mfa_verified', false),
            ]
        );

        Session::forget(self::INTENT_KEY);

        return [
            'ok' => true,
            'transfer_url' => match ($module) {
                'work' => $this->urls->work('/auth/module-sso/callback?code=' . rawurlencode($issued['token'])),
                'ticketing' => $this->urls->ticketing('/auth/module-sso/callback?code=' . rawurlencode($issued['token'])),
                default => $this->urls->automation('/auth/module-sso/callback?code=' . rawurlencode($issued['token'])),
            },
        ];
    }

    public function resumeFor(int $userId): array
    {
        return $this->issueFor($userId, (string) Session::get(self::INTENT_KEY, '/admin/automation'));
    }

    public function consume(string $code, string $requestHost): ?array
    {
        $audience = $this->urls->isWorkHost($requestHost)
            ? 'work'
            : ($this->urls->isTicketingHost($requestHost)
                ? 'ticketing'
                : ($this->urls->isAutomationHost($requestHost) ? 'automation' : null));
        if ($audience === null) {
            return null;
        }

        $record = $this->tokens->consume($code, self::PURPOSE, self::SOURCE, ['audience' => $audience]);
        if ($record === null) {
            return null;
        }

        $metadata = json_decode((string) ($record['metadata_json'] ?? ''), true);
        $record['safe_assignment_id'] = max(0, (int) ($metadata['active_role_assignment_id'] ?? 0));
        $record['safe_appointment_reference'] = trim((string) ($metadata['active_organizational_appointment'] ?? ''));
        $record['safe_mfa_verified'] = !empty($metadata['mfa_verified']);
        $record['safe_redirect_path'] = $this->returnPath((string) ($record['redirect_path'] ?? ''));
        return $record;
    }

    private function moduleForPath(string $path): string
    {
        $parsedPath = (string) parse_url(trim($path), PHP_URL_PATH);
        if ($parsedPath === '/admin/work' || str_starts_with($parsedPath, '/admin/work/')) {
            return 'work';
        }

        if ($parsedPath === '/admin/ticketing' || str_starts_with($parsedPath, '/admin/ticketing/')) {
            return 'ticketing';
        }

        return 'automation';
    }

    private function returnPath(string $path): string
    {
        $path = trim($path);
        $parsedPath = parse_url($path, PHP_URL_PATH);
        if (!is_string($parsedPath)
            || (!($parsedPath === '/admin/automation' || str_starts_with($parsedPath, '/admin/automation/'))
                && !($parsedPath === '/admin/work' || str_starts_with($parsedPath, '/admin/work/'))
                && !($parsedPath === '/admin/ticketing' || str_starts_with($parsedPath, '/admin/ticketing/')))
            || str_starts_with($path, '//')
            || parse_url($path, PHP_URL_HOST) !== null
        ) {
            return '/admin/automation';
        }

        $query = parse_url($path, PHP_URL_QUERY);
        return $parsedPath . (is_string($query) && $query !== '' ? '?' . $query : '');
    }
}
