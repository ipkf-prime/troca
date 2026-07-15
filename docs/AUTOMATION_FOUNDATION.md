# IPKF Automation Foundation

Current version: `0.4.7-automation-foundation-dev`

## Purpose

This milestone adds the database and metadata foundation for دبیرخانه and
organizational correspondence. It is deliberately non-operational: there is no
correspondence UI, letter editor, cartable page, file endpoint, notification,
number allocator, workflow designer, or external API.

`correspondences` is the aggregate root. It owns direction, lifecycle, priority,
confidentiality, channel, organization/unit scope, optional fiscal-year scope,
current version number, registration timestamps, and optimistic lock state.
Registered correspondence has no normal physical-delete contract.

## Foundation tables

- `lookup_domains` and `lookup_values`: reusable dynamic lookup registry.
- `correspondences`: aggregate roots with opaque public references.
- `correspondence_versions`: immutable Persian UTF-8 content snapshots.
- `correspondence_parties`: normalized senders and recipients, including external snapshots.
- `registry_books`: organization/unit/fiscal-year registration books.
- `correspondence_registrations`: historical official and secondary registrations.
- `correspondence_relations`: typed links between correspondence records.
- `correspondence_referrals`: authoritative source for personal and unit cartables.
- `correspondence_events`: append-only action history.
- `private_files`: generic private file metadata without binary content or public URLs.
- `correspondence_attachments`: links private files to correspondence/version records.

No real correspondence, registry book, registration number, referral, event, or
file record is seeded.

## Registration and numbering

Drafting and official registration are separate. Registry books retain the next
sequence and formatting metadata, while registrations retain the issued sequential
and formatted number. Cancellation records metadata and preserves the historical
registration.

The future allocator must start a database transaction, lock the selected
`registry_books` row with `SELECT ... FOR UPDATE`, allocate exactly one sequence,
insert the registration, advance the counter, and commit. No allocator endpoint or
numbering API exists in this milestone.

## Cartable model

`correspondence_referrals` is the sole mutable source from which cartables are
derived. A referral has exactly one primary target: user, unit, or position.
Forwarding creates a child row through `parent_referral_id`; it never rewrites the
parent. Completed referrals remain historical. A unit referral may later be
claimed by an authorized user.

No second inbox or cartable table is created.

## Lookups and permissions

Lookup domains and machine codes are seeded idempotently with Persian labels. The
foundation permissions are granted only to `super_admin` by default:

- `automation.correspondence.view`
- `automation.correspondence.create`
- `automation.correspondence.edit_draft`
- `automation.correspondence.register`
- `automation.correspondence.route`
- `automation.correspondence.cartable.view`
- `automation.correspondence.close`
- `automation.registry.manage`
- `automation.audit.view`

No admin menu or page route is added.

## Deployment

In development, run the protected migration and seeder endpoints with the existing
maintenance key. Both remain disabled when `APP_DEBUG=false`. Diagnostics expose
schema/permission readiness booleans only.

The official Ministry hierarchy remains the only operational canonical geography.
SCI, Rural Cooperation, and bot data are untouched.
