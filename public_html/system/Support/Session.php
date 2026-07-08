<?php

namespace IPKF\Support;

class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $name = (string) Env::get('AUTH_SESSION_NAME', 'ipkf_session');

        if ($name !== '') {
            session_name($name);
        }

        session_set_cookie_params([
            'lifetime' => (int) Env::get('AUTH_SESSION_LIFETIME', 7200),
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        ]);

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

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
        }

        session_destroy();
    }
}
