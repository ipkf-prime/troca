<?php

namespace IPKF\Database\Connections;

use PDO;
use Throwable;

class ConnectionHealthChecker
{
    public function __construct(private ?ConnectionResolver $resolver = null)
    {
        $this->resolver ??= new ConnectionResolver();
    }

    public function available(string $name): bool
    {
        try {
            $this->resolver->resolve($name)->query('SELECT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function utf8mb4Ready(string $name): bool
    {
        try {
            $charset = (string) $this->resolver->resolve($name)->query('SELECT @@character_set_connection')->fetchColumn();
            return strtolower($charset) === 'utf8mb4';
        } catch (Throwable) {
            return false;
        }
    }

    public function utcTimezoneApplied(string $name): bool
    {
        try {
            $timezone = (string) $this->resolver->resolve($name)->query('SELECT @@session.time_zone')->fetchColumn();
            return $timezone === '+00:00' || strtoupper($timezone) === 'UTC';
        } catch (Throwable) {
            return false;
        }
    }

    public function fallbackSharesPdo(string $name, string $fallbackName): bool
    {
        try {
            return $this->resolver->resolve($name) === $this->resolver->resolve($fallbackName);
        } catch (Throwable) {
            return false;
        }
    }
}
