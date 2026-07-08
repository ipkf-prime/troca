<?php

namespace IPKF\Database;

use IPKF\Support\Config;
use PDO;
use PDOException;
use RuntimeException;

class Database
{
    protected static ?PDO $connection = null;

    public static function configured(): bool
    {
        $config = self::config();

        return ($config['driver'] ?? 'mysql') === 'mysql'
            && !empty($config['host'])
            && !empty($config['database'])
            && !empty($config['username']);
    }

    public static function config(): array
    {
        $default = Config::get('database.default', 'mysql');

        return Config::get("database.connections.{$default}", []);
    }

    public static function connect(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = self::config();

        if (!self::configured()) {
            throw new RuntimeException('Database configuration is incomplete.');
        }

        $host = $config['host'] ?? 'localhost';
        $database = $config['database'] ?? '';
        $port = $config['port'] ?? 3306;
        $charset = $config['charset'] ?? 'utf8mb4';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        try {
            self::$connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Database connection failed.', 0, $exception);
        }

        return self::$connection;
    }
}
