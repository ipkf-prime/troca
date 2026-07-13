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
