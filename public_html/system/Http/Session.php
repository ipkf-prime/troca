<?php

namespace IPKF\Http;

use IPKF\Support\Session as SupportSession;

class Session
{
    public function __construct()
    {
        SupportSession::start();
    }

    public function set(string $key, mixed $value): void
    {
        SupportSession::put($key, $value);
    }

    public function get(string $key, $default = null)
    {
        return SupportSession::get($key, $default);
    }

    public function has(string $key): bool
    {
        return SupportSession::has($key);
    }

    public function forget(string $key): void
    {
        SupportSession::forget($key);
    }

    public function destroy(): void
    {
        SupportSession::destroy();
    }
}
