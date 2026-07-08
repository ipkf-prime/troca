# IPKF Auth/RBAC Schema Foundation

Current working version: `0.4.0-auth-rbac-schema-dev`

## Purpose

This milestone adds the database schema and lightweight application structure needed before authentication sessions, MFA verification, or admin panels are implemented.

No login UI, logout flow, admin user, admin panel, Bot, CRM, ERP, Automation, or Marketplace module is included in this milestone.

## Legacy Mapping

The previous national system used broad tables that mixed identity, login, access, and scope data. IPKF normalizes those concepts:

- `Ashkhas` maps into `persons` for identity/contact data and `users` for login account state.
- `AshkhasDastresi` maps into `user_role_assignments` for scoped access grants.
- `SathUser` maps into `roles`, with normalized `role_areas` and `role_kinds`.
- Legacy organization scope maps into a hierarchy-ready `organizations` table with `parent_id`, `depth`, `path`, and access inheritance support.

Legacy codes are retained only where useful for migration (`legacy_code`) and are not used as primary keys.

## Person vs User

`persons` stores real-person and legal-person identity information. It can hold national code, name, registration data, contact details, address, and status.

`users` stores login-account state separately from identity. It stores only `password_hash`; plain passwords are never stored.

Admin users are intentionally not created in this milestone.

## Roles and Permissions

`roles` define access profiles and replace the old access-level concept in a normalized form.

`permissions` define atomic actions by module, resource, and action. `role_permissions` assigns permissions to roles.

The core seed data creates foundational roles and permissions, then assigns all core permissions to `super_admin`.

## Scoped Role Assignment

`user_role_assignments` grants a role to a user inside a scope. Supported scope data includes:

- global and national access
- organization subtree access through `organization_id` and `include_children`
- province, city, county, district, village, company, center, and warehouse scopes
- fiscal-year-specific access

This replaces the legacy access assignment table without copying its denormalized structure.

## Organization Hierarchy

The organizations migration is non-destructive. If `organizations` already exists, it adds missing hierarchy columns and indexes without deleting data or changing existing IDs.

`parent_id`, `depth`, and `path` are present for later tree population. Existing records are not required to have parents yet.

## MFA-Ready Schema

The schema prepares for these methods:

- `totp`
- `sms`
- `email`
- `bot`

The tables are:

- `user_mfa_methods`
- `mfa_challenges`
- `trusted_devices`
- `recovery_codes`

Verification logic is intentionally deferred.

## Future Phases

- `v0.4.1` auth session
- `v0.4.2` MFA
- `v0.5` admin panel shell
