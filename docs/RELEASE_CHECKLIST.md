# IPKF Release Checklist

## Datetime Timezone Contract

- UTC persistence contract documented in `docs/DATETIME_TIMEZONE.md`.
- MySQL session timezone is explicit UTC.
- Admin Jalali datetime formatting converts stored instants to `APP_TIMEZONE` exactly once.
- Date-only values remain timezone-neutral.
- Fixed UTC instant verification passes.
- No existing timestamp rows are mass-modified.

## v0.4.6 Dynamic organization core

- [ ] Protected migration reports `dynamic_organization_core` complete.
- [ ] Existing `organizations` rows and legacy fields remain unchanged.
- [ ] `organization_classification_schemes` exists.
- [ ] `organization_classification_terms` exists.
- [ ] `organization_classifications` exists.
- [ ] `organization_relation_types` exists.
- [ ] `organization_relations` exists.
- [ ] `organization_unit_types` exists.
- [ ] `org_units.organization_id` and `org_units.unit_type_id` exist and remain nullable.
- [ ] Existing unscoped `org_units` rows remain valid.
- [ ] `organization_positions` exists and reuses `positions`.
- [ ] `organization_appointments` exists and references `persons`, not `users`.
- [ ] `/_diagnostics` reports all dynamic organization schema flags as `true` after migration.
- [ ] No business classification terms are seeded.
- [ ] No UI, CRUD, Automation, governance, ownership, signatory, registration, contact, or address feature is added.
- [ ] Existing login, MFA, admin navigation, user list, organization-unit list, and positions list still work.

## v0.4.6 Admin Users Organization Schema Checks

- `/health` returns `0.4.6-admin-users-organization`.
- Protected migrations run successfully.
- `org_units` table exists.
- `positions` table exists.
- `user_org_assignments` table exists.
- `/_diagnostics` includes `admin_users_organization_foundation_available=true`.
- `/_diagnostics` includes `org_units_schema_available=true`.
- `/_diagnostics` includes `positions_schema_available=true`.
- `/_diagnostics` includes `user_org_assignments_schema_available=true`.
- Run protected seeders in development.
- `/_diagnostics` includes `admin_users_menu_available=true`.
- `/_diagnostics` includes `admin_org_units_menu_available=true`.
- `/_diagnostics` includes `admin_positions_menu_available=true`.
- `/_diagnostics` includes `admin_users_permissions_seeded=true`.
- `super_admin` sees `کاربران`, `واحدهای سازمانی`, and `سمت‌ها`.
- Active base `user` role does not see users organization menu items by default.
- `/admin/users` as an unauthorized active role returns HTTP 403.
- `/admin/users` as `super_admin` opens the read-only users list.
- `/admin/org-units` opens the read-only organization units list for authorized roles.
- `/admin/positions` opens the read-only positions list for authorized roles.
- `/admin/dashboard` opens normally.
- Dashboard displays visually distinct solid-color module tiles after the statistic cards.
- Dashboard module tiles no longer contain quick-link lists.
- Clicking `مدیریت کاربران` opens `/admin/modules/users`.
- Clicking `ساختار سازمانی` opens `/admin/modules/organization`.
- Clicking `مدیریت سامانه` opens `/admin/modules/system`.
- Module hub pages show colorful action tiles.
- `/admin/users` opens a read-only users list for roles with `users.view`.
- `/admin/users` shows safe identity, status, role summary, primary org unit when available, and created date.
- `/admin/users?q=...` searches username, name, mobile, and email.
- `/admin/users?page=2` paginates server-side and preserves `q`.
- `/admin/users` renders as a table on desktop and cards on mobile.
- `/admin/users` does not expose password hashes, MFA secrets, recovery codes, login tokens, sessions, CSRF tokens, trusted device secrets, or internal hashes.
- `/admin/users` does not include create, edit, delete, password reset, role assignment editing, or organization assignment editing actions.
- `/admin/users` links each visible user to `/admin/users/{id}`.
- `/admin/users/{id}` opens a read-only user detail page for roles with `users.view`.
- `/admin/users/{id}` shows safe identity, account, MFA/security, role assignment, and organization assignment summaries.
- `/admin/users/{id}` renders human-facing dates as Jalali dates.
- `/admin/users/{id}` displays username with a visible `نام کاربری` label and never as an ambiguous standalone summary value.
- `/admin/users/{id}` displays province, county, and city once in the summary card as Persian semantic labels.
- `/admin/users/{id}` displays person type, organization scope, roles, organization units, positions, and statuses as Persian semantic labels.
- `/admin/users/{id}` displays county only from a genuine relationship; city is never reused as county.
- `/admin/users/{id}` displays missing optional lookup values as `—`.
- `/admin/users/{id}` displays broken lookup references as `نامشخص`.
- `/admin/users/{id}` does not display raw foreign key values as user-facing fallbacks.
- Role, organization unit, and position codes appear only as secondary administrative data.
- `/admin/users/{id}` returns a clean Persian 404 for invalid, missing, or unavailable users.
- `/admin/users/{id}` returns the standard Persian 403 for an authenticated active role without `users.view`.
- `/admin/users/{id}` does not include create, edit, delete, password reset, role assignment editing, organization assignment editing, or Automation actions.
- `/admin/users/{id}` does not select or expose password hashes, MFA secrets, recovery codes, login tokens, sessions, CSRF tokens, trusted device secrets, provider secrets, or internal hashes.
- `/admin/users/{id}` opens the overview tab in the reusable Entity Detail Workspace.
- `/admin/users/{id}/identity` opens the identity tab.
- `/admin/users/{id}/contacts` opens the contacts and addresses tab.
- `/admin/users/{id}/account` opens the account and security tab.
- `/admin/users/{id}/access` opens the selected user's read-only role assignments tab.
- `/admin/users/{id}/appointments` opens legacy organization assignments and canonical appointments as separate read-only subsections.
- User detail active tab is route-based and survives refresh and browser back/forward.
- User detail mobile navigation uses a vertical section navigator instead of a horizontally scrolling tab strip.
- User detail tables become cards or vertical lists on mobile.
- User detail pages do not create full-page horizontal scrolling on mobile.
- User detail diagnostics include `admin_entity_detail_workspace_available=true`.
- User detail diagnostics include `admin_user_detail_tabbed_workspace_available=true`.
- User detail diagnostics include `admin_user_detail_tab_specific_loading=true`.
- `/admin/users` displays a calculated row number instead of raw `users.id`.
- User detail compact header is available on desktop and mobile.
- Mobile user detail fields render as compact label/value rows.
- Optional empty identity fields are hidden in read-only mode.
- Account/security summary is deduplicated.
- Roles/access view uses semantic access scope and validity labels.
- Appointments UI uses Persian semantic section titles and hides technical table names.
- Compact empty states are used for missing contacts, addresses, roles, and appointments.
- Dashboard module launcher centers incomplete desktop rows without placeholder cards.
- `/_diagnostics` includes `admin_users_list_raw_ids_hidden=true`.
- `/_diagnostics` includes `admin_user_detail_technical_schema_terms_hidden=true`.
- `/admin/org-units` opens a read-only organization units list for roles with `org_units.view`.
- `/admin/org-units` searches title, code, type, and parent title.
- `/admin/org-units` paginates server-side and preserves `q`.
- `/admin/org-units` sorts by `sort_order ASC, id ASC`.
- `/admin/positions` opens a read-only positions list for roles with `positions.view`.
- `/admin/positions` searches title, code, and description.
- `/admin/positions` paginates server-side and preserves `q`.
- `/admin/positions` sorts by `sort_order ASC, id ASC`.
- `/admin/positions` renders as a table on desktop and cards on mobile.
- `/admin/positions` does not include create, edit, delete, user-position assignment, organization assignment editing, or Automation actions.
- `super_admin` sees all authorized dashboard module tiles and hub actions.
- `province_admin` sees only permitted dashboard module tiles and hub actions.
- Active base `user` role does not see restricted dashboard modules.
- Dashboard tiles are hidden when none of their destinations are permitted.
- Unauthorized module hub actions are not rendered.
- A module hub returns HTTP 403 if no action is available to the active role.
- Switching active role changes visible dashboard tiles after redirect/refresh.
- `/_diagnostics` includes `admin_dashboard_module_tiles_available=true`.
- `/_diagnostics` includes `admin_dashboard_modules_permission_filtered=true`.
- `/_diagnostics` includes `admin_dashboard_modules_active_role_aware=true`.
- `/_diagnostics` includes `admin_visual_module_launcher_available=true`.
- `/_diagnostics` includes `admin_module_hub_pages_available=true`.
- `/_diagnostics` includes `admin_local_icon_font_available=true`.
- `/_diagnostics` includes `admin_module_actions_permission_filtered=true`.
- `/_diagnostics` includes `admin_sidebar_module_level_navigation=true`.
- `/_diagnostics` includes `admin_sidebar_duplicate_child_links_removed=true`.
- `/_diagnostics` includes `admin_sidebar_child_route_active_mapping=true`.
- `/_diagnostics` includes `admin_users_list_available=true`.
- `/_diagnostics` includes `admin_users_search_available=true`.
- `/_diagnostics` includes `admin_users_pagination_available=true`.
- `/_diagnostics` includes `admin_users_sensitive_fields_protected=true`.
- `/_diagnostics` includes `admin_user_detail_available=true`.
- `/_diagnostics` includes `admin_user_detail_roles_available=true`.
- `/_diagnostics` includes `admin_user_detail_org_assignments_available=true`.
- `/_diagnostics` includes `admin_user_detail_security_summary_available=true`.
- `/_diagnostics` includes `admin_user_detail_sensitive_fields_protected=true`.
- `/_diagnostics` includes `admin_user_detail_semantic_lookups_available=true`.
- `/_diagnostics` includes `admin_raw_foreign_keys_hidden_from_ui=true`.
- `/_diagnostics` includes `admin_reference_titles_resolved=true`.
- `/_diagnostics` includes `admin_positions_list_available=true`.
- `/_diagnostics` includes `admin_positions_search_available=true`.
- `/_diagnostics` includes `admin_positions_pagination_available=true`.
- Icons come from the local icon-font foundation and no external CDN is used.
- Sidebar contains module-level links only: dashboard, users management, organization structure, system management, reports, and support.
- `/admin/users`, `/admin/access`, `/admin/org-units`, `/admin/positions`, `/admin/theme`, `/admin/settings`, and `/admin/pages` do not appear as separate global sidebar links.
- Child routes still open for authorized users through hub actions.
- `/admin/users` and `/admin/access` highlight the users management module.
- `/admin/org-units` and `/admin/positions` highlight the organization structure module.
- `/admin/theme`, `/admin/settings`, and `/admin/pages` highlight the system management module.
- Existing login, MFA, and admin routes still work.
- Existing RBAC navigation still works.
- Public landing page still works.
- Full CRUD remains deferred.
- No automation, correspondence, inbox/cartable, referral, attachment, workflow, CRM, ERP, or Bot tables are created in this phase.
- No secrets are exposed.

## v0.4.5 Admin Navigation RBAC Checks

- `/health` returns `0.4.5-admin-navigation-rbac`.
- Deploy the `v0.4.5-admin-navigation-rbac` branch.
- Run protected migrations in development.
- Run protected seeders in development.
- Login through the admin panel.
- Verify `/health` after deployment.
- `/_diagnostics` includes `admin_navigation_rbac_available=true`.
- `/_diagnostics` includes `admin_route_guards_available=true`.
- `/_diagnostics` includes `admin_menu_permission_filtering_available=true`.
- `/_diagnostics` includes `admin_active_role_permission_context=true`.
- `/_diagnostics` includes `admin_active_access_switch_available=true`.
- `/_diagnostics` includes `admin_active_access_switch_self_service=true`.
- Migrations and seeders run successfully.
- Required admin navigation permissions exist.

Super admin:

- `super_admin` active role sees dashboard, access, theme, settings, pages, reports, and support.
- `super_admin` can open `/admin/access` and `/admin/theme`.

Active user role:

- Switching active access to the base `user` role reduces sidebar items.
- Base user active role does not see access, theme, settings, pages, or reports unless explicitly granted.
- `/admin/access` returns HTTP 403 when the active role lacks `access.manage`.
- `/admin/theme` returns HTTP 403 when the active role lacks `admin.theme.manage`.
- Profile, account, security, password, and my-theme remain available if allowed.

Active access switch:

- `/admin/profile/access` shows the active row as active and other rows with a role selection button.
- Active role `user` can switch to the user's own `super_admin` assignment from `/admin/profile/access`.
- Active role switching does not require `access.manage`.
- `/access/switch` cannot switch to another user's assignment.
- `/access/switch` cannot switch to inactive, revoked, or expired assignments.
- Header active role badge and sidebar permissions update after switching.
- After switching back to user, restricted routes return 403 again.

Guest, MFA, and public routes:

- User/account dropdown items respect `account.profile.view`, `account.security.view`, `account.password.change`, and `account.theme.manage`.
- `/admin/dashboard` works for roles with `admin.dashboard.view`.
- Placeholder pages require their mapped permissions.
- Guest access to `/admin/dashboard` redirects to `/admin/login`.
- `/admin/login` opens for guests.
- Existing admin login, MFA, recovery, and logout flow remains unchanged.
- Existing JSON endpoints continue to work.
- `/admin/navigation/debug` works only with `APP_DEBUG=true` and an authorized active role.
- Public landing page still works.
- `/health` works.
- `/_diagnostics` remains development-only.
- Persian UTF-8 rendering remains correct.
- No password hash, session id, CSRF token, recovery code, OTP, provider secret, login token, or maintenance key is exposed.

## v0.4.4 Admin Panel Shell UI Checks

- `/health` returns `0.4.4-admin-panel-shell`.
- `/` renders the professional RTL Persian IPKF/Troca public landing page.
- `SITE_MODE=coming_soon` keeps `/` polished and shows `نسخه آزمایشی / در حال آماده‌سازی`.
- The homepage includes hero, capability cards, automation preview, roadmap, admin login CTA, and footer.
- The homepage uses local assets only and no CDN, remote images, or external font imports.
- The homepage is responsive, stacks cleanly on mobile, and has no horizontal overflow.
- `/admin/login` includes a useful back-to-homepage link to `/`.
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
- Mobile sidebar closes with its close button, overlay, Escape, menu item click, and viewport resize back to desktop.
- Mobile sidebar locks body scroll while open and does not cause horizontal overflow.
- `/admin/login` autofocuses the login field, or the password field after a validation error with a retained login value.
- `/admin/mfa`, `/admin/mfa/recovery`, `/admin/forgot-password`, and `/admin/password` autofocus the primary input.
- `/admin/theme` is tabbed and remains CSRF protected.
- `/admin/theme` saves system theme settings only for users with `admin.theme.manage`.
- `/admin/my-theme` saves personal display theme settings for the current user only.
- `/admin/my-theme` shows built-in system presets, the current active personal theme, save, and reset actions.
- v0.4.4 theme selection is built-in-preset-only: `official_emerald`, `modern_light`, `classic_green`, `neutral_light`, and `green_gold`.
- `/admin/theme` does not expose color/token editors in v0.4.4.
- `/admin/my-theme` lets the user select one preset only; typography and token customization are reserved for a future phase.
- Advanced theme editing shows the Persian next-version notice instead of editable custom-token fields.
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
- Diagnostics include `public_landing_available=true` and `coming_soon_landing_available=true` when public landing assets are present.
- Diagnostics include theme scope, reset, branding, canonical asset, local icon, and webfont path booleans.
- Diagnostics include `current_theme_resolver_available=true` and `theme_user_scope_supported=true`.
- Diagnostics include `admin_theme_resolved_source`, `admin_theme_system_preset_exists`, and `admin_theme_personal_preset_exists_for_current_user`.
- Diagnostics include `admin_theme_forensics_available=true` and `admin_theme_runtime_fix_version=theme-runtime-forensics-v1`.
- Diagnostics include `admin_theme_custom_editor_enabled=false` and `admin_theme_builtin_presets_only=true`.
- `/admin/theme/debug` works only in development/debug for an authorized admin and shows system rows, current user personal rows, resolved source, active preset, generated CSS variables, and loaded asset URLs.
- `/admin/theme/debug` returns 404 or is otherwise unavailable when `APP_DEBUG=false`.
- `/admin/theme/debug` shows `token_override_rows_ignored=true`.
- After saving a system preset, system `token.*` rows for `admin.theme` are cleared and `token_override_rows_count=0`.
- System theme test: login as `super_admin`, select `neutral_light` in `/admin/theme`, save, refresh, verify the UI visibly changes and `/admin/theme/debug` shows system active preset as `neutral_light`.
- Personal isolation test: User A selects `green_gold` in `/admin/my-theme`, verifies personal source in `/admin/theme/debug`, logs out, then User B logs in and must not see `green_gold` unless the system theme is `green_gold`.
- Reset test: User A resets personal theme and returns to the system theme while system rows remain unchanged.
- Mobile navigation closes when resizing back to desktop width and does not remain stuck open after viewport changes.
- No password hash, session id, CSRF token, recovery code, OTP, provider secret, or maintenance key is exposed.
- Existing JSON endpoints still work.
- Persian UTF-8 rendering remains correct.

Production safety:

- `APP_ENV=production`.
- `APP_DEBUG=false`.
- `IDENTITY_DEV_EXPOSE_TOKEN=false`.
- `MFA_DEV_EXPOSE_OTP=false`.
- `/migrate.php` and `/seed.php` stay protected by `DEV_MAINTENANCE_KEY`.
- No secrets, tokens, session ids, CSRF tokens, password hashes, recovery codes, login tokens, maintenance keys, or raw cookies are exposed.

Final v0.4.4 manual checks:

- Public: `/`, `/health`, and `/_diagnostics` in development only.
- Auth/admin: `/admin/login`, `/admin/mfa`, `/admin/mfa/recovery`, `/admin/forgot-password`, `/admin/dashboard`, `/admin/profile`, `/admin/account`, `/admin/security`, `/admin/password`, `/admin/access`, `/admin/theme`, `/admin/my-theme`, and `/admin/logout`.
- Responsive: mobile sidebar open/close, overlay close, Escape close, resize mobile to desktop, no horizontal overflow, and landing page mobile view.
- Theme: choose system preset, choose personal preset, reset personal preset, custom editor hidden/disabled, and debug route only in development.
- Autofocus: login field, MFA code, recovery code, forgot-password input, and password form.
- Landing: homepage displays, admin login CTA works, “back to homepage” from `/admin/login` works, and no CDN/external assets are loaded.

Deferred after v0.4.5:

- full permission management UI.
- advanced audit logs.
- automation module.
- CRM, ERP, Bot, and Marketplace modules.
- organization, geography, and fiscal-year scoped UI enforcement beyond the existing foundation.

Completed next phase: `v0.4.6-admin-users-organization`.

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

- `/health` returns version `0.4.4-admin-panel-shell`.
- `GET /admin/login` loads a RTL Persian login page.
- Admin login works with username, email, and mobile.
- Admin login keeps CSRF protection enabled.
- MFA-required login redirects to `/admin/mfa`.
- Valid TOTP completes admin login.
- Recovery code fallback is available through `/admin/mfa`.
- `/admin/dashboard` requires authentication.
- `/admin/dashboard` is a module launcher; account status and assignment summary are not rendered there.
- `/admin/profile/access` lists the authenticated user's own role assignments.
- `/admin/profile/access` can switch the authenticated user's own active role with CSRF protection.
- `/admin/profile` displays safe user profile fields.
- `/admin/theme` loads the dynamic admin theme page.
- `/admin/theme` shows preset, branding, footer, and advanced placeholder sections only.
- `/admin/theme` hides typography, color, and custom-token editors in v0.4.4.
- Persian labels on `/admin/theme` render correctly and do not show question marks.
- `official_emerald`, `modern_light`, `classic_green`, `neutral_light`, and `green_gold` presets are available and visually distinct.
- `official_emerald` uses a dark green sidebar, yellow active item, light content area, white cards, and soft shadow.
- Base user active role cannot update the admin theme.
- `super_admin` active role can update the admin theme with `admin.theme.manage`.
- Invalid preset, logo/avatar path, and unsafe CSS-like values are rejected.
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

## v0.4.6 Admin Users Organization Checks

- `/health` returns version `0.4.6-admin-users-organization`.
- Migrations create `org_units`, `positions`, and `user_org_assignments`.
- No Automation, correspondence, cartable, referral, or workflow tables are created.
- Users organization permissions are seeded idempotently.
- `/admin/users` requires `users.view`.
- `/admin/users` shows a read-only professional users list.
- `/admin/users` supports server-side search with `q`.
- `/admin/users` supports server-side pagination with `page`.
- `/admin/users` sorts records ascending from small to large by user id.
- `/admin/org-units` requires `org_units.view`.
- `/admin/org-units` shows a read-only professional organization units list.
- `/admin/org-units` supports server-side search with `q`.
- `/admin/org-units` supports server-side pagination with `page`.
- `/admin/org-units` sorts records ascending from small to large by `sort_order` and `id`.
- `/admin/org-units` resolves parent titles with a join and does not expose internal paths.
- `/admin/org-units` displays hierarchy with sanitized/capped depth indentation.
- `/admin/org-units` renders responsive desktop table and mobile cards without horizontal page overflow.
- `/admin/positions` requires `positions.view`.
- `/admin/positions` shows a read-only professional positions list.
- `/admin/positions` supports server-side search with `q`.
- `/admin/positions` supports server-side pagination with `page`.
- `/admin/positions` sorts records ascending from small to large by `sort_order` and `id`.
- `/admin/positions` renders responsive desktop table and mobile cards without horizontal page overflow.
- `/admin/positions` does not include create, edit, delete, user-position assignment, organization assignment editing, or Automation actions.
- Empty state says `هنوز واحد سازمانی ثبت نشده است.`
- No-result state says `واحد سازمانی مطابق جستجو پیدا نشد.`
- Positions empty state says `هنوز سمتی ثبت نشده است.`
- Positions no-result state says `سمتی مطابق جستجو پیدا نشد.`
- Database errors show a safe Persian message without SQL, stack traces, paths, or secrets.
- `/_diagnostics` includes `admin_org_units_list_available=true`.
- `/_diagnostics` includes `admin_org_units_search_available=true`.
- `/_diagnostics` includes `admin_org_units_pagination_available=true`.
- `/_diagnostics` includes `admin_org_units_hierarchy_display_available=true`.
- `/_diagnostics` includes `admin_positions_list_available=true`.
- `/_diagnostics` includes `admin_positions_search_available=true`.
- `/_diagnostics` includes `admin_positions_pagination_available=true`.
- Existing login, MFA, dashboard, RBAC navigation, active access switching, and theme routes still work.

## v0.4.6 Dashboard/Profile Access Checks

- `/admin/dashboard` no longer shows login status, active role, MFA status, runtime version cards, or the access summary table.
- `/admin/dashboard` remains a module launcher only.
- `/admin/profile/access` opens for authenticated users with account profile access.
- `/admin/profile/access` lists only the current user's assignments.
- `/admin/profile/access` switches the authenticated user's own active assignment.
- `/admin/security` shows login/session and MFA status.
- Runtime version appears in the admin footer instead of the dashboard cards.
- Human-facing admin UI numbers use Persian digits where safe.
- Technical values such as emails, usernames, query parameters, hidden ids, and stored form values remain canonical.
- `/_diagnostics` includes `admin_dashboard_account_cards_removed=true`.
- `/_diagnostics` includes `admin_dashboard_access_summary_removed=true`.
- `/_diagnostics` includes `admin_profile_access_page_available=true`.
- `/_diagnostics` includes `admin_profile_self_service_role_switch_available=true`.
- `/_diagnostics` includes `admin_profile_security_status_available=true`.
- `/_diagnostics` includes `admin_runtime_version_in_footer=true`.

## v0.4.6 Extended Person Data Foundation Checks

- Migration creates `person_profiles`, `contact_types`, `person_contacts`, `address_types`, and `person_addresses`.
- Existing `persons` and `users` records remain valid without related profile/contact/address rows.
- Extended identity fields are not added to `users`.
- Existing `persons.national_code`, `father_name`, and `birth_date` remain canonical and are not duplicated.
- `persons.birth_date` remains a standard database `DATE`; Jalali conversion stays in the UI/application layer.
- Nullable national-code uniqueness is added only when existing data makes it safe.
- `person_profiles.person_id` is unique.
- Contacts and addresses support multiple rows per person.
- Contact and address types remain configurable data rather than PHP enums.
- Address province/city fields reuse existing lookup tables where compatible.
- No mandatory business lookup seed data is added.
- No person/profile CRUD UI is added.
- No organization schema change or Automation feature is added.
- Diagnostics report only the six safe person-data schema booleans.
- Diagnostics do not expose person values, record counts, column details, or migration SQL.
- Sensitive-data masking, permissions, audit, and logging requirements are documented.
- `git diff --check` passes.

## v0.4.6 Ministry to SCI Geography Crosswalk Checks

- Protected migration creates additive versioned run, candidate, and issue tables.
- Existing Ministry and SCI staging rows and snapshots remain unchanged.
- Runner accepts only `SCI-XXXXXXXXXXXX`, `MOI-XXXXXXXXXXXX`, and `build-candidates`.
- Same snapshot pair, crosswalk type, and algorithm version reuse the completed run.
- Failed pending runs clear only their own generated candidates and issues.
- Non-pending reviewed candidates prevent automatic replacement.
- Matching order is province, county, district, rural district, then city candidate.
- County and lower candidates require a deterministic full parent path.
- Same normalized title under different parents is never globally merged.
- Ministry national identifiers are not used as globally unique crosswalk identity.
- Exact means deterministic pending pair, not confirmed mapping.
- Safe Persian normalization differences produce probable candidates only.
- Multiple compatible targets remain ambiguous and all useful pairs are retained.
- Missing or unresolved parents cannot produce exact child candidates.
- Numbered CODEREC 5 rows are excluded from official-city matching.
- Non-numbered CODEREC 5 rows can produce probable/ambiguous city candidates only.
- CODEREC 6 and 8 rows are classified without Ministry target comparison.
- DIAG remains opaque and is not interpreted.
- Synthetic fixture covers full-path, duplicate-title, parent, city, and settlement cases.
- Endpoint response is aggregate-only and exposes no candidate pairs or source data.
- Diagnostics are boolean-only.
- `canonical_write_performed=false` and `confirmed_mapping_write_performed=false`.
- No canonical geography, confirmed external mapping, bot, organization, or Automation write exists.
- `git diff --check` passes.

## v0.4.6 Ministry Canonical Geography Apply Checks

- Additive migration creates canonicalization run/item audit tables.
- Metadata seed creates only missing official level/relation definitions; no real location is seeded.
- `/geography-canonicalize.php` requires debug mode and the maintenance key.
- Plan requires exact `MOI-XXXXXXXXXXXX`, classifies every staging row, and performs no canonical write.
- Apply requires exact source batch, plan reference, and 16-character fingerprint prefix.
- Source row checksum or canonical conflict changes invalidate the plan.
- Unchanged `MOI-865CA310FC55` is expected to report 6,617 eligible and 2 excluded rows.
- Iran root resolution is unique and conflict-safe.
- Apply order is province, county, district, rural district, then city.
- Only `official_administrative`/`administrative_parent` relations are written.
- Hierarchy-code identity is exact and snapshot-versioned; leading zeroes remain intact.
- Confirmed mappings use `authoritative_source_apply`.
- Repeated national identifiers remain attached to separate locations and never merge them.
- Title-only reuse, first-result reuse, and national-identifier reuse are blocked.
- Invalid/missing-parent/duplicate-code rows are excluded.
- Existing official-parent and trusted-mapping conflicts are not overwritten.
- Chunked apply is resumable and repeated apply creates no duplicates.
- Apply chunks contain exactly one hierarchy level and levels advance parent-first.
- A forced first-province failure rolls back the first chunk without applying an item.
- Failed runs retain the same plan reference, source fingerprint, and stored items.
- Retry reuses an existing Iran root and skips already committed items.
- Run counters are reconciled from committed artifacts and cannot double on retry.
- Failure responses expose only opaque reference, safe stage, and aggregate state.
- Original exception details are written only to the private canonicalization log.
- Protected `mode=status` works with exact batch/plan and no fingerprint.
- Status mode exposes aggregate recovery state and no source or database details.
- No location, relation, identifier, mapping, or code value is automatically deleted or deactivated.
- No SCI settlement/city candidate, bot geography, organization, South Kerman province, UI, Automation, or Auth/RBAC/MFA write occurs.
- Endpoint responses are aggregate-only and diagnostics are boolean-only.
- Synthetic fixtures contain no production records.
- Migration and seed remain idempotent.
- `git diff --check` passes.

## v0.4.6 Statistical Center Geography Dry-Run Checks

- Protected migration adds only CODEREC mapping metadata and additive staging columns/indexes.
- Metadata seed registers SCI import settings, authority scope, and CODEREC `1,2,3,4,5,6,8` idempotently.
- Real source files remain private and ignored by Git.
- Basename, extension, MIME, file size, private source directory, UTF-8 headers, and SHA-256 are validated.
- The synthetic fixture covers all observed CODEREC values, leading zeroes, Persian digits, unknown records, DIAG, missing parent context, and exact/conflicting duplicates.
- CSV parsing is streaming and staging transactions are bounded.
- All nonblank rows are staged; no unknown CODEREC row is discarded.
- Source component codes, CODEREC, and DIAG remain strings.
- Full source hierarchy context scopes parent and duplicate checks.
- `CODEREC=5` remains a review-only statistical urban unit and never implies an official city.
- `CODEREC=6` and `CODEREC=8` remain distinguishable source observations.
- DIAG remains opaque and no legal meaning is inferred.
- Identical source/hash reruns reuse the completed summary without duplicate staging.
- The Ministry dry-run endpoint and source-specific private directory remain operational.
- Endpoint output is aggregate-only and sets `canonical_write_performed=false`.
- No canonical geography, relation, external mapping, match candidate, organization, address, or bot row is written.
- Diagnostics expose only safe availability booleans.
- `git diff --check` passes.

## v0.4.6 Ministry Geography Dry-Run Checks

- Protected migration creates only `geographic_source_level_mappings` and `data_source_import_settings` as missing metadata structures.
- Metadata seed idempotently registers Persian Ministry source types, code lengths, parent prefixes, country root, placeholder `11`, CSV extension, and size limit.
- UTF-8 CSV headers are detected semantically rather than by fixed column position.
- Arabic/Persian characters, digits, whitespace, and zero-width variants normalize for comparison while raw values remain preserved.
- Codes and identifiers remain strings and leading zeroes are retained.
- Every nonblank row is staged; blank formatting rows are counted.
- Missing, malformed, wrong-length, duplicate, and orphaned hierarchy codes create issues without failing the whole parsed batch.
- Parent codes come only from mapping metadata and must exist at the compatible source level.
- Repeated national identifiers and title/parent variations remain review warnings and never merge rows.
- Exact source/hash reruns reuse completed snapshot results without duplicating staging rows.
- Source snapshot and import timestamps use the shared UTC `Clock`.
- `/geography-import.php` requires debug mode, `DEV_MAINTENANCE_KEY`, exact source, safe basename, and `mode=validate`.
- CSV source files stay under private `storage/imports/geography/ministry` and are ignored by Git.
- XLSX diagnostics accurately report unavailable while PhpSpreadsheet is absent.
- Public summary exposes only aggregate counts, safe reference/status, and hash prefix.
- No canonical location, relation, identifier, mapping, match candidate, address, organization, SCI, bot, map, facility, Auth/RBAC, or Automation write occurs.
- Synthetic deterministic fixture covers all configured levels and validation edge cases.
- `git diff --check` passes.

## v0.4.6 Dynamic Geography Foundation Checks

- Protected migration creates `geographic_level_types`.
- Protected migration creates data-driven `geographic_relation_types`; the metadata seeder now defines the official parent relation idempotently.
- Protected migration creates `geographic_locations` without globally unique titles.
- Protected migration creates dated `geographic_location_relations`.
- Protected migration creates explicit `geographic_legacy_mappings`.
- `person_addresses.geographic_location_id` is nullable and indexed.
- Existing `provinces`, `cities`, province/city columns, rows, indexes, and foreign keys remain unchanged.
- Existing person, address, bot registration, Auth, MFA, RBAC, and organization behavior remains unchanged.
- Migration and seed create no country, province, county, city, or commercial location records; real Ministry locations require the protected apply workflow.
- No title-only mapping or city-as-county substitution occurs.
- Historical locations, validity periods, and dated parent changes are supported.
- Multi-country and deployment-defined level types are supported.
- Future organization addresses can reuse canonical locations.
- Diagnostics expose only required safe schema and invariant booleans.
- Diagnostics expose no location rows, hierarchy contents, addresses, postal codes, coordinates, IDs, or record counts.
- No geography UI, CRUD, geographic record seeder, external API, Automation, or access-scope behavior is added.
- `git diff --check` passes.

## v0.4.6 Multi-Source Coding and Geography Provenance Checks

- Protected migration creates data-source, authority-scope, immutable snapshot, coding-system, code-set, segment, and versioned code-value tables.
- Protected migration creates hierarchy-context, external geographic identifier, reviewed mapping, and generic geography staging tables.
- `geographic_location_relations` receives only nullable hierarchy/source/review columns; existing relations are not guessed or backfilled.
- Metadata seed registers Ministry, SCI, and Rural Cooperation sources and their domain authority scopes idempotently.
- Metadata seed registers official, statistical, operational, and custom hierarchy types.
- Metadata seed registers `operational_region` but creates no South Kerman location.
- Rural Cooperation 3/5/8 code lengths and one-based segments are seeded as metadata.
- All external codes are stored as strings and preserve leading zeroes.
- No external code values, source snapshots, real geography rows, mappings, organizations, or source-file contents are seeded.
- Existing bot tables, IDs, codes, filters, and selection flow are untouched.
- Ministry official-parent authority, SCI supplementary role, and operational hierarchy separation are documented.
- Staging cannot write canonical geography automatically; ambiguous/title-only matches remain review work.
- Diagnostics expose only required safe booleans and no source data, filenames, hashes, counts, codes, names, IDs, or paths.
- No UI, CRUD, parser, organization address, ownership, governance, signatory, facility, or Automation schema is added.
- `git diff --check` passes.

## v0.4.6 Stable Baseline Closure

Version: `0.4.6-admin-users-organization`

Runtime and admin checks:

- `/health` returns `0.4.6-admin-users-organization`.
- Admin authentication and MFA remain functional.
- Active access switching remains functional.
- RBAC menu filtering and direct route guards remain functional.
- Users list and tabbed user detail workspace load.
- Organization units list and organization workspace load.
- Canonical geography tables are readable.
- Persian UTF-8 rendering remains correct.
- Responsive/mobile layouts introduce no horizontal page scrolling.

Canonical Ministry verification:

- Plan `CAN-F0637B652432` reports final status `applied`.
- Total source rows: `6619`.
- Applied items: `6617`.
- Excluded audit items: `2`.
- Conflict items: `0`.
- Created canonical locations: `6617`.
- Created official relations: `6617`.
- Created external identifiers: `13234`.
- Created confirmed mappings: `6617`.
- Repeated apply is idempotent and does not change any counter.
- SCI writes remain `false`.
- Bot writes remain `false`.

Security and operational checks:

- Maintenance endpoints remain available only in development/debug mode with the maintenance key.
- Private canonicalization failure logs remain outside the public document root.
- Public diagnostics and maintenance responses expose no maintenance key, SQL error, source identifier, stack trace, token, or secret.
- The official Ministry hierarchy is the only operational canonical geography.
- SCI and Rural Cooperation staging remain non-operational source/audit infrastructure.
- Rural Cooperation regions, organizations, and the South Kerman operational-region extension remain deferred.

Relevant diagnostics already implemented by this milestone:

- `admin_users_organization_foundation_available`
- `org_units_schema_available`
- `positions_schema_available`
- `user_org_assignments_schema_available`
- `person_extended_profile_schema_available`
- `person_contacts_schema_available`
- `person_addresses_schema_available`
- `dynamic_organization_core_available`
- `organization_classification_schema_available`
- `organization_relations_schema_available`
- `organization_positions_schema_available`
- `organization_appointments_schema_available`
- `dynamic_geographic_hierarchy_available`
- `geographic_locations_schema_available`
- `geographic_location_relations_schema_available`
- `multi_source_data_registry_available`
- `geographic_import_staging_available`
- `ministry_geography_import_adapter_available`
- `statistical_center_geography_import_available`
- `ministry_sci_crosswalk_available`
- `ministry_canonicalization_available`
- `ministry_canonicalization_plan_available`
- `ministry_canonicalization_apply_available`
- `ministry_canonicalization_idempotency_available`
- `ministry_canonicalization_official_hierarchy_only`
- `ministry_canonicalization_sci_write_blocked`
- `ministry_canonicalization_bot_write_blocked`
- `ministry_canonicalization_failure_telemetry_available`
- `ministry_canonicalization_status_mode_available`
- `ministry_canonicalization_private_error_logging_available`
- `ministry_canonicalization_public_error_details_blocked`

## v0.4.7 Automation Correspondence Foundation Checks

Version: `0.4.7-automation-foundation`

Runtime verification:

- `/health` returns `0.4.7-automation-foundation`.
- PHP 8.4.21 verified on development hosting.
- Database connection is available.
- Protected migration completed successfully.
- Protected seeder completed successfully.
- `/_diagnostics` reports the automation correspondence foundation booleans as `true`.

Migration and schema:

- `CreateAutomationCorrespondenceFoundationTables` is registered exactly once.
- Migration is additive, idempotent, utf8mb4, InnoDB, and MariaDB-compatible.
- `lookup_domains` and `lookup_values` provide the reusable dynamic lookup registry.
- `correspondences` has an opaque public reference, organization/unit/fiscal-year scope, lifecycle codes, current version, and optimistic lock counter.
- `correspondence_versions` has unique `(correspondence_id, version_number)` and no mutable update timestamp.
- `correspondence_parties` enforces one target matching person, organization, unit, or external kind.
- `registry_books` stores configuration and next sequence only; no real book or number is seeded.
- `correspondence_registrations` enforces unique sequence and formatted numbers per book.
- Only one uncancelled registration exists per correspondence and registration role.
- Registration cancellation preserves historical rows.
- `correspondence_relations` rejects self-reference and duplicate identical relations.
- `correspondence_referrals` enforces exactly one user/unit/position target.
- Forwarding uses `parent_referral_id` and does not overwrite the parent.
- Completed referrals remain historical.
- Personal and unit cartables are derived from active referrals; no second inbox table exists.
- `correspondence_events` is append-only and stores safe metadata only.
- `private_files` stores no binary content or public URL.
- `correspondence_attachments` links private metadata to correspondence/version rows.

Lookup and permission seed:

- `AutomationCorrespondenceSeeder` is registered exactly once and is idempotent.
- All required lookup domains have canonical machine codes and Persian UTF-8 labels.
- Nine `automation.*` permissions are created idempotently.
- New permissions are granted only to `super_admin` by default.
- No sidebar link, page route, operational UI, or correspondence record is seeded.

Diagnostics expose booleans only:

- `automation_foundation_available`
- `correspondence_schema_available`
- `correspondence_versions_available`
- `correspondence_parties_available`
- `correspondence_registry_books_available`
- `correspondence_registrations_available`
- `correspondence_relations_available`
- `correspondence_referrals_available`
- `correspondence_event_history_available`
- `correspondence_attachment_metadata_available`
- `correspondence_permissions_available`
- `correspondence_no_operational_ui`

Security and regression:

- Future numbering allocation is documented as transaction and row-lock protected.
- Diagnostics expose no table names, counts, IDs, paths, SQL, keys, or records.
- No upload/download, notification, delivery, signature, OCR, PDF, workflow, tracking, or external API is added.
- Ministry canonical geography remains the only operational canonical geography.
- No canonical geography, SCI, Rural Cooperation, or bot data is written.
- No reserved `ROWS` SQL alias exists.
- Persian UTF-8 lookup labels remain valid.
- `git diff --check` passes.

## v0.4.8 Platform Commercial Foundation Checks

Version: `0.4.8-platform-commercial-foundation-dev`

Schema and registry:

- Protected migration creates all `platform_*` catalog, topology, licensing, and provisioning tables.
- Migration is additive, idempotent, utf8mb4, InnoDB, and MariaDB-compatible.
- No existing Auth, RBAC, MFA, organization, geography, or automation table is renamed, removed, or moved.
- No cross-database foreign key is introduced.
- No global foreign-key disabling is used.
- Endpoint credentials are represented only by secret/credential reference columns.
- No database password, storage secret, private key, token, or signed license content is stored in plaintext schema fields.
- Application codes are unique and lowercase.
- Module codes are unique and lowercase.
- Module dependency self-reference is rejected.
- Duplicate module dependencies are rejected.
- Installation/application and installation/module associations are unique.
- Domain primary and alias rules are deterministic per installation, environment, and application.
- License entitlements reference known modules.
- License limits are unique by entitlement and metric.
- Provisioning step order is deterministic.

Seeder:

- `PlatformCommercialFoundationSeeder` is registered exactly once.
- Application catalog seeds `core` and `automation` idempotently.
- Core modules are seeded idempotently.
- Automation modules are seeded idempotently.
- Module dependencies are seeded idempotently.
- No operational installation, environment, domain, endpoint, license, customer, invoice, or correspondence record is seeded.

Diagnostics:

- `platform_catalog_available`
- `platform_application_catalog_available`
- `platform_module_catalog_available`
- `platform_module_dependencies_available`
- `platform_installation_registry_available`
- `platform_topology_registry_available`
- `platform_license_foundation_available`
- `platform_entitlement_contract_available`
- `platform_provisioning_foundation_available`
- `platform_connection_secrets_not_stored_plaintext`
- `platform_cross_database_foreign_keys_absent`
- `platform_existing_runtime_compatibility_preserved`

Security and compatibility:

- Diagnostics expose no counts, IDs, domains, hosts, database names, customer references, license contents, topology details, or secret references.
- No existing route is license-blocked in this phase.
- Existing super_admin access remains functional.
- Current v0.4.7 automation correspondence foundation remains compatible.
- Runtime license enforcement, activation, infrastructure creation, and operational automation UI remain deferred.

## v0.4.8 Multi-Database Runtime Foundation Checks

- `core.primary` is registered.
- `core.primary` uses the existing core PDO connection behavior.
- `automation.primary` is registered.
- With no complete `AUTOMATION_DB_*` configuration, `automation.primary` falls back to `core.primary`.
- With complete dedicated automation configuration, `automation.primary` can create an independent PDO definition.
- Partial automation configuration fails safely and does not expose credentials, DSNs, hostnames, database names, SQL, or PDO errors publicly.
- Named connections enforce utf8mb4.
- Named connections apply the existing UTC database-session timezone policy.
- `ApplicationMigrationRegistry` groups core and automation migrations deterministically.
- `ApplicationSeederRegistry` groups core and automation seeders deterministically.
- `application_migrations` records application, connection, and migration name.
- Legacy protected `/migrate.php` behavior remains available.
- Legacy protected `/seed.php` behavior remains available.
- Existing automation correspondence tables remain in the current database.
- No table is moved, copied, renamed, or deleted.
- No cross-database SQL or cross-database foreign key is introduced.
- No distributed transaction support is added.
- No runtime route is license-blocked or forced to require a dedicated automation database.
- Diagnostics include only safe booleans for named connection readiness.

## v0.4.8 Standalone Automation Provisioning Checks

- `CreateStandaloneAutomationCorrespondenceFoundationTables` is registered only for the Automation application migration group.
- Legacy `CreateAutomationCorrespondenceFoundationTables` remains available for the Core-hosted rollback baseline.
- The standalone Automation profile preserves Automation-internal foreign keys.
- The standalone Automation profile omits Core-targeting foreign keys.
- Core references are represented by scalar reference contracts, not cross-database SQL.
- `AutomationCorrespondenceSeeder` writes only Automation-local lookup metadata.
- `AutomationCorrespondencePermissionsSeeder` writes Core RBAC permissions and super_admin grants.
- `/migrate.php` without `application` preserves existing legacy behavior.
- `/seed.php` without `application` preserves existing legacy behavior.
- `/migrate.php?application=automation` requires a valid maintenance key, `APP_DEBUG=true`, and a dedicated non-fallback `automation.primary`.
- `/seed.php?application=automation` requires a valid maintenance key, `APP_DEBUG=true`, a dedicated non-fallback `automation.primary`, and an existing standalone schema.
- Application failure responses expose only opaque failure references.
- Public diagnostics expose no database host, port, database name, username, password, DSN, SQL, row count, PDO error, Core ID, subject, party, filename, or storage key.
- Legacy Core Automation tables are retained and are not dropped, renamed, truncated, copied, synchronized, or retired.
- Cutover readiness remains false until dedicated connection, schema, metadata, FK, cross-database policy, and legacy operational-data guards all pass.
- No operational correspondence UI, service, workflow, document generation, licensing enforcement, database creation, database-user creation, or credential commit is added.

## v0.4.8 Guarded Automation Runtime Activation Checks

- `AUTOMATION_DB_MODE` is documented and present in `.env.example`.
- Allowed modes are `fallback`, `provisioning`, and `dedicated`.
- Missing mode never defaults to `dedicated`.
- Invalid mode is represented safely and does not activate dedicated runtime.
- `provisioning` keeps the current Automation runtime source unchanged.
- `dedicated` requires all cutover guard conditions.
- `dedicated` rejects fallback Core connection use for Automation runtime.
- Dedicated connection failure fails closed.
- Core runtime remains available if Automation dedicated storage is unavailable.
- Automatic cutover is disabled.
- Automatic rollback is disabled.
- Explicit rollback remains available through `AUTOMATION_DB_MODE=provisioning` or `fallback`.
- Legacy rollback source tables are retained.
- No dual-write behavior is introduced.
- No cross-database SQL or FK is introduced.
- No legacy Automation table is removed, renamed, truncated, copied, or modified.
- Application migration and seeder modes remain protected and compatible.
- Diagnostics expose only safe booleans and the safe runtime mode enum.
- Runtime version remains `0.4.8-platform-commercial-foundation-dev`.

## v0.4.8 Automation Correspondence Demo Slice Checks

- Runtime version remains `0.4.8-platform-commercial-foundation-dev`.
- `/admin/automation` opens the Automation demo dashboard when the dedicated Automation runtime and cutover guard pass.
- `/admin/automation/correspondences` lists correspondence drafts using opaque public references.
- `/admin/automation/correspondences/create` renders a Persian RTL draft form.
- `POST /admin/automation/correspondences` creates one draft aggregate, first immutable version, parties, and a created event in a single Automation transaction.
- `/admin/automation/correspondences/{public_reference}` renders the detail workspace without exposing numeric database IDs.
- Draft edit creates a new immutable version and uses a stale-update lock guard.
- Operational repositories use the dedicated Automation connection and do not use Core PDO for Automation operational tables.
- Core references are validated before Automation transactions and no cross-database SQL is introduced.
- Diagnostics expose only boolean demo-slice flags.
- No migration, seeder, schema, lookup metadata, permission code, or runtime version change is part of the demo slice.
- Registry numbering, cartable workflow, referrals UI, attachment upload/download, delivery, signature, OCR, document generation, tracking, and external APIs remain deferred.