# IPKF v0.4.0 Auth/RBAC Schema Checklist

Version: `0.4.0-auth-rbac-schema`

## Verified Checks

- Auth/RBAC migrations run successfully.
- Auth/RBAC seeders run successfully.
- `persons` table exists.
- `users` table exists.
- `role_areas` table exists.
- `role_kinds` table exists.
- `roles` table exists.
- `permissions` table exists.
- `role_permissions` table exists.
- `user_role_assignments` table exists.
- MFA schema tables exist.
- Organization hierarchy columns exist.
- Existing organizations data is not deleted.
- Seeders are idempotent.
- No admin user is created yet.
- No login UI is added yet.

## Runtime Checks

- `GET /` still works.
- `GET /unknown` still works.
- `GET /health` returns `0.4.0-auth-rbac-schema`.
- `GET /_diagnostics` reports Auth/RBAC/MFA schema availability in development.
- `GET /install.php` works in development.

## Release Boundaries

- Login UI is not part of this release.
- Admin panel UI is not part of this release.
- Admin user creation is not part of this release.
- Bot, CRM, ERP, Automation, and Marketplace modules are not part of this release.
- No secrets are exposed in diagnostics.
