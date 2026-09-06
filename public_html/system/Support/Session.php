<?php

namespace IPKF\Support;

class Session
{
    public static function name(): string
    {
        return (string) Env::get('AUTH_SESSION_NAME', 'ipkf_session');
    }

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $name = self::name();

        if ($name !== '') {
            session_name($name);
        }

        $cookie = [
            'lifetime' => (int) Env::get('AUTH_SESSION_LIFETIME', 7200),
            'path' => '/',
            'httponly' => filter_var(Env::get('AUTH_COOKIE_HTTPONLY', true), FILTER_VALIDATE_BOOL),
            'samesite' => (string) Env::get('AUTH_COOKIE_SAMESITE', 'Lax'),
            'secure' => filter_var(Env::get('AUTH_COOKIE_SECURE', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')), FILTER_VALIDATE_BOOL),
        ];
        $domain = trim((string) Env::get('AUTH_COOKIE_DOMAIN', ''));
        if ($domain !== '') {
            $cookie['domain'] = $domain;
        }

        session_set_cookie_params($cookie);

        session_start();
    }

    public static function put(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        self::start();

        $sessionName =
            session_name();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params =
                session_get_cookie_params();

            $path =
                trim(
                    (string) (
                        $params['path']
                        ?? '/'
                    )
                );

            if ($path === '') {
                $path = '/';
            }

            $secure =
                !empty(
                    $params['secure']
                );

            $httpOnly =
                !empty(
                    $params['httponly']
                );

            $sameSite =
                trim(
                    (string) (
                        $params['samesite']
                        ?? Env::get(
                            'AUTH_COOKIE_SAMESITE',
                            'Lax'
                        )
                    )
                );

            self::expireCookie(
                $sessionName,
                '',
                $path,
                $secure,
                $httpOnly,
                $sameSite
            );

            $configuredDomain =
                trim(
                    (string) (
                        $params['domain']
                        ?? ''
                    )
                );

            if ($configuredDomain !== '') {
                self::expireCookie(
                    $sessionName,
                    $configuredDomain,
                    $path,
                    $secure,
                    $httpOnly,
                    $sameSite
                );
            }

            /*
             * Older deployments used a parent-domain
             * session cookie. A host-only session with
             * the same cookie name can then coexist with
             * that legacy cookie and PHP may reopen the
             * wrong historical session.
             *
             * Expire only domain candidates that can be
             * derived from the current host and configured
             * Core host. No deployment domain is hardcoded.
             */
            foreach (
                self::legacyCookieDomains()
                as $legacyDomain
            ) {
                self::expireCookie(
                    $sessionName,
                    $legacyDomain,
                    '/',
                    $secure,
                    $httpOnly,
                    $sameSite
                );
            }
        }

        session_destroy();
    }


    private static function expireCookie(
        string $name,
        string $domain,
        string $path,
        bool $secure,
        bool $httpOnly,
        string $sameSite
    ): void {
        if ($name === '') {
            return;
        }

        $options = [
            'expires' =>
                time() - 42000,

            'path' =>
                $path !== ''
                    ? $path
                    : '/',

            'secure' =>
                $secure,

            'httponly' =>
                $httpOnly,

            'samesite' =>
                $sameSite !== ''
                    ? $sameSite
                    : 'Lax',
        ];

        if ($domain !== '') {
            $options['domain'] =
                $domain;
        }

        setcookie(
            $name,
            '',
            $options
        );
    }


    private static function legacyCookieDomains(): array
    {
        $domains = [];

        $configured =
            trim(
                (string) Env::get(
                    'AUTH_COOKIE_DOMAIN',
                    ''
                )
            );

        if ($configured !== '') {
            $domains[] =
                $configured;

            $domains[] =
                ltrim(
                    $configured,
                    '.'
                );

            $domains[] =
                '.'
                . ltrim(
                    $configured,
                    '.'
                );
        }

        $requestHost =
            self::normalizeHost(
                (string) (
                    $_SERVER['HTTP_HOST']
                    ?? ''
                )
            );

        $coreUrl =
            trim(
                (string) Env::get(
                    'CORE_APP_URL',
                    ''
                )
            );

        $coreHost =
            $coreUrl !== ''
                ? parse_url(
                    $coreUrl,
                    PHP_URL_HOST
                )
                : null;

        $coreHost =
            is_string($coreHost)
                ? self::normalizeHost(
                    $coreHost
                )
                : '';

        $common =
            self::commonCookieDomain(
                $requestHost,
                $coreHost
            );

        if ($common !== null) {
            $domains[] =
                $common;

            $domains[] =
                '.'
                . $common;
        }

        $domains =
            array_values(
                array_unique(
                    array_filter(
                        array_map(
                            static fn ($value): string =>
                                trim(
                                    (string) $value
                                ),
                            $domains
                        ),
                        static fn (string $value): bool =>
                            $value !== ''
                    )
                )
            );

        return $domains;
    }


    private static function commonCookieDomain(
        string $firstHost,
        string $secondHost
    ): ?string {
        if (
            $firstHost === ''
            || $secondHost === ''
            || $firstHost === $secondHost
        ) {
            return null;
        }

        $first =
            array_reverse(
                explode(
                    '.',
                    $firstHost
                )
            );

        $second =
            array_reverse(
                explode(
                    '.',
                    $secondHost
                )
            );

        $common = [];

        $limit =
            min(
                count($first),
                count($second)
            );

        for (
            $index = 0;
            $index < $limit;
            $index++
        ) {
            if (
                !hash_equals(
                    $first[$index],
                    $second[$index]
                )
            ) {
                break;
            }

            $common[] =
                $first[$index];
        }

        /*
         * Never manufacture a parent-domain cookie
         * from only a single common DNS label.
         */
        if (count($common) < 2) {
            return null;
        }

        return implode(
            '.',
            array_reverse(
                $common
            )
        );
    }


    private static function normalizeHost(
        string $host
    ): string {
        $host =
            strtolower(
                trim(
                    $host
                )
            );

        return preg_replace(
            '/:\\d+$/',
            '',
            $host
        ) ?: '';
    }
}
