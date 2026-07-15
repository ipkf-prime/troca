# Ministry Geography Dry-Run Import

Current milestone: `0.4.6-admin-users-organization`

## Purpose

The Ministry of Interior adapter validates and stages one authoritative administrative-division snapshot without changing canonical geography. It writes only source snapshot, import batch, staging row, import issue, and source mapping/configuration metadata.

It never writes `geographic_locations`, `geographic_location_relations`, external-to-canonical mappings, person addresses, organization geography, or match candidates.

## Private Input

Place a source file manually under the application-private directory:

`storage/imports/geography/ministry`

The directory is outside the `/public` document root. Dataset files are ignored by Git. The maintenance runner accepts a basename only, rejects path separators and traversal, validates extension, MIME where available, configured size limit, and computes SHA-256 before parsing. It does not upload files and never returns physical paths.

CSV must be UTF-8. CSV is the supported format in this release. XLSX is deliberately unavailable because PhpSpreadsheet is not installed and the repository has no reviewed Composer lock/deployment path for that dependency. Diagnostics therefore report `ministry_geography_xlsx_parser_available=false`.

## Header Contract

Columns are detected semantically, not by position. Normalized aliases cover:

- `ردیف`
- `نوع`
- `نام مصوب`
- `استان`
- `شهرستان`
- `بخش`
- `کد سلسله‌مراتبی`
- `شناسه ملی`
- optional `توضیح` or `یادداشت`

Arabic/Persian forms of `ی` and `ک`, digits, whitespace, byte-order marks, and safe zero-width variants are normalized for comparison. Original titles, codes, identifiers, notes, and raw row values remain preserved in staging.

## Data-Driven Levels

`geographic_source_level_mappings` is the source of truth:

| Ministry value | Level | Code length | Parent prefix | Parent level |
| --- | --- | ---: | ---: | --- |
| `استان` | `province` | 2 | none | configured Iran root |
| `شهرستان` | `county` | 4 | 2 | `province` |
| `بخش` | `district` | 6 | 4 | `county` |
| `دهستان` | `rural_district` | 8 | 6 | `district` |
| `شهر` | `city` | 9 | 6 | `district` |

Source labels stay Persian. PHP has no fixed source-level enum and does not assume the source will always contain exactly five levels.

Parent identity is derived from code metadata. Descriptive province/county/district columns may support review but never override the code-derived parent. A missing or virtual parent becomes an issue and is never fabricated. City is never substituted for county.

## Source-Specific Placeholders

`data_source_import_settings` configures source-specific behavior. For the Ministry source, descriptive value `11` is interpreted as null while its raw value is preserved. It never creates a location titled `11`. Numeric titles are not treated as placeholders globally.

## Validation and Review

Codes and identifiers remain strings and retain leading zeroes. Validation records missing, malformed, wrong-length, duplicate, and orphaned hierarchy codes. Every nonblank row is staged; blank formatting rows are counted in the summary.

National identifiers are separate from hierarchy codes and are not globally unique. Repetition creates `DUPLICATE_NATIONAL_IDENTIFIER`; differing titles create `IDENTIFIER_TITLE_VARIATION`; differing derived parents create `IDENTIFIER_PARENT_VARIATION`. Rows are never merged and a current observation is never selected automatically.

Rows finish as `valid`, `warning`, or `invalid`. A clean batch becomes `validated`; a parsed batch with row issues becomes `ready_for_review`; structural validation failures and parser/system failures remain distinct. There is no apply state.

## Snapshot Idempotency

SHA-256 identifies an immutable source snapshot. Revalidating an identical completed file reuses its snapshot and safe summary without duplicating staging rows. Interrupted or failed batches reuse and clear only their own staging rows/issues before retry. Import timestamps use the shared UTC `Clock`.

## Maintenance Endpoint

In development, after protected migration and seed, run:

`/geography-import.php?key=DEV_MAINTENANCE_KEY&source=iran_ministry_of_interior&file=SAFE_BASENAME.csv&mode=validate`

The endpoint requires `APP_DEBUG=true` and a non-default valid maintenance key. Only `mode=validate` exists. The response contains aggregate status/counts and a hash prefix only; it never contains source records, names, identifiers, full codes, full hashes, filenames, paths, database IDs, SQL, or credentials.

## Verification Fixture

`tests/fixtures/ministry-geography-validation.csv` is synthetic and covers every configured level, placeholder handling, Arabic/Persian normalization, Persian digits, leading zeroes, missing/duplicate/invalid codes, missing parents, identifier repetition, title variation, parent variation, and deterministic repeated validation. It contains no real source or sensitive data.

## Candidate crosswalk

The Ministry staging snapshot can now serve as the authoritative target of a
separate SCI candidate-only crosswalk. The crosswalk references exact Ministry
staging rows, never treats repeated national identifiers as globally unique, and
never modifies this batch. All results remain pending review. See
`MINISTRY_SCI_GEOGRAPHY_CROSSWALK.md`.

## Canonical apply

The completed Ministry batch can now produce an immutable canonicalization plan.
Only a second protected request containing the exact plan reference and fingerprint
prefix can apply eligible observations. Hierarchy code, not national identifier or
title, controls source identity. Apply is parent-first, official-hierarchy-only,
audited, resumable, and non-destructive. See
`MINISTRY_CANONICAL_GEOGRAPHY_APPLY.md`.

## Deferred Adapters

Rural Cooperation/bot parsing, canonical review/apply, self-hosted mapping UI,
physical sites, facilities, organization geography, South Kerman, and map UI remain
deferred.
