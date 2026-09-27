<?php

declare(strict_types=1);

/**
 * IPKF account page: connected Bale bot instances.
 *
 * The IPKF authenticated session is the sole user identity.
 * Bot instances and bindings come from existing IPKF tables.
 *
 * This initial page is read-only. Enrollment is not started
 * until the live bot polling confirmation bridge is ready.
 */

$router->get(
    '/admin/account/bale',
    function (
        $request,
        $response
    ) use (
        $adminGuard,
        $adminRender
    ) {
        $context = $adminGuard(
            $response,
            '/admin/account'
        );

        if (!is_array($context)) {
            return $context;
        }

        $userId = (int) (
            $context['user_id'] ?? 0
        );

        if ($userId < 1) {
            return $response->redirect(
                '/admin/login'
            );
        }

        $page = [
            'items' => [],
            'available' => false,
            'status' => '',
        ];

        try {
            $repository = new
                \App\Repositories\NotificationMessengerEnrollmentRepository();

            $providers =
                $repository->accountConnectableBaleProviders();

            $db = \IPKF\Database\Database::connect();

            $bindingQuery = $db->prepare(
                'SELECT status_code, verified_at ' .
                'FROM notification_messenger_bindings ' .
                'WHERE user_id = ? ' .
                'AND provider_instance_id = ? ' .
                'LIMIT 1'
            );

            foreach ($providers as $provider) {
                $providerId = (int) (
                    $provider['id'] ?? 0
                );

                if ($providerId < 1) {
                    continue;
                }

                $configuration = json_decode(
                    (string) (
                        $provider[
                            'configuration_json'
                        ] ?? ''
                    ),
                    true
                );

                if (!is_array($configuration)) {
                    continue;
                }

                $bindingQuery->execute([
                    $userId,
                    $providerId,
                ]);

                $binding = $bindingQuery->fetch(
                    \PDO::FETCH_ASSOC
                );

                $connected =
                    is_array($binding) &&
                    ($binding['status_code'] ?? null)
                        === 'active';

                $page['items'][] = [
                    'provider_id' => $providerId,
                    'provider_reference' => (string) (
                        $provider['public_reference'] ?? ''
                    ),
                    'purpose_code' =>
                        $repository->baleProviderPurpose($provider),
                    'title' => (
                        $repository->baleProviderPurpose($provider)
                            === 'service_access'
                        ? \App\Services\UiContent\UiContentInlineGuide::bodyText('core.bale-account-link.ui.service_title_prefix','core','bale-account-link') : ''
                    ) . (string) (
                        $provider['title'] ?? ''
                    ),
                    'bot_username' => ltrim(
                        trim((string) (
                            $configuration[
                                'bot_username'
                            ] ?? ''
                        )),
                        '@'
                    ),
                    'connected' => $connected,
                    'verified_at' => $connected
                        ? (string) (
                            $binding[
                                'verified_at'
                            ] ?? ''
                        )
                        : '',
                ];
            }

            $page['available'] = true;

        } catch (\Throwable) {
            $page['items'] = [];
            $page['status'] = 'unavailable';
        }

        $activation = \IPKF\Support\Session::get(
            'bale_service_access_activation'
        );

        \IPKF\Support\Session::forget(
            'bale_service_access_activation'
        );

        if (
            is_array($activation) &&
            ($activation['user_id'] ?? null) === $userId &&
            ($activation['expires_at'] ?? 0) > time() &&
            is_string($activation['link'] ?? null)
        ) {
            $page['activation_link'] =
                $activation['link'];

            $page['activation_expires_at'] =
                (int) $activation['expires_at'];

            header(
                'Cache-Control: private, no-store'
            );

            header(
                'Referrer-Policy: no-referrer'
            );
        }

        $allowedStatuses = [
            'csrf',
            'invalid',
            'rate_limited',
            'connected',
            'failed',
        ];

        $requestedStatus = (string)
            $request->input('status', '');

        if (in_array(
            $requestedStatus,
            $allowedStatuses,
            true
        )) {
            $page['status'] = $requestedStatus;
        }

        return $adminRender(
            $response,
            'bale-account-self-service',
            [
                'title' => \App\Services\UiContent\UiContentInlineGuide::bodyText('core.bale-account-link.ui.page_title','core','bale-account-link'),
                'context' => $context,
                'page' => $page,
            ]
        );
    }
);


/* IPKF_BALE_SERVICE_SELF_ENROLLMENT_V1 */
$router->post(
    '/admin/account/bale/start',
    function (
        $request,
        $response
    ) use ($adminGuard) {

        $basePath = '/admin/account/bale';

        $fail = static function (
            string $status
        ) use ($response, $basePath) {
            return $response->redirect(
                $basePath
                . '?status='
                . rawurlencode($status)
            );
        };

        $context = $adminGuard(
            $response,
            '/admin/account'
        );

        if (!is_array($context)) {
            return $context;
        }

        $userId = (int) (
            $context['user_id'] ?? 0
        );

        if ($userId < 1) {
            return $fail('invalid');
        }

        if (!(new \IPKF\Security\Csrf())->check(
            (string) $request->input('_token', '')
        )) {
            return $fail('csrf');
        }

        $reference = trim(
            (string) $request->input(
                'provider_reference',
                ''
            )
        );

        if (
            preg_match(
                '/^npi_[a-f0-9]{24}$/D',
                $reference
            ) !== 1
        ) {
            return $fail('invalid');
        }

        try {
            $repository = new
                \App\Repositories\NotificationMessengerEnrollmentRepository();

            $providers =
                $repository->serviceAccessBaleProviders();

            $selected = null;

            foreach ($providers as $provider) {
                if (
                    hash_equals(
                        (string) (
                            $provider['public_reference']
                                ?? ''
                        ),
                        $reference
                    )
                ) {
                    $selected = $provider;
                    break;
                }
            }

            if (!is_array($selected)) {
                return $fail('invalid');
            }

            $providerId = (int) (
                $selected['id'] ?? 0
            );

            if ($providerId < 1) {
                return $fail('invalid');
            }

            $configuration = json_decode(
                (string) (
                    $selected['configuration_json']
                        ?? ''
                ),
                true
            );

            $username = is_array($configuration)
                ? ltrim(trim((string) (
                    $configuration['bot_username']
                        ?? ''
                )), '@')
                : '';

            if (
                preg_match(
                    '/^[A-Za-z][A-Za-z0-9_]{4,31}$/D',
                    $username
                ) !== 1
            ) {
                return $fail('invalid');
            }

            $db = \IPKF\Database\Database::connect();

            $bindingQuery = $db->prepare(
                'SELECT 1 ' .
                'FROM notification_messenger_bindings ' .
                'WHERE user_id = ? ' .
                'AND provider_instance_id = ? ' .
                "AND status_code = 'active' " .
                'LIMIT 1'
            );

            $bindingQuery->execute([
                $userId,
                $providerId,
            ]);

            if ($bindingQuery->fetchColumn() !== false) {
                return $fail('connected');
            }

            /*
             * Limit self-service issuance to avoid
             * repeated cancellation and token flooding.
             */
            $limit = $db->prepare(
                'SELECT COUNT(*) AS total, ' .
                'COALESCE(MAX(created_at), ' .
                "'1970-01-01 00:00:00') AS latest " .
                'FROM notification_messenger_enrollments ' .
                'WHERE user_id = ? ' .
                'AND provider_instance_id = ? ' .
                'AND created_at > ' .
                'DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 HOUR)'
            );

            $limit->execute([
                $userId,
                $providerId,
            ]);

            $usage = $limit->fetch(
                \PDO::FETCH_ASSOC
            );

            if (
                (int) ($usage['total'] ?? 0) >= 10
            ) {
                return $fail('rate_limited');
            }

            $latest = strtotime(
                (string) (
                    $usage['latest'] ?? ''
                )
            );

            if (
                $latest !== false &&
                $latest > time() - 60
            ) {
                return $fail('rate_limited');
            }

            $token = bin2hex(
                random_bytes(24)
            );

            $expiresAt = time() + 600;

            /*
             * This is an authenticated IPKF
             * self-service request.
             *
             * No SMS is sent. The service bot
             * must verify the token independently.
             */
            $repository->createEnrollment(
                $userId,
                $providerId,
                '',
                hash('sha256', $token),
                $userId,
                date(
                    'Y-m-d H:i:s',
                    $expiresAt
                )
            );

            $link =
                'https://ble.ir/'
                . rawurlencode($username)
                . '?start='
                . rawurlencode($token);

            /*
             * Raw token is stored temporarily
             * only in the authenticated session.
             * The database stores its hash.
             */
            \IPKF\Support\Session::put(
                'bale_service_access_activation',
                [
                    'user_id' => $userId,
                    'provider_reference' => $reference,
                    'link' => $link,
                    'expires_at' => $expiresAt,
                ]
            );

            return $response->redirect(
                $basePath
            );

        } catch (\Throwable) {
            return $fail('failed');
        }
    }
);


/* IPKF_BALE_SERVICE_ACCESS_CONFIRM_V1 */
require_once __DIR__ . '/bale-service-access-confirm.php';

/* IPKF_BALE_SERVICE_QUERY_V1 */
require_once __DIR__ . '/bale-service-query.php';

/* B7_A123_MENU_EDITOR */
require_once __DIR__ . '/bale-menu-management.php';

/* B7_A128_SIGNED_TICKET_DEEP_LINK */
require_once __DIR__ . '/bale-ticket-deep-link.php';
