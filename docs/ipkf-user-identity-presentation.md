# IPKF user identity presentation standard

Internal identifiers such as `users.id`, `user:17`, and labels such as `کاربر #17` are implementation details. They must not be rendered in forms, tables, cards, comments, or activity timelines.

Visible identity priority:

1. Email
2. Mobile
3. Full name
4. Username
5. Generic `کاربر`

Rules:

- Internal IDs remain valid for database relations, route parameters, form values, audit payloads, and API contracts.
- Views resolve display labels through `UserIdentityLabelService`.
- Modules with independent databases resolve identity data from `core.primary`.
- Existing snapshots may remain stored for audit stability, but current UI should prefer the live Core identity label.
- A missing contact must never fall back to a numbered user label.
