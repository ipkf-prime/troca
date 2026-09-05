<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

/**
 * USER_INVITATION_FOUNDATION_V1
 *
 * Secure invitation metadata.
 * The raw invitation token is never persisted.
 */
final class CreateUserInvitationFoundationTables extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS user_invitations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

                public_reference
                    CHAR(32)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                token_hash
                    CHAR(64)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                full_name VARCHAR(150) NULL,

                mobile VARCHAR(20) NOT NULL,
                mobile_norm VARCHAR(20) NOT NULL,

                email VARCHAR(150) NULL,
                email_norm VARCHAR(150) NULL,

                status VARCHAR(30)
                    NOT NULL
                    DEFAULT 'pending',

                expires_at TIMESTAMP NOT NULL,

                created_by_user_id
                    BIGINT UNSIGNED NOT NULL,

                accepted_user_id
                    BIGINT UNSIGNED NULL,

                accepted_at TIMESTAMP NULL,
                revoked_at TIMESTAMP NULL,

                created_ip VARCHAR(64) NULL,
                created_user_agent VARCHAR(255) NULL,

                created_at TIMESTAMP NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NOT NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                PRIMARY KEY (id),

                UNIQUE KEY
                    user_invitations_public_reference_unique
                    (public_reference),

                UNIQUE KEY
                    user_invitations_token_hash_unique
                    (token_hash),

                INDEX
                    user_invitations_mobile_status_index
                    (mobile_norm, status),

                INDEX
                    user_invitations_email_status_index
                    (email_norm, status),

                INDEX
                    user_invitations_status_expiry_index
                    (status, expires_at),

                INDEX
                    user_invitations_creator_index
                    (created_by_user_id),

                INDEX
                    user_invitations_accepted_user_index
                    (accepted_user_id),

                CONSTRAINT
                    user_invitations_created_by_fk
                    FOREIGN KEY (
                        created_by_user_id
                    )
                    REFERENCES users(id)
                    ON UPDATE CASCADE
                    ON DELETE RESTRICT,

                CONSTRAINT
                    user_invitations_accepted_user_fk
                    FOREIGN KEY (
                        accepted_user_id
                    )
                    REFERENCES users(id)
                    ON UPDATE CASCADE
                    ON DELETE SET NULL
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->db->exec(
            'DROP TABLE IF EXISTS user_invitations'
        );
    }
}
