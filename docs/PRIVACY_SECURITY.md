# IPKF Privacy and Security

## Datetime Safety

Runtime diagnostics may expose safe timezone policy names such as `UTC` and `Asia/Tehran`, but must not expose user timestamps, login history, token values, token hashes, session identifiers, CSRF tokens, provider secrets, or database credentials. Persisted instant timestamps use UTC. Display conversion happens once through the shared application clock.

## Sensitive person data

The following values are sensitive personal data:

- national identifier
- birth date
- identity document number and serial
- full physical/postal address
- postal code
- contact methods

Future UI and service implementations must:

- mask sensitive values in list pages and summaries;
- require separate view and manage permissions;
- audit changes to sensitive fields;
- avoid logging raw sensitive values;
- never expose sensitive records in diagnostics;
- use prepared statements for database access;
- escape all rendered output;
- return semantic lookup labels instead of raw foreign keys.

Diagnostics may report schema-readiness booleans only. They must not report record counts, column contents, national identifiers, contact values, addresses, user details, migration SQL, tokens, or secrets.

No custom cryptography is introduced by the person data foundation. Field encryption may be added only after the framework has a reviewed encryption service, key-management policy, rotation plan, and operational recovery procedure.

## Deferred controls

Fine-grained permissions, masking policy, audit events, retention/deletion policy, export controls, and breach-response procedures must be defined before person profile CRUD is released.

## Geographic privacy

Canonical geographic reference rows and administrative hierarchy definitions are generally non-sensitive. Linking a person or organization to a specific physical address, postal code, or precise coordinate creates sensitive contextual data.

Diagnostics must expose geography schema booleans only. They must not expose location rows, hierarchy contents, user or organization addresses, postal codes, coordinates, IDs, or record counts. Application logs must not contain address values. Future address access requires permissions, masking, and auditing separate from general geographic-reference viewing.
## Multi-source import and provenance

Source files and import artifacts are administrative maintenance data and must remain outside the public web root. Sanitized filenames may be stored in snapshot metadata, but diagnostics must not expose filenames, hashes, paths, record counts, code values, locations, or source rows.

Raw metadata and staging payloads must not contain credentials, secrets, or personal information. Future source upload, import, review, and mapping UI requires dedicated permissions, CSRF protection, audit records, size/type validation, and controlled retention. Canonical writes require explicit reviewed workflows; title-only automatic matching is forbidden.

The Ministry runner is development-only, maintenance-key protected, basename-only, and bound to `storage/imports/geography/ministry`. It performs no upload and no canonical write. Its public response omits filenames, source rows, location names, identifiers, full codes/hashes, paths, SQL, and internal database IDs. Real CSV/XLSX datasets are ignored by Git.

The SCI adapter follows the same boundary under
`storage/imports/geography/statistical-center`. It preserves source codes and opaque
DIAG metadata only in private staging. Public responses and diagnostics remain
aggregate/boolean-only and do not expose filenames, hashes, paths, codes, titles,
DIAG values, source rows, or database identifiers.
