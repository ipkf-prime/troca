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

## Security Notes

- Admin POST forms are CSRF protected.
- Existing Auth, MFA, and Access services are reused.
- No password hash is exposed.
- No session ID is exposed.
- No CSRF token is exposed outside forms.
- No MFA secret is exposed.
- No recovery codes are exposed by the admin shell.
- No provider secrets are exposed.

## Known Limitations

- Logout is currently exposed as `GET /admin/logout` because this route was required for the shell milestone.
- No admin CRUD exists yet.
- No profile editing exists yet.
- No MFA management UI exists yet.
- No business modules are included.

## Next Phase Suggestions

- Admin users list
- Admin invitation flow
- Permission-aware navigation
- MFA management UI
- Identity-change profile forms
