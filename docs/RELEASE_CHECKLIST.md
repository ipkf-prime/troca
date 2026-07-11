# IPKF Release Checklist

## v0.4.4 Admin Panel Shell UI Checks

- `/health` returns `0.4.4-admin-panel-shell-dev`.
- `/admin/login` loads the RTL Persian login UI.
- Login works with email, mobile, and username.
- `/admin/mfa` uses one-time-password wording for users.
- `/admin/mfa/recovery` loads and submits recovery codes safely.
- `/admin/forgot-password` returns a generic safe response.
- `/admin/dashboard` loads after authentication and shows active role.
- `/admin/access` lists assignments and switches active role.
- `/admin/profile`, `/admin/account`, `/admin/security`, `/admin/password`, and `/admin/my-theme` load safely.
- Password change verifies the current password and never exposes password hashes.
- Sidebar contains only system navigation.
- User/account actions are in the dropdown menu.
- Dropdown closes on outside click and Escape.
- Mobile sidebar opens with hamburger and closes with overlay/Escape.
- `/admin/theme` is tabbed and remains CSRF protected.
- `/admin/theme` saves system theme settings only for users with `admin.theme.manage`.
- `/admin/my-theme` saves personal display theme settings for the current user only.
- `/admin/my-theme` shows built-in system presets, the current active personal theme, save, and reset actions.
- Personal theme controls do not change global brand name, logo, default avatar, or footer text.
- One active personal theme per user is supported in v0.4.4; multi-profile theme libraries are reserved for a future phase.
- Theme preset cards are clickable, keyboard-safe radio controls and persist after save.
- System branding changes update header, sidebar, and login.
- Rerunning seed does not overwrite healthy customized system branding.
- User A's `/admin/my-theme` preset does not affect User B.
- Users without personal overrides inherit the latest `/admin/theme` system preset.
- Users with personal overrides keep their own preset until personal reset.
- Changing system preset visibly changes sidebar, active menu, content surface, cards, and shadows.
- Changing a preset clears stale visual token overrides for that scope so the new preset applies.
- Invalid logo/avatar paths are rejected.
- `POST /admin/theme/reset` with `scope=user` clears only the current user's personal theme.
- `POST /admin/theme/reset` with `scope=system` requires `admin.theme.manage`.
- Admin CSS/JS/icon assets load from canonical `/assets/admin/...` paths.
- Local icon/webfont support uses `/assets/admin/css/icons.css` and `/assets/admin/webfonts/`.
- No CDN or external font/icon URL is used.
- Coming Soon page links to `/admin/login`.
- Diagnostics include safe admin UI booleans.
- Diagnostics include theme scope, reset, branding, canonical asset, local icon, and webfont path booleans.
- Diagnostics include `current_theme_resolver_available=true` and `theme_user_scope_supported=true`.
- Diagnostics include `admin_theme_resolved_source`, `admin_theme_system_preset_exists`, and `admin_theme_personal_preset_exists_for_current_user`.
- Diagnostics include `admin_theme_forensics_available=true` and `admin_theme_runtime_fix_version=theme-runtime-forensics-v1`.
- `/admin/theme/debug` works only in development/debug for an authorized admin and shows system rows, current user personal rows, resolved source, active preset, generated CSS variables, and loaded asset URLs.
- System theme test: login as `super_admin`, select `neutral_light` in `/admin/theme`, save, refresh, verify the UI visibly changes and `/admin/theme/debug` shows system active preset as `neutral_light`.
- Personal isolation test: User A selects `green_gold` in `/admin/my-theme`, verifies personal source in `/admin/theme/debug`, logs out, then User B logs in and must not see `green_gold` unless the system theme is `green_gold`.
- Reset test: User A resets personal theme and returns to the system theme while system rows remain unchanged.
- Mobile navigation closes when resizing back to desktop width and does not remain stuck open after viewport changes.
- No password hash, session id, CSRF token, recovery code, OTP, provider secret, or maintenance key is exposed.
- Existing JSON endpoints still work.
- Persian UTF-8 rendering remains correct.

## v0.2.0 Foundation Release Checklist

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

## v0.4.4 Admin Panel Shell Checks

- `/health` returns version `0.4.4-admin-panel-shell-dev`.
- `GET /admin/login` loads a RTL Persian login page.
- Admin login works with username, email, and mobile.
- Admin login keeps CSRF protection enabled.
- MFA-required login redirects to `/admin/mfa`.
- Valid TOTP completes admin login.
- Recovery code fallback is available through `/admin/mfa`.
- `/admin/dashboard` requires authentication.
- `/admin/dashboard` shows auth status, active role, MFA status, and version.
- `/admin/access` lists role assignments.
- `/admin/access` can switch active role with CSRF protection.
- `/admin/profile` displays safe user profile fields.
- `/admin/theme` loads the dynamic admin theme page.
- `/admin/theme` shows typography controls.
- `/admin/theme` shows grouped sections for preset, branding, header, sidebar, dashboard content, footer, typography, and advanced settings.
- Persian labels on `/admin/theme` render correctly and do not show question marks.
- `official_emerald`, `modern_light`, `classic_green`, `neutral_light`, and `green_gold` presets are available and visually distinct.
- `official_emerald` uses a dark green sidebar, yellow active item, light content area, white cards, and soft shadow.
- Base user active role cannot update the admin theme.
- `super_admin` active role can update the admin theme with `admin.theme.manage`.
- Invalid color, radius, font family, font size, logo/avatar path, and unsafe CSS-like values are rejected.
- External `http/https`, `javascript:`, `data:`, `../`, `url()`, and script-like logo/avatar values are rejected.
- Changing presets updates the admin UI.
- Admin CSS loads from `public/assets/admin/css/admin.css`.
- Admin asset folders exist under `public/assets/admin/`.
- Admin upload folders exist under `public/uploads/admin/`.
- Upload folders include `.htaccess` protection to block PHP/script execution where Apache supports it.
- Default logo exists at `/assets/admin/images/logos/default-logo.svg`.
- Default avatar exists at `/assets/admin/images/avatars/default-avatar.svg`.
- `/admin/login` and `/admin/dashboard` use admin typography tokens.
- Optional `Vazirmatn.woff2` can be placed under `public/assets/admin/fonts/` without requiring a CDN.
- Header displays brand name, user avatar, user display name when enabled, and active role when enabled.
- Sidebar is RTL on the right, includes Persian menu labels, and highlights the active item.
- Footer displays the Troca ownership text and year when enabled.
- `/admin/logout` logs the user out.
- Existing JSON endpoints continue to work.
- No password hash, session ID, MFA secret, recovery code, provider secret, or business data is exposed.
- `/_diagnostics` may report `admin_panel_shell_available=true`, `admin_theme_available=true`, `admin_theme_active_preset`, `admin_assets_available=true`, `admin_typography_available=true`, and `admin_theme_persian_ok=true`.
