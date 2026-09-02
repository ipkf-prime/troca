# IPKF Identity Access Tokens Foundation

Current version: `0.4.3-identity-access`

## Scope

This milestone adds JSON-first foundations for identity verification, one-time login tokens, MFA delivery channels, and active access switching.

It does not add UI, Bot module logic, CRM, ERP, Automation, Marketplace, or admin panel pages.

## Runtime Verification

The v0.4.3 stable baseline has been verified on the development host:

- Login works with email, mobile, and username.
- Wrong or unknown credentials fail safely.
- CSRF and unified session handling work through `AUTH_SESSION_NAME`.
- TOTP MFA works.
- Recovery codes are generated, consumed, and cannot be reused.
- `/mfa/status` works.
- Email, sms, and bot MFA delivery foundations return `channel_not_configured` when providers are not configured.
- Login token issue, consume, single-use failure, timezone display, and MFA-respecting token login work.
- Identity change request and confirm work for username, email, and mobile.
- Duplicate and unchanged identity values are rejected.
- Username policy is enforced.
- Default active role is the lowest role.
- `/access/assignments` and `/access/switch` work.
- `/admin-check` fails with the base role and passes after switching to `super_admin`.

## Environment Notes

Use `.env` for real values. Do not commit secrets.

- `APP_URL` controls generated login-token URLs.
- `APP_TIMEZONE` controls local display timestamps; UTC remains canonical.
- `AUTH_SESSION_NAME` controls the runtime-local CSRF/Auth/MFA session cookie; each runtime owns its own host-only authentication session.
- `IDENTITY_DEV_EXPOSE_TOKEN` must be `false` in production. It only exposes identity-change `dev_token` when `APP_ENV=development` and `APP_DEBUG=true`.
- `MFA_DEV_EXPOSE_OTP` must remain `false` by default.
- `MFA_EMAIL_ENABLED`, `MFA_SMS_ENABLED`, and `MFA_BOT_ENABLED` enable delivery-channel foundations only when provider configuration is present.
- `MAIL_*`, `SMS_*`, `KAVENEGAR_*`, and `BALE_*` values are provider configuration placeholders and must not contain committed secrets.

## MFA Delivery Channels

The MFA channel registry includes:

- `totp`
- `recovery`
- `email`
- `sms`
- `bot`

Environment flags default to disabled:

- `MFA_EMAIL_ENABLED=false`
- `MFA_SMS_ENABLED=false`
- `MFA_BOT_ENABLED=false`
- `MFA_DEV_EXPOSE_OTP=false`

If a delivery provider is disabled or missing configuration, `POST /mfa/challenge` returns `channel_not_configured` and never fakes delivery success.

## Login Tokens

`POST /auth/login-token/issue` issues a short-lived one-time token for authorized users with `auth.login_token.issue`.

The plain token is returned only once as a login URL. The database stores only a password-hashed token value.

`APP_URL` controls the generated login URL. Development must use:

```env
APP_URL=https://dev.troca.ir
```

Production can later use:

```env
APP_URL=https://troca.ir
```

If `APP_URL` is missing, IPKF falls back to the current request scheme and host.

`APP_TIMEZONE` controls display timestamps. The canonical expiry is `expires_at_utc`; `expires_at_local` is display only.

Default:

```env
APP_TIMEZONE=Asia/Tehran
```

Token login endpoints:

- `GET /auth/token-login?token=...`
- `POST /auth/token-login`

For `POST /auth/token-login`, send `TOKEN_ONLY`, not the full `login_url`:

```json
{
  "token": "TOKEN_ONLY"
}
```

Tokens are single-use and expire after five minutes. If the target user has MFA enabled, token login starts pending MFA instead of fully authenticating.

Token issue response includes:

- `login_url`
- `expires_at`
- `expires_at_utc`
- `expires_at_local`
- `timezone`
- `ttl_seconds`

## Identity Changes

Identity changes use verification requests:

- `POST /identity/change/request`
- `POST /identity/change/confirm`

Supported fields:

- `username`
- `email`
- `mobile`

### Username Policy

IPKF usernames have one canonical policy for login, identity change requests, and admin seeding.

Allowed:

- English letters `a-z` and `A-Z`
- Digits `0-9`
- Underscore `_`

Disallowed:

- Hyphen `-`
- Dot `.`
- Space
- `@`
- Persian/Arabic letters
- Any other special character

Format:

- Minimum length: 3 characters
- Maximum length: 32 characters
- Must start with an English letter
- Must end with an English letter or digit
- Must not contain consecutive underscores `__`

Normalization:

- Trim whitespace
- Convert to lowercase canonical username
- Store `username_norm` as the lowercase canonical username

Valid examples:

- `admin`
- `admin_test`
- `admin123`
- `hamzeh_alaei`

Invalid examples:

- `admin-test`
- `admin.test`
- `admin test`
- `admin@`
- Persian or Arabic letters
- `_admin`
- `admin_`
- `ad`
- `admin__test`

Invalid username format returns a safe `422` JSON response:

```json
{
  "status": "error",
  "error": "invalid_identity_value",
  "message": "invalid_identity_value"
}
```

The request requires the current password. If the current user has MFA enabled, the session must already have recent MFA verification.

The system stores only token hashes and does not expose verification tokens unless `IDENTITY_DEV_EXPOSE_TOKEN=true` in debug mode.

`POST /identity/change/request` only creates a pending verified-change request. It does not update `/me` and does not change login identifiers until confirmation succeeds.

Development testing can expose the one-time verification token only when all of these are true:

```env
APP_ENV=development
APP_DEBUG=true
IDENTITY_DEV_EXPOSE_TOKEN=true
```

When enabled, the request response may include:

```json
{
  "status": "ok",
  "request_id": 123,
  "pending_verification": true,
  "delivery_status": "dev_token_exposed",
  "dev_token": "123456",
  "expires_at_utc": "2026-07-09T10:00:00Z",
  "expires_at_local": "2026-07-09T13:30:00+03:30"
}
```

Production must keep `IDENTITY_DEV_EXPOSE_TOKEN=false`. Plain verification tokens and token hashes must never be exposed outside this development-only flow.

If email, SMS, or bot delivery providers are not configured, IPKF still creates a pending request when validation passes and returns:

```json
{
  "delivery_status": "not_configured"
}
```

This makes the flow explicit without pretending an external message was sent.

Identity request validation is strict and idempotent:

- Current value requests return `value_unchanged` and do not create a row.
- Values already owned by another active user/person return `value_not_available` and do not reveal the owner.
- Repeating the same active pending request returns `change_request_already_pending` instead of creating unlimited rows.

`POST /identity/change/confirm` verifies the token hash, checks expiry and attempts, re-checks uniqueness, applies the change atomically, and marks the request verified/applied.

For username changes, confirm also re-validates the pending value with the canonical username policy. Invalid pending username requests from older builds are cancelled and are not applied.

Confirm body:

```json
{
  "request_id": 123,
  "token": "123456"
}
```

After confirm, `/me` reflects the updated username, email, or mobile where applicable, and login uses the new normalized identity value.

## Active Access

Users can have multiple active role assignments.

On login, IPKF chooses the lowest-priority role assignment by default and stores it in:

- `active_role_assignment_id`

Endpoints:

- `GET /access/assignments`
- `POST /access/switch`

Authorization checks use the active assignment. A super admin permission only applies while the active assignment is `super_admin`.

## Security Notes

Do not expose:

- login token hashes
- OTP values
- OTP hashes
- identity token hashes
- recovery code values or hashes
- TOTP secrets
- provider secrets
- session IDs
- CSRF tokens
- password hashes

## Runtime Flow

1. Run migrations.
2. Run seeders.
3. Login with email, mobile, or username.
4. Complete MFA if required.
5. Confirm `/access/assignments` defaults to the lowest role.
6. Switch to `super_admin` before calling `/admin-check`.
7. Issue and consume a login token.
8. Request an identity change. In development, enable `IDENTITY_DEV_EXPOSE_TOKEN=true` to receive `dev_token`.
9. Confirm the identity change with `/identity/change/confirm`.
10. Verify `/me` and login by the changed username, email, or mobile.
