-- SAFIR R70 / v2.5.0 / ISOLATED REHEARSAL DRAFT. NEVER run against Production.
-- Changes from R65: enforce the same campaign across import batches, recipients and attempts by composite FKs.
-- MySQL/MariaDB InnoDB; no dependency on SQLite; original notification_* tables unaffected.
-- NOT EXECUTABLE from the accompanying R65 preflight script. DO NOT RUN manually in production.
-- Review actual DB backup, grants, table name conflicts, and migration registry before adoption.

CREATE TABLE safir_campaigns (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_reference CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    approved_by_user_id BIGINT UNSIGNED NULL,
    approved_at DATETIME(6) NULL,
    title VARCHAR(190) NOT NULL,
    template_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    template_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    template_snapshot_json LONGTEXT NOT NULL,
    state_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',
    purpose_code VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'operational',
    consent_basis VARCHAR(255) NULL,
    interval_ms INT UNSIGNED NOT NULL DEFAULT 1500,
    daily_send_limit INT UNSIGNED NULL,
    approved_recipient_count BIGINT UNSIGNED NULL,
    started_at DATETIME(6) NULL,
    completed_at DATETIME(6) NULL,
    paused_at DATETIME(6) NULL,
    last_error_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY safir_campaign_ref_unique (public_reference),
    KEY safir_campaign_owner_state_idx (owner_user_id, state_code, id),
    KEY safir_campaign_state_idx (state_code, id),
    CONSTRAINT safir_campaign_template_json_chk CHECK (JSON_VALID(template_snapshot_json)),
    CONSTRAINT safir_campaign_state_chk CHECK (state_code IN
      ('draft','validating','validated','approved','queued','running','paused','needs_review','completed','cancelled')),
    CONSTRAINT safir_campaign_interval_chk CHECK (interval_ms BETWEEN 500 AND 600000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE safir_campaign_import_batches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    campaign_id BIGINT UNSIGNED NOT NULL,
    source_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_filename VARCHAR(255) NULL,
    state_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'processing',
    source_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
    accepted_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
    rejected_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    finished_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY safir_batch_source_unique (campaign_id, source_sha256),
    UNIQUE KEY safir_batch_campaign_id_unique (campaign_id, id),
    KEY safir_batch_campaign_idx (campaign_id, id),
    CONSTRAINT safir_batch_campaign_fk FOREIGN KEY (campaign_id)
      REFERENCES safir_campaigns (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT safir_batch_state_chk CHECK (state_code IN ('processing','complete','failed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE safir_campaign_recipients (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    campaign_id BIGINT UNSIGNED NOT NULL,
    import_batch_id BIGINT UNSIGNED NOT NULL,
    source_row_number INT UNSIGNED NOT NULL,
    phone_normalized CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    text_fields_json LONGTEXT NOT NULL,
    state_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'ready',
    request_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    attempted_at DATETIME(6) NULL,
    sent_at DATETIME(6) NULL,
    provider_message_id VARCHAR(180) NULL,
    provider_http_code SMALLINT UNSIGNED NULL,
    provider_error_code INT NULL,
    last_error VARCHAR(255) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY safir_recipient_campaign_phone_unique (campaign_id, phone_normalized),
    UNIQUE KEY safir_recipient_campaign_id_unique (campaign_id, id),
    UNIQUE KEY safir_recipient_request_unique (request_id),
    KEY safir_recipient_queue_idx (campaign_id, state_code, id),
    KEY safir_recipient_batch_idx (import_batch_id, id),
    CONSTRAINT safir_recipient_campaign_fk FOREIGN KEY (campaign_id)
      REFERENCES safir_campaigns (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT safir_recipient_batch_fk FOREIGN KEY (campaign_id, import_batch_id)
      REFERENCES safir_campaign_import_batches (campaign_id, id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT safir_recipient_fields_json_chk CHECK (JSON_VALID(text_fields_json)),
    CONSTRAINT safir_recipient_phone_chk CHECK (phone_normalized REGEXP '^989[0-9]{9}$'),
    CONSTRAINT safir_recipient_state_chk CHECK (state_code IN ('ready','skipped','unknown','sent','failed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE safir_campaign_send_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    campaign_id BIGINT UNSIGNED NOT NULL,
    recipient_id BIGINT UNSIGNED NOT NULL,
    request_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    attempt_number INT UNSIGNED NOT NULL DEFAULT 1,
    state_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'unknown',
    reserved_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    resolved_at DATETIME(6) NULL,
    provider_http_code SMALLINT UNSIGNED NULL,
    provider_message_id VARCHAR(180) NULL,
    provider_error_code INT NULL,
    error_description VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY safir_attempt_request_unique (request_id),
    UNIQUE KEY safir_attempt_recipient_num_unique (recipient_id, attempt_number),
    KEY safir_attempt_campaign_time_idx (campaign_id, reserved_at),
    CONSTRAINT safir_attempt_campaign_fk FOREIGN KEY (campaign_id)
      REFERENCES safir_campaigns (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT safir_attempt_recipient_fk FOREIGN KEY (campaign_id, recipient_id)
      REFERENCES safir_campaign_recipients (campaign_id, id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT safir_attempt_state_chk CHECK (state_code IN ('unknown','sent','failed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE safir_suppressed_phones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    phone_normalized CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    reason_code VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    note VARCHAR(255) NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY safir_suppressed_phone_unique (phone_normalized),
    CONSTRAINT safir_suppressed_phone_chk CHECK (phone_normalized REGEXP '^989[0-9]{9}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
