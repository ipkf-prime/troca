# SAFIR R73C2 Rev2 — independent historical isolated preflight

Status: READ-ONLY / NO SCHEMA EXECUTION / NO LIVE DB MIGRATION.

## Latest operator evidence
- R66B on troca-php: **PASS** with pinned 879-file R67 (537121996 bytes) and 880-file R68 (478471014 bytes) complete SHA256 manifest validation.
- Historical 185-table baseline (141 InnoDB / 44 MyISAM); current stage 186 tables (142/44). New `organization_data_review_index` appeared after backup; no matching historical backup files found. `DRIFT_SCOPE_MANUALLY_APPROVED=NO`.
- Old R72C check fails transitively because old R70->R69B->R69->R68->R67 currently checks a LIVE table-count freeze. The historical backups themselves remain pinned and verified.
- R73C1 private-stage tests: 39/39 PASS.

## Rev2 artifact and responsibility
- `tools/safir/SAFIR_S36_R73C2_Historical_Isolated_Preflight_Rev2.py`.
- Requires a successful **full recheck** of standalone R66B pinned historical backup attestation, including no-change metadata guard, original R67+R68 receipts, all source SHA256 digests, and known current live drift.
- Imports the original **SHA-pinned R70 Python static SQL contract** and runs **only** `validate_sql_contract()`, NOT its historical-inappropriate live-freeze `main()`.
- Statically checks SHA-pinned R73C2 additive SQL against exact two-statement, 2-FK/8-CHECK, same-campaign and completion-count constraints.
- Re-pins existing R72C isolated harness, R72A/R72B repository policy and R73C1 staging sources.
- Checks isolated host: MariaDB 11.8 binary, non-root mysql OS account, sticky /var/tmp, enough scratch disk space and private R68 source dirs.
- `--check` is **read-only**, opens no private MariaDB, makes no copies, no schema changes, sends no messages; downstream production approval remains explicitly NO.

## Follow-up, not currently implemented
Only after operator reports `R73C2_REV2_PREFLIGHT=PASS`, construct an independent new **disposable-instance-only** DDL rehearsal:
- PIN exact Rev2 and original source SHA256; prompt interactive explicit confirmation for isolated copy only; retain original isolation lock, MariaDB socket-only/no replication/no binlog/read_only ON startup and stop-in-finally guards.
- Start from a **new copy** of R68 historical 185-table prepared backup, not the 186-table live schema.
- Rehearse pinned R70 five tables plus R73C2 file-level receipt table and additive batch columns; validate 7 total FKs and 17 total CHECK constraints.
- Add positive/negative same-campaign, duplicate chunk, pair-nullability and terminal-status tests; preserve all 185 restored tables.
- No production DB migration or live messaging.

## Outstanding non-database gates
- Obtain authorized provenance of new live `organization_data_review_index` table; R66B does not approve it.
- Build DB-backed file finalization and approval state machine. Cross-table counters and actual file immutability require application transactions, not only CHECKs.
- Explicit separate approval for production migration only after migrations, recovery, permissions, compatibility and isolation tests.
