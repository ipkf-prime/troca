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
AUTH_COOKIE_DOMAIN=.troca.ir
AUTH_COOKIE_SECURE=true
AUTOMATION_DB_MODE=dedicated
```

`APP_URL` remains deployment-specific: use `https://dev.troca.ir` in the Core `.env` and `https://oa-dev.troca.ir` in the Automation `.env`.

## Routing policy

- Automation URLs are generated from `AUTOMATION_APP_URL`.
- Core URLs are generated from `CORE_APP_URL`.
- Automation paths requested on another allowed host redirect to the Automation host.
- Non-Automation admin paths requested on the Automation host redirect to Core.
- Login, logout, password recovery and MFA remain available on the Automation host.
- Unknown hosts are rejected with HTTP 421 when the guard is enabled.
- The shared cookie is HTTPS-only, HTTP-only and scoped to `.troca.ir`; authorization and CSRF checks remain active on both hosts.

## cPanel prerequisites

Create the `oa-dev.troca.ir` subdomain, issue its SSL certificate, and set its document root to `/home/troca/oa-dev.troca.ir/public` before enabling the host guard. Never commit either deployment's `.env` or database credentials.

Production uses the same code and only changes environment values to the production Core and Automation hosts.
