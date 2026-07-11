# IPKF Admin Panel Shell

Current version: `0.4.4-admin-panel-shell-dev`

## Purpose

This milestone adds the first server-rendered admin panel shell on top of the existing IPKF Auth, MFA, RBAC, session, and active access foundations.

It does not add CRM, Bot, ERP, Automation, Marketplace, business workflows, admin CRUD, or profile editing features.

## Routes

- `GET /admin`
- `GET /admin/login`
- `POST /admin/login`
- `GET /admin/mfa`
- `POST /admin/mfa`
- `GET /admin/mfa/recovery`
- `POST /admin/mfa/recovery`
- `GET /admin/forgot-password`
- `POST /admin/forgot-password`
- `GET /admin/dashboard`
- `GET /admin/profile`
- `GET /admin/account`
- `GET /admin/security`
- `GET /admin/password`
- `POST /admin/password`
- `GET /admin/my-theme`
- `GET /admin/access`
- `POST /admin/access`
- `GET /admin/theme`
- `POST /admin/theme`
- `GET /admin/settings`
- `GET /admin/pages`
- `GET /admin/reports`
- `GET /admin/support`
- `GET /admin/logout`

## Auth Flow

`/admin/login` is an RTL Persian login page. The `login` field accepts email, mobile, or username through the existing `AuthService` and `UserRepository` login resolution.

The login form is CSRF protected with the existing framework `_token` flow. Invalid credentials return a generic safe error.

If the user does not require MFA, successful login redirects to `/admin/dashboard`.

## MFA Flow

When `AuthService` returns `mfa_required=true`, the admin shell redirects to `/admin/mfa`.

`/admin/mfa` uses Persian user-facing labels for one-time password verification. It avoids technical MFA/TOTP wording in the UI.

`/admin/mfa/recovery` supports recovery-code login when the user cannot access a one-time password.

Internally the shell still reuses existing MFA services:

- TOTP code
- recovery code fallback

The page uses the existing `MfaService` pending challenge flow. Valid verification completes the existing auth session and redirects to `/admin/dashboard`.

## Dashboard

`/admin/dashboard` requires authentication and shows only shell-level operational data:

- current auth status
- active role
- MFA status
- framework version
- available role assignments

It does not show CRM, Bot, ERP, Automation, Marketplace, or business data.

## Active Access Switching

`/admin/access` lists the current user's active assignments and uses the existing `AccessService::switchTo()` method.

After switching, `active_role_assignment_id` is updated in the session. Permission-sensitive checks such as `/admin-check` continue to depend on the active role.

The default active role remains the lowest-privilege assignment selected by the Auth foundation.

## Profile Shell

`/admin/profile` safely displays:

- name
- username
- email
- mobile
- active role
- MFA status

Profile editing is intentionally not implemented in this phase. Future profile edits should reuse the existing identity change verification flow.

## Account Pages

The shell includes account-focused pages:

- `/admin/profile`
- `/admin/account`
- `/admin/security`
- `/admin/password`
- `/admin/my-theme`

`/admin/password` verifies the current password, requires confirmation, and stores only a new password hash. Password recovery UI is available at `/admin/forgot-password` and returns a generic safe message without user enumeration.

## Two-Part Navigation

Navigation is split intentionally:

- The right sidebar contains only system sections: dashboard, access, admin theme, settings, internal pages, reports, and support.
- User and account actions live in the avatar/name dropdown: profile, account, security, one-time password, password change, personal display theme, and logout.

The dropdown closes on outside click and Escape. On mobile, the sidebar opens with a hamburger button and closes with the overlay, close button, or Escape.

## Theme Page UX

`/admin/theme` is tabbed:

- پوسته‌ها
- برندینگ
- هدر
- سایدبار
- داشبورد
- فوتر
- فونت
- پیشرفته

## Theme Scopes

Admin theme settings now have two scopes:

- `/admin/theme` manages the system-wide admin panel theme. It is limited to users with `admin.theme.manage`.
- `/admin/my-theme` manages the current user's personal display theme. Any authenticated user can save or reset their own display theme.

Theme resolution priority:

1. Current user personal display theme
2. System admin theme
3. environment defaults
4. hardcoded safe defaults

Personal theme settings can override display-focused values such as preset, font family, base font size, line height, and radius. System theme settings control global branding, logo, default avatar, footer, and global visual defaults.

For v0.4.4, each user has one active personal display theme. The current storage uses `app_settings` with scoped `user_id` values; `user_id=0` is the system scope and a positive `user_id` is a personal scope. This keeps the architecture ready for a future `user_theme_profiles` table with saved named profiles, but that full profile library is intentionally not implemented yet.

The theme resolver must be called with the current user id on authenticated admin pages. If no user id is provided, only the system theme is resolved. This prevents one user's `/admin/my-theme` selection from leaking into another user's session or into the global `/admin/theme` settings.

When a system preset is changed in `/admin/theme`, old `token.*` overrides for the system scope are cleared so the selected preset's actual colors, shadows, radius, sidebar, header, and card tokens apply visibly. Personal preset changes remain scoped to the current user's `user_id`.

Future-ready profile shape:

- `id`
- `user_id`
- `title`
- `preset_base`
- `settings_json`
- `is_active`
- `created_at`
- `updated_at`

Reset actions:

- `POST /admin/theme/reset` with `scope=user` clears only the current user's personal theme.
- `POST /admin/theme/reset` with `scope=system` resets the system theme and requires `admin.theme.manage`.
- User reset never touches system branding or another user's personal settings.
- System reset restores safe defaults for the global theme and does not delete user personal overrides.

## Canonical Admin Assets

Admin assets must be loaded from canonical `/assets/admin/...` paths:

- `public/assets/admin/css/`
- `public/assets/admin/js/`
- `public/assets/admin/webfonts/`
- `public/assets/admin/images/`
- `public/assets/admin/images/logos/`
- `public/assets/admin/images/avatars/`
- `public/assets/admin/images/icons/`
- `public/assets/admin/images/placeholders/`

Runtime uploads stay under:

- `public/uploads/admin/logos/`
- `public/uploads/admin/avatars/`

The admin panel must not use CDN fonts or external icon CSS. Local icon support is provided through `/assets/admin/css/icons.css`, which references webfonts through `../webfonts/...`. If webfont files are missing, the UI must continue to work without broken navigation.

Logo and avatar values are validated as local paths only. External URLs, `javascript:`, `data:`, `../`, and CSS `url()` values are rejected.

## Layout

The admin shell uses a reusable RTL Persian layout with:

- header
- right-side sidebar
- content area
- user display
- active role badge
- logout link
- footer

CSS is local at `public/assets/admin/css/admin.css`; no external CDN is required.

## Admin Assets

Admin shell assets live under:

- `public/assets/admin/css/`
- `public/assets/admin/js/`
- `public/assets/admin/images/logos/`
- `public/assets/admin/images/avatars/`
- `public/assets/admin/images/icons/`
- `public/assets/admin/images/placeholders/`
- `public/assets/admin/fonts/`

Prepared runtime upload folders:

- `public/uploads/admin/logos/`
- `public/uploads/admin/avatars/`

The upload folders include Apache `.htaccess` protection to disable PHP/script execution while keeping normal static image serving available.

Default local placeholders:

- `/assets/admin/images/logos/default-logo.svg`
- `/assets/admin/images/avatars/default-avatar.svg`

No upload UI is implemented in this phase.

## Dynamic Theme System

`/admin/theme` provides the first dynamic admin theme management surface.

Available presets:

- `official_emerald` is the recommended official emerald theme with dark green sidebar and yellow active menu.
- `modern_light` is a lighter white/green operational theme.
- `classic_green` keeps a stronger classic green look.
- `neutral_light` provides a restrained neutral office option.
- `green_gold` uses stronger gold highlights for a more promotional Troca identity.

Older stored values such as `cooperative_official`, `cooperative_light`, `cooperative_classic`, and `golden_green` are normalized safely by the resolver. `ADMIN_DEFAULT_THEME` supports the canonical keys and defaults to `official_emerald`.

The theme page is grouped for non-technical admins:

- انتخاب پوسته
- برندینگ
- هدر
- سایدبار / منو
- محتوای داشبورد
- فوتر
- فونت و خوانایی
- تنظیمات پیشرفته

The admin CSS is driven by safe design tokens:

- `--admin-primary`
- `--admin-font-family`
- `--admin-font-size-base`
- `--admin-font-size-sm`
- `--admin-font-size-lg`
- `--admin-line-height-base`
- `--admin-font-weight-normal`
- `--admin-font-weight-medium`
- `--admin-font-weight-bold`
- `--admin-primary-hover`
- `--admin-primary-dark`
- `--admin-primary-soft`
- `--admin-accent`
- `--admin-accent-hover`
- `--admin-bg`
- `--admin-bg-gradient-start`
- `--admin-bg-gradient-end`
- `--admin-surface`
- `--admin-surface-muted`
- `--admin-text`
- `--admin-text-muted`
- `--admin-border`
- `--admin-danger`
- `--admin-warning`
- `--admin-success`
- `--admin-sidebar-bg`
- `--admin-sidebar-bg-2`
- `--admin-sidebar-text`
- `--admin-sidebar-text-muted`
- `--admin-sidebar-active-bg`
- `--admin-sidebar-active-text`
- `--admin-header-bg`
- `--admin-footer-bg`
- `--admin-footer-text`
- `--admin-radius`
- `--admin-shadow`
- `--admin-sidebar-width`
- `--admin-topbar-height`

Theme settings are persisted in the framework-safe `app_settings` table under the `admin.theme` namespace. If the table is not available yet, the admin panel falls back to the default preset and environment branding values.

Scoped persistence uses a unique key on `user_id + namespace + setting_key`. System settings are saved with `user_id=0`. Personal settings are saved with the authenticated user's id.

Branding values:

- `ADMIN_BRAND_NAME` sets the fallback admin brand name.
- `ADMIN_LOGO_URL` sets an optional fallback logo URL.
- `ADMIN_DEFAULT_AVATAR_URL` sets the fallback user avatar URL.
- `ADMIN_FONT_FAMILY=Vazirmatn` documents the preferred local font family.
- Stored database settings override environment fallback values.
- Branding is system-only. `/admin/my-theme` must not write brand name, logo, default avatar, or footer text.
- The admin theme seeder fills missing or unsafe defaults but must not overwrite healthy customized system branding.

Typography strategy:

- No external font CDN is used.
- The default stack is `"Vazirmatn", "IRANSans", "Tahoma", "Segoe UI", sans-serif`.
- Commercial or unknown font binaries are not bundled.
- If desired, place a safe local `Vazirmatn.woff2` file at `public/assets/admin/fonts/Vazirmatn.woff2`.
- If that file is absent, browsers fall back to the remaining Persian-friendly stack.
- Admin views escape Persian output with UTF-8 and substitution handling.
- If saved `app_settings` values contain mojibake or question marks, the theme seeder repairs core defaults idempotently without overwriting healthy customized settings.

Persian encoding troubleshooting:

- HTML admin responses must send `Content-Type: text/html; charset=UTF-8`.
- JSON responses use UTF-8 and `JSON_UNESCAPED_UNICODE`.
- `app_settings` must use `utf8mb4`.
- If labels show as `?????`, rerun the protected development seeder after deployment so corrupted admin theme defaults can be repaired.
- Do not paste values from incorrectly decoded terminal output into theme settings.

Only an active role with `admin.theme.manage` can update the theme. The seed flow grants this permission to `super_admin`. Base user roles can view the page but cannot save changes.

Theme inputs are validated before persistence:

- colors must be six-digit hex values
- font family must be selected from approved options
- font family options are Vazirmatn, Tahoma, Segoe UI, and System UI
- font size options are `13px`, `14px`, `15px`, `16px`, and `1rem`
- line height options are `1.5`, `1.6`, `1.7`, and `1.8`
- radius options are `8px`, `12px`, `16px`, `18px`, `20px`, and `24px`
- layout dimensions must be pixel values
- shadows are restricted to safe simple CSS shadow text
- logo and avatar URLs must be local safe image paths under `/assets/admin/images/`, `/uploads/admin/logos/`, or `/uploads/admin/avatars/`
- `javascript:`, `data:`, external `http/https` URLs, and paths containing `../` are rejected
- arbitrary CSS, `url(...)` injection, scripts, and secrets are not accepted

Two-user theme isolation test:

- User A saves `green_gold` in `/admin/my-theme`.
- User B logs in and should not inherit User A's personal theme.
- A super admin changes `/admin/theme` to `neutral_light`.
- Users without personal overrides see `neutral_light`.
- User A keeps `green_gold` until they reset their personal theme.
- After User A resets, User A sees the system theme again.

Safe diagnostics for runtime testing include:

- `admin_theme_active_preset`
- `admin_theme_resolved_source`
- `admin_theme_system_preset_exists`
- `admin_theme_personal_preset_exists_for_current_user`
- `theme_user_scope_supported`
- `current_theme_resolver_available`

Development-only runtime forensics are available at `/admin/theme/debug` when `APP_DEBUG=true` and the authenticated active role can manage the admin theme. The page shows current user id, system rows, current user personal rows, resolved source, active preset, generated CSS variables, and loaded asset URLs without exposing secrets.

## Security Notes

- Admin POST forms are CSRF protected.
- Existing Auth, MFA, and Access services are reused.
- Theme updates require `admin.theme.manage`.
- No password hash is exposed.
- No session ID is exposed.
- No CSRF token is exposed outside forms.
- No MFA secret is exposed.
- No recovery codes are exposed by the admin shell.
- No provider secrets are exposed.
- No arbitrary CSS or provider secret is accepted through theme settings.
- Upload folders deny PHP/script execution where Apache `.htaccess` is supported.

## Known Limitations

- Logout is currently exposed as `GET /admin/logout` because this route was required for the shell milestone.
- No admin CRUD exists yet.
- No profile editing exists yet.
- No MFA management UI exists yet.
- No business modules are included.
- The theme system is a shell-level UI foundation only; it does not add tenant branding or module-specific themes yet.

## Next Phase Suggestions

- Admin users list
- Admin invitation flow
- Permission-aware navigation
- MFA management UI
- Identity-change profile forms
- Tenant-aware branding and theme governance
