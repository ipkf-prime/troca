# Multi-Source Data Provenance

Current milestone: `0.4.6-admin-users-organization-dev`

## Snapshot and Import Timestamps

Future source snapshots and import batches must store instant timestamps in UTC and display them through `APP_TIMEZONE`. Source-provided date-only values must remain timezone-neutral until a reviewed importer maps them to canonical fields.

## Purpose

IPKF separates stable canonical entities from observations supplied by external authorities and operational systems. This foundation registers sources, authority scopes, immutable snapshots, external dictionaries, reviewable mappings, and import staging. It imports no source file and writes no canonical geography or organization record.

## Identity boundaries

- A canonical entity is a stable internal IPKF location or organization.
- A data source identifies the authority or information system that supplied an observation.
- A source snapshot identifies one immutable published or observed file/version.
- An external coding system owns one or more named code sets.
- An external code value preserves a code and title exactly as observed in one snapshot.
- A reviewed mapping links an external observation to a canonical entity without replacing either identity.

An external code is never a canonical primary key. Titles are comparison aids only and never establish identity by themselves. One canonical location may retain several identifiers from several sources and versions.

## Registered sources and authority

System metadata registers:

- `iran_ministry_of_interior`: authoritative for official administrative hierarchy, official location status, and official parent relationships.
- `iran_statistical_center`: supplementary authority for statistical geography, villages, settlements, statistical points, and census identifiers.
- `rural_cooperation_statistical_system`: authoritative for its own operational geography, organization codes, and classification codes.

Authority is evaluated per `domain_code` in `data_source_authority_scopes`; a global source priority cannot decide every conflict. Ministry data wins an official-parent conflict. SCI may supplement missing settlement/statistical observations but cannot overwrite an official Ministry parent. Rural Cooperation operational parents remain in their own hierarchy and never replace official provinces or counties.

## Immutable snapshots

`data_source_snapshots` records publication and observation metadata, sanitized filenames, optional SHA-256, schema signature, and import status. Source files must remain outside the public web root. An identical source/file hash pair is not registered twice. Publication date is distinct from legal effective dates on source observations and relations.

New snapshots do not delete or silently invalidate older observations. Removed rows, changed codes, and source disagreements create history or review work; they do not automatically delete canonical records.

## Import and review flow

Future import services follow this sequence:

1. Register an immutable source snapshot.
2. Parse source rows into `geographic_import_rows` without canonical writes.
3. Record validation/conflict details in `geographic_import_issues`.
4. Produce zero or more reviewable match candidates.
5. Preserve external code values exactly, including leading zeroes.
6. Confirm canonical mappings only through explicit matching/review rules.

Title-only matches cannot become confirmed automatically. Ambiguous rows remain reviewable. No import process may fabricate missing parents or silently overwrite a conflicting source observation.

## Source-specific parser contracts

Future Ministry parsers may observe provinces, counties (shahrestan), districts (bakhsh), rural districts (dehestan), and official cities. Hierarchy codes and national identifiers remain separate. Repeated identifiers, missing parents, virtual parents, and administrative changes become review/history events rather than fabricated rows.

Future SCI parsers preserve raw `CODEREC`, DIAG, and other type indicators. `CODEREC=5` does not automatically mean canonical official city; statistical urban subdivisions, villages, settlements, and statistical points remain semantically distinct. Unfamiliar type values are reported rather than guessed, and official-parent conflicts are reviewed against Ministry observations.

Future Rural Cooperation parsers preserve province, county, organization, level, type, and classification codes exactly. A source table named `cities` may semantically represent counties. Operational regions such as South Kerman remain separate from official provinces, and the active bot filtering/selection workflow is never rewritten by an import.

## Security

Source metadata contains no personal data or credentials. Raw payload JSON must exclude credentials and personal information. Diagnostics expose schema booleans only, never filenames, hashes, source records, code values, IDs, names, counts, or server paths. Future import administration requires dedicated permissions and audit controls.

## Deferred work

- File-specific Ministry, SCI, and Rural Cooperation parsers
- Snapshot upload/storage service outside the public web root
- Review and mapping services/UI
- Canonical geography writes after explicit review
- Organization registry staging and external organization mappings
