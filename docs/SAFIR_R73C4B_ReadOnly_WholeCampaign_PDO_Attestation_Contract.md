# SAFIR R73C4B — Guarded real-PDO whole-campaign readiness attestation

Status: CODE+HARNESS AUTHORED; NOT RUN, NOT APPROVED, NO PRODUCTION ACTIONS.

## Confirmed baseline
- 2026-11-05 R73C3B operator isolated PASS (25 new PHP/PDO checks, R72C 5 tests, R73C2 14 tests). Six SAFIR tables, seven FKs, 17 CHECKs. Original 185-table R68 historical restore; R67 and R68 untouched. No production mutation or provider sends.
- 2026-11-05 R73C4A operator source-only **47/47** PASS after pinned SHA and PHP lint, no DB access.
- R67 historical freeze 185 vs current stage 186 (new `organization_data_review_index`). This source-only SAFIR work does NOT approve stage drift or override R67.

## Implementation
`public_html/app/Services/BaleSafirDurableCampaignReadinessRepository.php`:
- Disconnected PDO-injected `attestForOwner(actorId,reference,privateRoot)` for diagnostic import readiness; no approval, mutation or send authorization.
- Requires owner match and starts transaction at REPEATABLE READ; `SELECT ... FOR UPDATE` of campaign row to coordinate with campaign-first writers, then queries ALL file receipts and ALL batches (including unlinked legacy), grouped real recipients and delivery metadata, previous attempts, and current suppression status in a consistent transaction.
- Calls pinned R73C4A fail-closed policy. Then independently rehashes *all* private CSV stages with R73C1 `Stage::verify`, compares full receipt to DB, replays R73A chunk parser and checks exact persisted chunk digests, row counts and recipient source row index ranges with distinct row numbers. Rehashes file after replay.
- Releases transaction, returns immutable summary with `state='import_ready_not_approved'`; no DB write methods anywhere.
- Owner access is not independent approver identity or RBAC; no approval transition.

`tools/safir/SAFIR_R73C4B_RealPDO_CampaignAttestation_Integration.php`:
- Copies existing previously tested R73C3B synthetic two-chunk fixture, then tests incomplete campaign denial before file terminalization, whole-campaign readiness, another owner denied, outer transaction denied, zero-harm rollback under mutated fixtures.
- Negative tests: unlinked/legacy batch, unfinished second file, newly suppressed ready recipient, recipient delivery metadata, assigned request ID, existing send attempt, mutated private source, corrupted persisted chunk SHA, out-of-range source row, duplicate source row; restores all isolated test mutations, confirms final summary unchanged and campaign remains unapproved.
- Strict CLI private socket and MariaDB identity guards inherited unchanged from R73C3B PHP fixture.

`tools/safir/SAFIR_S36_R73C4B_RealPDO_Readiness_Guarded.py`:
- `--check` pins Rev2/R67/R68 integrity, R70/R73C2 source, R72C core, old R73C3B fixture and all new R73C4B sources. No SQL executed and no database mutation.
- `--run-isolated` requires interactive exact `DDL R73C4B ISOLATED`; same lock as R71/R72C/R73C3B and entirely new private random R68 copy; socket-only MariaDB 11.8 non-root with TCP/replication/binlog/events OFF. DDL only on isolated DB. Stops the daemon even on errors, retains private source copied into stage for review. No prod actions.

## Pending
1. Operator PHP lints and pinned-source `--check` only; inspect before --run-isolated.
2. If PASS, explicit approved-for-test independent disposable MariaDB run and inspect all output.
3. Canonical independent approver RBAC, per-organization capability/segregation, production recheck under transaction, real authorization and rollback/recovery design, suppression at send time, provider worker, retries and operational monitoring are NOT IMPLEMENTED.
4. Prod migration and provider delivery remain blocked.

No need to run R73C3B again just to confirm R73C4A; this fresh separate harness will replay earlier tests inside its disposable copy if invoked with explicit operator approval.
