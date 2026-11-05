# SAFIR R73C3B — staged-file bound chunk import + guarded real-PDO test

Status: SOURCE + TEST-HARNESS ONLY. No live/stage schema writes, no deploy, no worker, no provider send.

## Proven historical gates
- R66B original 879-file R67 + 880-file R68 complete pinned hash review PASS; current stage drift +1 InnoDB documented but not approved.
- R73C2 Rev2 historical preflight PASS.
- R73C2 guarded disposable MariaDB rehearsal PASS: historical 185 base + six SAFIR tables, 7 FK, 17 CHECK, 5 R72C negative tests, 14 R73C2 negative tests, real PDO success; isolated daemon stopped, R67/R68 untouched, provider send NO.
- R73C3A policy syntax PASS, 29/29 standalone pure terminal-accounting tests PASS (operator evidence, 2026-11-05).

## Source implementation
`public_html/app/Services/BaleSafirDurableFileImportRepository.php` is a disconnected PDO-injected source-only service:
- `registerVerifiedFile(actorId,campaignReference,privateRoot,stageId)`: locks owning draft campaign first, revalidates pinned dynamic template, rehashes/private-stage CSV receipt through existing R73C1, verifies exact source/template/chunk manifest/row counts, and inserts `safir_campaign_import_files` in state `verified`.
- `importVerifiedChunk(...,chunkIndex)`: locks campaign, verifies immutable receipt and locked same-campaign file, requires next ordinal and complete previous file-linked batches, parses only the bounded desired chunk of the reverified private source using R73A and checks source hash again, rejects cross-chunk source hash duplicate, creates linked `import_batch` with `import_file_id` + `chunk_index`, applies frozen R72A planning and DB suppression/duplicate scope, commits accepted recipients, terminal batch counters and cumulative file progress atomically. Never accepts unverified caller-defined rows or SHA.
- Does **not** call original R72B `importChunk` because R72B lacks the file+chunk foreign-key linkage. The existing R72B route/worker remains untouched.
- R73C3A `completeForOwner` is called separately under the same campaign-first lock order. It rehashes the stage, counts the actual per-batch recipient rows, rejects non-ready/doubly numbered recipients, checks ordered completed chunk counters and commits file terminalization. No automatic campaign approval.

## Tests and harness
`tools/safir/SAFIR_R73C3B_RealPDO_FileImport_Integration.php` uses test-only synthetic CSV, dynamic synthetic template and fake provider identifiers; strict private R72C-stage path, own private stage marker, socket-only root local DB connection; asserts owner/draft restrictions, duplicate stage and file, strict chunk ordering, import two chunks, suppression, deduplication, incomplete-file rollback, actual recipient-count fault injection and rollback, success terminalization, repeated completion/import rejection, tampered private source rejection and SQL persisted row counts.

`tools/safir/SAFIR_S36_R73C3B_RealPDO_LinkedFile_Guarded.py` is a standalone copy of the SHA-pinned R73C2 guarded isolated harness, with added source pins and test call:
- `--check` rechecks R66B/R73C2 Rev2 chain and SHA pins, and does NOT run SQL or start a daemon.
- `--run-isolated` prompts for exact TTY acknowledgement; uses same lock and R72C-approved private stage prefix, copies only R68 historical prepared files into a new private random directory, compares copied file hashes, starts socket-only/no-TCP/no-binlog/no-replication/no-event MariaDB nonroot, replays R70 + R72C realPDO and R73C2 DDL/constraint probes, then R73C3B realPDO import/finalization probe. Stops the isolated daemon, retains private copy, and does not touch original backup.
- The test is NOT yet run by the operator. Source syntax/lint tests are required next. A source-only PASS is not a production release.

## Outstanding production gates
1. Verify PHP lint for both new PHP files and isolated Python `ast.parse`; run isolated `--check` only, then the separate explicitly acknowledged isolated test if PASS.
2. Cross-process crash/resume/retry semantics, chunk manifest replay and idempotency model require additional bounded tests; current new source rejects repeated chunk imports, which is fail closed but not an automatic resume API.
3. Whole-campaign approval must reject every unlinked/incomplete/unverified file and any unlinked legacy batch, check RBAC, ready/skip accounting, fresh suppression and immutable per-message provider snapshot. No approval or worker implemented.
4. MDM-created `organization_data_review_index` production/live schema provenance remains unapproved in the SAFIR workflow.
5. Production migration, worker, cron and provider sending stay disabled until separate explicit authorized review and deployment.
