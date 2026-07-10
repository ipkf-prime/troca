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
- `/mfa/status` returns authenticated MFA state after MFA verification.
- `/mfa/verify` remains available as a compatible challenge verification route.
- TOTP confirmation generates recovery codes when none are active.
- `/mfa/recovery-codes/regenerate` requires a valid current TOTP code.
- `/mfa/recovery/verify` consumes one unused recovery code and completes pending MFA login.
- Used recovery codes cannot be reused.
- No TOTP secret, recovery code hash, password hash, session id, CSRF token, cookie value, maintenance key, or database secret is exposed in diagnostics.
- No duplicate MFA tables are introduced.

## v0.4.3 Identity Access Stable Checks

- `/health` returns version `0.4.3-identity-access`.
- Migrations ran successfully.
- Seeders ran successfully.
- Migrations create identity/access foundation tables.
- Login by email, mobile, and username is tested.
- Wrong and unknown credentials fail safely.
- CSRF and unified `AUTH_SESSION_NAME` session handling are tested.
- TOTP MFA is tested.
- Recovery code generation, verification, consumption, and reuse failure are tested.
- `/mfa/status` is tested.
- MFA delivery channels return `channel_not_configured` when disabled or missing provider config.
- Login tokens are issued only with `auth.login_token.issue`.
- `APP_URL` controls login token URL host.
- Dev token links must use `https://dev.troca.ir`.
- `expires_at_utc` is ISO-8601 UTC.
- `expires_at_local` uses `APP_TIMEZONE`.
- Login token issue, consume, reuse failure, and expiry behavior are tested.
- Login token authentication respects MFA.
- `GET /auth/token-login?token=TOKEN_ONLY` consumes a valid token once.
- `POST /auth/token-login` must receive `TOKEN_ONLY`, not the full URL.
- Identity changes require password and token confirmation.
- Identity change username, email, and mobile flows are tested.
- Identity change requests do not apply values before `/identity/change/confirm`.
- In development only, `IDENTITY_DEV_EXPOSE_TOKEN=true` can expose `dev_token` for Postman testing.
- `IDENTITY_DEV_EXPOSE_TOKEN=false` is required in production.
- `MFA_DEV_EXPOSE_OTP=false` remains the default.
- If identity delivery providers are disabled, request responses show `delivery_status=not_configured`.
- Current username, email, or mobile requests return `value_unchanged`.
- Existing username, email, or mobile requests return `value_not_available`.
- Repeating the same active pending identity request does not create unlimited rows.
- Identity confirm re-checks uniqueness, expiry, attempts, and applies the change atomically.
- Username policy allows lowercase canonical `admin`, `admin_test`, `admin123`, and `hamzeh_alaei`.
- Username policy rejects `admin-test`, `admin.test`, `admin test`, `admin@`, Persian/Arabic letters, `_admin`, `admin_`, `ad`, and `admin__test`.
- Invalid username format returns `invalid_identity_value`.
- `username_norm` is lowercase and duplicate username checks use `username_norm`.
- Default active access is the lowest-priority assignment.
- `/access/assignments` lists assignments.
- `/access/switch` changes active assignment.
- `/admin-check` fails with the base user role and passes after switching to `super_admin`.
- UTF-8 Persian RBAC titles remain correct.
- `APP_URL`, `APP_TIMEZONE`, `AUTH_SESSION_NAME`, `IDENTITY_DEV_EXPOSE_TOKEN`, `MFA_EMAIL_ENABLED`, `MFA_SMS_ENABLED`, `MFA_BOT_ENABLED`, `MAIL_*`, `SMS_*`, `KAVENEGAR_*`, and `BALE_*` are documented without secrets.
- Development branches use version-prefixed `-dev` names such as `v0.4.3-identity-access-dev`; release tags omit `-dev`.
- Diagnostics expose only safe booleans.
- Diagnostics do not expose tokens, token hashes, OTPs, recovery codes, TOTP secrets, session IDs, CSRF tokens, passwords, or provider secrets.
