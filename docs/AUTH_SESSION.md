# IPKF Auth Session Foundation

Current version: `0.4.2-mfa-foundation-dev`

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

It does not include login UI, admin panel UI, Bot, CRM, ERP, Automation, or Marketplace modules.

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

- `GET /csrf-token`
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

## Postman JSON Test Flow

1. Send `GET /csrf-token`.
2. Copy the `csrf_token` value and keep the returned session cookie.
3. Send `POST /auth/login` with header `X-CSRF-TOKEN: <token>` and JSON body:

```json
{
  "login": "admin@example.com",
  "password": "your-real-admin-password"
}
```

4. Send `GET /me` with the same cookie.
5. Send `GET /admin-check` with the same cookie.
6. Send `POST /auth/logout` with header `X-CSRF-TOKEN: <token>` and the same cookie.
7. Send `GET /auth/status` and confirm `authenticated=false`.

POST auth routes keep CSRF enabled. Tokens are accepted from `X-CSRF-TOKEN` or `_token`.

## MFA-Aware Login Flow

MFA runtime starts in `0.4.2-mfa-foundation-dev`.

When the user has no enabled MFA method, login behavior is unchanged.

When the user has an enabled MFA method:

- `POST /auth/login` validates the password.
- The session receives pending MFA keys instead of `auth_user_id`.
- The response includes `mfa_required=true`.
- `POST /mfa/challenge/verify` or `POST /mfa/verify` completes the login after a valid MFA code.

Pending MFA session keys:

- `auth_pending_user_id`
- `auth_pending_at`
- `auth_pending_methods`

`AUTH_SESSION_NAME` still controls the single shared session cookie. CSRF, pending MFA, and authenticated session state must all use the same cookie. Postman must keep cookies between `/csrf-token`, `/auth/login`, `/mfa/verify`, `/mfa/status`, `/me`, and `/auth/logout`.

## MFA Runtime Routes

- `GET /mfa/status`
- `POST /mfa/totp/setup`
- `POST /mfa/totp/confirm`
- `POST /mfa/challenge/verify`
- `POST /mfa/verify`
- `POST /mfa/recovery-codes/regenerate`
- `GET /mfa/trusted-devices`
- `POST /mfa/trusted-devices/revoke`

All MFA POST routes require a valid CSRF token.

`GET /mfa/status` returns safe authenticated MFA state after login and after MFA verification. It never exposes TOTP secrets, recovery code values, recovery code hashes, session IDs, CSRF tokens, password hashes, or raw trusted device tokens.

## MFA Postman Flow

1. `GET /csrf-token`
2. `POST /auth/login` with `X-CSRF-TOKEN`
3. If the response has `mfa_required=true`, send `POST /mfa/verify` with `X-CSRF-TOKEN` and a valid code.
4. `GET /mfa/status`
5. `GET /me`
6. `POST /auth/logout` with `X-CSRF-TOKEN`

## Session Keys

The auth session uses:

- `auth_user_id`
- `auth_login_at`
- `active_role_assignment_id` reserved for later

The session name and lifetime are configurable through:

- `AUTH_SESSION_NAME`
- `AUTH_SESSION_LIFETIME`

`AUTH_SESSION_NAME` controls the session cookie name. CSRF and Auth must share this same session, so `/csrf-token`, `/auth/login`, `/auth/status`, `/me`, `/admin-check`, and `/auth/logout` all use the same cookie.

Postman and browser tests must keep cookies between requests. If the cookie from `GET /csrf-token` is not sent to `POST /auth/login`, CSRF validation will fail. If the cookie from login is not sent to `GET /auth/status` or `GET /me`, the user will appear unauthenticated.

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

- Login UI is not implemented yet.
- Admin panel UI is not implemented yet.
- Password reset and invitation flows are not implemented yet.
- MFA UI and trusted-device login prompts are not implemented yet.

## UTF-8 Data

The database connection must use `utf8mb4` for Persian RBAC seed data. The auth/RBAC seeder is idempotent and repairs canonical seeded records such as `roles.code=super_admin` with the Persian title `مدیر کل سامانه`.

## Verified Runtime Flow

- `GET /csrf-token` works.
- `POST /auth/login` works with `X-CSRF-TOKEN`.
- `GET /auth/status` returns `authenticated=true` after login.
- `GET /me` returns the current user and roles.
- `GET /admin-check` returns `status=ok` for `super_admin`.
- `POST /auth/logout` works with `X-CSRF-TOKEN`.
- `GET /auth/status` returns `authenticated=false` after logout.

## Next Phase

The next phase can stabilize MFA or start Admin Panel Shell, depending on release priority.
