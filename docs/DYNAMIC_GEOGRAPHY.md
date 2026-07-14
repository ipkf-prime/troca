# IPKF Dynamic Geography

Current milestone: `0.4.6-admin-users-organization-dev`

## Purpose

The dynamic geography foundation provides a canonical, configurable, multi-country hierarchy for people, organizations, access scopes, reporting, and future routing. This phase is schema, diagnostics, and documentation only. It adds no geography UI, commercial dataset, file parser, automatic mapping, or geographic record seeder. A later provenance extension seeds only system reference metadata.

The hierarchy is data-driven. PHP does not assume that every deployment or country has the same administrative levels.

## Existing legacy geography

The current application contains legacy geographic references:

- `persons.province_id` and `persons.city_id`
- `person_addresses.province_id` and `person_addresses.city_id`
- `user_role_assignments.province_id` and `user_role_assignments.city_id`
- optional runtime lookup tables named `provinces` and `cities`

Existing repository code resolves those references only when compatible lookup tables are available. All existing legacy tables, columns, rows, indexes, foreign keys, bot registration behavior, and user-detail behavior remain unchanged.

Legacy values are compatibility fields. This migration does not rename, drop, rewrite, or infer any existing record. In particular, a city is never copied into or displayed as a county.

## Canonical model

### `geographic_level_types`

Defines configurable geographic or administrative levels. Codes, titles, expected parent level, hierarchy order, addressability, selectability, status, and optional metadata are stored as data. Deployments may later define levels such as country, province, county, district, city, rural district, village, neighborhood, or a custom level without adding PHP enums.

### `geographic_relation_types`

Defines configurable relation semantics. The project uses a normalized lookup because the dynamic organization foundation already follows a data-driven relation-type pattern. Future rows may describe administrative parent, containment, reporting region, historical parent, or deployment-specific relationships. This task does not seed those rows.

### `geographic_locations`

Stores canonical geographic entities and references one level type. Titles are human-readable but are not globally unique. Identity may additionally use level-scoped codes or official codes when a controlled dataset provides them.

Optional coordinates use decimal precision. Status, `valid_from`, and `valid_to` preserve inactive and historical locations. Country ISO code and timezone are optional, so the model supports multiple countries without assuming Iranian geography.

### `geographic_location_relations`

Stores dated parent/child relationships between canonical locations. A child may have a primary active administrative parent while alternate and historical relationships remain representable. Service validation must later reject self-relations, detect cycles, and enforce primary-parent rules.

Parent changes are represented by closing one dated relation and opening another. Historical locations and relations are not deleted.

### `geographic_legacy_mappings`

Maps one explicit legacy source/table/record identity to a canonical location. Mapping is never inferred from title alone. Identical city names may exist beneath different parents, so reviewed mappings should rely on trustworthy official codes or controlled administrative datasets.

The migration creates only the mapping structure. It does not populate mappings, guess parents, or generate geographic records.

## Address integration

`person_addresses.geographic_location_id` is an optional reference to the most specific known canonical location. Existing `province_id` and `city_id` values remain untouched, and existing address rows remain valid with a null canonical reference.

Future address writes may retain legacy compatibility values while also storing a reviewed canonical location. Residential and postal geography stays on address records rather than being duplicated directly on `persons`.

Future `organization_addresses` should reuse the same `geographic_location_id`, hierarchy resolver, and shared or scoped address types. It should support country, province, county, district, city, rural district, village, address line, and postal code while showing only levels that genuinely exist.

## Resolution contract

Given one canonical location ID, a future geography service must:

1. Resolve the location and its semantic level type.
2. Walk active primary administrative-parent relations with a strict depth limit.
3. Detect cycles defensively.
4. Return available semantic ancestors such as country, province, county, district, city, rural district, and village.
5. Avoid N+1 queries when resolving lists.

UI output uses semantic Persian titles, never raw IDs. Missing optional levels display `—`; broken references display `نامشخص`. Resolution never guesses by title and never substitutes city for county.

## Future access scopes

A future role assignment may reference a canonical location for country, province, county, city, or branch-service scope. Existing role-assignment schema is unchanged in this task because adding an ambiguous scope column without a complete access contract would be unsafe.

Descendant access must be opt-in and validated against active canonical relations. A scope must not automatically include descendants merely because a location has children.

## Privacy and security

Geographic reference entities are generally non-sensitive. A person's or organization's specific address, postal code, and precise location are sensitive contextual data.

Diagnostics expose schema-readiness booleans only. They never expose location rows, hierarchy contents, record counts, addresses, postal codes, coordinates, or IDs. Future address permissions, masking, audit, and logging controls must remain separate from general geography viewing.

## Migration

Run the protected development migration endpoint after deployment:

`/migrate.php?key=DEV_MAINTENANCE_KEY`

The migration is additive and idempotent. It creates no seed data and performs no automatic legacy mapping.

## Deferred work

- Additional controlled level/relation-type administration tools
- Reviewed official geographic dataset import
- Legacy mapping review tools
- Hierarchy resolver and cycle validation services
- Geographic CRUD and selectors
- Organization addresses
- Geographic RBAC scopes
- Aliases and historical names

## Multi-source provenance extension

The canonical geography model is now complemented by source provenance, immutable snapshots, external coding dictionaries, hierarchy contexts, reviewed mappings, and import staging. These structures do not replace `geographic_locations`, `geographic_location_relations`, or `geographic_legacy_mappings`.

Official, statistical, and operational parent relationships coexist through `geographic_hierarchy_types`. Existing relations remain unclassified compatibility relations until explicitly reviewed; the migration does not guess or backfill their source/hierarchy.

Ministry of Interior data is authoritative for official administrative hierarchy. SCI is supplementary for settlements and statistical geography. Rural Cooperation operational regions remain separate from official administrative geography. See `MULTI_SOURCE_DATA_PROVENANCE.md`, `EXTERNAL_CODING_SYSTEMS.md`, and `GEOGRAPHIC_HIERARCHY_CONTEXTS.md`.
