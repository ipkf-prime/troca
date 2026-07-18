# Multi-host module routing

The Core admin panel and Automation module are one versioned codebase with separate runtime hosts and an independent Automation database.

## Development topology

- Core: `https://dev.troca.ir`
- Automation: `https://oa-dev.troca.ir`
- Core deployment: `/home/troca/dev.troca.ir`
- Automation deployment: `/home/troca/oa-dev.troca.ir`
- Core document root: `/home/troca/dev.troca.ir/public`
- Automation document root: `/home/troca/oa-dev.troca.ir/public`

The repository deployment copies the same `public_html` release to both application paths. Each target retains its own untracked `.env`. Schema and seed operations for Automation continue to use the dedicated `automation.primary` connection.

## Required environment contract

Both development deployments must define:

```env
CORE_APP_URL=https://dev.troca.ir
AUTOMATION_APP_URL=https://oa-dev.troca.ir
ALLOWED_APP_HOSTS=dev.troca.ir,oa-dev.troca.ir
APP_HOST_GUARD_ENABLED=true
AUTH_COOKIE_SECURE=true
AUTOMATION_DB_MODE=dedicated
```

`APP_URL` and the session name remain deployment-specific. Core uses `APP_URL=https://dev.troca.ir` and `AUTH_SESSION_NAME=ipkf_dev_core`; Automation uses `APP_URL=https://oa-dev.troca.ir` and `AUTH_SESSION_NAME=ipkf_dev_automation`. `AUTH_COOKIE_DOMAIN` must remain empty, so credentials and sessions are never shared through a parent-domain cookie.

## Routing policy

- Automation URLs are generated from `AUTOMATION_APP_URL`.
- Core URLs are generated from `CORE_APP_URL`.
- Automation launches go through the Core SSO start endpoint.
- Non-Automation admin paths requested on the Automation host redirect to Core.
- Password and MFA are accepted only by Core. Automation has no independent login UI.
- Core issues a hashed, audience-bound, one-time authorization code with a 60-second lifetime.
- Automation consumes the code atomically, creates its own host-only session, and immediately removes the code from the browser URL.
- Return paths are restricted to `/admin/automation` and its descendants.
- Federated logout clears the Automation session and then the Core session.
- Unknown hosts are rejected with HTTP 421 when the guard is enabled.
- Both independent cookies are HTTPS-only, HTTP-only and host-scoped; authorization and CSRF checks remain active on both hosts.

## Correspondence document workspace

- Letter templates are selected from the active versioned catalog at `/admin/automation/templates`.
- A template snapshot is fixed on each correspondence version so later template changes do not alter historical letters.
- Copy and blind-copy recipients use correspondence party roles.
- `reply_to` is presented as «عطف / پاسخ به» and `follow_up` as «پیرو»; the referenced letter number and date are shown with the current content.
- Attachments are stored outside the public web root. Set `PRIVATE_FILE_STORAGE_PATH` to a writable private directory; PDF, DOCX, JPG and PNG files up to 10 MiB are accepted.

## cPanel prerequisites

Create the `oa-dev.troca.ir` subdomain, issue its SSL certificate, and set its document root to `/home/troca/oa-dev.troca.ir/public` before enabling the host guard. Never commit either deployment's `.env` or database credentials.

Production uses the same code and only changes environment values to the production Core and Automation hosts.
