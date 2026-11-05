# SAFIR R73C3A — Exact File Terminalization Foundation (source-only)

Status: **implementation committed, not deployed, not integrated with production, not yet isolated-PDO tested**.

## Inputs and previous gates
- Operator's R73C2 disposable MariaDB 11.8 rehearsal: PASS on 2026-11-05.
- 185 historical tables restored, six new SAFIR InnoDB tables created, seven total FKs, seventeen CHECK constraints verified. Original R72C PDO integration and 5 negative DB tests PASS. Fourteen new receipt/Batch negative checks PASS.
- Explicitly confirmed schema-only gap: receipt may read `complete` even if actual linked batches are missing. R73C2 deliberately did not implement approval or worker.

## Implementation
- `public_html/app/Services/BaleSafirDurableFileCompletionPolicy.php` — pure file terminalization accounting, no I/O.
  - `requireExactCompletion(file, batches, recipientCounts)` rejects incomplete/failed/previously complete receipts.
  - Requires every ordered chunk index 1..N, exact bound, same-campaign/same-file completed batches with timestamps, each ≤500 rows, exact source/accepted/rejected totals, actual recipient counts, and no duplicate batches.
  - Preliminary ready/skipped counts must cover source rows; persisted accepted cannot exceed preliminary ready.
  - Returns only verified counters, **never campaign approval**.
- `public_html/app/Services/BaleSafirDurableFileCompletionRepository.php` — DB-injected, independent terminalization method `completeForOwner(actorId, reference, privateRoot, stageId)`.
  - Rejects outer transactions; begins its own; campaign-first `SELECT ... FOR UPDATE`; checks owner and draft.
  - Checks originally frozen template snapshot hashes; rehashes private staged CSV through existing `BaleSafirDurableCsvPrivateStage::verify`; compares complete receipt to locked DB receipt.
  - Locks linked batches, compares actual scoped recipient rows (including zero-accepted chunks), requires recipients ready, and detects repeated source row numbers across chunks.
  - Calls the pure strict completion policy and updates file to complete with exact batch counters; commits only when exactly one terminal update succeeds; otherwise rolls back.
  - No provider API, no outbox, no approval, no mutable file-system operation, no route/controller/cron registrations.

## Source-only tests
- `tools/safir/SAFIR_R73C3A_FileCompletionPolicy_StandaloneTests.php` — **29** deterministic policy assertions, including absent chunks, duplicate/gapped indices, wrong owner scope, missing or miscounted recipients, oversized chunks, incomplete batches, previously complete receipts, progress overflow, and valid zero-accepted all-rejected file.
- PHP syntax and tests must be executed on the operator's CLI before any next step. This tests **only the pure policy**, not the PDO repository.

## Outstanding gates — NOT finished by R73C3A
1. Independently authorized `registerVerifiedFile` transaction with the private-stage receipt and template, state=verified; then atomic `importFileChunk` with a new `import_file_id` and `chunk_index`, same-campaign lock order and R72B suppression/dedup behavior. The original R72B importer currently has no file linkage and must **not** be used as a replacement.
2. Guarded **real isolated PDO** tests of `completeForOwner`, including both rollback and success, over a disposable R68 copy with R70+R73C2 DDL and staged synthetic CSV.
3. Pre-approval whole-campaign attestation must reject any unlinked/failed/incomplete file or batch. Final source file and chunk manifest identity and RBAC before explicit approval are separate steps.
4. Review provenance of new live `organization_data_review_index`. It remains unapproved by the SAFIR backup checks.
5. Production DDL/migration, worker, cron, provider sends and runtime registration remain prohibited without explicit review and approval.

R73C3A is source-only, not production-ready.
