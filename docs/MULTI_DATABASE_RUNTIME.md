# IPKF Multi-Database Runtime Foundation

Current development version: `0.4.8-platform-commercial-foundation-dev`

## Purpose

The multi-database runtime foundation adds named logical connections for the platform core and specialized applications. It is backward-compatible with the current single-database deployment and does not move existing automation tables.

## Logical Connection Names

Initial logical names:

- `core.primary`
- `automation.primary`

Future purposes are expected to use the same naming pattern, for example:

- `automation.read_replica`
- `automation.reporting`
- `automation.archive`

Future application codes such as `crm`, `erp`, `hr`, `finance`, `marketplace`, `integration`, and `reporting` can use the same pattern without a schema redesign.

## Ownership

The core database remains authoritative for identity, authentication, MFA, RBAC, persons, organizations, organizational units, positions, appointments, geography, platform registry, application/module catalog, topology metadata, and licensing metadata.

Specialized application databases own operational data. The first specialized connection is `automation.primary`.

## Core versus Automation ownership

Core-owned records must not be referenced from the standalone Automation database through SQL foreign keys, schema-qualified queries, views, triggers, or generated SQL. Automation stores Core-owned identities as scalar reference values only.

Automation-owned records include:

- `lookup_domains` and `lookup_values` for Automation-local metadata.
- `correspondences` and immutable `correspondence_versions`.
- `correspondence_parties`.
- `registry_books` and `correspondence_registrations`.
- `correspondence_relations`.
- `correspondence_referrals`.
- `correspondence_events`.
- `private_files` metadata and `correspondence_attachments`.
- Automation-local `application_migrations`.

Core-owned references include persons, users, organizations, org units, positions, appointments, fiscal years, and geographic locations. Future write services must validate these through the Core application boundary before committing Automation transactions.

## Configuration and Fallback

The existing `DB_*` configuration remains the core connection.

Optional automation connection variables:

- `AUTOMATION_DB_HOST`
- `AUTOMATION_DB_PORT`
- `AUTOMATION_DB_DATABASE`
- `AUTOMATION_DB_USERNAME`
- `AUTOMATION_DB_PASSWORD`
- `AUTOMATION_DB_CHARSET`
- `AUTOMATION_DB_SSL_MODE`
- `AUTOMATION_DB_CONNECTION_TIMEOUT`
- `AUTOMATION_DB_MODE`

If no automation-specific connection is configured, `automation.primary` falls back to the existing `core.primary` PDO connection. This preserves the current v0.4.7 automation correspondence tables in the current database.

If a partial automation configuration is present, the dedicated definition is considered incomplete and fails safely without exposing credentials or topology details.

## Provisioning states

The runtime supports these safe states:

- `fallback`: `automation.primary` shares `core.primary`; current runtime remains unchanged and standalone provisioning is blocked.
- `provisioning`: a dedicated `automation.primary` can be checked, migrated, and seeded while runtime remains on the legacy Core-hosted Automation tables.
- `dedicated`: deferred future state where Automation services may resolve the dedicated database only after readiness checks pass.

`AUTOMATION_DB_MODE` accepts only `fallback`, `provisioning`, or `dedicated`.
Invalid values fail safely. If the variable is absent, the runtime resolves to
`provisioning` when a complete dedicated Automation connection is configured,
and `fallback` when it is not. It never defaults to `dedicated`.

No automatic cutover or rollback exists in this release.

## Dedicated runtime activation

Dedicated runtime activation is explicit:

```text
AUTOMATION_DB_MODE=dedicated
```

Dedicated mode is active only when the cutover guard passes all readiness
conditions:

- dedicated connection configured and available;
- utf8mb4 and UTC database session policy ready;
- standalone schema and metadata available;
- Automation-local migration history available;
- internal Automation foreign keys preserved;
- Core-targeting foreign keys absent;
- cross-database SQL policy clean;
- schema parity contract available;
- legacy operational data absent;
- rollback source retained.

If any condition fails in dedicated mode, the Automation runtime resolver fails
closed and never silently reads or writes the legacy Core Automation tables.
Existing non-Automation routes continue to use Core runtime behavior.

## Split-brain prevention

Automation-owned future repositories must use the Automation runtime connection
resolver instead of calling the global Core PDO directly. The resolver has one
active source per request:

- fallback/provisioning resolves the legacy Core source;
- dedicated resolves `automation.primary` only after the guard passes.

There is no dual-write behavior and no silent fallback from dedicated to Core.

## Explicit rollback

Rollback is manual and configuration-based:

```text
AUTOMATION_DB_MODE=provisioning
```

or, if no dedicated Automation database is intended:

```text
AUTOMATION_DB_MODE=fallback
```

Because no operational Automation writes exist yet, rollback currently requires
no data synchronization. Once operational writes begin, rollback will require a
separately designed reconciliation policy.

## Migration Grouping

Legacy `/migrate.php` remains protected by `APP_DEBUG=true` and `DEV_MAINTENANCE_KEY`, and keeps its existing behavior.

The application-aware migration registry groups migrations by logical application and connection:

- core migrations on `core.primary`
- automation migrations on `automation.primary`

The automation correspondence migration is intentionally registered in the automation catalog while remaining in the legacy migration path during this transitional phase. The application-aware runner uses a separate `application_migrations` history table keyed by application, connection, and migration name.

Standalone Automation provisioning uses:

```text
/migrate.php?key=DEV_MAINTENANCE_KEY&application=automation
```

Rules:

- `APP_DEBUG=true` and a valid maintenance key are required.
- `application` is allowlisted to `core` and `automation`.
- `application=core` is accepted as a no-op compatibility mode in this phase so the legacy default path is not duplicated.
- `application=automation` is allowed in `provisioning` and `dedicated` modes, rejects fallback mode, and requires a dedicated, configured, available, utf8mb4 `automation.primary`.
- The standalone Automation migration profile omits only Core-targeting foreign keys and preserves Automation-internal foreign keys.
- Public failure output exposes only an opaque failure reference.

## Seeder Grouping

The application-aware seeder registry groups metadata seeders by logical application and named connection:

- core metadata seeders on `core.primary`
- automation metadata seeders on `automation.primary`

Legacy `/seed.php` remains protected and keeps current behavior. No operational data is seeded by the multi-database runtime foundation.

Automation metadata seeding uses:

```text
/seed.php?key=DEV_MAINTENANCE_KEY&application=automation
```

The Automation seeder writes only Automation-local lookup metadata. Automation RBAC permissions remain a Core responsibility and are seeded by the Core permissions seeder.

`application=core` is accepted as a no-op compatibility mode in this phase; continue using the legacy default `/seed.php` path for Core seeding until a later explicit release changes that behavior.

## Core reference contract

The lightweight Core reference contract is represented by:

- `CoreReference`
- `CoreReferenceType`
- `CoreReferenceValidator`
- `CoreReferenceSnapshotPolicy`

It supports person, user, organization, org unit, position, appointment, fiscal year, and geographic location references without creating cross-database SQL dependencies.

## Snapshot policy

Future correspondence write services must preserve historical display snapshots where a document needs the value visible at creation or registration time. Required snapshot subjects include organization title, organizational unit title, person display name, position title, external correspondent identity, signer identity and position, and registry owner display information.

## Cutover readiness and rollback

Cutover remains blocked unless all safe readiness checks are true:

- dedicated Automation connection configured and available;
- standalone schema exists;
- Automation metadata exists;
- internal Automation foreign keys are present;
- Core-targeting foreign keys are absent;
- no cross-database SQL policy violation exists;
- legacy Core Automation operational data is absent.

Legacy Core-hosted Automation tables are retained as the rollback source. They are not dropped, renamed, truncated, copied, synchronized, or retired in this phase.

## Transaction Boundaries

A database transaction is valid only inside one logical application database. Future cross-application communication must use application service calls, immutable snapshots, outbox/integration events, or other asynchronous integration patterns.

Distributed transactions are explicitly deferred.

## Safety Rules

- No cross-database foreign keys.
- No SQL that references another database/schema by name.
- No database creation.
- No database-user creation.
- No table copy, move, rename, or deletion.
- No runtime connection switching for existing automation repositories yet.
- No credentials, complete DSNs, hostnames, database names, usernames, secret references, SQL, stack traces, or PDO driver errors in public diagnostics.
- Private failure logs remain outside the public document root.

## Deferred Work

Deferred items include adding real `AUTOMATION_DB_*` values to the repository, creating databases or database users, copying operational data, dual writes, deleting legacy tables, switching existing diagnostics away from Core, operational correspondence services, correspondence UI, Workflow and forms, document generation, PDF/JPG/QR, digital signature, object storage, licensing enforcement, Installer UI, commercial billing, online license server, message broker, and outbox delivery workers.

## Manual deployment sequence

Use real credentials only in the hosting environment, never in git:

1. Set `AUTOMATION_DB_*` values and keep `AUTOMATION_DB_MODE=provisioning`.
2. Deploy the branch and verify safe diagnostics.
3. Run the protected Automation migration endpoint.
4. Run the protected Automation seeder endpoint.
5. Verify `automation_cutover_guard_passed=true`.
6. Change only the hosting `.env` to `AUTOMATION_DB_MODE=dedicated`.
7. Deploy/reload and verify `automation_dedicated_runtime_active=true`.

To roll back before operational writes begin, set `AUTOMATION_DB_MODE=provisioning`
or `fallback` as appropriate and redeploy/reload.
