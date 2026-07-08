# IPKF v0.4.1 Auth Session Checklist

Version: `0.4.1-auth-session`

## Runtime Acceptance

- CSRF token endpoint works.
- Login works with `X-CSRF-TOKEN`.
- Session persists after login.
- `/me` returns the current user.
- Roles are returned.
- Persian role title is correct: `مدیر کل سامانه`.
- `/admin-check` permission check works.
- Logout clears auth state.
- `/auth/status` returns `authenticated=false` after logout.

## Security Checks

- No `password_hash` is exposed.
- No session ID is exposed.
- No CSRF token is exposed in diagnostics.
- No cookie values are exposed.
- No maintenance key is exposed.
- Diagnostics remain safe.

## Release Boundaries

- Admin UI is not part of this release.
- MFA runtime is not part of this release.
- Bot, CRM, ERP, Automation, and Marketplace modules are not part of this release.
- No duplicate core classes are introduced.
