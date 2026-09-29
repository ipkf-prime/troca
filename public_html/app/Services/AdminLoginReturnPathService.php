<?php

namespace App\Services;

use IPKF\Support\Session;

class AdminLoginReturnPathService extends BaseService
{
    private const INTENT_KEY =
        'admin_login_return_path';

    private const INTENT_TTL_SECONDS =
        900;

    private const FALLBACK_PATH =
        '/admin/dashboard';


    public function rememberCurrentRequest(
        string $fallbackPath =
            self::FALLBACK_PATH
    ): void {
        $method =
            strtoupper(
                trim(
                    (string) (
                        $_SERVER[
                            'REQUEST_METHOD'
                        ]
                        ?? 'GET'
                    )
                )
            );

        if (
            in_array(
                $method,
                ['GET', 'HEAD'],
                true
            )
        ) {
            $candidate =
                (string) (
                    $_SERVER[
                        'REQUEST_URI'
                    ]
                    ?? $fallbackPath
                );
        } else {
            $candidate =
                $this->sameHostRefererPath()
                ?? self::FALLBACK_PATH;
        }

        $this->remember(
            $candidate
        );
    }


    /*
     * SAME_HOST_LOGIN_REFERER_V1
     *
     * Some older Core routes redirect directly to
     * /admin/login instead of passing through the
     * canonical admin guard. A same-origin admin
     * Referer is therefore a valid newer navigation
     * intent after session expiry.
     */
    public function rememberFromSameHostReferer(): bool
    {
        $candidate =
            $this->sameHostRefererPath();

        if ($candidate === null) {
            return false;
        }

        $candidatePath =
            (string) (
                parse_url(
                    $candidate,
                    PHP_URL_PATH
                )
                ?: ''
            );

        $safe =
            $this->safePath(
                $candidate
            );

        /*
         * safePath() deliberately falls back to the
         * dashboard for invalid/authentication paths.
         * Do not treat that fallback as a captured
         * Referer unless the Referer really was the
         * dashboard itself.
         */
        if (
            $safe === self::FALLBACK_PATH
            && rtrim(
                $candidatePath,
                '/'
            ) !== self::FALLBACK_PATH
        ) {
            return false;
        }

        $this->remember(
            $safe
        );

        return true;
    }


    public function remember(
        string $returnPath
    ): void {
        Session::put(
            self::INTENT_KEY,
            [
                'path' =>
                    $this->safePath(
                        $returnPath
                    ),

                'created_at' =>
                    time(),
            ]
        );
    }


    public function pending(): ?string
    {
        $intent =
            Session::get(
                self::INTENT_KEY
            );

        if (!is_array($intent)) {
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

        return $this->safePath(
            $path
        );
    }


    public function consume(): ?string
    {
        $path =
            $this->pending();

        if ($path !== null) {
            $this->forgetPendingIntent();
        }

        return $path;
    }


    public function forgetPendingIntent(): void
    {
        Session::forget(
            self::INTENT_KEY
        );
    }


    private function sameHostRefererPath(): ?string
    {
        $referer =
            trim(
                (string) (
                    $_SERVER[
                        'HTTP_REFERER'
                    ]
                    ?? ''
                )
            );

        if ($referer === '') {
            return null;
        }

        $parsed =
            parse_url(
                $referer
            );

        if (!is_array($parsed)) {
            return null;
        }

        $refererHost =
            $this->normalizeHost(
                (string) (
                    $parsed['host']
                    ?? ''
                )
            );

        $requestHost =
            $this->normalizeHost(
                (string) (
                    $_SERVER[
                        'HTTP_HOST'
                    ]
                    ?? ''
                )
            );

        if (
            $refererHost === ''
            || $requestHost === ''
            || !hash_equals(
                $requestHost,
                $refererHost
            )
        ) {
            return null;
        }

        $path =
            (string) (
                $parsed['path']
                ?? ''
            );

        if (
            isset($parsed['query'])
            && $parsed['query'] !== ''
        ) {
            $path .=
                '?'
                . $parsed['query'];
        }

        return $path;
    }


    private function safePath(
        string $path
    ): string {
        $path =
            trim(
                $path
            );

        if ($path === '') {
            return self::FALLBACK_PATH;
        }

        $parsed =
            parse_url(
                $path
            );

        if (
            $parsed === false
            || isset(
                $parsed['scheme']
            )
            || isset(
                $parsed['host']
            )
        ) {
            return self::FALLBACK_PATH;
        }

        $normalized =
            '/'
            . ltrim(
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
            return self::FALLBACK_PATH;
        }

        if (
            preg_match(
                '#^/admin/(?:'
                . 'login'
                . '|logout'
                . '|mfa(?:/|$)'
                . '|forgot-password(?:/|$)'
                . ')#',
                $normalized
            ) === 1
        ) {
            return self::FALLBACK_PATH;
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


    private function normalizeHost(
        string $host
    ): string {
        $host =
            strtolower(
                trim(
                    $host
                )
            );

        return preg_replace(
            '/:\d+$/',
            '',
            $host
        ) ?: '';
    }
}
