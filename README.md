# IPKF Framework

IPKF Framework is a lightweight PHP framework foundation for the IPKF product line.
The current baseline is focused on the Foundation runtime and does not include
business modules.

## Version

Current version: `0.4.7-automation-foundation`

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

Admin panel theme and branding notes:

- `ADMIN_BRAND_NAME` sets the fallback admin brand label.
- `ADMIN_LOGO_URL` sets an optional fallback logo URL.
- Stored admin theme settings override these environment fallback values.

On cPanel Git deployment, this repository deploys `public_html/` to the configured application path. The active dev domain uses:

- Repository path: `/home/troca/repositories/troca`
- Application path: `/home/troca/dev.troca.ir`
- Document root: `/home/troca/dev.troca.ir/public`

## Production Safety

For production:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `IDENTITY_DEV_EXPOSE_TOKEN=false`
- `MFA_DEV_EXPOSE_OTP=false`

The `/admin/theme/debug` route is available only when `APP_DEBUG=true` and must not be available in production.

Development maintenance endpoints such as `/migrate.php` and `/seed.php` remain protected by `DEV_MAINTENANCE_KEY`; production usage must be controlled and never expose the key.

## Development Route Tests

The Foundation baseline has been verified with:

- `GET /` renders the public RTL Persian IPKF/Troca landing page
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
- `GET /admin/login` loads the RTL Persian admin login page
- `GET /admin/dashboard` loads the authenticated admin dashboard shell
- `GET /admin/theme` loads the configurable admin theme page
- `GET /admin/access` returns a clean 403 page when the active role lacks `access.manage`
- `GET /admin/theme` returns a clean 403 page when the active role lacks `admin.theme.manage`

## Site Mode

Set `SITE_MODE=coming_soon` to show the polished public Persian landing page at `GET /` with the development/coming-soon badge.
Set `SITE_MODE=app` to show the same public landing foundation without the coming-soon badge until future application routing replaces it.

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

The v0.4.4 stable baseline adds the first admin panel shell, simplified built-in theme preset selection, responsive admin navigation, autofocus behavior, and the public landing page without adding CRM, Bot, ERP, Automation, Marketplace, admin CRUD, or business workflows.

The v0.4.5 stable baseline adds permission-based admin navigation and route guards. Sidebar visibility, account dropdown visibility, and direct URL access now share one route permission map and respect the current active role assignment.

The v0.4.6 stable baseline adds the admin users and organization foundation required before Automation. It includes person/user separation, multi-role active access, organization-neutral dynamic classifications, organization relationships, internal units, reusable and concrete positions, person-based appointments, read-only responsive admin workspaces, and the official Ministry canonical geography.

Deferred after v0.4.5:

- full permission management UI
- advanced audit logs
- automation module
- CRM, ERP, Bot, and Marketplace modules
- organization, geography, and fiscal-year scoped UI enforcement beyond the existing foundation

The only operational canonical geography in this stable baseline is the official Ministry hierarchy stored in the canonical geography model. SCI and Rural Cooperation staging remain non-operational audit/source infrastructure. Rural Cooperation regions and organizations, including the South Kerman operational-region extension, are deferred to a future extension and will not replace or duplicate the Ministry canonical hierarchy.

Dynamic organization architecture is documented in `docs/DYNAMIC_ORGANIZATION_CORE.md`. Existing `organizations` rows and legacy compatibility fields remain intact; no organization type or relationship is hardcoded in PHP.

The v0.4.7 stable baseline is `0.4.7-automation-foundation`. It adds the additive correspondence domain foundation for دبیرخانه, incoming/outgoing/internal correspondence, immutable versions, normalized parties, registry books, official registrations, relations, referrals, personal/unit cartable derivation, append-only events, and private attachment metadata.

This milestone is schema and metadata only. It adds no operational correspondence UI, editor, upload/download endpoint, notification delivery, numbering API, workflow designer, signature, OCR, PDF generation, public tracking, external API, or operational record. Future official number allocation must use a database transaction and row-level lock on the registry book.

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
