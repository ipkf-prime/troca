# IPKF Admin User Management

The users module now supports:

- Creating user and person records in one transaction
- Editing identity, login, contact, verification and account status
- Initial password creation and optional password reset
- Assigning and updating global roles
- Preserving organization/province/scoped role assignments
- Preventing accidental self-deactivation
- Preventing accidental removal of the current super-admin role
- Restricting protected role assignment through `permissions.assign`

Permissions:

- `users.view`: list/detail
- `users.create` or `users.manage`: create
- `users.update` or `users.manage`: edit
- `permissions.assign`: allow assignment of protected roles such as `super_admin`

No migration or seed is required.
