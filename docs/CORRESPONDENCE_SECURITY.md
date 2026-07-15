# IPKF Correspondence Security

Current version: `0.4.7-automation-foundation-dev`

## Authorization boundary

Correspondence capabilities use explicit RBAC permissions and the current active
access assignment. Only `super_admin` receives the new permissions by default.
Future services must additionally enforce organization, unit, fiscal-year, party,
and referral scope before returning any content.

No operational route, menu item, public endpoint, or external API is introduced by
this foundation.

## History integrity

- Registered correspondence must not be physically deleted by normal behavior.
- Versions are immutable and identified by a unique per-correspondence number.
- Registrations are cancelled with metadata; issued numbers are not removed or reused.
- Forwarding creates child referrals and preserves parent/completed rows.
- Events are append-only and have no update timestamp contract.
- Relations and attachment links preserve correspondence history.

The future registration allocator must use one database transaction and a row lock
on the registry book. Application-side read/increment/write without locking is not
acceptable.

## Private file contract

Binary content stays outside MariaDB and outside the public document root. The
database stores only private storage metadata. Physical storage keys, checksums,
raw filenames, MIME details, and file identifiers must never appear in diagnostics
or public URLs.

Upload/download endpoints, authorization, content disposition, type/size
validation, malware scanning integration, retention, legal hold, and deletion are
deferred and require separate security review.

## Metadata and logging

Event `safe_metadata_json` must never contain passwords, session IDs, CSRF tokens,
maintenance keys, access tokens, provider secrets, private file paths, raw document
contents, or unnecessary personal data. It supplements, but never replaces,
relational source-of-truth fields.

Diagnostics expose booleans only. Error responses and logs must not expose SQL,
schema internals, correspondence content, parties, references, IDs, storage keys,
or record counts.

## Deferred security controls

Operational audit review, retention, records classification policy, legal hold,
electronic signature/seal, encryption/key management, watermarking, DLP, OCR,
public tracking, external delivery, and notification channels are outside v0.4.7.
