# SAFIR v2.5.0 — Durable Bulk Campaign Implementation Contract

Status: DEVELOPMENT DESIGN ONLY. No production activation authorized by this file.
Starting Git HEAD: `686f74fc5a143cfc39cbe348bf338d93f2bb497b` (R62 URL fix). Runtime production also contains parallel org-r2a7: preserve concurrent changes during integration/release.

## Purpose
Replace session-coupled 2–3 recipient pilot dispatcher with persistent, administratively controlled campaign jobs importing arbitrary-size logical recipient lists from one or more XLSX/CSV chunks. Campaign size is not hard-capped at 3/5000; bound each upload/chunk by memory/disk limits, and apply adjustable provider pacing, budget and operational safeguards.

## Existing source contract
- `public_html/app/Services/BaleSafirCampaignService.php`:
  - `SESSION_KEY = ipkf_bale_safir_campaign_v1`, all rows stored in Session.
  - `MAX_UPLOAD_BYTES = 2097152`, `MAX_ROWS_DEFAULT = 5000`, max import configured at 50000.
  - `dispatch()` sends max 3 recipients/request, requiring a session-bound test result and a confirmation string.
  - One-recipient API `/api/v3/send_message`, a stable `request_id` per attempt is already supported.
- `public_html/routes/bale-safir-campaigns.php`: CSRF + `notifications.send.manage` gate for provider sends, scoped template maintenance uses `notifications.safir.manage`.
- `public_html/resources/views/admin/partials/bale-safir-campaign.php`: current preview/testing UI, R60/61/62 fixes.
- `IPKF_SHARED_ENV`: canonical persistent configuration, never modify release-local `.env` as a replacement for shared settings.
- Last production pilot was disabled using R59. Existing manual test mode remains a separate permission/flag.

## Required implementation
1. Dedicated persistent store (approved existing core DB schema/transaction support, or separately approved private disk-backed transactional store). NO reusing browser Session as source of truth.
2. Campaign record: immutable ID, actor ID, template ID/code/hash and frozen approved template content, normalized source hash, creation timestamp, status, unique campaign token, total counts, consent/purpose metadata and aggregate audit.
3. Recipients: normalized phone; resolved dynamic template fields, text preview, exact file/row, unique (campaign, phone), state `ready|claimed|sent|failed|unknown|skipped`, immutable request ID, attempt timestamp, HTTP/error/message_id. Do not store API credentials or expose full phones outside authorized scoped views.
4. Streaming chunked import (XLSX and preferably CSV) without loading an entire very large file into PHP memory. Preserve existing mapping and normalization logic. Detect duplicates and blocked/opted-out recipients and show a dry-run summary prior to approval. No fixed **total campaign** count cap; sane file/row/chunk/system resource limits remain mandatory.
5. No direct provider send from browser HTTP handler. Explicit admin approval changes `validated -> queued`. Dedicated CLI/systemd worker processes small batches under a least-privileged user, independent of browser session.
6. Atomic claim/lease with concurrency locking. Persist `unknown` *before* external API; do not automatically retry ambiguous results. Stable `request_id` to leverage provider idempotency. Per-recipient audit and export.
7. Worker must re-check `BALE_SAFIR_SEND_ENABLED` and `BALE_SAFIR_PRODUCTION_APPROVED` before every request; pilot override preserved. Manual test and pilot flags separate. Stop immediately if flags off.
8. Pause/resume/cancel operations, idempotent controls and time-bounded job lease. Restart workers safely after crash. Limit one active campaign per approved sending context unless concurrency policy explicitly expanded.
9. Provider error policies: `RateLimitExceeded(code=3)` pause/backoff; `PaymentRequired(20)` pause entire job; `MaximumContactLimitReached(21)` pause; `NotBaleUser(17)` skipped/failed with clear reason. Never auto-retry `unknown`.
10. Rate limits: configurable pacing and optional daily/campaign cost ceiling; honor provider contract instead of assuming unlimited throughput. Review opt-in/marketing permission and do-not-contact rules before production.
11. UI: `Draft -> Imported/Validated -> Approved -> Queued -> Running -> Paused/Completed/NeedsReview` with progress from durable store. Only `notifications.send.manage` may approve/send; template managers keep editing rights only. CSV reporting remains template scoped; campaign reports individually exportable.
12. Security: CSRF and RBAC on all mutating endpoints, persist outside public web root, strong file permissions, strict upload validation/ZIP bomb limits, formula injection neutralization for CSV, audit events, no credentials in logs. No external CDN.

## Release gates
- READ ONLY baseline & source/path/permission preflight; verify active **composite** runtime release and existing changes.
- Isolated DB/storage backup, tested migrations and rollback before any schema mutation.
- New feature flag `BALE_SAFIR_DURABLE_BULK_ENABLED` defaults OFF. Existing bulk flags OFF during deploy.
- Offline unit/integration tests: 0/1/2/3/3000/50000 recipients, duplicate/malformed/suppression, interrupted worker, wrong template hash, unauthorized actor, expired CSRF, unknown provider response, 429/3, credit 20, 21, parallel claims, repeated request ID.
- Dry-run >= 3000 without provider calls, verify consistent counts and bounded RAM, then staged production with authorized recipients, explicit operator approval and documented budgets.
- Only after all gates pass: guarded production enablement of dedicated bulk flow. Preserve manual test, pilot and all unrelated app releases.
- Recheck downstream delivery and receipt semantics: `sent` means provider accepted/responded with message_id, not guaranteed recipient read.

## Explicit non-goals
- Do not turn on unrestricted send flags solely to bypass current pilot.
- Do not create auto-sending crons without an operator-approved campaign.
- Do not silently replay or auto-retry ambiguous sends.
- Do not claim infinite physical file capacity or unlimited provider quota.
