# Entity Detail Workspace

The Entity Detail Workspace is the reusable admin detail-page pattern for IPKF.

It provides:

- Compact entity header with avatar/logo/icon, title, subtitle, badges, metadata, and back link.
- Route-based tabs instead of query-string tabs.
- Permission-aware tab metadata with a separate permission field per tab.
- Active tab state derived from the current route.
- Tab-specific data loading so inactive tab datasets are not queried and hidden with CSS.
- Desktop tab bar with wrapping.
- Mobile section navigator using a vertical disclosure menu, not a horizontal tab strip.
- No full-page horizontal overflow on mobile.

## Presentation Rules

- Compact headers are preferred over hero-style detail cards.
- Mobile fields use a compact label/value list instead of one oversized card per field.
- Optional empty fields are hidden in read-only mode when they add visual noise.
- A concise incomplete-information notice may be shown when optional profile data is not complete.
- Forms may still show all editable fields later; this rule is for read-only detail display.
- Empty states must be content-driven and compact.
- Raw IDs and technical schema/table names must never be visible in the user-facing UI.
- Access scopes must be displayed semantically, such as global, organization, province, city, unit, or organization/unit summaries.

## User Detail Routes

Current user detail routes are:

- `/admin/users/{id}` for overview.
- `/admin/users/{id}/identity` for identity data.
- `/admin/users/{id}/contacts` for contacts and addresses.
- `/admin/users/{id}/account` for account and security.
- `/admin/users/{id}/access` for read-only role assignments.
- `/admin/users/{id}/appointments` for organization assignments and appointments.

All current user detail tabs require `users.view`. The workspace supports separate tab permissions for later phases, such as `users.security.view`, `users.access.view`, and `users.appointments.view`.

## Mobile Rules

Mobile detail pages must not use a horizontally scrolling tab bar. Tables should become cards or vertical lists, long Persian labels and values must wrap safely, and page containers must keep `min-width: 0` and `max-width: 100%`.

## Future Reuse

The same workspace is intended for later organization detail pages with tabs for summary, identity and registration, contacts, organization structure, governance and ownership, authorities, and documents.
