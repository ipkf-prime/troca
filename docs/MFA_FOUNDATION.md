# IPKF MFA Foundation

Current version: `0.4.2-mfa-foundation-dev`

## Purpose

This milestone adds the first runtime foundation for MFA without adding UI, admin panel flows, or business modules.

It uses the existing MFA schema from v0.4.0:

- `user_mfa_methods`
- `mfa_challenges`
- `trusted_devices`
- `recovery_codes`

No duplicate MFA tables are created in this phase.

## Environment

MFA runtime behavior is controlled by safe environment values:

- `MFA_ENABLED=true`
- `MFA_ENFORCEMENT=optional`

Supported enforcement modes:

- `optional`
- `required`

This phase keeps enforcement lightweight. A user without enabled MFA continues through the normal login flow.

## Routes

MFA routes are JSON-first:

- `GET /mfa/status`
- `POST /mfa/totp/setup`
- `POST /mfa/totp/confirm`
- `POST /mfa/challenge/verify`
- `POST /mfa/recovery-codes/regenerate`
- `GET /mfa/trusted-devices`
- `POST /mfa/trusted-devices/revoke`

All POST routes keep CSRF protection enabled and accept `X-CSRF-TOKEN`.

## Login Flow

When a user has no enabled MFA method:

- `POST /auth/login` keeps the existing behavior.
- The session receives `auth_user_id`.

When a user has an enabled MFA method:

- password verification succeeds
- `auth_user_id` is not set yet
- pending session keys are set:
  - `auth_pending_user_id`
  - `auth_pending_at`
  - `auth_pending_methods`
- `POST /auth/login` returns `mfa_required=true`
- full authentication is completed by `POST /mfa/challenge/verify`

CSRF and Auth continue to share the same `AUTH_SESSION_NAME` session cookie.

## TOTP

`POST /mfa/totp/setup` creates or refreshes a pending TOTP method for the authenticated user and returns an `otpauth_uri` for authenticator apps.

`POST /mfa/totp/confirm` verifies a 6-digit TOTP code and enables the method.

This phase does not add a QR-code UI.

## Recovery Codes

`POST /mfa/recovery-codes/regenerate` replaces existing recovery codes for the authenticated user and returns the plain codes once.

Only hashed recovery codes are stored.

## Trusted Devices

Trusted device endpoints are foundation-only in this phase:

- list existing trusted devices
- revoke a trusted device

Creating trusted devices during login will be added in a later phase.

## OTP Channel Skeletons

Email OTP, SMS OTP, and Bot OTP are represented only by channel interfaces in this phase.

No delivery implementation, Bot module, CRM module, ERP module, Marketplace module, or UI is added.

## Safe Diagnostics

`/_diagnostics` may include:

- `mfa_runtime_available`
- `mfa_totp_available`
- `mfa_recovery_codes_available`
- `mfa_trusted_devices_available`
- `mfa_routes_available`

Diagnostics must not expose:

- TOTP secrets
- recovery codes
- recovery code hashes
- session IDs
- CSRF tokens
- cookie values
- maintenance keys
- database secrets

## Manual Runtime Test Flow

1. `GET /csrf-token`
2. `POST /auth/login` with `X-CSRF-TOKEN`
3. `GET /mfa/status`
4. `POST /mfa/totp/setup` with `X-CSRF-TOKEN`
5. Register the returned `otpauth_uri` in an authenticator app.
6. `POST /mfa/totp/confirm` with `X-CSRF-TOKEN` and a valid code.
7. `POST /auth/logout` with `X-CSRF-TOKEN`
8. `POST /auth/login` again.
9. Confirm login returns `mfa_required=true` and does not authenticate fully.
10. `POST /mfa/challenge/verify` with a valid TOTP code.
11. Confirm `GET /auth/status` returns `authenticated=true`.

## Current Limitations

- MFA UI is not implemented.
- Trusted-device creation during login is not implemented.
- MFA challenge audit rows are not fully implemented.
- Admin panel MFA management is not implemented.
- MFA enforcement policy is intentionally minimal.
