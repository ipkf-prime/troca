# IPKF Privacy and Security

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
