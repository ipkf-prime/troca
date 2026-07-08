# IPKF Project Context

## Stable Baseline

IPKF v0.1.0 Genesis Core has passed runtime tests on the development hosting environment.

Verified routes:

- `GET /`
- `GET /unknown`
- `GET /health`
- `GET /_diagnostics`

The Genesis runtime is deployed through GitHub and cPanel Git deployment on the `core-v0.1-genesis` branch.

## Stable Baseline

IPKF v0.2.0 Foundation is the current stable foundation baseline.

Focus areas:

- Env and config stability
- Database foundation
- Migration and seeder convention
- Framework-safe runtime migration and seeder verification
- Maintenance-key protection for development migration and seeder entry points
- Service, repository, and model layering
- Diagnostics coverage for foundation status

Stable version: `0.2.0-foundation`

Deployment branch: `foundation-v0.2`

## Stable Baseline

IPKF v0.3.0 Installer is the current stable installer baseline. It introduces a read-only JSON installer entry point and framework-level installer check classes.

Focus areas:

- Installer access rules
- Safe environment and requirement checks
- Safe database readiness checks
- Diagnostics visibility for installer availability
- No writes to `.env`
- No admin users or business tables

Stable version: `0.3.0-installer`

Deployment branch: `installer-v0.3`

## Stable Baseline

IPKF v0.3.1 public site baseline adds a controlled public root mode while the main application is still under development.

Site mode behavior:

- `SITE_MODE=coming_soon` displays the public Persian construction page at `GET /`.
- `SITE_MODE=app` keeps the framework or future application home behavior.

## Stable Baseline

IPKF v0.4.0 Auth/RBAC Schema Foundation adds normalized database structure for identity, user accounts, roles, permissions, scoped access, organization hierarchy, and MFA readiness.

Stable version: `0.4.0-auth-rbac-schema`

Deployment branch: `auth-rbac-schema-v0.4`

Scope:

- Person identity is separated from user login accounts.
- RBAC uses roles, permissions, and role-permission assignments.
- User access is scoped through `user_role_assignments`.
- Organizations are hierarchy-ready without destructive data changes.
- MFA schema is prepared without verification logic.
- No login UI, admin panel, admin user, or business modules are added.

## Active Milestone

IPKF v0.4.1 Auth Session starts the first JSON-first session authentication foundation.

Working version: `0.4.1-auth-session-dev`

Scope:

- Admin user seeding from safe `.env` values.
- Secure password hashing with PHP `PASSWORD_DEFAULT`.
- Session login/logout and current user lookup.
- Basic permission checks through roles and permissions.
- Auth diagnostics without exposing secrets.
- No login UI, full MFA, admin panel, or business modules are added.

## Future Milestones

- MFA
- Admin Panel Shell
- Core documentation
- Auth
- RBAC
- Bot Engine
- CRM
- Automation
- ERP Foundation
