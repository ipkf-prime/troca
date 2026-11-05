# SAFIR R73C2 — Guarded isolated MariaDB receipt-schema rehearsal

Status: TEST-ONLY, NO PRODUCTION DDL, NO SEND, NO CRON, NO DEPLOY.

## Operator-passed gates
- R66B historical receipt integrity **PASS**: R67 879 files (537121996 bytes), R68 880 files (478471014 bytes), complete pinned SHA256 match; no source mutation.
- Historical base 185 tables (141 InnoDB, 44 MyISAM); current live schema 186 tables (142 InnoDB, 44 MyISAM).
- New live `organization_data_review_index` appeared after historical backup; provenance/authorization not yet confirmed; `DRIFT_SCOPE_MANUALLY_APPROVED=NO`.
- R73C2 Rev2 historical isolated preflight **PASS**, with pinned historical R66B, R70 five-table SQL and new R73C2 additive SQL; no SQL executed.
- R73C1 private staging 39/39 PASS, R73B 33/33 PASS, R73A 38/38 PASS, R72C real PDO isolated PASS.

## R73C2 isolated rehearsal artifact
`tools/safir/SAFIR_S36_R73C2_Additional_ReceiptDDL_Isolated_Guarded.py`:
- `--check`: runs pinned Rev2, verifies original R70/R72C and R73C2 source SHA256; **no** DDL, copy, daemon, worker or message send.
- `--run-isolated`: explicit TTY confirmation `DDL R73C2 ISOLATED`. Acquires same lock as R71/R72C. Rechecks Rev2. Copies pinned 880-file R68 prepared historical data to a new 0700 private random path; full per-file SHA256 verification.
- Launches MariaDB 11.8 solely on a new private socket, under non-root mysql user; TCP, replication auto-start, binlog and event scheduler off, read-only startup. Verifies datadir/socket/settings/version.
- Replays original pinned R72C five-table DDL, negative FK tests and actual PHP PDO integration in that **copy only**, then restores read-only.
- Executes pinned R73C2 `CREATE TABLE safir_campaign_import_files` and `ALTER TABLE safir_campaign_import_batches` only after the isolated identity gates, and only in that fresh instance. DDL cannot be rolled back transactionally; the copy is disposable.
- Verifies historical 185+6=191 tables, 6 new InnoDB SAFIR tables, 7 total SAFIR foreign keys, 17 total SAFIR CHECK constraints.
- 14 new negative SQL tests: duplicate stage/source, malformed stage, preliminary counts, missing failure data, cross-campaign/orphan file FK, partial file link, zero chunk index, duplicate chunk, premature complete, excess chunk count, no completed_at, referenced file deletion. Also positive two-chunk file completion.
- Deliberately tests that a file marked `complete` can lack linked batches despite count CHECKs. **This is a known design gap**: production application approval MUST compare all batch rows and file receipts transactionally; schema alone is insufficient.
- Cleanup stops isolated MariaDB even on failure, resets stage directory to root-owned private permissions, retains sensitive private copy and prints path. Does not delete original backups or touch live schema.
- Explicitly reports production migration not approved and approval/workers not implemented.

## Approval remain blocked
The R73C2 file-level receipt schema does not implement file state transitions, approval, actual batch aggregation, retry/resume, source file custody or suppression at send time. R73C3 must implement durable transactionally consistent complete-file gate plus owner/RBAC permissions and explicit approval. Production migration requires separate authorized decision and rollback planning.

If the rehearsal fails, inspect the reported code and private copy without rerunning blindly. All send flags remain OFF.
