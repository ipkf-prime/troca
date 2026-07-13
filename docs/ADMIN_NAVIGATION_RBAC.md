# IPKF Admin Navigation RBAC

Current version: `0.4.6-admin-users-organization-dev`

## Purpose

This milestone aligns admin navigation visibility with route access checks. The admin sidebar, account dropdown, and direct URL guards use one central permission map and respect the current active role assignment.

No CRM, Bot, ERP, Automation, Marketplace, or business module is added in this phase.

In v0.4.6 the admin dashboard also shows a visual permission-aware module launcher. These module tiles, hub actions, and module-level sidebar entries use the same active-role permission context as the route guards.

## Final Scope

Included in v0.4.5:

- RBAC-based sidebar menu filtering
- RBAC-based user dropdown filtering
- Admin route permission map
- Route guards for protected admin pages
- Clean Persian 403 page
- Active role/access-aware permissions
- Self-service active access switching
- Seeded permissions and default role-permission mappings

Deferred after v0.4.5:

- full permission management UI
- advanced audit logs
- automation module
- CRM, ERP, and Bot modules
- organization, geography, and fiscal-year scoped UI enforcement beyond the existing foundation

Next phase: `v0.4.6-admin-users-organization-dev`

Planned next work:

- admin users organization schema
- organizational units
- positions
- user organizational assignments
- automation readiness without creating automation tables

## Permission Map

- `/admin/dashboard` -> `admin.dashboard.view`
- `/admin/profile` -> `account.profile.view`
- `/admin/account` -> `account.profile.view`
- `/admin/security` -> `account.security.view`
- `/admin/password` -> `account.password.change`
- `/admin/my-theme` -> `account.theme.manage`
- `/admin/access` -> `access.manage`
- `/admin/users` -> `users.view`
- `/admin/org-units` -> `org_units.view`
- `/admin/positions` -> `positions.view`
- `/admin/theme` -> `admin.theme.manage`
- `/admin/theme/debug` -> `admin.theme.manage` and `APP_DEBUG=true`
- `/admin/navigation/debug` -> `admin.navigation.debug` and `APP_DEBUG=true`
- `/admin/settings` -> `admin.settings.manage`
- `/admin/pages` -> `admin.pages.manage`
- `/admin/reports` -> `admin.reports.view`
- `/admin/support` -> `support.view`

Public and auth routes remain public or pending-auth as appropriate:

- `/admin/login`
- `/admin/forgot-password`
- `/admin/mfa`
- `/admin/mfa/recovery`
- `/admin/logout`

## Active Role Context

Permission checks use `AuthorizationService`, which reads the active assignment from `active_role_assignment_id`. A user with multiple roles receives only the permissions of the currently selected assignment.

`super_admin` remains an override through the existing authorization service. If the same user switches to the base `user` assignment, restricted menu items disappear and direct URLs return 403.

## Active Access Switching

There are two separate concepts:

- `/admin/access` is the access management page and requires `access.manage`.
- `/admin/access` POST and `/access/switch` switch the authenticated user's own active assignment and do not require `access.manage`.

Switching active access is self-service because a limited active role must still be able to switch back to another assigned role. The switch action can only target assignments that belong to the current authenticated user and are active, enabled, and unexpired.

After a successful switch, the session `active_role_assignment_id` changes, permissions are recalculated from the new active assignment, and the sidebar updates on the next page load.

Dashboard module tiles also update after the redirect/refresh. They do not aggregate permissions from inactive assignments.

## Dashboard Module Tiles

The dashboard is the primary module entry point in v0.4.6.

- Tiles are permission filtered.
- Tiles use the active-role permission context.
- A tile is hidden when none of its destinations are permitted.
- Dashboard tiles are full-card links and no longer render quick-link lists.
- Multi-action modules open dedicated hub pages.
- Hub pages render only authorized action tiles.
- A hub page returns the standard Persian 403 response if no action is available to the active role.
- The sidebar is module-level only and does not list child routes individually.
- Child routes stay directly accessible when authorized, but their parent module is highlighted in the sidebar.
- Automation module tiles will be added later only when real Automation routes exist.

Module hub routes:

- `/admin/modules/users`
- `/admin/modules/organization`
- `/admin/modules/system`

Reports and support remain direct destinations.

## Module-Level Sidebar

The sidebar contains only:

- dashboard
- users management
- organization structure
- system management
- reports
- support

The users module is visible when the active role can access at least one of `users.view` or `access.manage`.

The organization module is visible when the active role can access at least one of `org_units.view` or `positions.view`.

The system module is visible when the active role can access at least one of `admin.theme.manage`, `admin.settings.manage`, or `admin.pages.manage`.

The child routes `/admin/users`, `/admin/access`, `/admin/org-units`, `/admin/positions`, `/admin/theme`, `/admin/settings`, and `/admin/pages` are no longer shown as global sidebar items. They remain guarded routes and are linked from module hub actions.

Active sidebar mapping:

- `/admin/users` and `/admin/access` highlight the users management module.
- `/admin/org-units` and `/admin/positions` highlight the organization structure module.
- `/admin/theme`, `/admin/settings`, and `/admin/pages` highlight the system management module.

Icons are rendered through the local admin icon-font foundation and `App\Support\AdminIcon`; no external icon CDN is used.

## Seeded Permissions

The seeder adds the v0.4.5 admin navigation permissions idempotently and uses `INSERT IGNORE` for role-permission links.

Default grants:

- `super_admin`: all permissions
- `system_admin`: dashboard, account pages, access switching, theme, settings, pages, reports, support, navigation debug
- `province_admin`: dashboard, account pages, reports, support
- `user`: dashboard, account pages, personal theme, support

Existing custom assignments are preserved.

## 403 Behavior

If a guest opens a protected admin URL, the shell redirects to `/admin/login`.

If an authenticated user lacks permission, the shell returns HTTP 403 with a clean Persian page:

- title: `دسترسی غیرمجاز`
- message: `شما مجوز لازم برای مشاهده این بخش را ندارید.`
- action: back to dashboard

## Debug

`GET /admin/navigation/debug` is available only when:

- `APP_DEBUG=true`
- the current active role is `super_admin` or has `admin.navigation.debug`

It returns safe route and menu metadata only. It does not expose secrets, session ids, CSRF tokens, passwords, recovery codes, or login tokens.

## Manual Verification

- Login as `super_admin`; verify dashboard, access, theme, settings, pages, reports, and support are visible.
- Switch active access to the base `user` role; verify access, theme, settings, pages, and reports disappear.
- Open `/admin/access` with the base role; verify HTTP 403.
- Open `/admin/theme` with the base role; verify HTTP 403.
- Use the dashboard assignment table to switch from `user` back to `super_admin`.
- Verify the header active role badge updates and the sidebar expands after switching.
- Verify profile, account, security, password, and my-theme remain available when their account permissions exist.
- Switch back to `super_admin`; verify restricted items return.
- Verify `/admin/navigation/debug` works only in debug for an authorized active role.
