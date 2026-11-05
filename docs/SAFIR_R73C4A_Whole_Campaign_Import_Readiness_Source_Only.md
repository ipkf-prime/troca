# SAFIR R73C4A — Whole-Campaign Import Readiness (SOURCE ONLY)

Status: **NOT DEPLOYED; NOT AN APPROVAL; NOT A SEND AUTHORIZATION; NO DB MIGRATION.**

## Previous evidence (2026-11-05, operator result)
- R73C3B `R73C3B_ISOLATED_REHEARSAL=PASS` with **25/25** new real PDO checks; 14 R73C2 negative constraints; five original R72C tests. Verified source backup untouched; isolated process stopped.
- Completed two-chunk CSV import including suppression/duplicate rejection, file receipt terminalization, rollback on missing chunk and recipient-count drift, and tampered private CSV rejection.
- `R67_R68_SOURCE_MUTATION=NO`; `PRODUCTION_DATABASE_MUTATION=NO`; `PROVIDER_SEND=NO`.
- No R73C3B production deployment or live SAFIR tables have been authorized.

## R73C4A pure policy
`public_html/app/Services/BaleSafirDurableCampaignImportReadinessPolicy.php`:
- Pure deterministic `requireCompleteImport(campaign, files, batches, recipientStats, sendAttemptCount, currentlySuppressedReadyRecipients)`.
- Locks and database snapshots **NOT** acquired by this function. A future separate repository MUST supply complete canonical DB rows under the same campaign-first isolation lock and verify all private staged CSV files and SHA/manifest again.
- Rejects already approved/not-draft campaigns, missing owner, snapshot/template mismatch.
- Every registered file must be `complete` with full terminal counters, file-level template SHA match, full source identity, unique stage/source and no failure markers. Nonempty file, source and chunks.
- Every persisted campaign batch, including legacy/unlinked batches, must belong to exactly one completed file, be complete and numbered 1..N without duplicate/gaps, with count-limited source/accepted/rejected totals.
- Per-batch real recipient counts must exactly match each accepted count, and every recipient must be `ready` with no `request_id` or existing send/attempt/provider metadata. Zero-accepted batches must be represented explicitly by zero counts.
- Rejects any existing send attempt and any ready recipient now on suppression list. **Same checks must be repeated immediately before later actual send**, under appropriate concurrency control.
- Rejects zero eligible recipients; returns aggregate numbers only. It never changes campaign state, signs approval, creates queues or invokes provider.
- Canonical RBAC, the identity of an independent approver and duties segregation are NOT represented as caller-controlled booleans and are NOT implemented. **A policy PASS is NOT an approval token.**
- The current R73C2 DB schema has nullable `import_file_id` for backwards compatibility. This policy deliberately rejects unlinked legacy batches for complete-file campaign readiness.

## Source-only test
`tools/safir/SAFIR_R73C4A_CampaignImportReadiness_StandaloneTests.php`:
- Deterministic synthetic cases including one/two files, zero-accepted second file, empty/missing batches, duplicate file/source/chunk, legacy unlinked batch, wrong campaign, incomplete file, wrong template, invalid chunk manifest, premature approval/sending, current suppression, count drift, non-ready recipients and assigned request IDs.
- **47 asserted cases are authored but not yet executed by the operator**. PHP lint + run should precede further implementation.
- Does not connect to a database or send provider messages.

## Pending external / integration work
1. Read-only persisted DB attestation with exact queries for all files, batches, recipient states, currently suppressed ready recipients, previous send attempts, and all mismatches, using the correct snapshot and locks; no approval state changes yet.
2. Independently authenticated actual approver, canonical organization/role/scoped RBAC (not supplied by the user), permission boundary and 4-eyes separation if required.
3. File source bytes and manifests reverified, retry/resume/concurrency and file-race contracts tested. Current R73C3B rejects repeated chunks instead of automatically resuming.
4. Prequeue suppression, dynamic provider template and capability revalidation, retry/idempotency and production send flags remain separate explicit future gates.
5. No production migrations, deployment, provider sends or worker until separate approved release and rollback plan.
