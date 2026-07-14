# Statistical Center Geography Dry-Run Import

## Purpose

The Statistical Center of Iran (SCI) adapter validates supplementary statistical
geography observations without changing canonical geography. The Ministry of
Interior remains authoritative for official administrative hierarchy, official
city existence, and official parent relationships.

The adapter is intentionally staging-only:

- it reads a private UTF-8 CSV;
- stages every nonblank source observation;
- records validation and review issues;
- returns aggregate-only output;
- never writes canonical locations, relations, external mappings, organizations,
  addresses, or match candidates.

Every successful response includes **canonical_write_performed=false**.

## Private source contract

Place the real source file in:

    storage/imports/geography/statistical-center

Real CSV/XLSX/XLS files in that directory are ignored by Git. The protected runner
accepts a basename only, validates the configured extension, MIME type, file size,
private source directory, and SHA-256 hash before parsing. XLSX support remains
false; no spreadsheet dependency is installed by this phase.

The 12 semantically detected UTF-8 columns are:

1. کد استان
2. نام استان
3. کد شهرستان
4. نام شهرستان
5. کد بخش
6. نام بخش
7. کد دهستان/ شهر
8. نام دهستان
9. کد آبادی
10. نام
11. CODEREC
12. DIAG

Header comparison normalizes BOM, whitespace, zero-width characters, Arabic/Persian
letter variants, slash spacing, and digit variants. Original headers and values are
preserved in the private staging payload. Missing required headers fail the batch
before any misleading partial dataset is staged.

## Source identity and hierarchy

All source codes remain strings. Raw values retain leading zeroes such as 00,
0001, and 000268; comparison fields normalize only digit shapes. No component
code is treated as globally unique.

Composite staging keys use the complete available source context:

- province: province code
- county: province + county
- district: province + county + district
- rural/statistical urban unit: province + county + district + source unit
- settlement: complete parent context + settlement code

These private keys support set-based parent and duplicate validation and are not
returned by the endpoint.

## Data-driven CODEREC mappings

The geographic_source_record_type_mappings table defines the active SCI interpretation:

| CODEREC | Source kind | Derived level | Safety rule |
| --- | --- | --- | --- |
| 1 | province_observation | province | statistical observation |
| 2 | county_observation | county | parent scoped by province |
| 3 | district_observation | district | parent scoped by province/county |
| 4 | rural_district_observation | rural_district | source hierarchy only |
| 5 | statistical_urban_unit | none | never auto-match as official city |
| 6 | settlement_observation | settlement | final class remains undecided |
| 8 | diag_classified_settlement_observation | settlement | DIAG remains opaque |

Unknown values are staged as unsupported source observations and reported for
review. They are not discarded.

### CODEREC 5 boundary

CODEREC=5 may describe an official-looking title, a numbered statistical urban
subdivision, or another coded city occurrence. It always produces
STATISTICAL_URBAN_UNIT; numbered titles also produce
NUMBERED_URBAN_SUBDIVISION, while non-numbered titles may produce
POSSIBLE_OFFICIAL_CITY_CANDIDATE. None of these issues confirms a canonical city.

### CODEREC 6, CODEREC 8, and DIAG

Settlement observations remain distinguishable by their source kind and raw
CODEREC. DIAG is stored as an opaque string, included in the checksum, and never
converted to a legal or Persian classification title. Its presence is informational
for CODEREC=8; unexpected combinations are review warnings.

## Validation and performance

The parser is a generator and never loads the full 105,407-row dataset into PHP
memory. Settings and mappings are loaded once. Rows are inserted in bounded chunks,
then indexed set-based SQL validates parents, exact duplicates, conflicting
composite observations, title variation, parent variation, and DIAG variation.

Exact source/hash reruns reuse the completed batch summary. Failed or interrupted
batches are retried after clearing only their own staging rows and issues.

## Development invocation

With APP_DEBUG=true and the exact maintenance key:

    /geography-import.php?key=DEV_MAINTENANCE_KEY&source=iran_statistical_center&file=iran_statistical_center_geography_1403.csv&mode=validate

The response contains aggregate validation counts only. It never exposes source
rows, codes, titles, DIAG values, filenames, paths, full hashes, database IDs, SQL,
or credentials.

## Candidate crosswalk and deferred apply

A future reviewed Ministry-to-SCI crosswalk may propose candidates within a fully
scoped hierarchy. This adapter does not populate match candidates or confirmed
external mappings. Ministry precedence and human review remain mandatory.

The first candidate-only crosswalk is now available as a separate protected run.
It consumes this immutable staging snapshot, applies parent-first full-hierarchy
matching, excludes numbered CODEREC 5 units and settlement-only observations from
official matching, and leaves every result pending. See
`MINISTRY_SCI_GEOGRAPHY_CROSSWALK.md`.
