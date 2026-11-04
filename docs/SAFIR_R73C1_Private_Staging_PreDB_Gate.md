# SAFIR R73C1 — Private source staging and verified file receipts

Status: SOURCE-ONLY, TEST-ONLY, NO PROD DEPLOY, NO DB MUTATION, NO PROVIDER SEND.

## Operator evidence
- R67: physical backup ready; R68: independent prepared copy ready.
- R69B: isolated restored MariaDB read test PASS.
- R71: isolated schema DDL (5 new tables, 5 FK, 9 CHECK) PASS.
- R72A: dynamic import policy 21/21 PASS.
- R72B: fake-PDO repository 28/28 PASS.
- R72C: disposable actual MariaDB PDO repository integration PASS, daemon stopped.
- R73A: bounded CSV streaming 38/38 PASS on troca-php.
- R73B: complete-file CSV receipt 33/33 PASS on troca-php.

## R73C1 source
- `public_html/app/Services/BaleSafirDurableCsvPrivateStage.php`
- `tools/safir/SAFIR_R73C1_Private_Staging_StandaloneTests.php`

## Design
- Stage a verified CSV from an explicit absolute source filename into a private directory outside webroot.
- Enforce canonical root, exact 0600 root marker, root and per-stage permissions 0700, file+manifest permissions 0600; reject symlink paths and unexpected stage IDs.
- Generate unguessable stage ID; stream bytes in 64 KiB blocks, limit each staged file by configurable capped maximum (default 256 MiB). Logical campaign total remains uncapped, subject to controlled file/chunk limits.
- Match the declared SHA256 while copying and hash staged bytes again.
- Consume the entire CSV with R73B and preserve a `verified_not_imported` receipt for that private staged file; no early chunk-level DB writes.
- Re-verify by opening that private staged file and recomputing the full deterministic R73B receipt before any later import.
- Failures should not publish a receipt; remove the newly created private partial stage where safe.
- No actual phone numbers are recorded in metadata receipts. The CSV itself contains recipients and must remain private.

## Safety limitations
- Unix 0700/0600 permissions do not make an owner-writable file immutable. Treat the staged file as untrusted until every use rechecks full digest and receipt.
- The configured root marker is a safety gate, not a cryptographic authorization token.
- `stage_verified` is *not* a DB import commit, operator approval, or queued-send state.
- The current five-table R70 design does not yet record a file-level terminal DB receipt and its subordinate batch IDs. **R73C2 must design and test these DB objects and prevent approval until a complete, immutable file-level receipt is confirmed.**
- R73C1 tests are source-only and use synthetic contacts in temporary directories. Do not point them to actual customer data or public web directories.
- All SAFIR production send flags remain OFF, no cron/worker/prod migration is permitted.
