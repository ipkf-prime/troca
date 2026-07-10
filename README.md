# IPKF Framework

IPKF Framework is a lightweight PHP framework foundation for the IPKF product line.
The current baseline is focused on the Foundation runtime and does not include
business modules.

## Version

Current version: `0.4.3-identity-access`

## Requirements

- PHP 8.4 tested on the development hosting environment.
- The web server document root must point to the application's `/public` directory.
- For this repository layout, deploy `public_html/` as the application root and point the domain document root to `public_html/public`.

## Installation Notes

1. Copy `.env.example` to the application `.env` file.
2. Set environment values for the target machine.
3. Ensure `storage/` is writable by the web server.
4. Configure the web server document root to the `public/` directory.
5. Deploy from the active feature branch for development work. The current installer release branch is `installer-v0.3`.

For login token links and local time display, set:

- `APP_URL=https://dev.troca.ir`
- `APP_TIMEZONE=Asia/Tehran`

Auth, MFA, identity, and provider foundation environment notes:

- `AUTH_SESSION_NAME=ipkf_session` keeps CSRF, Auth, MFA, and access switching on one session cookie.
- `IDENTITY_DEV_EXPOSE_TOKEN=false` must be used in production. Set it to `true` only with `APP_ENV=development` and `APP_DEBUG=true` for development identity-change testing.
- `MFA_DEV_EXPOSE_OTP=false` must remain the default.
- `MFA_EMAIL_ENABLED`, `MFA_SMS_ENABLED`, and `MFA_BOT_ENABLED` enable delivery-channel foundations only when matching provider settings are configured.
- `MAIL_*`, `SMS_*`, `KAVENEGAR_*`, and `BALE_*` values must be stored in `.env` and never committed.

On cPanel Git deployment, this repository deploys `public_html/` to the configured application path. The active dev domain uses:

- Repository path: `/home/troca/repositories/troca`
- Application path: `/home/troca/dev.troca.ir`
- Document root: `/home/troca/dev.troca.ir/public`

## Development Route Tests

The Foundation baseline has been verified with:

- `GET /` returns `IPKF Framework Genesis OK`
- `GET /unknown` returns a clean 404 response
- `GET /health` returns JSON health status
- `GET /_diagnostics` returns JSON diagnostics when `APP_DEBUG=true`
- `GET /migrate.php?key=DEV_MAINTENANCE_KEY` runs dev migrations when `APP_DEBUG=true`
- `GET /seed.php?key=DEV_MAINTENANCE_KEY` runs dev seeders when `APP_DEBUG=true`
- `GET /install.php` returns safe installer JSON when installer access rules allow it
- `GET /mfa/status` returns MFA status for an authenticated session
- `POST /mfa/totp/setup` starts TOTP setup for an authenticated session
- `POST /mfa/totp/confirm` confirms a TOTP method with CSRF protection
- `POST /mfa/challenge/verify` completes pending MFA login challenges

## Site Mode

Set `SITE_MODE=coming_soon` to show the public Persian Coming Soon page at `GET /`.
Set `SITE_MODE=app` to keep the framework/app home behavior for future application routing.

## Architecture Guardrails

- Framework core code lives under `system/`.
- Application code lives under `app/`.
- `IPKF\` maps to `system/`.
- `App\` maps to `app/`.
- Controllers should coordinate requests and responses.
- Services contain business logic.
- Repositories handle data access.
- Do not add business features unless explicitly requested.

## MFA Foundation

The v0.4.2 development branch adds JSON-first MFA foundation routes without adding UI or business modules.

The v0.4.3 stable baseline adds identity verification, one-time login tokens, MFA delivery channel foundations, and active access switching without adding UI or business modules.

Set:

- `MFA_ENABLED=true`
- `MFA_ENFORCEMENT=optional`

MFA shares the same `AUTH_SESSION_NAME` session cookie as CSRF and Auth.

## Branch Naming

Active development branches should start with the version and end with `-dev`, for example:

- `v0.4.3-identity-access-dev`
- `v0.4.4-admin-panel-shell-dev`
- `v0.4.5-admin-users-dev`

Release tags should omit the `-dev` suffix, for example:

- `v0.4.3-identity-access`
- `v0.4.4-admin-panel-shell`
- `v0.4.5-admin-users`
