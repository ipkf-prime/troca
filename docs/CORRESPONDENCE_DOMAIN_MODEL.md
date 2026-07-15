# IPKF Correspondence Domain Model

Current version: `0.4.7-automation-foundation-dev`

## Aggregate boundary

`correspondences` is the aggregate root. A root belongs to one organization and
optionally one organizational unit and fiscal year. Direction, status, priority,
confidentiality, and channel are resolved through dynamic lookup machine codes.
The root stores a nullable `current_version_id`, the denormalized current version
number, and optimistic `lock_version`, but not the mutable body of a letter. A
three-column composite foreign key ensures the selected version ID and number both
identify the same snapshot in the same aggregate. An initial draft keeps the ID
null and number zero. Future write services update both selectors together in one
transaction.

## Immutable versions

Each revision is an immutable `correspondence_versions` snapshot containing its
subject, Persian UTF-8 body, optional summary and change note, SHA-256 content
checksum, author, and creation time. `(correspondence_id, version_number)` is
unique, while `(correspondence_id, id)` is the candidate key used by aggregate
foreign keys. The aggregate's selected ID and current version number identify the
same revision under the write-service contract.

Draft creation, revision, and official registration are separate future commands.

## Parties

Parties are normalized by role and target kind. A party points to exactly one of:

- a person;
- an organization;
- an organizational unit; or
- an external snapshot.

External correspondents use display/contact snapshots. The system must not create
fake `persons` or `organizations` rows for them. Internal targets cannot carry
external snapshot values; external targets cannot carry internal IDs and require a
non-blank display name.

## Registry and relations

Registry books are scoped to an organization and optionally a fiscal year and unit.
Generated scope keys normalize nullable fiscal-year and unit IDs to zero for the
unique index only, so MariaDB also rejects duplicate books in null scopes without
requiring sentinel rows. Sequence and formatted numbers are unique inside each
book. At most one uncancelled registration exists for the same correspondence and
registration role. Cancelled registrations remain historical.

Correspondence relations are typed and directional. Self-relations and duplicate
identical relations are rejected. Correspondence history is protected by
`RESTRICT` references rather than cascade deletion.

## Referrals and cartables

Referrals form a parent-child forwarding tree. Exactly one primary target is set:
user, unit, or position. Personal cartables are queries over active user-targeted
referrals; unit cartables are queries over active unit-targeted referrals. Claiming
a unit referral records the claimant without changing its original target.

Forwarding inserts a child referral. Seen, claimed, returned, and completed state
timestamps remain on the original row. Completed rows are historical and are not
replaced by a mutable inbox row. Composite references ensure a parent referral and
an event referral always belong to the same correspondence.

## Events and attachments

`correspondence_events` is append-only. Relational columns remain the source of
truth; `safe_metadata_json` may contain only non-secret contextual metadata.

`private_files` stores private-provider metadata, opaque reference, storage key,
filename, MIME type, byte size, checksum, uploader, scan status, and lifecycle
status. No binary content or public URL is stored. `correspondence_attachments`
links that metadata to an aggregate and optionally a specific immutable version.
The optional version link is composite, so an attachment cannot point to a version
owned by another correspondence.

## Lookup integrity

`lookup_domains` and `lookup_values` are the single generic lookup registry used by
this foundation. Value codes are unique only within a domain, so correspondence
code columns deliberately do not use invalid single-column foreign keys to
`lookup_values.code`. Future domain services must resolve and validate every code
against its required domain before a write transaction is committed.

## Deferred commands

Operational services, transaction-safe number allocation, editing, routing,
cartable queries, upload/download, notifications, signatures, OCR, document/PDF
conversion, workflow/SLA engines, public tracking, and integrations are deferred.
