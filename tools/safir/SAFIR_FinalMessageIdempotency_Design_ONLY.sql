-- SAFIR final-message idempotency ledger. DESIGN ONLY; NOT AUTO-DEPLOYED.
-- Apply only through reviewed, backed-up, guarded migration to the actual SAFIR database.
-- This ledger must be shared by ALL SAFIR workers/campaigns for the same provider.
-- Historical sends are NOT automatically covered; reconcile or explicitly suppress them.
CREATE TABLE safir_message_idempotency (
  dedup_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  provider_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  phone_normalized CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  message_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  character_count INT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  recipient_id BIGINT UNSIGNED NOT NULL,
  request_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  state_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'reserved',
  reserved_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  resolved_at DATETIME(6) NULL,
  provider_message_id VARCHAR(180) NULL,
  PRIMARY KEY (dedup_sha256),
  UNIQUE KEY safir_message_idempotency_request_unique (request_id),
  KEY safir_message_idempotency_campaign_idx (campaign_id,recipient_id),
  CONSTRAINT safir_message_idempotency_state_chk CHECK
     (state_code IN ('reserved','accepted','unknown')),
  CONSTRAINT safir_message_idempotency_phone_chk CHECK
     (phone_normalized REGEXP '^989[0-9]{9}$'),
  CONSTRAINT safir_message_idempotency_count_chk CHECK (character_count > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
