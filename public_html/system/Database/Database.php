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
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci",
            ]);
            self::$connection->exec("SET NAMES {$charset} COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $exception) {
            throw new RuntimeException('Database connection failed.', 0, $exception);
        }

        return self::$connection;
    }

    public static function tableExists(string $table): bool
    {
        try {
            $statement = self::connect()->prepare("
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = ?
            ");

            $statement->execute([$table]);

            return (int) $statement->fetchColumn() > 0;
        } catch (RuntimeException|PDOException) {
            return false;
        }
    }

    public static function columnExists(string $table, string $column): bool
    {
        try {
            $statement = self::connect()->prepare("
                SELECT COUNT(*)
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                  AND column_name = ?
            ");

            $statement->execute([$table, $column]);

            return (int) $statement->fetchColumn() > 0;
        } catch (RuntimeException|PDOException) {
            return false;
        }
    }

    public static function connectionCharset(): ?string
    {
        try {
            return (string) self::connect()->query("SELECT @@character_set_connection")->fetchColumn();
        } catch (RuntimeException|PDOException) {
            return null;
        }
    }
}
