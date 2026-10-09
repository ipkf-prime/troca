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

    /*
     * MODULE_SSO_INTENT_TTL_V2
     *
     * An abandoned module launch must never hijack
     * an unrelated future Core login.
     */
    private const INTENT_TTL_SECONDS = 900;

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
            [
                'path' =>
                    $this->returnPath(
                        $returnPath
                    ),

                'created_at' =>
                    time(),

                'owner_binding_version' =>
                    1,

                'owner_fingerprint' =>
                    (
                        new AdminLoginReturnPathService()
                    )->currentOwnerFingerprint(),
            ]
        );
    }


    public function pendingResumeUrl(): ?string
    {
        return $this->pendingReturnPath()
            !== null
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

        /*
         * PROJECT_LOCAL_TICKETING_SSO_OPERATIONAL_BRIDGE_V1
         * Project membership never grants global management permissions.
         * Only canonical operational paths approved for this specific
         * user may use the project-scoped SSO entrance.
         * Requester-owned paths retain their independent support.view gate.
         */
        $operationalTicketingAllowed = false;

        if (
            $moduleKeyForAccess === 'ticketing'
            &&
            !$this->isRequesterTicketingReturnPath(
                $returnPath
            )
        ) {
            try {
                $operationalTicketingAllowed =
                    (new \App\Services\Ticketing\TicketingProjectScopedAccessService())
                        ->canAccessPath(
                            $userId,
                            $returnPath
                        );
            } catch (\Throwable) {
                $operationalTicketingAllowed = false;
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
            &&
            !$operationalTicketingAllowed
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
                    '/admin/ticketing',
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
        $returnPath =
            $this->pendingReturnPathForUser(
                $userId
            );

        if ($returnPath === null) {
            return [
                'ok' => false,
                'error' => 'intent_expired',
            ];
        }

        return $this->issueFor(
            $userId,
            $returnPath
        );
    }


    private function pendingReturnPath(): ?string
    {
        $userId =
            (int) Session::get(
                'auth_user_id',
                0
            );

        return $this->pendingReturnPathForUser(
            $userId > 0
                ? $userId
                : null
        );
    }


    private function pendingReturnPathForUser(
        ?int $userId = null
    ): ?string {
        $intent =
            Session::get(
                self::INTENT_KEY
            );

        /*
         * Previous deployments stored only a raw string
         * and therefore had no expiry information.
         * Treat legacy state as stale rather than allowing
         * it to redirect a later unrelated login.
         */
        if (!is_array($intent)) {
            $this->forgetPendingIntent();

            return null;
        }

        $bindingVersion =
            (int) (
                $intent[
                    'owner_binding_version'
                ]
                ?? 0
            );

        if ($bindingVersion !== 1) {
            $this->forgetPendingIntent();

            return null;
        }

        $ownerFingerprint =
            strtolower(
                trim(
                    (string) (
                        $intent[
                            'owner_fingerprint'
                        ]
                        ?? ''
                    )
                )
            );

        if (
            $ownerFingerprint !== ''
            &&
            preg_match(
                '/^[a-f0-9]{64}$/D',
                $ownerFingerprint
            ) !== 1
        ) {
            $this->forgetPendingIntent();

            return null;
        }

        if (
            $ownerFingerprint !== ''
            &&
            $userId !== null
            &&
            $userId > 0
            &&
            !(
                new AdminLoginReturnPathService()
            )->ownerFingerprintMatchesUser(
                $ownerFingerprint,
                $userId
            )
        ) {
            $this->forgetPendingIntent();

            return null;
        }

        $path =
            trim(
                (string) (
                    $intent['path']
                    ?? ''
                )
            );

        $createdAt =
            (int) (
                $intent['created_at']
                ?? 0
            );

        $now =
            time();

        if (
            $path === ''
            || $createdAt <= 0
            || $createdAt > $now + 60
            || ($now - $createdAt)
                > self::INTENT_TTL_SECONDS
        ) {
            $this->forgetPendingIntent();

            return null;
        }

        return $this->returnPath(
            $path
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

        $safeRedirectPath =
            $this->returnPath(
                (string) (
                    $record['redirect_path']
                    ?? ''
                )
            );

        /*
         * DESTINATION_USER_BOUND_RETURN_V1
         *
         * Core and module sessions are deliberately
         * host-scoped.
         *
         * Therefore the destination module host is the
         * authoritative place to compare its surviving
         * host-local owner hint with the identity carried
         * by the freshly consumed SSO authorization code.
         *
         * This executes before AuthService::finalizeLogin()
         * on the destination host, so a different user
         * cannot overwrite the historical owner hint
         * before the comparison.
         *
         * The hint grants no authorization. A mismatch
         * only removes the historical deep destination.
         */
        $recordUserId =
            max(
                0,
                (int) (
                    $record['user_id']
                    ?? 0
                )
            );

        if ($recordUserId < 1) {
            return null;
        }

        $returnOwner =
            new AdminLoginReturnPathService();

        $ownerFingerprint =
            $returnOwner
                ->currentOwnerFingerprint();

        $record['safe_return_owner_state'] =
            'unbound';

        if ($ownerFingerprint !== '') {

            if (
                $returnOwner
                    ->ownerFingerprintMatchesUser(
                        $ownerFingerprint,
                        $recordUserId
                    )
            ) {
                $record[
                    'safe_return_owner_state'
                ] =
                    'matched';

            } else {

                $record[
                    'safe_return_owner_state'
                ] =
                    'mismatch';

                /*
                 * Never reuse the previous user's exact
                 * module destination. Fall back only to
                 * this destination host's registered
                 * module root.
                 */
                $moduleRoutePath =
                    trim(
                        (string) (
                            $module['route_path']
                            ?? ''
                        )
                    );

                $safeRedirectPath =
                    $this->returnPath(
                        $moduleRoutePath
                    );
            }
        }

        $record['safe_redirect_path'] =
            $safeRedirectPath;

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
