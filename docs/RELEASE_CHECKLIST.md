# IPKF Release Checklist

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

- `/health` returns `0.4.6-admin-users-organization-dev`.
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

Next phase: `v0.4.6-admin-users-organization-dev`.

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

- `/health` returns version `0.4.6-admin-users-organization-dev`.
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
