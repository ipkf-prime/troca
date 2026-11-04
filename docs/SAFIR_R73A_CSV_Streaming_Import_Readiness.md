# SAFIR R73A — bounded streaming CSV parser (source-only)

Status: NOT DEPLOYED; no DB migration, no routes, no worker, no provider sending.

Verified gate from operator on 2026-11-04:
- R72C isolated real PDO and five-table DDL rehearsal completed `R72C=PASS`, MariaDB second instance stopped, R67/R68 original backups unchanged.
- Production DDL remains unapproved, send flags remain OFF.

## Artifacts
- `public_html/app/Services/BaleSafirDurableCsvChunkReader.php`, commit `7dda3a5ee61102d04692425b70911c52828db69d`, SHA256 `0ba5951e276de57046f5668aca4a43ac567cc71bfbfb5db6914c71d7b8a74f05`.
- `tools/safir/SAFIR_R73A_Streaming_CSV_StandaloneTests.php`, commit `8930c951101826c886720b597a70968dfb7fb0ee`, SHA256 `efd18fb7912aa9238815b674a2f47c4cd72c124ab7d18bef659dbbcab9b7002b`.

## Contract
- Input: opened binary readable stream at start, frozen R72A provider-approved dynamic template snapshot.
- Parser reads small blocks and returns a PHP Generator of bounded chunks; default max 500 rows/chunk, no hard total campaign size limit.
- CSV delimiters comma or semicolon; quoted delimiters, doubled quotes, quoted multiline fields, CRLF/LF, UTF-8 BOM and Persian headers.
- Maximum per record 64 KiB; per field 8 KiB; max 64 columns; header max 16 KiB; all columns must match the header count; duplicate/invalid/missing mapped headers rejected.
- Per-chunk SHA256 is based on domain separator, raw header bytes, chunk ordinal and raw CSV record bytes, not on a mutable browser session.
- Logical CSV record number (including header) is preserved as row number; multiline quoted fields do not increment logical row.
- Caller may call `BaleSafirDurableCampaignPolicy::planChunk` then `BaleSafirDurableCampaignRepository::importChunk` AFTER completing upload and authorization checks, but this integration is intentionally **not wired** yet.

## Critical continuation constraints
- A yielded chunk does NOT establish that the full file is valid. An error after an earlier yielded chunk can leave partial imported batches if caller commits immediately. Future import controller must persist an import session with a final verified whole-file receipt or prevalidate the complete file before committing anything, and MUST prevent approval/queueing until finalized.
- This parser does not validate the upload transport, MIME/type, declared content hash, user authorization, storage location, campaign ownership, suppression during send, or provider policy. These are distinct required gates.
- Current repository imports only into state=draft. No approval/queue/worker exists.
- XLSX streaming remains a separate deliverable; CSV support alone does not replace existing XLSX.
- No unrestricted send flags, cron, production SQL, or live provider requests.
