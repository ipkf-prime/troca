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

## Active Milestone

IPKF v0.3.0 Installer Skeleton introduces a read-only JSON installer entry point and framework-level installer check classes.

Focus areas:

- Installer access rules
- Safe environment and requirement checks
- Safe database readiness checks
- Diagnostics visibility for installer availability
- No writes to `.env`
- No admin users or business tables

## Future Milestones

- Core documentation
- Installer
- Auth
- RBAC
- Bot Engine
- CRM
- Automation
- ERP Foundation
