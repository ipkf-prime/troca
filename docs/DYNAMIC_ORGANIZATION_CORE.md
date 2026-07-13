# IPKF Dynamic Organization Core

Current version: `0.4.6-admin-users-organization-dev`

## Purpose

The dynamic organization core provides an organization-neutral schema for public, private, nonprofit, cooperative, academic, healthcare, municipal, holding, branch, and customer-defined institutions. Organization categories, relation types, and unit types are data, not fixed PHP enums.

This phase is schema, diagnostics, and documentation only. It adds no UI, CRUD, Automation, governance, ownership, board, signatory-authority, registration, contact, or address workflows.

## Canonical boundaries

- `organizations` stores legal, administrative, commercial, or institutional entities.
- `org_units` stores internal structural units belonging optionally to one organization.
- `positions` stores reusable job-title catalog entries.
- `organization_positions` stores concrete posts or seats inside an organization or unit.
- `organization_appointments` stores a natural person occupying a concrete post for a time period.
- `persons` stores real-world natural people.
- `users` stores authentication accounts only.

Appointments reference `persons`, not `users`. A person can therefore hold an appointment without having a login account. Future legal authority and signatory rules must reference the organization, person, concrete organizational position, and appointment rather than only a title string.

## Existing organization compatibility

The existing `organizations` table remains canonical and is reused without dropping, renaming, or rewriting columns or rows. Legacy fields such as `parent_id`, `org_type_id`, `org_level_id`, and `org_reg_id` remain compatibility fields. New classifications and relationships are represented through optional canonical tables.

`organizations.parent_id` remains available as the legacy or primary-tree compatibility field. It does not replace configurable organization relation types.

No organization is assumed to have a parent, legal form, shareholder, branch, registration record, or internal hierarchy.

## Dynamic classifications

`organization_classification_schemes` defines customer-configurable schemes. `selection_mode` supports `single` and `multiple`; hierarchical, required, and system behavior are stored as data.

`organization_classification_terms` contains terms within a scheme. Terms support optional parent terms, Persian display titles, technical codes unique within a scheme, ordering, status, and optional JSON metadata storage.

`organization_classifications` assigns terms to organizations with optional primary, validity-period, and status fields. A unique organization-term pair prevents accidental duplicate assignments. Single-select and multi-select business validation remains deferred to services.

No organization kinds, ownership sectors, legal forms, activity domains, or institution categories are seeded in this phase.

## Dynamic relationships

`organization_relation_types` defines configurable directional, hierarchical, dated, or percentage-capable relationship types.

`organization_relations` connects source and target organizations with an optional ownership percentage, validity period, primary flag, reference number, and description. Ownership percentages are optional because most relationships are not ownership relationships.

Self-relation validation and cycle prevention for hierarchical relation types are deferred to future services.

## Units, posts, and appointments

`organization_unit_types` provides data-driven unit types. Existing `org_units.type` remains a compatibility field; future UI should prefer the related unit-type title and use the legacy value only as a fallback.

`org_units.organization_id` and `org_units.unit_type_id` are nullable. Existing rows remain valid and are never assigned to guessed organizations.

The existing global `org_units.code` uniqueness is replaced with organization-scoped uniqueness only when migration-time data checks prove that every coded row is organization-scoped and conflict-free. Otherwise the protective global unique index is retained and a non-unique organization/code lookup index is added. No protective index is silently dropped.

`organization_positions` distinguishes a concrete post from its reusable `positions` title. It supports an optional unit, reporting parent, local title override, headcount limit, acting allowance, validity period, and ordering.

`organization_appointments` assigns `persons` to concrete organizational positions. It supports appointment history, primary and acting appointments, validity periods, appointment references, and descriptions. Overlap rules are deferred to future services.

## Diagnostics

When `APP_DEBUG=true`, `/_diagnostics` reports safe schema-only booleans:

- `dynamic_organization_core_available`
- `organization_classification_schema_available`
- `organization_relations_schema_available`
- `organization_unit_types_schema_available`
- `org_units_organization_scope_available`
- `organization_positions_schema_available`
- `organization_appointments_schema_available`

Diagnostics expose no organization data, person data, raw IDs, tokens, credentials, or secrets.

## Migration

In development, run the protected migration endpoint after deployment:

`/migrate.php?key=DEV_MAINTENANCE_KEY`

The migration is additive and idempotent. Existing organizations, units, positions, user assignments, persons, users, and bot registration behavior remain intact. No seeder is required for this phase.

## Deferred work

- Organization CRUD and classification management UI
- Organization contact, address, and registration details
- Governance, board membership, and ownership records
- Authorized signatories and delegated legal authority
- Appointment overlap and hierarchy-cycle validation services
- Automation, correspondence, cartable, referral, and workflow tables

## Future organization addresses

Future organization address records must reuse the canonical dynamic geography model rather than introduce organization-specific province/city hierarchy tables. An organization address should reference the most specific reviewed `geographic_locations` row through `geographic_location_id`, preserve legacy compatibility only when required, and derive available semantic ancestors from active dated relations.

Only genuine hierarchy levels are displayed. City is never substituted for county, and no parent is inferred from a matching title. Address values and postal codes remain sensitive organization data and are excluded from diagnostics.
