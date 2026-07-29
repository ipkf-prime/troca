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


## Shared Admin Shell Contract

Work admin pages must render through the shared Admin shell at `resources/views/admin/layout.php`.
Module views provide only page content, module identity, safe module asset paths, and navigation context.
The shared shell owns the full HTML document, UTF-8 charset, Persian RTL attributes, common navigation, theme variables, `admin.css`, `icons.css`, `admin.js`, and local Vazirmatn font loading.

`AdminModuleUiContract` formalizes this contract for Work and future modules. Module-specific CSS or JS must stay under `/assets/admin/` and must not use absolute domains, protocol URLs, parent-directory traversal, or non-admin asset paths.

Runtime checks for Work shell deployment:

- `GET /admin/work` must not return the generic `404 - Route not found` response.
- `HEAD /admin/work` must resolve through the same GET route and return headers without a response body.
- Authenticated `/admin/work` HTML must contain `data-admin-shell-kind="work"` and `data-admin-module-ui-contract="shared-admin-shell"`.
- The response must include `<meta charset="UTF-8">` and `<html lang="fa" dir="rtl">`.
- Shared assets must load from `/assets/admin/css/admin.css`, `/assets/admin/css/icons.css`, `/assets/admin/js/admin.js`, and local Vazirmatn webfonts.
- Runtime files must not hardcode `work-dev.troca.ir` or any deployment hostname.
- Persian text must be stored and served as UTF-8; mojibake markers such as `Ã`, `Â`, or replacement characters indicate a regression.

Server-only configuration such as `IPKF_SHARED_ENV=/home/troca/config/ipkf-development.env`, `IPKF_MODULE=work`, `WORK_APP_URL`, and `WORK_DB_*` remains outside Git.

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
