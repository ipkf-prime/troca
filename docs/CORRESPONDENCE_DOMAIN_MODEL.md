# IPKF Correspondence Domain Model

Current version: `0.4.7-automation-foundation-dev`

## Aggregate boundary

`correspondences` is the aggregate root. A root belongs to one organization and
optionally one organizational unit and fiscal year. Direction, status, priority,
confidentiality, and channel are resolved through dynamic lookup machine codes.
The root stores the current version number and optimistic `lock_version`, but not
the mutable body of a letter.

## Immutable versions

Each revision is an immutable `correspondence_versions` snapshot containing its
subject, Persian UTF-8 body, optional summary and change note, SHA-256 content
checksum, author, and creation time. `(correspondence_id, version_number)` is
unique. The aggregate's `current_version_number` identifies the selected revision.

Draft creation, revision, and official registration are separate future commands.

## Parties

Parties are normalized by role and target kind. A party points to exactly one of:

- a person;
- an organization;
- an organizational unit; or
- an external snapshot.

External correspondents use display/contact snapshots. The system must not create
fake `persons` or `organizations` rows for them.

## Registry and relations

Registry books are scoped to an organization and optionally a fiscal year and unit.
Sequence and formatted numbers are unique inside each book. At most one uncancelled
registration exists for the same correspondence and registration role. Cancelled
registrations remain historical.

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
replaced by a mutable inbox row.

## Events and attachments

`correspondence_events` is append-only. Relational columns remain the source of
truth; `safe_metadata_json` may contain only non-secret contextual metadata.

`private_files` stores private-provider metadata, opaque reference, storage key,
filename, MIME type, byte size, checksum, uploader, scan status, and lifecycle
status. No binary content or public URL is stored. `correspondence_attachments`
links that metadata to an aggregate and optionally a specific immutable version.

## Deferred commands

Operational services, transaction-safe number allocation, editing, routing,
cartable queries, upload/download, notifications, signatures, OCR, document/PDF
conversion, workflow/SLA engines, public tracking, and integrations are deferred.
