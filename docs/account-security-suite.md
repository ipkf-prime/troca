# IPKF Account and Security Suite

This package completes the current account area:

- Compact responsive account navigation
- Minimal profile and account information views
- TOTP authenticator setup, replacement and disable flow
- Recovery-code generation and regeneration
- Password re-authentication for sensitive MFA operations
- Correct password-change flow with stronger validation
- Session ID rotation after password change
- Current-session information
- Compact responsive role switcher
- Compact personal-theme selector

TOTP replacement is safe: the existing authenticator remains active until the
new secret has been confirmed. The pending setup expires after 10 minutes.

No migration or seed is required. Existing `user_mfa_methods` and
`recovery_codes` tables are used.
