# SAFIR final message gate — integration contract (NOT production enabled)

Base: 1de470ebde6a26241aed0da010d9c732e86ef310

## Implemented source
- BaleSafirFinalMessageIdentity::prepare() renders final Unicode text, checks configurable character cap, and returns SHA256 of message plus a provider/phone/message identity.
- BaleSafirMessageIdempotencyRepository::reserve() performs one atomic INSERT against a shared InnoDB primary key; duplicate identity is blocked, other database errors fail closed.
- recordOutcome() records accepted or unknown; reserved/unknown states remain holds rather than silently retrying.
- safir_message_idempotency schema is DESIGN ONLY and has not been deployed.

## Required guarded operational integration
1. Identify the actual SAFIR provider dispatch function and existing campaign/notification DB. Do not replace the successful two-recipient sending path without review.
2. Before approval, render every message with the approved template and canonical fields. Use provider-specific max-character setting. Count over-limit, malformed, duplicate-in-campaign, previously sent and unknown separately.
3. Reconcile legacy notification/provider send records (including the two-recipient pilot) into the ledger or block any overlapping campaign recipients until independently reconciled. No automatic assumption that absence from this new ledger implies never sent.
4. In a reviewed migration create the shared ledger on the actual operational database, after backup/schema ownership/grants gates.
5. Immediately before each provider call, recalculate identity from immutable campaign template + recipient data, then reserve in the ledger; do not call provider unless reservation returned true and its commit is durable. On database exception fail closed. Never retry a duplicate.
6. Recheck suppression, approval, consent, campaign state, owner/RBAC, rate limits and pause before dispatch; reservation is NOT approval.
7. Store actual provider response as accepted or unknown. On timeout/crash keep reservation held. Reconcile unknown manually with provider before any explicit release; never automatically clear ledger entries.
8. Audit two concurrent workers competing for the same identity, restart after reservation-before-send, restart after provider acceptance-before-outcome, cross-campaign repeat, changed text, over-limit Unicode text, and historical send overlap — all on disposable DB, without provider traffic.
9. Recompute approved recipient counts after duplicate suppression; enforce preview/explicit operator approval before first real send.
10. Production deployment, migration, queue enablement, cron activation, and provider send remain disabled until these gates pass.

## Current status
SOURCE_COMPONENTS_ADDED=YES
ATOMIC_LEDGER_DESIGN_ADDED=YES
LIVE_DATABASE_MIGRATION=NO
WORKER_INTEGRATION=NO
PROVIDER_SEND=NO
PRODUCTION_READY=NO
