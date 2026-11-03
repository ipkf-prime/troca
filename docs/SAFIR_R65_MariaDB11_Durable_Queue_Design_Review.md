# SAFIR R65 — MariaDB 11 durable outbound campaign queue (schema review)

STATUS=DESIGN_ONLY; PRODUCTION_SCHEMA_MUTATION=NO; SEND_ENABLE=NO; PROVIDER_SEND=NO.

Validated runtime evidence (R64, Nov 3 2026): MariaDB 11; PDO MySQL present; existing users, notification_outbox, notification_events and notification_templates tables use InnoDB. No SAFIR campaign queue tables existed. Current production bulk+pilot flags were OFF; manual-test flags are separate. Existing deployed app also contains org-r2a7, SAFIR R60/R61/R62.

Proposed five-table schema from `SAFIR_R65_Durable_Campaign_MariaDB11_Design_ONLY.sql` (SHA256 `819be1e64a383e2591cfab754015ad39d3f80e2366d7cde1909eadea4923719a`):

1. `safir_campaigns`: unique campaign reference, operator/approval, immutable template snapshot and hash, state, interval/budget, timestamps.
2. `safir_campaign_import_batches`: multiple uploaded chunks per campaign with unique source hash.
3. `safir_campaign_recipients`: unique normalized phone **within campaign**, source batch and row, frozen variable values, durable state, provider message and unique request ID; all phones protected in the database and masked in views.
4. `safir_campaign_send_attempts`: immutable reservation/attempt tracking, unique request ID and attempt number, provider response and ambiguous outcome.
5. `safir_suppressed_phones`: opt-out/do-not-contact list, enforced before import approval and each send.

Foreign keys reference only new queue tables, not `users.id` until the actual type, referential policy and permissions have been verified. All five use InnoDB. No existing notification tables are changed. No deletion cascades are proposed; retain historical receipts unless a controlled retention workflow exists.

Worker behavior REQUIRED BEFORE PRODUCTION MUTATION: an operator must approve validated content; create `unknown` reservation in committed transaction BEFORE HTTP call; never automatically replay unknown sends; use the provider request ID only if its actual idempotency contract is verified, not assumed. A worker rechecks master flags, campaign state, suppression and daily/provider limits for every send, with atomic claim/locks and explicit stop/resume. User interface is an observer only; no browser-loop send. Rate-limit, credit and contact-cap failures pause or stop. A user with `notifications.safir.manage` alone cannot transmit.

Capacity: no hard total campaign size cap; *bounded per-upload chunks* and streaming XML/CSV parser remain necessary. Existing upload 2 MiB, configured 5000 rows and session-held campaign are NOT production-grade at scale. The proposed schema alone does not implement the new import or worker and does not authorize enabling bulk flags.

R65 read-only preflight script: `SAFIR_S36_R65_Queue_Schema_Guarded_ReadOnly_Preflight.sh` (SHA256 `bffd4f4d02fbdbdf067ae86553711c795e142ecf0a6637e12c5f0e464b062e00`). It checks all flags off, DB metadata and schema collisions, InnoDB, migrations registry, and read-only transaction. It deliberately never executes the attached SQL.

Release gates: verify a recent restorable MySQL backup, an isolated migration rehearsal, DDL structural testing against MariaDB 11, separate least-privilege worker access, then guarded production schema migration. The worker must be tested with no external calls, failure injections, duplicates, accidental retries, 3000+ records, and provider rate/budget stops. Feature flag defaults OFF. Do not mutate the live DB or enable provider sends at R65.
