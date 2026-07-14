# Ministry to SCI Geography Crosswalk Candidates

## Purpose and authority

This phase builds reviewable candidate pairs between completed Ministry of Interior
and Statistical Center of Iran staging snapshots. Ministry observations remain
authoritative for official province, county, district, rural district, city, and
official administrative parent relationships. SCI remains supplementary.

The crosswalk never creates canonical geography and never creates confirmed
external mappings. It writes only:

- versioned crosswalk run metadata;
- pending candidate/classification records;
- crosswalk issues;
- aggregate summaries.

Source staging rows and snapshots remain unchanged.

## Direction

The source direction is:

    SCI observation -> Ministry authoritative observation

Automatic candidate comparison applies to SCI province, county, district, and rural
district observations. Statistical urban units may produce official-city review
candidates under stricter policy. Settlement observations do not trigger Ministry
candidate searches.

## Parent-first algorithm

Algorithm version **ministry-sci-v1** processes:

1. province
2. county
3. district
4. rural district
5. statistical urban unit versus Ministry city
6. settlement/unsupported classification

Province matching requires one normalized title. Every lower level requires:

- a deterministic parent candidate from the prior level;
- a compatible Ministry child under that exact Ministry parent row;
- identical normalized title bytes;
- compatible source and target levels.

The engine never searches globally by title. Component codes are not considered
globally unique. Repeated Ministry national identifiers are not used as direct
identity; candidates reference exact Ministry staging rows.

## Candidate meanings

- **exact**: one deterministic pair, exact normalized hierarchy, and exact source
  title bytes. This is still pending review, not a confirmed mapping.
- **probable**: one deterministic pair after safe Persian character, digit, spacing,
  or half-space normalization, or a deterministic child under a probable parent.
- **ambiguous**: multiple compatible Ministry rows or an unresolved parent
  candidate. No first row is selected.
- **unmatched**: an eligible observation has no compatible Ministry target after
  parent-first filtering.
- **excluded**: the row is outside Ministry crosswalk scope or structurally invalid.

All candidate records use review status **pending**.

## Statistical urban unit guard

CODEREC 5 remains **statistical_urban_unit**:

- a title ending in normalized digits is excluded with
  NUMBERED_URBAN_UNIT_EXCLUDED;
- a non-numbered unit is compared only with Ministry city rows under a compatible
  district;
- a unique pair is probable, never automatically confirmed;
- multiple compatible cities remain ambiguous;
- no candidate creates or implies a canonical city.

## Settlement preservation

CODEREC 6 and CODEREC 8 rows are classified as excluded from the Ministry
crosswalk with SETTLEMENT_NOT_IN_MINISTRY_SCOPE. This avoids a 99,000-row
cross-product while preserving every SCI staging observation for a future settlement
phase. DIAG remains opaque and is never interpreted by the crosswalk.

## Versioning and idempotency

A run is uniquely protected by:

- SCI source snapshot;
- Ministry target snapshot;
- crosswalk type;
- algorithm version.

Repeating a completed combination returns its existing aggregate summary. A failed
or interrupted run may clear and rebuild only its own pending generated candidates
and issues. Any non-pending reviewed result prevents automatic replacement. A new
algorithm version creates a new historical run.

## Performance

Candidate generation is SQL set-based and level-scoped. Indexed batch, level,
source-kind, normalized-title, source-composite, and parent-composite fields avoid
PHP nested loops and N+1 queries. A bounded temporary pair table is reused per level.
Settlement exclusions are inserted directly without target comparison.

## Protected development runner

Requirements:

- APP_DEBUG=true
- exact DEV_MAINTENANCE_KEY
- exact SCI and Ministry batch references
- mode=build-candidates

Example:

    /geography-crosswalk.php?key=DEV_MAINTENANCE_KEY&source_batch=SCI-DA96E2A10368&target_batch=MOI-865CA310FC55&mode=build-candidates

The response is aggregate-only. It may show run reference, algorithm version, input
batch references, status counts, source-kind counts, and reason counts. It never
shows candidate pairs, titles, codes, rows, database IDs, paths, SQL, or credentials.

Every response states:

- canonical_write_performed=false
- confirmed_mapping_write_performed=false

## Deferred review and apply

Future work may add permission-protected human review, audit history, rejection,
supersession, and an explicit apply phase. That future phase must revalidate Ministry
authority and source snapshots before any canonical or external mapping action.
There is no UI, confirmation, or apply operation in this release.
