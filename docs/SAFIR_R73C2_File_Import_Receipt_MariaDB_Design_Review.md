# SAFIR R73C2 — durable file-level receipt / batch linkage design review

STATUS=DESIGN_ONLY; SQL_EXECUTION=NO; NO_DATABASE_MUTATION; NO_SEND; NO_PRODUCTION_DEPLOY.

## Verified operator checkpoint (2026-11-04)
- R73C1 `R73C1_PRIVATE_STAGE_TESTS=39_PASS` on troca-php.
- Prior: R73B 33/33 PASS; R73A 38/38 PASS; R72C real isolated MariaDB PDO PASS, isolated daemon stopped.
- Live five-table R70 schema remains **absent and unapproved for production**.
- R67/R68 original backups preserved; SAFIR production flags remain OFF.

## R73C2 schema-only proposal

The additional `safir_campaign_import_files` InnoDB table binds a private staged file to exactly one campaign:
- `stage_id` (unique), whole `source_sha256`, frozen `template_sha256`, `chunk_manifest_sha256`;
- immutable expected total logical rows, bytes, chunk count, prevalidation ready/skipped counts;
- `state_code` = `verified|importing|complete|failed|abandoned` with timestamps, persisted batch progress and failure reason;
- CHECKs reject invalid counts and require completed files to have all expected chunks and complete accounting.

Two nullable columns are added to `safir_campaign_import_batches`: `import_file_id`, `chunk_index`.
- Unique file/chunk index prohibits duplicate chunk receipt within a file.
- Composite FK `(campaign_id, import_file_id)` guarantees every non-null linked batch references a file belonging to the SAME campaign.
- Composite pair CHECK requires both linkage columns to be null (legacy R72B data) or both valid.
- `NULL` is only for backward compatibility with the source-only R72B Repository; **it must block all future approval** until older imports are formally reconciled.

Only two statements are in `tools/safir/SAFIR_R73C2_File_Receipt_Link_MariaDB11_Design_ONLY.sql`:
1. CREATE TABLE `safir_campaign_import_files`;
2. ALTER TABLE `safir_campaign_import_batches` with additive columns / indexes / constraints.

## Critical fail-closed approval contract (NOT IMPLEMENTED)

A new approval service must lock the campaign row `FOR UPDATE`, require no unknown legacy batches, no active/incomplete/failed/abandoned file receipts, and at least one completed source file and at least one authorized recipient. For EACH complete file it must independently verify:
- actual linked batch count == `expected_chunk_count`;
- chunk index sequence exactly 1..N (unique index + count/min/max);
- all batch states are `complete` and every batch `source_rows = accepted_rows + rejected_rows`;
- SUM(batch source/accepted/rejected counts) equals the corresponding file receipt counts;
- approved template digest and private stage file content are still valid;
- owner / explicit sender permissions, purpose/consent and suppression are independently checked.

**Schema CHECK constraints cannot enforce cross-table SUMs, prevent an application from approving early, or attest that a private file is still unchanged.** That requires dedicated transactional application logic and tests. No approved/queued/sending transition is implemented at R73C2.

## Required next gates
1. Read-only pinned schema preflight R73C2 on troca-php.
2. Guarded *disposable isolated MariaDB* DDL/negative-FK rehearsal; never production.
3. Transactional file->chunk import finalization and failure/restart flow; staged file verification and root ownership.
4. R73C3 approval safety check and concurrency tests, then explicit separate production DB migration review.
5. Preserve all four OFF switches and existing SAFIR routes; no worker or provider calls in these gates.

Note: MariaDB DDL, especially ALTER TABLE, is not generally reversible with a transaction; a clean disposable isolated copy and a documented rollback strategy are mandatory before any later release.
