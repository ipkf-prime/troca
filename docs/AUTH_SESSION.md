# IPKF Auth Session Foundation

Current working version: `0.4.1-auth-session-dev`

## Purpose

This milestone adds the first working session-based authentication foundation for IPKF.

It includes:

- admin user seeding from `.env`
- secure password hashing with `password_hash`
- JSON login and logout routes
- current user lookup
- session-based authentication state
- basic permission checks
- safe auth diagnostics

It does not include login UI, admin panel UI, MFA verification logic, Bot, CRM, ERP, Automation, or Marketplace modules.

## Admin Seeding

Admin seed values are read from `.env`:

- `ADMIN_NAME`
- `ADMIN_EMAIL`
- `ADMIN_MOBILE`
- `ADMIN_PASSWORD`

The admin seeder refuses to create an admin user when:

- `ADMIN_EMAIL` is missing
- `ADMIN_PASSWORD` is missing
- `ADMIN_PASSWORD=change-me-securely`

The real admin password must never be committed. The seeder stores only `password_hash` using PHP `PASSWORD_DEFAULT`.

The seeder is idempotent and updates the same person/user/role assignment instead of duplicating records.

## Routes

Auth routes are JSON-first:

- `POST /auth/login`
- `POST /auth/logout`
- `GET /auth/status`
- `GET /me`
- `GET /admin-check`

`POST /auth/login` accepts:

- `login`
- `password`

The `login` value may be username, email, or mobile.

API responses never expose `password_hash`, MFA secrets, database secrets, session IDs, or maintenance keys.

## Session Keys

The auth session uses:

- `auth_user_id`
- `auth_login_at`
- `active_role_assignment_id` reserved for later

The session name and lifetime are configurable through:

- `AUTH_SESSION_NAME`
- `AUTH_SESSION_LIFETIME`

## Authorization

`AuthorizationService` reads roles and permissions through repositories.

For this foundation phase, `super_admin` is allowed to pass all permission checks.

`GET /admin-check` requires:

- authenticated session
- `system.diagnostics.view`

## Middleware Status

`AuthMiddleware` can enforce authenticated JSON responses.

Parameterized permission middleware is limited by the current router shape, so `GET /admin-check` performs route-level permission checks through `AuthorizationService`.

## Current Limitations

- MFA is not implemented yet.
- Login UI is not implemented yet.
- Admin panel UI is not implemented yet.
- Password reset and invitation flows are not implemented yet.

## Next Phase

The next phase can be MFA verification or Admin Panel Shell, depending on release priority.
