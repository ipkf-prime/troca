# SAFIR R73B — complete CSV file receipt and verification gate

Status: SOURCE-ONLY / NOT DEPLOYED / NO DB CONNECTION / NO HTTP SEND.

## Prerequisite evidence
- R71 isolated five-table DDL and negative MariaDB constraints: PASS.
- R72A standalone template normalization: 21/21 PASS.
- R72B fake-PDO tests: 28/28 PASS.
- R72C isolated MariaDB **real PDO** integration and stop: PASS.
- R73A bounded CSV streaming parser and 3000-row tests on troca-php: 38/38 PASS.

## R73B source contracts
- `public_html/app/Services/BaleSafirDurableCsvFileReceipt.php` (`inspect`, `verify`).
- `tools/safir/SAFIR_R73B_CompleteFile_Receipt_StandaloneTests.php` (33 source-only checks).
- Full-file SHA256, byte count, dynamic approved-template SHA256, chunk count, total logical rows and preliminary valid/skipped counts; one ordered chunk-manifest digest.
- Input must be a seekable stream from byte zero. The parser is fully consumed before returning a receipt; a malformed final row cannot yield a successful complete-file receipt.
- Input bytes hashed before and after parsing; file identity metadata checked, and the stream is rewound after success.
- Chunking reuses R73A (500 max rows per chunk) and R72A dynamic template/phone validation; not wired to a controller or any database.
- `verify` recomputes and strictly compares the full deterministic receipt; rejects changed bytes, counts, options or the template.
- `preliminary_ready_rows` and `preliminary_skipped_rows` describe structural/field validation only, **not deduplication, suppression, operator authorization or sending eligibility**.

## Critical continuation gates — do not omit
1. A receipt is **not a security signature, upload authorization, complete import transaction, durable file staging token, or a campaign approval**. A caller cannot rely on it if an underlying mutable file may later change.
2. R73C must stage source bytes in a private immutable/pinned location outside webroot, fully verify again before DB use, and persist a durable file-level import receipt/terminal status. Current five-table schema has no file-level terminal receipt, so do not wire early chunk commits to approval.
3. Failure after an early chunk transaction in the current R72B repository can leave valid-but-partial rows in a draft. The subsequent workflow must prevent that draft from being validated/queued and have an explicit failed-import/restart/reconciliation path.
4. Additional gates: CSV/XLSX transport validation, RBAC/CSRF, consent, suppression at *send* time, unique campaign IDs, trusted storage, worker idempotency, durable unknown attempts, provider budget/rate enforcement, and separate production migration approval.
5. **All SAFIR production send flags remain OFF**. No production SQL, worker, cron or provider calls are part of R73B.
