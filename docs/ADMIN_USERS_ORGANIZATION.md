# IPKF Admin Users Organization Schema

Current version: `0.4.6-admin-users-organization-dev`

## Purpose

This phase adds the schema foundation required before Automation. It prepares internal organizational units, job positions, and user organizational assignments so later correspondence and cartable flows can route work to users or units.

This is schema foundation only. It does not add Automation, letter/correspondence tables, inbox/cartable tables, routing/referral tables, attachments, workflow history, CRM, ERP, Bot modules, or UI.

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

Full CRUD pages remain deferred. Organization and position links still open guarded placeholders only.

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
- Admin pages are placeholders only.
- Full CRUD remains deferred.
- No automation features are implemented yet.
