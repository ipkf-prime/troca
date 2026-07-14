# IPKF Project Context

## Datetime Timezone Contract

The admin runtime now uses a documented UTC persistence and single-pass display conversion contract. MySQL sessions are set to UTC, `APP_TIMEZONE=Asia/Tehran` is display-only, and Jalali formatting happens after timezone resolution. See `docs/DATETIME_TIMEZONE.md`.

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

## Stable Baseline

IPKF v0.4.1 Auth Session starts the first JSON-first session authentication foundation.

Stable version: `0.4.1-auth-session`

Deployment branch: `auth-session-v0.4.1`

Scope:

- Admin user seeding from safe `.env` values.
- Secure password hashing with PHP `PASSWORD_DEFAULT`.
- Session login/logout and current user lookup.
- Basic permission checks through roles and permissions.
- Auth diagnostics without exposing secrets.
- No login UI, full MFA, admin panel, or business modules are added.

## Stable Baseline

IPKF v0.4.3 Identity Access Tokens extends MFA Foundation with identity verification, one-time login tokens, MFA delivery channel foundations, and active access switching.

Stable version: `0.4.3-identity-access`

Development branch: `v0.4.3-identity-access-dev`

Legacy branch retained during cPanel transition: `identity-access-tokens-v0.4.3`

Scope:

- Login works with email, mobile, and username.
- CSRF and unified `AUTH_SESSION_NAME` session handling are verified.
- TOTP MFA, recovery codes, and MFA status are verified.
- MFA delivery channel registry for email, sms, and bot.
- Unconfigured email, sms, and bot providers return `channel_not_configured` without fake success.
- One-time login token foundation.
- Login token URLs use `APP_URL`, display time uses `APP_TIMEZONE`, tokens are single-use, and token login respects MFA.
- Identity change request and verification foundation with development-only token exposure, unchanged/duplicate validation, pending duplicate protection, and atomic confirm.
- Username policy is enforced for identity changes and canonical username normalization.
- Active role/access assignment switching.
- Default lowest-privilege access selection.
- `/admin-check` fails with the base user role and passes after switching to `super_admin`.
- No login UI, admin panel UI, Bot, CRM, ERP, Automation, or Marketplace modules are added.

Branch naming convention:

- Development branches start with the version and end with `-dev`, such as `v0.4.3-identity-access-dev`.
- Release tags use the stable version name without `-dev`, such as `v0.4.3-identity-access`.

## Stable Baseline

IPKF v0.4.4 Admin Panel Shell starts the first server-rendered RTL Persian admin UI on top of the existing Auth, MFA, RBAC, and active access foundations.

Stable version: `0.4.4-admin-panel-shell`

Development branch: `v0.4.4-admin-panel-shell-dev`

Scope:

- Admin login page using the existing AuthService login resolution for email, mobile, and username.
- MFA verification page using the existing MfaService pending challenge flow.
- Dashboard shell as a module launcher; account status cards and assignment summary are kept out of the dashboard.
- Self-service access switch UI under `/admin/profile/access` using the existing AccessService.
- Profile display shell with safe user identity fields.
- Built-in admin theme preset selection, system/personal theme scope, and `/admin/theme`.
- Reusable RTL admin layout and local CSS.
- Public landing page for `/`.
- Responsive mobile sidebar and autofocus behavior for admin forms.
- No CRM, Bot, ERP, Automation, Marketplace, admin CRUD, or business modules are added.

## Stable Baseline

IPKF v0.4.5 Admin Navigation RBAC adds permission-based admin navigation and route guards.

Stable version: `0.4.5-admin-navigation-rbac`

Development branch: `v0.4.5-admin-navigation-rbac`

Scope:

- Central admin route permission map.
- Sidebar menu visibility based on current active role permissions.
- User/account dropdown filtering based on current active role permissions.
- Direct URL route guards with clean Persian 403 responses.
- Active role/access assignment compatibility.
- Idempotent seeding for admin navigation permissions.
- Safe APP_DEBUG-only navigation diagnostics.
- No Automation, CRM, ERP, Bot, Marketplace, or business module is added.

## Active Milestone

IPKF v0.4.6 Admin Users Organization starts the schema foundation required before Automation.

Working version: `0.4.6-admin-users-organization-dev`

Development branch: `v0.4.6-admin-users-organization-dev`

Scope:

- `org_units` table for internal units and departments.
- `positions` table for job positions and titles.
- `user_org_assignments` table linking users to units and positions.
- Safe diagnostics for the organization schema foundation.
- No Automation tables, correspondence tables, UI, CRM, ERP, Bot, or business workflows are added.

Extended person data foundation:

- `person_profiles` adds optional one-to-one complementary identity attributes while existing `persons` fields remain canonical.
- `contact_types` and `address_types` provide configurable semantic lookups.
- `person_contacts` and `person_addresses` support multiple optional records per person.
- Existing authentication behavior and `users` schema remain unchanged.
- Sensitive person values are excluded from diagnostics and require masking, permissions, and auditing in future UI work.
- Forms and CRUD remain deferred.

Dynamic organization foundation now includes:

- Configurable organization classification schemes, terms, and assignments.
- Configurable organization relation types and dated organization relationships.
- Data-driven organization unit types and optional organization scope for `org_units`.
- Concrete `organization_positions` built from the reusable `positions` catalog.
- Person-based `organization_appointments` that do not require a user account.
- Additive compatibility with existing `organizations` data and legacy hierarchy/type fields.

This remains schema-only. Organization UI/CRUD, registration/contact/address details, governance, ownership, signatory authority, and Automation are deferred.

Admin entity detail workspace foundation now includes:

- Reusable compact entity detail header and route-based tabs.
- Refactored user detail routes for overview, identity, contacts, account/security, access, and appointments.
- Tab-specific data loading for admin user detail.
- Mobile section navigation without a horizontally scrolling tab bar.
- Future reuse path for organization, unit, position, correspondence, and other entity detail pages.

The workspace has been polished for compact desktop/mobile display: row numbers replace raw user IDs in the users list, mobile field lists are compact, optional empty identity fields are hidden in read-only mode, technical schema names are hidden from appointments UI, and dashboard incomplete module rows are centered.

Dynamic geographic hierarchy foundation now includes:

- Configurable `geographic_level_types` for multi-country and deployment-defined levels.
- Data-driven `geographic_relation_types` and dated `geographic_location_relations`.
- Canonical `geographic_locations` without title-only identity assumptions.
- Explicit `geographic_legacy_mappings` with no automatic title matching.
- Optional `person_addresses.geographic_location_id` while legacy province/city compatibility remains intact.
- Historical status and validity support for renamed locations, boundary changes, and parent changes.
- A documented resolver contract that never substitutes city for county.
- Future reuse by organization addresses, reports, access scopes, and routing without implementing those features yet.

The original foundation remains additive and metadata-driven. Geographic records
and reviewed Ministry mappings can now be created only by the separate protected
canonical plan/apply workflow. No public CRUD UI, organization address,
Auth/RBAC change, or Automation behavior is added.

Multi-source provenance foundation now adds:

- Data-driven source registry and domain-specific authority scopes for Ministry of Interior, SCI, and Rural Cooperation operational data.
- Immutable source snapshot metadata without storing files in the public web root.
- External coding systems, code sets, fixed-width segment definitions, and version-aware code values.
- Explicit official, statistical, operational, and custom geographic hierarchy contexts.
- Nullable source/hierarchy/review context on canonical geographic relations without backfill.
- Multiple external identifiers and reviewable external-to-canonical mapping history.
- Generic geography import staging that never writes canonical locations automatically.
- Rural Cooperation 3/5/8-character string-code contract while preserving all active bot data and behavior.

This extension imports no Ministry, SCI, bot, geographic, organization, or source-file records. South Kerman remains a documented operational-region use case and is not inserted as canonical data.

Future automation readiness:

- دبیرخانه
- مدیر
- کارشناس
- ارجاع نامه به کاربر
- ارجاع نامه به واحد
- کارتابل شخصی
- کارتابل واحد

## Future Milestones

- MFA stabilization
- Core documentation
- Auth
- RBAC
- Bot Engine
- CRM
- Automation
- ERP Foundation

## Ministry geography validation adapter

The first concrete source adapter accepts private UTF-8 Ministry CSV files, resolves Persian source types through seeded metadata, stages every nonblank row, validates code-derived hierarchy and repeated national identifiers, reuses identical source/hash snapshots, and returns aggregate-only summaries. It remains validate-only and performs no canonical geography, relation, mapping, organization, address, SCI, or bot write. XLSX remains unavailable until a compatible reviewed dependency/deployment path exists.

## Statistical Center geography validation adapter

The SCI source adapter extends the same dry-run framework with streaming UTF-8 CSV
parsing suitable for more than 105,000 rows, data-driven CODEREC mappings, full
source-context hierarchy keys, database-backed parent/duplicate validation, and
opaque DIAG preservation. Statistical urban units and settlements remain source
observations. Ministry official hierarchy stays authoritative and all canonical
writes remain deferred.

## Ministry to SCI crosswalk candidates

Completed Ministry and SCI snapshots now support a versioned, idempotent,
parent-first candidate run. Exact, probable, ambiguous, unmatched, and excluded
classifications are stored as pending review records. Numbered statistical urban
units and SCI settlements are excluded from official Ministry matching. No source
staging, canonical geography, confirmed external mapping, bot data, organization,
or address is modified.

## Ministry official canonical geography

Reviewed Ministry staging now supports the first controlled canonical write. A
protected immutable plan classifies every row before an exact fingerprint-confirmed
apply creates/reuses the Iran root and applies province, county, district, rural
district, and city parent-first. Exact hierarchy-code mappings and source
identifiers retain snapshot provenance. National identifier repetition never
merges rows; title-only reuse is blocked; no deletion, SCI apply, bot geography,
organization import, UI, Automation, or Auth/RBAC/MFA change is included.

Canonical apply recovery is now hardened with additive private failure telemetry,
safe operation-stage tracking, private exception logging, strict single-level
chunks, aggregate status mode, and same-plan failed-run resume. Committed artifact
counters are reconciled from database state, preventing retry inflation while
preserving the existing plan, staging rows, audit items, and Iran root.
