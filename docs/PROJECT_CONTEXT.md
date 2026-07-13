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
