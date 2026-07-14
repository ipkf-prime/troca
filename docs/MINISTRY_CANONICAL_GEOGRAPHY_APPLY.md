# Ministry Canonical Geography Apply

Current milestone: `0.4.6-admin-users-organization-dev`

## Purpose and authority

This workflow is the first operation allowed to write canonical geography. The
Iran Ministry of Interior is authoritative for current official country,
province, county, district, rural-district, city, and administrative-parent
observations. SCI remains supplementary and Rural Cooperation geography remains
operationally separate.

Ministry hierarchy code is the authoritative source-observation identity in one
snapshot. National location identifiers are supplementary, may repeat, and never
merge locations. Titles are display/comparison values only and cannot establish
canonical identity.

## Audit model

`geographic_canonicalization_runs` records the immutable plan boundary: source,
snapshot, batch, algorithm, reference, source/plan fingerprints, status, aggregate
counts, and timestamps. The unique boundary is source snapshot,
`ministry_official_administrative`, and `ministry-canonical-v1`.

`geographic_canonicalization_items` stores one classification per Ministry staging
row. Actions are `create`, `reuse`, `conflict`, `exclude`, or `no_change`.
Conflicts remain `review_required`; invalid rows are excluded; successfully
applied rows retain the resulting canonical and parent references for audit and
safe resume.

Source staging rows are never edited by planning or applying.

## Protected two-phase workflow

Both modes require development/debug mode and the exact
`DEV_MAINTENANCE_KEY`.

Run the protected migration endpoint after deployment before using apply or
status mode so the additive failure-telemetry columns are available.

Plan:

    /geography-canonicalize.php?key=DEV_MAINTENANCE_KEY&source_batch=MOI-865CA310FC55&mode=plan

Plan performs no canonical write. It validates the completed batch and snapshot,
classifies every row, records an immutable plan, and returns aggregate counts,
`plan_reference`, and a 16-character fingerprint prefix.

Apply:

    /geography-canonicalize.php?key=DEV_MAINTENANCE_KEY&source_batch=MOI-865CA310FC55&mode=apply&plan_reference=CAN-XXXXXXXXXXXX&fingerprint=0123456789ABCDEF

Apply requires the exact batch, completed plan reference, and fingerprint prefix.
It rechecks source row fingerprints and current canonical state before the first
write. A stale plan becomes unusable and must be rebuilt. Responses never expose
full hashes, source codes, identifiers, titles, database IDs, raw issues, SQL,
paths, or secrets.

## Eligibility and deterministic reuse

Valid and warning rows with supported levels, unique numeric hierarchy codes, and
compatible parents are eligible. Missing codes/parents, unsupported levels,
structurally invalid codes, duplicate hierarchy codes, and invalid staging rows
are excluded.

Canonical reuse is allowed only when:

1. a confirmed Ministry hierarchy-code mapping for the exact snapshot resolves
   uniquely; or
2. an active Ministry hierarchy-code identifier for the exact snapshot resolves
   uniquely; or
3. the same canonicalization run already applied the item.

Title-only, normalized-title, national-identifier, parent-title, and first-result
matching are prohibited. A title-only candidate becomes `review_required`.
Conflicting trusted mappings, incompatible levels, or a different active official
parent are never overwritten.

## Country and parent-first application

One unambiguous Iran root is reused or safely created with country code `IR`.
Multiple compatible Iran roots stop apply for review. Ministry rows are processed
in strict order:

1. province -> Iran
2. county -> province
3. district -> county
4. rural district -> district
5. city -> district

Only `official_administrative` relations using the canonical
`administrative_parent` relation type are written. Statistical, operational,
custom, and historical relations are untouched. Missing applied parents block the
child and therefore its descendants; no parent is fabricated.

## Codes, identifiers, and mappings

Each successfully applied hierarchy code becomes a versioned
`administrative_location_code` value linked to the exact Ministry snapshot. Codes
remain strings, preserve leading zeroes, and retain their source parent code-value
relationship.

The exact code value receives one confirmed mapping to the canonical location with
match method `authoritative_source_apply`. Each location receives a primary
`ministry_hierarchy_code` identifier. A present national identifier is stored as
secondary `ministry_national_identifier`; the same value may safely belong to
multiple locations.

No SCI external code value or mapping is created by this workflow.

## Recovery and idempotency

Apply uses parent-first chunks of 250 rows bounded to one level. Province, county,
district, rural district, and city chunks are never mixed. A level must finish
without pending safe items or unresolved parents before the next level starts.
Excluded and conflict items are not loaded into apply chunks.

Completed item status survives later chunk failures. A failed run may be retried
with the same source batch, plan reference, and fingerprint. Applied items and an
existing Iran root are reused; exact relations, identifiers, code values, and
mappings are not duplicated. Run counters are reconciled from committed database
state instead of incremented again during retry. A run reports `applied` only when
every eligible safe item is applied and no conflict remains.

Each failure receives an opaque `failure_reference` and a safe operation stage.
The public response is aggregate-only. The original exception chain is written to
the private `storage/logs/ministry-canonicalization.log`; database diagnostics,
source values, SQL details, paths, and identifiers are never returned over HTTP.

Recovery status can be inspected without a fingerprint:

    /geography-canonicalize.php?key=DEV_MAINTENANCE_KEY&source_batch=MOI-865CA310FC55&mode=status&plan_reference=CAN-F0637B652432

Status mode still requires development/debug mode, the maintenance key, the exact
source batch, and the exact plan reference. It returns aggregate item/artifact
counts, the latest opaque failure reference/stage, and whether same-plan resume is
safe.

This workflow never deletes, deactivates, expires, reparents, or infers abolition
from absence in a snapshot. Snapshot diff, historical succession, and legal
effective dates are separate future work.

## Verified source contract

For the unchanged batch `MOI-865CA310FC55`, planning is expected to classify
6,617 eligible rows and 2 excluded rows. These values are verification targets,
not hardcoded production rules.

Synthetic tests use no production records and cover parent order, missing code,
missing parent, repeated national identifiers, trusted mapping reuse, title-only
blocking, deterministic fingerprints, first-chunk rollback, private failure
references, public error privacy, same-plan retry, level-bounded chunks, country
reuse, counter reconciliation, and repeated apply idempotency.

## Explicit exclusions

This phase does not apply SCI settlements or city candidates, alter the SCI
crosswalk, import Rural Cooperation/bot geography, import organizations, create
South Kerman as an official province, add CRUD UI, add Automation, or modify
Auth/RBAC/MFA.
