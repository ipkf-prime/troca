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

If no automation-specific connection is configured, `automation.primary` falls back to the existing `core.primary` PDO connection. This preserves the current v0.4.7 automation correspondence tables in the current database.

If a partial automation configuration is present, the dedicated definition is considered incomplete and fails safely without exposing credentials or topology details.

## Migration Grouping

Legacy `/migrate.php` remains protected by `APP_DEBUG=true` and `DEV_MAINTENANCE_KEY`, and keeps its existing behavior.

The application-aware migration registry groups migrations by logical application and connection:

- core migrations on `core.primary`
- automation migrations on `automation.primary`

The automation correspondence migration is intentionally registered in the automation catalog while remaining in the legacy migration path during this transitional phase. The application-aware runner uses a separate `application_migrations` history table keyed by application, connection, and migration name.

## Seeder Grouping

The application-aware seeder registry groups metadata seeders by logical application and named connection:

- core metadata seeders on `core.primary`
- automation metadata seeders on `automation.primary`

Legacy `/seed.php` remains protected and keeps current behavior. No operational data is seeded by the multi-database runtime foundation.

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

## Deferred Work

Deferred items include physical automation database creation, storing real automation credentials, moving automation tables, switching automation repositories, deleting legacy automation tables, installer UI, DNS or SSL provisioning, runtime license enforcement, sales and invoicing, correspondence operational services, workflow engine, document generation, QR generation, message broker, and outbox delivery workers.
