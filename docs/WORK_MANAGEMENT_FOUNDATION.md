# IPKF Work Management Foundation

Current development version: `0.5.0-work-management-foundation-dev`

- Application key: `work`
- Development host: `work-dev.troca.ir`
- Dedicated connection: `work.primary`
- Runtime route: `/admin/work`

## First operational hierarchy

`Project -> Work -> Milestone -> Task -> Subtask`

The schema includes projects, project members, canonical `work_items`, item assignees, dependencies, checklists, labels, private attachment metadata, comments, and append-only activity events.

Core user and organization identities are stored as stable references. No cross-database foreign keys or cross-database SQL are introduced.

## Runtime dashboard

The Work dashboard reads from the canonical Work schema:

- `work_projects`
- `work_items`
- `work_statuses`

It does not query a non-existent `work_tasks` table. Seed data is minimal and idempotent: one safe Work project, Work statuses, and one root Work item.

## Multi-module launch behavior

The central admin module launcher routes Work links through `ApplicationUrlRegistry::workLaunch()`. Core hosts start module SSO, while the Work host opens `/admin/work` directly after authentication.

The seven main dashboard tiles use fixed non-random colors:

- users: blue
- organization: teal
- system: purple
- work: green
- automation: indigo
- reports: amber
- support: rose

## Deployment

1. Keep server-only values such as `WORK_DB_*`, `WORK_APP_URL`, `ALLOWED_APP_HOSTS`, and `DEV_MAINTENANCE_KEY` outside Git.
2. Deploy branch `v0.5.0-work-management-foundation-dev` through cPanel Git deployment.
3. Verify `work.primary` connection readiness without printing host, database name, username, password, or DSN.
4. Run protected migration with `migrate.php?application=work&key=...`.
5. Run protected seed with `seed.php?application=work&key=...`.
6. Check `https://work-dev.troca.ir/health`, `/_diagnostics`, and `/admin/work`.

## Diagnostics

Development diagnostics expose only safe booleans for Work:

- `work_primary_connection_registered`
- `work_primary_dedicated_connection_configured`
- `work_primary_connection_available`
- `work_primary_utf8mb4_ready`
- `work_primary_utc_timezone_applied`
- `work_schema_available`
- `work_application_migration_history_available`
- `work_management_foundation_available`
- `work_dashboard_runtime_available`

Diagnostics must not expose credentials, DSNs, database names, SQL, table data, row counts, tokens, or maintenance keys.
