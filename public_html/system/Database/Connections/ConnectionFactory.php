<?php

namespace IPKF\Database\Connections;

use IPKF\Support\Clock;
use PDO;
use PDOException;
use RuntimeException;

class ConnectionFactory
{
    public function make(ConnectionDefinition $definition): PDO
    {
        if (!$definition->configured()) {
            throw new RuntimeException('Named connection configuration is incomplete.');
        }

        $config = $definition->config();
        $charset = $definition->charset();
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) ($config['host'] ?? ''),
            (int) ($config['port'] ?? 3306),
            (string) ($config['database'] ?? ''),
            $charset
        );

        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => max(1, (int) ($config['connection_timeout'] ?? 5)),
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci",
            ];

            $pdo = new PDO($dsn, (string) ($config['username'] ?? ''), (string) ($config['password'] ?? ''), $options);
            $pdo->exec("SET NAMES {$charset} COLLATE utf8mb4_unicode_ci");
            $pdo->exec("SET time_zone = '" . Clock::DATABASE_SESSION_TIMEZONE . "'");

            return $pdo;
        } catch (PDOException $exception) {
            throw new RuntimeException('Named connection failed.', 0, $exception);
        }
    }
}
