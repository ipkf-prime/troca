# IPKF Admin Users Organization Schema

Current version: `0.4.6-admin-users-organization-dev`

## Purpose

This phase adds the schema foundation required before Automation. It prepares internal organizational units, job positions, and user organizational assignments so later correspondence and cartable flows can route work to users or units.

This is schema foundation only. It does not add Automation, letter/correspondence tables, inbox/cartable tables, routing/referral tables, attachments, workflow history, CRM, ERP, Bot modules, or UI.

The milestone also adds the canonical dynamic organization core described in `docs/DYNAMIC_ORGANIZATION_CORE.md`. It keeps the existing `organizations` table, separates reusable position titles from concrete organization posts, and assigns concrete posts to `persons` rather than requiring a `users` login account.

## Admin permissions

The users organization foundation seeds these permissions idempotently:

- `users.view`
- `users.manage`
- `org_units.view`
- `org_units.manage`
- `positions.view`
- `positions.manage`
- `user_org_assignments.manage`

Default role mappings:

- `super_admin`: all users organization permissions.
- `system_admin`: all users organization permissions.
- `province_admin`: `users.view`, `org_units.view`, and `positions.view`.
- `user`: none by default.

The seeder creates missing permissions and missing role-permission mappings only. It does not delete custom permissions and does not overwrite unrelated assignments.

## Admin routes

The admin panel includes permission-filtered placeholder routes:

- `/admin/users` requires `users.view`.
- `/admin/org-units` requires `org_units.view`.
- `/admin/positions` requires `positions.view`.

Guests are redirected to `/admin/login`. Authenticated users without the required permission receive the standard Persian 403 page.

Direct route access remains permission guarded. The sidebar is module-level only, so users and organization child routes are reached through their module hub actions instead of appearing as separate global menu items.

Account access switching is not part of the organization management pages. The self-service role assignment page is `/admin/profile/access`; `/admin/access` remains an administrative page protected by `access.manage`.

## Dashboard module tiles

The admin dashboard is the primary visual entry point for users and organization management in this phase.

Permission-aware dashboard tiles include:

- مدیریت کاربران: visible when the active role can access `users.view` or `access.manage`.
- ساختار سازمانی: visible when the active role can access `org_units.view` or `positions.view`.

Tiles are filtered by the current active role. Empty module tiles are hidden. The dashboard tile opens a dedicated module hub page instead of rendering multiple quick links directly on the dashboard.

Hub routes:

- `/admin/modules/users`
- `/admin/modules/organization`

Hub pages render only authorized action tiles. If no action is available, the standard Persian 403 page is returned.

The sidebar shows مدیریت کاربران when the active role can access `users.view` or `access.manage`. It shows ساختار سازمانی when the active role can access `org_units.view` or `positions.view`. Child routes such as `/admin/users`, `/admin/access`, `/admin/org-units`, and `/admin/positions` activate their parent module in the sidebar.

## Users list

`GET /admin/users` is a read-only admin users list protected by `users.view`.

The page includes:

- Persian RTL module-style header and breadcrumb back to `/admin/modules/users`
- safe user identity fields from `users` and `persons`
- active role summary without duplicate user rows
- primary organization unit when available
- server-side search with `q`
- server-side pagination with `page`
- responsive desktop table and mobile cards

The page never exposes password hashes, MFA secrets, recovery codes, login tokens, session values, CSRF tokens, trusted device secrets, or internal hashes.

Create, edit, delete, password reset, role assignment editing, and organizational assignment editing remain deferred.

## User detail

`GET /admin/users/{id}` is a read-only admin user detail page protected by `users.view`.

The user detail page is now a tabbed Entity Detail Workspace:

- `/admin/users/{id}` shows the compact overview.
- `/admin/users/{id}/identity` shows safe identity/profile fields.
- `/admin/users/{id}/contacts` shows contacts and addresses.
- `/admin/users/{id}/account` shows account and MFA/security summaries.
- `/admin/users/{id}/access` shows read-only role assignments for the selected user.
- `/admin/users/{id}/appointments` shows legacy user organization assignments and canonical organization appointments as separate subsections.

All tabs currently require `users.view`. The workspace is ready for separate per-tab permissions in later phases. The route URL preserves active tab state on refresh and browser back/forward. Mobile uses a vertical section navigator instead of a horizontal scrolling tab bar.

Visible user lists and detail pages must not expose raw internal user IDs. The users list displays a calculated row number, while detail links may continue to use the internal ID in the URL. Appointments and organization assignment tabs use Persian semantic titles and never expose table names such as `user_org_assignments` or `organization_appointments`.

The page includes:

- Persian RTL module-style header and breadcrumb back to `/admin/modules/users`
- safe identity fields from `users` and `persons`
- account status, username, email, mobile, verification status, and Jalali dates
- labeled username in the summary card, always displayed as a username and never as an ambiguous standalone value
- semantic province, county, and city labels in the summary when real lookup relationships exist, without repeating them in the identity field list
- MFA/security summary using counts only
- active role assignments without exposing internal assignment ids or scope ids
- organization assignments with unit and position summaries
- responsive desktop tables and mobile-safe overflow containers

Guests are redirected to `/admin/login`. Authenticated users without `users.view` receive the standard Persian 403 page. Missing, invalid, or unavailable users return a clean Persian 404 page without SQL errors or stack traces.

The detail page does not select or render password hashes, MFA secrets, recovery codes, login tokens, session values, CSRF tokens, trusted device secrets, provider secrets, or internal hashes.

National code is displayed only in the identity section and is masked in the UI.

Admin UI uses semantic lookup labels in this page. Reference ids and internal lookup codes are stored internally, but Persian titles are displayed to users. Broken references display `نامشخص`; missing optional values display `—`. Technical codes such as role code, org unit code, or position code may appear only as muted secondary administrative data.

Province, county, and city display must use real semantic lookup titles. City must never be substituted for county. When a genuine county/shahrestan relationship is not present in the current schema, county displays `—` and is deferred to the future dynamic geographic foundation.

Create, edit, delete, password reset, role assignment editing, organizational assignment editing, and Automation actions remain deferred.

## Organization units list

`GET /admin/org-units` is a read-only organization units list protected by `org_units.view`.

The page includes:

- Persian RTL module-style header and breadcrumb back to `/admin/modules/organization`
- safe fields from `org_units`
- parent unit title through a single SQL join
- hierarchy-aware indentation from sanitized `depth`
- server-side search with `q` over title, code, type, and parent title
- server-side pagination with `page`
- responsive desktop table and mobile cards
- ascending table order by `sort_order` and then `id`

The page does not expose internal `path` values and hides soft-deleted rows.

Create, edit, delete, drag/drop hierarchy management, user assignment editing, and automation workflows remain deferred.

## Positions list

`GET /admin/positions` is a read-only positions list protected by `positions.view`.

The page includes:

- Persian RTL module-style header and breadcrumb back to `/admin/modules/organization`
- safe fields from `positions`
- short truncated description display
- server-side search with `q` over title, code, and description
- server-side pagination with `page`
- responsive desktop table and mobile cards
- ascending table order by `sort_order` and then `id`

Create, edit, delete, user-position assignment, organizational assignment editing, and automation workflows remain deferred.

Full CRUD pages remain deferred.

## Tables

### org_units

Stores internal organizational units and departments.

Important fields:

- `parent_id`
- `code`
- `title`
- `type`
- `path`
- `depth`
- `sort_order`
- `status`
- `description`
- timestamps and optional `deleted_at`

This structure can later support hierarchy-aware units such as دبیرخانه, management departments, expert teams, and operational units.

### positions

Stores job positions and titles used by future routing and automation.

Important fields:

- `code`
- `title`
- `description`
- `status`
- `sort_order`
- timestamps

Examples for future use include مدیر, کارشناس, دبیرخانه, and other role titles. This phase does not seed business-specific positions.

### user_org_assignments

Connects existing `users` to organizational units and optional positions.

Important fields:

- `user_id`
- `org_unit_id`
- `position_id`
- `is_primary`
- `status`
- `started_at`
- `ended_at`
- timestamps

The table is designed for future personal and unit cartables, routing work to a user, and routing work to a unit.

## Guardrails

- The existing `users` table is reused.
- No duplicate users table is introduced.
- Existing persons/users/RBAC schema remains unchanged.
- Text columns are utf8mb4-compatible.
- Migrations are idempotent.
- Admin users, organization units, and positions pages are read-only list foundations only.
- Full CRUD remains deferred.
- Users, organization units, and positions read-only lists sort from small to large.
- Dashboard remains a module launcher only. Account cards, security status, and access summaries are kept under profile/security pages.
- Human-facing admin UI numbers are rendered with Persian digits where safe; technical identifiers, form values, search terms, emails, usernames, and hidden ids stay canonical.
- Reference IDs are never used as the user-facing fallback. Admin pages display Persian semantic titles when lookup/reference data exists.
- Technical codes may appear only as secondary administrative data, not as the primary label.
- No automation features are implemented yet.
- Organization classifications, relation types, and unit types are configurable data rather than PHP enums.
- Existing `organizations` rows and cooperative-era compatibility fields are preserved without rewrite.
- `org_units.organization_id` and `org_units.unit_type_id` are optional; no organization is guessed for existing rows.
- `positions` remains the reusable title catalog; `organization_positions` represents concrete posts.
- `organization_appointments` references `persons`, so appointments do not require login accounts.
- Governance, ownership, signatory authority, organization registration/contact/address data, and full organization CRUD remain deferred.

## Extended person data foundation

This milestone also prepares optional person profile, contact, and address data before user creation/editing workflows are introduced.

- `persons` remains the core real-world identity.
- `users` remains authentication-account data only.
- `person_profiles` contains complementary one-to-one identity details without duplicating existing `persons` fields.
- `contact_types` and `address_types` are configurable lookups without hardcoded business values.
- `person_contacts` and `person_addresses` support multiple optional rows per person.
- Existing `provinces` and `cities` lookups are reused for addresses.
- Existing people and users remain valid without any new related row.

Sensitive values require future masking, separate permissions, and audit controls. This phase adds no profile forms, CRUD routes, synchronization, or UI output. See `docs/PERSON_DATA_MODEL.md` and `docs/PRIVACY_SECURITY.md`.
