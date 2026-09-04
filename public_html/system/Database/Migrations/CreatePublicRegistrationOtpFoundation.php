<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

class CreatePublicRegistrationOtpFoundation extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                public_registration_attempts (
                    id BIGINT UNSIGNED
                        AUTO_INCREMENT PRIMARY KEY,

                    user_id BIGINT UNSIGNED
                        NOT NULL,

                    nonce_hash CHAR(64)
                        CHARACTER SET ascii
                        COLLATE ascii_bin
                        NOT NULL,

                    full_name VARCHAR(150)
                        NOT NULL,

                    mobile VARCHAR(30)
                        NOT NULL,

                    mobile_norm VARCHAR(30)
                        NOT NULL,

                    email VARCHAR(255)
                        NULL,

                    email_norm VARCHAR(255)
                        NULL,

                    password_hash VARCHAR(255)
                        NOT NULL,

                    status VARCHAR(30)
                        CHARACTER SET ascii
                        COLLATE ascii_bin
                        NOT NULL
                        DEFAULT 'pending',

                    verification_attempts
                        INT UNSIGNED
                        NOT NULL
                        DEFAULT 0,

                    expires_at TIMESTAMP
                        NOT NULL,

                    consumed_at TIMESTAMP
                        NULL,

                    created_ip VARCHAR(64)
                        NULL,

                    created_user_agent TEXT
                        NULL,

                    created_at TIMESTAMP
                        NULL
                        DEFAULT CURRENT_TIMESTAMP,

                    updated_at TIMESTAMP
                        NULL
                        DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,

                    UNIQUE KEY
                        public_registration_attempts_nonce_unique (
                            nonce_hash
                        ),

                    INDEX
                        public_registration_attempts_user_status_index (
                            user_id,
                            status,
                            id
                        ),

                    INDEX
                        public_registration_attempts_mobile_status_index (
                            mobile_norm,
                            status,
                            id
                        ),

                    INDEX
                        public_registration_attempts_ip_created_index (
                            created_ip,
                            created_at
                        ),

                    INDEX
                        public_registration_attempts_status_expiry_index (
                            status,
                            expires_at
                        )
                )
                ENGINE=InnoDB
                DEFAULT CHARSET=utf8mb4
                COLLATE=utf8mb4_unicode_ci
        ");

        $this->ensureChallengeIndex(
            'mfa_delivery_challenges_registration_rate_index',
            '
                user_id,
                method,
                purpose,
                created_at
            '
        );

        $this->ensureChallengeIndex(
            'mfa_delivery_challenges_ip_rate_index',
            '
                created_ip,
                created_at
            '
        );
    }

    public function down(): void
    {
        /*
         * Intentionally non-destructive.
         *
         * Registration-attempt rows are security/audit
         * evidence and must not disappear on rollback.
         */
    }

    private function ensureChallengeIndex(
        string $indexName,
        string $columns
    ): void {
        if (
            !$this->tableExists(
                'mfa_delivery_challenges'
            )
            || $this->indexExists(
                'mfa_delivery_challenges',
                $indexName
            )
        ) {
            return;
        }

        $this->db->exec(
            "
                ALTER TABLE
                    mfa_delivery_challenges
                ADD INDEX {$indexName} (
                    {$columns}
                )
            "
        );
    }

    private function tableExists(
        string $table
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = ?
            ");

        $statement->execute([
            $table,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }

    private function indexExists(
        string $table,
        string $index
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                  AND index_name = ?
            ");

        $statement->execute([
            $table,
            $index,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }
}
