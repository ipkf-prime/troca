<?php

namespace App\Services;

use IPKF\Support\ApplicationUrlRegistry;
use IPKF\Support\ModuleRuntimeConfig;
use IPKF\Support\Session;

class ModuleSsoService extends BaseService
{
    private const PURPOSE = 'module_sso';
    private const SOURCE = 'core_panel';
    private const INTENT_KEY = 'module_sso_return_path';

    public function __construct(
        private ?LoginTokenService $tokens = null,
        private ?AuthorizationService $authorization = null,
        private ?ApplicationUrlRegistry $urls = null,
        private ?ModuleRuntimeConfig $runtime = null
    ) {
        $this->tokens ??=
            new LoginTokenService();

        $this->authorization ??=
            new AuthorizationService();

        $this->urls ??=
            new ApplicationUrlRegistry();

        $this->runtime ??=
            new ModuleRuntimeConfig();
    }


    public function remember(
        string $returnPath
    ): void {
        Session::put(
            self::INTENT_KEY,
            $this->returnPath($returnPath)
        );
    }


    public function pendingResumeUrl(): ?string
    {
        return Session::has(self::INTENT_KEY)
            ? $this->urls->core(
                '/auth/module-sso/resume'
            )
            : null;
    }


    public function forgetPendingIntent(): void
    {
        Session::forget(self::INTENT_KEY);
    }


    public function issueFor(
        int $userId,
        string $returnPath
    ): array {
        $returnPath =
            $this->returnPath($returnPath);

        $module =
            $this->moduleForPath($returnPath);

        if ($module === null) {
            return [
                'ok' => false,
                'error' => 'module_not_found',
            ];
        }

        $permission = trim(
            (string) (
                $module['permission_key']
                ?? ''
            )
        );

        /*
         * REQUESTER_TICKETING_SSO_BRIDGE_RUNTIME
         *
         * ticketing.ticket.view remains a Staff/Admin permission.
         * It is not granted to requester roles.
         *
         * Requester SSO is allowed only for requester-owned
         * Ticketing paths and only with:
         *
         * - support.view in the active role
         * - at least one active Ticketing project membership
         */
        $moduleKeyForAccess =
            trim(
                (string) (
                    $module['module_key']
                    ?? ''
                )
            );

        $requesterTicketingAllowed =
            false;

        if (
            $moduleKeyForAccess === 'ticketing'
            &&
            $this->isRequesterTicketingReturnPath(
                $returnPath
            )
            &&
            $this->authorization->hasPermission(
                $userId,
                'support.view'
            )
        ) {
            try {
                $onboarding =
                    new \App\Services\Ticketing\TicketRequesterOnboardingService();

                $requesterTicketingAllowed =
                    $onboarding->hasMembership(
                        $userId
                    );

            } catch (\Throwable) {
                $requesterTicketingAllowed =
                    false;
            }
        }

        if (
            $permission !== ''
            &&
            !$this->authorization->hasPermission(
                $userId,
                $permission
            )
            &&
            !$requesterTicketingAllowed
        ) {
            return [
                'ok' => false,
                'error' => 'forbidden',
            ];
        }


        $moduleKey = trim(
            (string) (
                $module['module_key']
                ?? ''
            )
        );

        if ($moduleKey === '') {
            return [
                'ok' => false,
                'error' => 'module_not_found',
            ];
        }

        $issued = $this->tokens->issue(
            $userId,
            self::PURPOSE,
            self::SOURCE,
            $returnPath,
            $userId,
            60,
            [
                'audience' =>
                    $moduleKey,

                'active_role_assignment_id' =>
                    (int) Session::get(
                        'active_role_assignment_id',
                        0
                    ),

                'active_organizational_appointment' =>
                    (string) Session::get(
                        'active_organizational_appointment',
                        ''
                    ),

                'mfa_verified' =>
                    (bool) Session::get(
                        'auth_mfa_verified',
                        false
                    ),
            ]
        );

        Session::forget(
            self::INTENT_KEY
        );

        $callback = trim(
            (string) (
                $module['sso_callback_url']
                ?? ''
            )
        );

        if ($callback === '') {
            $baseUrl = rtrim(
                trim(
                    (string) (
                        $module['base_url']
                        ?? ''
                    )
                ),
                '/'
            );

            if ($baseUrl === '') {
                return [
                    'ok' => false,
                    'error' => 'module_url_missing',
                ];
            }

            $callback =
                $baseUrl
                . '/auth/module-sso/callback';
        }

        return [
            'ok' => true,

            'transfer_url' =>
                $callback
                . (
                    str_contains(
                        $callback,
                        '?'
                    )
                        ? '&'
                        : '?'
                )
                . 'code='
                . rawurlencode(
                    $issued['token']
                ),
        ];
    }


    private function isRequesterTicketingReturnPath(
        string $returnPath
    ): bool {
        $path =
            parse_url(
                $returnPath,
                PHP_URL_PATH
            )
            ?: '/';

        $path =
            rtrim(
                $path,
                '/'
            )
            ?: '/';

        if (
            in_array(
                $path,
                [
                    '/admin/ticketing/tickets',
                    '/admin/ticketing/tickets/create',
                ],
                true
            )
        ) {
            return true;
        }

        return
            preg_match(
                '#^/admin/ticketing/tickets/[A-Za-z0-9_-]+$#',
                $path
            ) === 1;
    }


    public function resumeFor(
        int $userId
    ): array {
        return $this->issueFor(
            $userId,
            (string) Session::get(
                self::INTENT_KEY,
                '/admin/dashboard'
            )
        );
    }


    public function consume(
        string $code,
        string $requestHost
    ): ?array {
        $module =
            $this->moduleForHost(
                $requestHost
            );

        if ($module === null) {
            return null;
        }

        $audience = trim(
            (string) (
                $module['module_key']
                ?? ''
            )
        );

        if ($audience === '') {
            return null;
        }

        $record = $this->tokens->consume(
            $code,
            self::PURPOSE,
            self::SOURCE,
            [
                'audience' =>
                    $audience,
            ]
        );

        if ($record === null) {
            return null;
        }

        $metadata = json_decode(
            (string) (
                $record['metadata_json']
                ?? ''
            ),
            true
        );

        if (!is_array($metadata)) {
            $metadata = [];
        }

        $record['safe_assignment_id'] =
            max(
                0,
                (int) (
                    $metadata[
                        'active_role_assignment_id'
                    ]
                    ?? 0
                )
            );

        $record['safe_appointment_reference'] =
            trim(
                (string) (
                    $metadata[
                        'active_organizational_appointment'
                    ]
                    ?? ''
                )
            );

        $record['safe_mfa_verified'] =
            !empty(
                $metadata['mfa_verified']
            );

        $record['safe_redirect_path'] =
            $this->returnPath(
                (string) (
                    $record['redirect_path']
                    ?? ''
                )
            );

        return $record;
    }


    private function moduleForPath(
        string $path
    ): ?array {
        $parsedPath = (string) parse_url(
            trim($path),
            PHP_URL_PATH
        );

        $parsedPath =
            '/' . ltrim(
                $parsedPath,
                '/'
            );

        $best = null;
        $bestLength = -1;

        foreach (
            $this->runtime->allActive()
            as $module
        ) {
            $route = trim(
                (string) (
                    $module['route_path']
                    ?? ''
                )
            );

            if ($route === '') {
                continue;
            }

            $route =
                '/' . trim(
                    $route,
                    '/'
                );

            $matches =
                $parsedPath === $route
                || str_starts_with(
                    $parsedPath,
                    $route . '/'
                );

            if (
                $matches
                && strlen($route) > $bestLength
            ) {
                $best = $module;
                $bestLength = strlen($route);
            }
        }

        return is_array($best)
            ? $best
            : null;
    }


    private function moduleForHost(
        string $requestHost
    ): ?array {
        $requestHost =
            $this->normalizeHost(
                $requestHost
            );

        if ($requestHost === '') {
            return null;
        }

        foreach (
            $this->runtime->allActive()
            as $module
        ) {
            $baseUrl = trim(
                (string) (
                    $module['base_url']
                    ?? ''
                )
            );

            if ($baseUrl === '') {
                continue;
            }

            $host = parse_url(
                $baseUrl,
                PHP_URL_HOST
            );

            if (
                is_string($host)
                && $this->normalizeHost($host)
                    === $requestHost
            ) {
                return $module;
            }
        }

        return null;
    }


    private function normalizeHost(
        string $host
    ): string {
        $host = strtolower(
            trim($host)
        );

        return preg_replace(
            '/:\d+$/',
            '',
            $host
        ) ?: '';
    }


    private function returnPath(
        string $path
    ): string {
        $path = trim($path);

        if ($path === '') {
            return '/admin/dashboard';
        }

        $parsed = parse_url($path);

        if (
            $parsed === false
            || isset($parsed['scheme'])
            || isset($parsed['host'])
        ) {
            return '/admin/dashboard';
        }

        $normalized =
            '/' . ltrim(
                (string) (
                    $parsed['path']
                    ?? ''
                ),
                '/'
            );

        if (
            !str_starts_with(
                $normalized,
                '/admin/'
            )
        ) {
            return '/admin/dashboard';
        }

        if (
            isset($parsed['query'])
            && $parsed['query'] !== ''
        ) {
            $normalized .=
                '?'
                . $parsed['query'];
        }

        return $normalized;
    }
}
