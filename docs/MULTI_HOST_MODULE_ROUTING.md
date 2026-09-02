# Multi-host module routing

The platform uses one versioned release across independent runtime hosts.

Core provides the central portal and authentication entry point.
Application modules are registered dynamically and may use dedicated
hosts, databases and runtime configuration.

Adding a module is a registry and deployment operation, not a source-code
change.

## Development topology

Current development runtimes:

- Core: `https://dev.troca.ir`
- Automation: `https://oa-dev.troca.ir`
- Work: `https://work-dev.troca.ir`
- Ticketing: `https://ticketing-dev.troca.ir`

The same release is deployed to every runtime. Each runtime keeps its own
untracked `.env`, database settings, storage configuration and secrets.

## Deployment contract

Shared deployment configuration defines:

- `DEPLOYMENT_ID`
- Core application URL
- shared security policy
- optional non-module hosts

Each runtime identifies itself using `IPKF_MODULE`.

Session names are derived from `DEPLOYMENT_ID` and `IPKF_MODULE`.
Authentication cookies remain host-only because `AUTH_COOKIE_DOMAIN`
is empty.

## Dynamic routing policy

- Core host comes from deployment configuration.
- Active module hosts come dynamically from the module registry.
- Module base URL, callback, permission and route come from registry data.
- `ALLOWED_APP_HOSTS` is only for optional non-module hosts.
- Unknown hosts are rejected when the host guard is enabled.
- One-login UX uses generic module SSO, not a shared parent-domain cookie.
- Each module creates and owns its independent host-only session.

## Correspondence document workspace

- Letter templates are selected from the active versioned catalog at `/admin/automation/templates`.
- A template snapshot is fixed on each correspondence version so later template changes do not alter historical letters.
- Copy and blind-copy recipients use correspondence party roles.
- `reply_to` is presented as «عطف / پاسخ به» and `follow_up` as «پیرو»; the referenced letter number and date are shown with the current content.
- Attachments are stored outside the public web root. Set `PRIVATE_FILE_STORAGE_PATH` to a writable private directory; PDF, DOCX, JPG and PNG files up to 10 MiB are accepted.

## cPanel and deployment prerequisites

Before enabling any runtime host:

- create its domain or subdomain;
- issue and validate SSL;
- point the document root to that runtime's `public` directory;
- deploy the approved release;
- configure its local runtime descriptor;
- register and activate the module when applicable;
- verify host guard, SSO and session isolation.

Never commit runtime `.env` files, database credentials or secrets.

## Session and SSO contract

Development session names are:

- `ipkf_dev_core`
- `ipkf_dev_automation`
- `ipkf_dev_work`
- `ipkf_dev_ticketing`

Each session is host-only. Generic module SSO provides one-login UX.

Module permission, base URL, callback URL and route are resolved from the
dynamic module registry.

A newly registered active module does not require a source-code change or
an entry in `ALLOWED_APP_HOSTS`.

Production uses the same tested release and changes only approved
deployment configuration, registry data, database configuration, storage
configuration and secrets.
