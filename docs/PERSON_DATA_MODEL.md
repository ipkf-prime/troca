# IPKF Person Data Model

Current milestone: `0.4.6-admin-users-organization-dev`

## Model boundaries

IPKF keeps real-world identity separate from authentication:

- `persons` stores the core identity shared by application domains.
- `person_profiles` stores optional extended identity attributes.
- `users` stores authentication-account data only.
- `person_contacts` stores additional structured communication channels.
- `person_addresses` stores optional physical and postal addresses.

Existing records remain valid without a profile, contact, or address row. This phase does not move or rewrite data already stored in `persons` or `users`.

## Existing canonical fields

The current `persons` schema already contains `national_code`, `father_name`, `birth_date`, registration fields, primary contact fields, province/city references, and a legacy address. These fields are not duplicated in `person_profiles`.

- `persons.national_code` remains the canonical national identifier. The migration adds nullable uniqueness only when existing data makes that safe.
- `persons.father_name` remains the canonical father name.
- `persons.birth_date` remains the canonical birth date and is already stored as a standard database `DATE`.
- Jalali conversion is an application and UI concern; Jalali strings must never be persisted as database dates.

`person_profiles` is one-to-one with `persons` through a unique `person_id`. It contains complementary fields such as birth place, identity document number/serial, and an optional description.

## Contact model

`contact_types` is a configurable lookup. Codes and titles are data, not a PHP enum. A later seed or administration phase may define mobile, landline, email, fax, website, emergency, or other contact types.

`person_contacts` supports multiple values per person and includes:

- contact type
- original and normalized values
- optional label
- primary and verification state
- active/inactive status

Existing authentication email/mobile values are not moved. Any future synchronization with a selected primary contact must be explicit and audited.

## Address model

`address_types` is a configurable lookup and is intentionally left without mandatory business seed data in this phase.

`person_addresses` supports multiple addresses per person. It stores address type, existing province/city references, district, address line, postal code, primary state, and status. Telephone values remain in `person_contacts`.

Province and city relations reuse the existing `provinces` and `cities` lookups when those tables are present and structurally compatible. No duplicate geographic lookup is introduced.

County/shahrestan display requires a genuine county relationship and lookup table. Until the dynamic geographic foundation defines that relationship, county values must display as `—`. City must never be copied or inferred as a county fallback.

## Validation readiness

Future application validation must include:

- National identifier: normalize Persian/Arabic digits, apply configurable Iranian national-code validation, enforce uniqueness when supplied, and mask in ordinary lists.
- Birth date: accept Jalali UI input when needed, convert to a standard database date, and reject future dates.
- Postal code: normalize digits and use deployment-specific validation instead of assuming one country format.
- Contact values: apply type-specific normalization and validation, including phone/mobile normalization, email validation, and verification state.

This schema task does not add validators, forms, synchronization, or CRUD services.

## Semantic display rule

Foreign keys and technical codes are stored internally. User interfaces must display Persian semantic labels, never raw numeric ids as fallback. Usernames must have a visible label such as `نام کاربری` when shown near identity summaries. Missing optional values display `—`; broken references display `نامشخص`.

## Admin detail workspace

Admin user detail uses the reusable Entity Detail Workspace. Identity fields, contacts, addresses, account/security summaries, access assignments, and organization appointments are loaded per active route tab instead of rendering the full person/account dataset on one long page. Empty contact and address datasets render clean Persian empty states instead of empty tables.

Read-only identity display hides optional empty fields to avoid repeated dash-only rows. A compact incomplete-information notice may be shown when profile data is not complete. Future create/edit forms may still show all editable fields with validation and help text.

## Deferred work

Create/edit forms, permissions for sensitive profile data, masking, audit history, verification workflows, and synchronization remain deferred. The next recorded phase is Dynamic organization foundation.
