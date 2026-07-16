# Automation Correspondence Demo Slice

Current version: `0.4.8-platform-commercial-foundation-dev`

This slice adds the first guarded operational correspondence demo for the Admin panel. It is intentionally narrow: it demonstrates draft creation, draft revision, parties, immutable versions, and append-only history without adding workflow, cartable operations, registration numbering, attachment upload/download, delivery, signature, tracking, or external APIs.

## Runtime Contract

Operational correspondence services must use the dedicated Automation runtime only. The runtime resolves `automation.primary` through the guarded Automation connection resolver and fails closed when the dedicated connection, standalone schema, metadata, internal foreign keys, cutover guard, or schema parity prerequisites are not available.

The demo must not:

- fall back to the legacy Core-hosted Automation tables
- use Core PDO for Automation operational tables
- use cross-database SQL or joins
- introduce distributed transactions
- dual-write or synchronize legacy and dedicated Automation data
- expose database names, hosts, credentials, DSNs, SQL, numeric row IDs, or internal table details

Core references are validated before the Automation transaction by existing reference contracts. Only scalar reference values and safe display snapshots enter Automation operational rows.

## Routes

Admin routes are server-rendered, RTL, Persian, and permission guarded:

- `GET /admin/automation`
- `GET /admin/automation/correspondences`
- `GET /admin/automation/correspondences/create`
- `POST /admin/automation/correspondences`
- `GET /admin/automation/correspondences/{public_reference}`
- `GET /admin/automation/correspondences/{public_reference}/edit`
- `POST /admin/automation/correspondences/{public_reference}/versions`

Public URLs use `public_reference`. Numeric database IDs must not be exposed.

## Draft Lifecycle

Creating a draft writes one Automation transaction that creates:

- the correspondence aggregate
- the first immutable correspondence version
- correspondence parties
- an initial `created` event

Editing is allowed only while the correspondence is still a draft. Each edit creates a new immutable version, updates the selected current version, appends a `revised` event, and uses the lock version as a stale-update guard.

## Parties

The demo supports these party targets:

- internal person
- internal user, stored as the mapped person reference
- organization
- organization unit
- external correspondent snapshot

External party snapshots are stored as display/contact text only. Internal party labels are resolved for display without cross-database joins.

## Diagnostics

APP_DEBUG diagnostics expose boolean-only readiness flags for repositories, query/command services, draft creation, versioning, party runtime, event runtime, routes, dashboard, list/create/detail UI, RBAC guards, dedicated runtime usage, legacy runtime block, cross-database query absence, and operational demo availability.

Diagnostics must not expose counts, IDs, correspondence content, parties, tokens, session data, credentials, DSNs, SQL, file paths, or database topology.

## Deferred Scope

Still deferred:

- registry numbering and registry-book operations
- inbox/cartable operational workflow
- referral command UI
- official correspondence dispatch/receipt lifecycle
- attachments upload/download
- document generation, QR, signature, OCR, and tracking
- notification delivery and outbox workers
- public tracking or external APIs