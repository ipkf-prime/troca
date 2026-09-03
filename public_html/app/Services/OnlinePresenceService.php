<?php

declare(strict_types=1);

namespace App\Services;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use Throwable;

final class OnlinePresenceService
{
    private const ONLINE_WINDOW_MINUTES = 5;

    private ?PDO $db = null;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;

        if ($this->db === null) {
            try {
                $this->db = (
                    new ConnectionResolver()
                )->resolve('core.primary');
            } catch (Throwable) {
                $this->db = null;
            }
        }
    }

    public function touch(int $userId): void
    {
        if (
            $userId < 1
            || !$this->available()
        ) {
            return;
        }

        try {
            $statement = $this->db->prepare("
                INSERT INTO online_user_presence (
                    user_id,
                    last_seen_at,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )
                ON DUPLICATE KEY UPDATE
                    last_seen_at =
                        VALUES(last_seen_at),
                    updated_at =
                        VALUES(updated_at)
            ");

            $statement->execute([
                $userId,
            ]);
        } catch (Throwable) {
            /*
             * Presence must never break
             * an authenticated page.
             */
        }
    }

    public function onlineCount(): ?int
    {
        if (!$this->available()) {
            return null;
        }

        try {
            $minutes =
                self::ONLINE_WINDOW_MINUTES;

            $statement =
                $this->db->query("
                    SELECT COUNT(*)
                    FROM online_user_presence
                    WHERE last_seen_at >=
                        DATE_SUB(
                            UTC_TIMESTAMP(),
                            INTERVAL {$minutes} MINUTE
                        )
                ");

            return (int) (
                $statement->fetchColumn()
                ?: 0
            );
        } catch (Throwable) {
            return null;
        }
    }

    private function available(): bool
    {
        if (!$this->db instanceof PDO) {
            return false;
        }

        try {
            $statement =
                $this->db->query("
                    SELECT COUNT(*)
                    FROM information_schema.tables
                    WHERE table_schema = DATABASE()
                      AND table_name =
                          'online_user_presence'
                ");

            return
                (int) $statement->fetchColumn()
                > 0;
        } catch (Throwable) {
            return false;
        }
    }
}
