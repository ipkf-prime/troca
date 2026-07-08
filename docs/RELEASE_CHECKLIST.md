# IPKF v0.2.0 Foundation Release Checklist

Version: `0.2.0-foundation`

## Verified Routes

- `/`
- `/unknown`
- `/health`
- `/_diagnostics`
- `/migrate.php?key=DEV_MAINTENANCE_KEY`
- `/seed.php?key=DEV_MAINTENANCE_KEY`

## Security Checks

- `/migrate.php` without key must not run.
- `/seed.php` without key must not run.
- Wrong maintenance key must not run.
- `APP_DEBUG=false` must disable diagnostics, migration, and seeder entry points in production.
- `.env` must never be committed.
- `DEV_MAINTENANCE_KEY` must never be committed.

## Foundation Checks

- Environment loader reports loaded in diagnostics.
- Config loader reports loaded in diagnostics.
- Database config reports loaded without exposing secrets.
- Database connection reports available on the dev environment.
- Migration system reports available.
- Seeder system reports available.
- Runtime check table exists after migration.
- Runtime check row exists after seeding.
- Rerunning seed does not duplicate the `foundation_v0_2` row.

## v0.4.2 MFA Foundation Runtime Checks

- `/health` returns version `0.4.2-mfa-foundation-dev`.
- `/_diagnostics` remains safe and includes MFA runtime booleans.
- `mfa_runtime_available=true`.
- `mfa_totp_available=true`.
- `mfa_recovery_codes_available=true`.
- `mfa_trusted_devices_available=true`.
- `mfa_routes_available=true`.
- Existing Auth Session routes still work.
- CSRF remains enabled for Auth and MFA POST routes.
- `AUTH_SESSION_NAME=ipkf_session` remains the only auth session cookie.
- Login without enabled MFA remains unchanged.
- Login with enabled MFA sets pending MFA session state and does not set `auth_user_id`.
- MFA challenge verification completes authentication.
- No TOTP secret, recovery code hash, password hash, session id, CSRF token, cookie value, maintenance key, or database secret is exposed in diagnostics.
- No duplicate MFA tables are introduced.
