# Layered module configuration

IPKF uses stable module identifiers (`core`, `automation`, and `work`). Domains,
document roots, database names, and brand labels are deployment data and must not
be used as module identities.

Each deployed copy has a minimal `.env` descriptor:

```env
IPKF_SHARED_ENV=/home/account/config/ipkf-development.env
IPKF_MODULE=work
```

The shared file is outside all public document roots and contains common secrets,
session settings, module URLs, allowed hosts, and database mappings. `APP_URL` is
derived at runtime from `IPKF_MODULE` and the matching `<MODULE>_APP_URL` entry.
Changing a domain therefore requires editing the shared registry and the hosting
domain's document-root mapping, not application code.

Deployment destinations are stored separately in
`/home/troca/config/ipkf-deploy.env`. `.cpanel.yml` reads logical destination
variables from that server-owned file and contains no domain names.

Before the first deployment using this layout:

1. Copy `deploy/ipkf-deploy.env.example` to the server-owned deployment registry.
2. Copy `deploy/ipkf-shared.env.example` to the server-owned shared runtime file.
3. Put a two-line descriptor in each module's deployed `.env`.
4. Keep the shared runtime file and deployment registry outside document roots,
   deny web access, and restrict filesystem permissions to the hosting account.

The database remains the operational module registry when available. Environment
values are the safe bootstrap and recovery fallback when Core is unavailable.
