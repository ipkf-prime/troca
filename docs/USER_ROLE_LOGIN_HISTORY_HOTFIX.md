# User Role and Login History Hotfix

This patch fixes an account-view variable collision and adds recent login audit
history.

## Root cause of the role display bug

The account navigation partial used the generic variable `$active` for the
currently selected navigation tab. Profile views also used `$active` for the
active role assignment. Including the partial overwrote the assignment array
with a boolean.

The patch uses dedicated variable names:

- `$accountLinkIsActive`
- `$activeAssignment`
- `$activeAssignmentId`

## Login history

A new `auth_login_history` table records successful authentication sessions:

- login time
- IP address
- browser/platform label
- authentication method
- MFA state
- active role assignment at login
- a SHA-256 hash of the session ID, never the raw session ID

The security page shows the ten most recent successful logins. Existing
historical detail was not stored before this patch, so the list starts
accumulating after deployment. A legacy fallback displays `users.last_login_at`
until the first detailed record is created.
