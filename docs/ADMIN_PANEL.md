# IPKF Admin Panel Shell

Current version: `0.4.4-admin-panel-shell-dev`

## Purpose

This milestone adds the first server-rendered admin panel shell on top of the existing IPKF Auth, MFA, RBAC, session, and active access foundations.

It does not add CRM, Bot, ERP, Automation, Marketplace, business workflows, admin CRUD, or profile editing features.

## Routes

- `GET /admin`
- `GET /admin/login`
- `POST /admin/login`
- `GET /admin/mfa`
- `POST /admin/mfa`
- `GET /admin/dashboard`
- `GET /admin/profile`
- `GET /admin/access`
- `POST /admin/access`
- `GET /admin/theme`
- `POST /admin/theme`
- `GET /admin/logout`

## Auth Flow

`/admin/login` is an RTL Persian login page. The `login` field accepts email, mobile, or username through the existing `AuthService` and `UserRepository` login resolution.

The login form is CSRF protected with the existing framework `_token` flow. Invalid credentials return a generic safe error.

If the user does not require MFA, successful login redirects to `/admin/dashboard`.

## MFA Flow

When `AuthService` returns `mfa_required=true`, the admin shell redirects to `/admin/mfa`.

`/admin/mfa` supports:

- TOTP code
- recovery code fallback

The page uses the existing `MfaService` pending challenge flow. Valid verification completes the existing auth session and redirects to `/admin/dashboard`.

## Dashboard

`/admin/dashboard` requires authentication and shows only shell-level operational data:

- current auth status
- active role
- MFA status
- framework version
- available role assignments

It does not show CRM, Bot, ERP, Automation, Marketplace, or business data.

## Active Access Switching

`/admin/access` lists the current user's active assignments and uses the existing `AccessService::switchTo()` method.

After switching, `active_role_assignment_id` is updated in the session. Permission-sensitive checks such as `/admin-check` continue to depend on the active role.

The default active role remains the lowest-privilege assignment selected by the Auth foundation.

## Profile Shell

`/admin/profile` safely displays:

- name
- username
- email
- mobile
- active role
- MFA status

Profile editing is intentionally not implemented in this phase. Future profile edits should reuse the existing identity change verification flow.

## Layout

The admin shell uses a reusable RTL Persian layout with:

- header
- sidebar
- content area
- user display
- active role badge
- logout link

CSS is local at `public/assets/css/admin.css`; no external CDN is required.

## Dynamic Theme System

`/admin/theme` provides the first dynamic admin theme management surface.

Available presets:

- `cooperative_light` is the default lighter cooperative theme.
- `cooperative_classic` keeps a stronger green cooperative look.
- `neutral_light` provides a restrained neutral light option.

The admin CSS is driven by safe design tokens:

- `--admin-primary`
- `--admin-primary-hover`
- `--admin-primary-soft`
- `--admin-accent`
- `--admin-bg`
- `--admin-bg-gradient-start`
- `--admin-bg-gradient-end`
- `--admin-surface`
- `--admin-surface-muted`
- `--admin-text`
- `--admin-text-muted`
- `--admin-border`
- `--admin-danger`
- `--admin-warning`
- `--admin-success`
- `--admin-radius`
- `--admin-shadow`
- `--admin-sidebar-width`
- `--admin-topbar-height`

Theme settings are persisted in the framework-safe `app_settings` table under the `admin.theme` namespace. If the table is not available yet, the admin panel falls back to the default preset and environment branding values.

Branding values:

- `ADMIN_BRAND_NAME` sets the fallback admin brand name.
- `ADMIN_LOGO_URL` sets an optional fallback logo URL.
- Stored database settings override environment fallback values.

Only an active role with `admin.theme.manage` can update the theme. The seed flow grants this permission to `super_admin`. Base user roles can view the page but cannot save changes.

Theme inputs are validated before persistence:

- colors must be six-digit hex values
- radius and layout dimensions must be pixel values
- shadows are restricted to safe simple CSS shadow text
- logo URLs must be local safe paths or `http/https` URLs
- arbitrary CSS, `url(...)` injection, scripts, and secrets are not accepted

## Security Notes

- Admin POST forms are CSRF protected.
- Existing Auth, MFA, and Access services are reused.
- Theme updates require `admin.theme.manage`.
- No password hash is exposed.
- No session ID is exposed.
- No CSRF token is exposed outside forms.
- No MFA secret is exposed.
- No recovery codes are exposed by the admin shell.
- No provider secrets are exposed.
- No arbitrary CSS or provider secret is accepted through theme settings.

## Known Limitations

- Logout is currently exposed as `GET /admin/logout` because this route was required for the shell milestone.
- No admin CRUD exists yet.
- No profile editing exists yet.
- No MFA management UI exists yet.
- No business modules are included.
- The theme system is a shell-level UI foundation only; it does not add tenant branding or module-specific themes yet.

## Next Phase Suggestions

- Admin users list
- Admin invitation flow
- Permission-aware navigation
- MFA management UI
- Identity-change profile forms
- Tenant-aware branding and theme governance
