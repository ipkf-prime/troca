-- SAFIR R73C2 / v2.5.0: FILE-LEVEL TERMINAL RECEIPT; DESIGN-ONLY.
-- STOP: NOT APPROVED FOR PRODUCTION. Never execute outside an explicitly isolated disposable MariaDB copy.
-- Prerequisite: R70's five-table design already created on the SAME isolated database.
-- Does not authorize sending, approve campaigns, deploy worker code or modify any existing notification_* tables.
-- MariaDB 11.8; InnoDB; restrictive FKs; no implicit cleanup.
-- Add one file-level receipt table and nullable linkage to the existing import-batches table.
-- NULL linkage preserves the standalone R72B repository behavior; future approval MUST block unlinked batches.

CREATE TABLE safir_campaign_import_files (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    campaign_id BIGINT UNSIGNED NOT NULL,
    stage_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    template_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    chunk_manifest_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_bytes BIGINT UNSIGNED NOT NULL,
    source_rows BIGINT UNSIGNED NOT NULL,
    expected_chunk_count INT UNSIGNED NOT NULL,
    preliminary_ready_rows BIGINT UNSIGNED NOT NULL,
    preliminary_skipped_rows BIGINT UNSIGNED NOT NULL,
    state_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'verified',
    imported_chunk_count INT UNSIGNED NOT NULL DEFAULT 0,
    accepted_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
    rejected_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
    verified_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    started_at DATETIME(6) NULL,
    completed_at DATETIME(6) NULL,
    failed_at DATETIME(6) NULL,
    failure_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY safir_file_stage_unique (stage_id),
    UNIQUE KEY safir_file_campaign_sha_unique (campaign_id, source_sha256),
    UNIQUE KEY safir_file_campaign_id_unique (campaign_id, id),
    KEY safir_file_campaign_state_idx (campaign_id, state_code, id),
    CONSTRAINT safir_file_campaign_fk FOREIGN KEY (campaign_id)
      REFERENCES safir_campaigns (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT safir_file_stage_id_chk CHECK
      (stage_id REGEXP '^safir-r73c-file-[a-f0-9]{32}$'),
    CONSTRAINT safir_file_state_chk CHECK
      (state_code IN ('verified','importing','complete','failed','abandoned')),
    CONSTRAINT safir_file_size_chk CHECK (source_bytes > 0 AND source_rows > 0
      AND expected_chunk_count > 0),
    CONSTRAINT safir_file_preliminary_counts_chk CHECK
      (preliminary_ready_rows + preliminary_skipped_rows = source_rows),
    CONSTRAINT safir_file_progress_chk CHECK
      (imported_chunk_count <= expected_chunk_count
        AND accepted_rows + rejected_rows <= source_rows),
    CONSTRAINT safir_file_completed_chk CHECK
      (state_code <> 'complete'
        OR (completed_at IS NOT NULL
          AND imported_chunk_count = expected_chunk_count
          AND accepted_rows + rejected_rows = source_rows)),
    CONSTRAINT safir_file_failed_chk CHECK
      (state_code <> 'failed' OR (failed_at IS NOT NULL AND failure_code IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE safir_campaign_import_batches
    ADD COLUMN import_file_id BIGINT UNSIGNED NULL,
    ADD COLUMN chunk_index INT UNSIGNED NULL,
    ADD UNIQUE KEY safir_batch_file_chunk_unique (import_file_id, chunk_index),
    ADD KEY safir_batch_campaign_file_idx (campaign_id, import_file_id),
    ADD CONSTRAINT safir_batch_file_pair_chk CHECK
      ((import_file_id IS NULL AND chunk_index IS NULL)
        OR (import_file_id IS NOT NULL AND chunk_index IS NOT NULL AND chunk_index > 0)),
    ADD CONSTRAINT safir_batch_same_campaign_file_fk
      FOREIGN KEY (campaign_id, import_file_id)
      REFERENCES safir_campaign_import_files (campaign_id, id)
      ON DELETE RESTRICT ON UPDATE RESTRICT;
