<?php

/** @var \IPKF\Routing\Router $router */

$router->get('/', function ($request, $response) {
    $siteMode = (string) \IPKF\Support\Env::get('SITE_MODE', 'coming_soon');
    $view = BASE_PATH . '/resources/views/site/coming-soon.php';

    if (is_readable($view)) {
        ob_start();
        require $view;
        $content = ob_get_clean() ?: '';

        return $response
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->send($content);
    }

    return $response->send('IPKF Framework Genesis OK');
});

$router->get('/health', function ($request, $response) {
    return $response->json([
        'status' => 'ok',
        'framework' => 'IPKF',
        'version' => \IPKF\Support\Version::CURRENT,
    ]);
});

$router->get('/csrf-token', function ($request, $response) {
    return $response->json([
        'status' => 'ok',
        'csrf_token' => (new \IPKF\Security\Csrf())->token(),
    ]);
});

$router->get('/_diagnostics', function ($request, $response) use ($router) {
    $debug = \IPKF\Support\Env::isDebug();

    if (!$debug) {
        return $response->status(404)->send('404 - Route not found: /_diagnostics');
    }

    $databaseConnectionAvailable = false;
    $databaseConnectionMessage = 'not configured';

    if (\IPKF\Database\Database::configured()) {
        try {
            \IPKF\Database\Database::connect();
            $databaseConnectionAvailable = true;
            $databaseConnectionMessage = 'available';
        } catch (\Throwable $exception) {
            $databaseConnectionMessage = 'unavailable';
        }
    }

    $runtimeCheckTableExists = $databaseConnectionAvailable
        ? \IPKF\Database\Database::tableExists('ipkf_runtime_checks')
        : false;

    $runtimeCheckFound = false;
    $runtimeCheckValue = null;

    if ($runtimeCheckTableExists) {
        try {
            $statement = \IPKF\Database\Database::connect()->prepare("
                SELECT check_value
                FROM ipkf_runtime_checks
                WHERE check_key = ?
                LIMIT 1
            ");
            $statement->execute(['foundation_v0_2']);
            $value = $statement->fetchColumn();

            if ($value !== false) {
                $runtimeCheckFound = true;
                $runtimeCheckValue = $value;
            }
        } catch (\Throwable $exception) {
            $runtimeCheckFound = false;
            $runtimeCheckValue = null;
        }
    }

    $personsTableExists = \IPKF\Database\Database::tableExists('persons');
    $usersTableExists = \IPKF\Database\Database::tableExists('users');
    $roleAreasTableExists = \IPKF\Database\Database::tableExists('role_areas');
    $roleKindsTableExists = \IPKF\Database\Database::tableExists('role_kinds');
    $rolesTableExists = \IPKF\Database\Database::tableExists('roles');
    $permissionsTableExists = \IPKF\Database\Database::tableExists('permissions');
    $rolePermissionsTableExists = \IPKF\Database\Database::tableExists('role_permissions');
    $userRoleAssignmentsTableExists = \IPKF\Database\Database::tableExists('user_role_assignments');
    $orgUnitsTableExists = \IPKF\Database\Database::tableExists('org_units');
    $positionsTableExists = \IPKF\Database\Database::tableExists('positions');
    $userOrgAssignmentsTableExists = \IPKF\Database\Database::tableExists('user_org_assignments');
    $personProfilesTableExists = \IPKF\Database\Database::tableExists('person_profiles');
    $contactTypesTableExists = \IPKF\Database\Database::tableExists('contact_types');
    $personContactsTableExists = \IPKF\Database\Database::tableExists('person_contacts');
    $addressTypesTableExists = \IPKF\Database\Database::tableExists('address_types');
    $personAddressesTableExists = \IPKF\Database\Database::tableExists('person_addresses');

    $mfaSchemaAvailable = \IPKF\Database\Database::tableExists('user_mfa_methods')
        && \IPKF\Database\Database::tableExists('mfa_challenges')
        && \IPKF\Database\Database::tableExists('trusted_devices')
        && \IPKF\Database\Database::tableExists('recovery_codes');

    $organizationsHierarchyReady = \IPKF\Database\Database::tableExists('organizations')
        && \IPKF\Database\Database::columnExists('organizations', 'parent_id')
        && \IPKF\Database\Database::columnExists('organizations', 'depth')
        && \IPKF\Database\Database::columnExists('organizations', 'path');

    $adminUserExists = false;
    $superAdminRoleExists = false;
    $superAdminAssignmentExists = false;
    $adminUsersPermissionsSeeded = false;

    if ($databaseConnectionAvailable) {
        try {
            $db = \IPKF\Database\Database::connect();

            if ($usersTableExists) {
                $adminEmail = \IPKF\Support\Env::get('ADMIN_EMAIL', '');
                $statement = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND status = 'active'");
                $statement->execute([$adminEmail]);
                $adminUserExists = $adminEmail !== '' && (int) $statement->fetchColumn() > 0;
            }

            if ($rolesTableExists) {
                $statement = $db->prepare("SELECT id FROM roles WHERE code = 'super_admin' AND is_active = 1 LIMIT 1");
                $statement->execute();
                $superAdminRoleId = $statement->fetchColumn();
                $superAdminRoleExists = $superAdminRoleId !== false;

                if ($superAdminRoleExists && $userRoleAssignmentsTableExists) {
                    $statement = $db->prepare("
                        SELECT COUNT(*)
                        FROM user_role_assignments
                        WHERE role_id = ?
                          AND scope_type = 'global'
                          AND is_active = 1
                    ");
                    $statement->execute([(int) $superAdminRoleId]);
                    $superAdminAssignmentExists = (int) $statement->fetchColumn() > 0;
                }
            }

            if ($permissionsTableExists && $rolesTableExists && $rolePermissionsTableExists) {
                $statement = $db->query("
                    SELECT COUNT(*)
                    FROM permissions
                    WHERE code IN (
                        'users.view',
                        'users.manage',
                        'org_units.view',
                        'org_units.manage',
                        'positions.view',
                        'positions.manage',
                        'user_org_assignments.manage'
                    )
                      AND is_active = 1
                ");
                $adminUsersPermissionsSeeded = (int) $statement->fetchColumn() === 7;

                $requiredRolePermissions = [
                    'super_admin' => [
                        'users.view',
                        'users.manage',
                        'org_units.view',
                        'org_units.manage',
                        'positions.view',
                        'positions.manage',
                        'user_org_assignments.manage',
                    ],
                    'system_admin' => [
                        'users.view',
                        'users.manage',
                        'org_units.view',
                        'org_units.manage',
                        'positions.view',
                        'positions.manage',
                        'user_org_assignments.manage',
                    ],
                    'province_admin' => [
                        'users.view',
                        'org_units.view',
                        'positions.view',
                    ],
                ];

                foreach ($requiredRolePermissions as $roleCode => $permissionCodes) {
                    $quotedCodes = "'" . implode("','", $permissionCodes) . "'";
                    $statement = $db->query("
                        SELECT COUNT(DISTINCT permissions.code)
                        FROM role_permissions
                        INNER JOIN roles ON roles.id = role_permissions.role_id
                        INNER JOIN permissions ON permissions.id = role_permissions.permission_id
                        WHERE roles.code = '{$roleCode}'
                          AND permissions.code IN ({$quotedCodes})
                          AND roles.is_active = 1
                          AND permissions.is_active = 1
                    ");

                    if ((int) $statement->fetchColumn() !== count($permissionCodes)) {
                        $adminUsersPermissionsSeeded = false;
                        break;
                    }
                }
            }
        } catch (\Throwable $exception) {
            $adminUserExists = false;
            $superAdminRoleExists = false;
            $superAdminAssignmentExists = false;
            $adminUsersPermissionsSeeded = false;
        }
    }

    $adminTheme = class_exists(\App\Services\AdminThemeService::class)
        ? new \App\Services\AdminThemeService()
        : null;
    $diagnosticUserId = null;

    if (class_exists(\App\Services\AuthService::class)) {
        try {
            $diagnosticUserId = (new \App\Services\AuthService())->currentUserId();
        } catch (\Throwable $exception) {
            $diagnosticUserId = null;
        }
    }

    $adminThemeData = $adminTheme === null ? [] : $adminTheme->theme($diagnosticUserId);
    $adminThemeTokens = $adminThemeData['tokens'] ?? [];
    $adminAssetsAvailable = is_dir(BASE_PATH . '/public/assets/admin')
        && is_dir(BASE_PATH . '/public/assets/admin/css')
        && is_dir(BASE_PATH . '/public/assets/admin/fonts')
        && is_readable(BASE_PATH . '/public/assets/admin/images/logos/default-logo.svg')
        && is_readable(BASE_PATH . '/public/assets/admin/images/avatars/default-avatar.svg')
        && is_dir(BASE_PATH . '/public/uploads/admin/logos')
        && is_dir(BASE_PATH . '/public/uploads/admin/avatars')
        && is_readable(BASE_PATH . '/public/uploads/admin/.htaccess')
        && is_readable(BASE_PATH . '/public/uploads/admin/logos/.htaccess')
        && is_readable(BASE_PATH . '/public/uploads/admin/avatars/.htaccess');

    return $response->json([
        'php_version' => PHP_VERSION,
        'base_path' => BASE_PATH,
        'app_env' => \IPKF\Support\Env::get('APP_ENV', 'production'),
        'app_debug' => $debug,
        'site_mode' => \IPKF\Support\Env::get('SITE_MODE', 'coming_soon'),
        'public_landing_available' => is_readable(BASE_PATH . '/resources/views/site/coming-soon.php')
            && is_readable(BASE_PATH . '/public/assets/css/landing.css'),
        'coming_soon_landing_available' => is_readable(BASE_PATH . '/resources/views/site/coming-soon.php'),
        'env_loaded' => \IPKF\Support\Env::loaded(),
        'config_loaded' => \IPKF\Support\Config::loaded(),
        'database_config_loaded' => \IPKF\Support\Config::has('database.connections.mysql'),
        'database_connection_available' => $databaseConnectionAvailable,
        'database_connection_message' => $databaseConnectionMessage,
        'database_charset_configured' => \IPKF\Support\Config::get('database.connections.mysql.charset', 'utf8mb4'),
        'database_connection_charset' => \IPKF\Database\Database::connectionCharset(),
        'utf8mb4_ready' => \IPKF\Support\Config::get('database.connections.mysql.charset', 'utf8mb4') === 'utf8mb4'
            && \IPKF\Database\Database::connectionCharset() === 'utf8mb4',
        'migration_system_available' => class_exists(\IPKF\Database\Migrations\MigrationRunner::class)
            && class_exists(\IPKF\Database\Migrations\CreateRuntimeChecksTable::class),
        'seeder_system_available' => class_exists(\IPKF\Database\Seeds\SeederRunner::class)
            && class_exists(\IPKF\Database\Seeds\RuntimeCheckSeeder::class),
        'runtime_check_table_exists' => $runtimeCheckTableExists,
        'runtime_check_found' => $runtimeCheckFound,
        'runtime_check_value' => $runtimeCheckValue,
        'auth_schema_available' => $personsTableExists && $usersTableExists,
        'rbac_schema_available' => $roleAreasTableExists
            && $roleKindsTableExists
            && $rolesTableExists
            && $permissionsTableExists
            && $rolePermissionsTableExists
            && $userRoleAssignmentsTableExists,
        'persons_table_exists' => $personsTableExists,
        'users_table_exists' => $usersTableExists,
        'role_areas_table_exists' => $roleAreasTableExists,
        'role_kinds_table_exists' => $roleKindsTableExists,
        'roles_table_exists' => $rolesTableExists,
        'permissions_table_exists' => $permissionsTableExists,
        'role_permissions_table_exists' => $rolePermissionsTableExists,
        'user_role_assignments_table_exists' => $userRoleAssignmentsTableExists,
        'mfa_schema_available' => $mfaSchemaAvailable,
        'mfa_runtime_available' => class_exists(\App\Services\MfaService::class),
        'mfa_totp_available' => class_exists(\App\Services\TotpService::class),
        'mfa_recovery_codes_available' => class_exists(\App\Services\RecoveryCodeService::class),
        'mfa_trusted_devices_available' => class_exists(\App\Services\TrustedDeviceService::class),
        'mfa_routes_available' => true,
        'mfa_delivery_channels_available' => class_exists(\App\Services\MfaDeliveryChannelService::class),
        'identity_normalizer_available' => class_exists(\App\Services\IdentityNormalizer::class),
        'identity_change_flow_available' => class_exists(\App\Services\IdentityChangeService::class)
            && \IPKF\Database\Database::tableExists('identity_change_requests'),
        'identity_dev_expose_token_enabled' => \IPKF\Support\Env::get('APP_ENV', 'production') === 'development'
            && $debug
            && filter_var(\IPKF\Support\Env::get('IDENTITY_DEV_EXPOSE_TOKEN', false), FILTER_VALIDATE_BOOLEAN),
        'login_token_system_available' => class_exists(\App\Services\LoginTokenService::class)
            && \IPKF\Database\Database::tableExists('auth_login_tokens'),
        'app_url_configured' => \IPKF\Support\Env::get('APP_URL', '') !== '',
        'app_timezone_configured' => \IPKF\Support\Env::get('APP_TIMEZONE', 'Asia/Tehran'),
        'login_token_url_base' => (new \App\Services\LoginTokenService())->urlBase(),
        'active_access_switching_available' => class_exists(\App\Services\AccessService::class),
        'default_lowest_role_available' => \IPKF\Database\Database::tableExists('roles')
            && \IPKF\Database\Database::columnExists('roles', 'priority'),
        'organizations_hierarchy_ready' => $organizationsHierarchyReady,
        'auth_session_available' => class_exists(\App\Services\AuthService::class)
            && class_exists(\IPKF\Support\Session::class),
        'csrf_available' => class_exists(\IPKF\Security\Csrf::class),
        'session_name_configured' => \IPKF\Support\Session::name(),
        'session_cookie_name' => \IPKF\Support\Session::name(),
        'admin_user_exists' => $adminUserExists,
        'super_admin_role_exists' => $superAdminRoleExists,
        'super_admin_assignment_exists' => $superAdminAssignmentExists,
        'auth_routes_available' => true,
        'admin_panel_shell_available' => class_exists(\App\Services\AdminPanelService::class),
        'admin_theme_available' => $adminTheme !== null
            && \IPKF\Database\Database::tableExists('app_settings'),
        'admin_theme_forensics_available' => $adminTheme !== null,
        'admin_theme_runtime_fix_version' => $adminTheme !== null ? \App\Services\AdminThemeService::RUNTIME_FIX_VERSION : null,
        'admin_theme_active_preset' => $adminThemeData['active_preset'] ?? 'official_emerald',
        'admin_theme_resolved_source' => $adminTheme !== null ? $adminTheme->resolvedPresetSource($diagnosticUserId) : 'default',
        'admin_theme_system_preset_exists' => $adminTheme !== null && $adminTheme->systemPresetExists(),
        'admin_theme_personal_preset_exists_for_current_user' => $adminTheme !== null && $adminTheme->personalPresetExists($diagnosticUserId),
        'admin_theme_token_override_rows_count' => $adminTheme !== null ? $adminTheme->forensics($diagnosticUserId)['token_override_rows_count'] : 0,
        'admin_theme_token_override_rows_ignored' => true,
        'admin_theme_custom_editor_enabled' => false,
        'admin_theme_builtin_presets_only' => true,
        'admin_theme_scope_support' => $adminTheme !== null && $adminTheme->scopeSupport(),
        'admin_personal_theme_available' => $adminTheme !== null && $adminTheme->scopeSupport(),
        'admin_system_theme_available' => $adminTheme !== null && \IPKF\Database\Database::tableExists('app_settings'),
        'admin_theme_reset_available' => $adminTheme !== null && $adminTheme->scopeSupport(),
        'admin_branding_configurable' => $adminTheme !== null,
        'admin_assets_canonical' => $adminTheme !== null && $adminTheme->assetsCanonical(),
        'current_theme_resolver_available' => $adminTheme !== null && $adminTheme->currentThemeResolverAvailable(),
        'theme_user_scope_supported' => $adminTheme !== null && $adminTheme->themeUserScopeSupported(),
        'admin_local_icons_available' => $adminTheme !== null && $adminTheme->localIconsAvailable(),
        'admin_webfonts_path_available' => $adminTheme !== null && $adminTheme->webfontsPathAvailable(),
        'admin_assets_available' => $adminAssetsAvailable,
        'admin_typography_available' => isset($adminThemeTokens['font_family'], $adminThemeTokens['font_size_base'], $adminThemeTokens['line_height_base']),
        'admin_logo_configured' => ($adminThemeData['logo_url'] ?? '') !== '',
        'admin_default_avatar_configured' => ($adminThemeData['default_avatar_url'] ?? '') !== '',
        'admin_theme_persian_ok' => $adminTheme !== null && $adminTheme->persianDefaultsOk(),
        'admin_footer_available' => true,
        'admin_user_menu_available' => true,
        'admin_password_recovery_ui_available' => true,
        'admin_mfa_recovery_ui_available' => true,
        'admin_two_part_navigation_available' => true,
        'admin_responsive_ui_available' => true,
        'admin_navigation_rbac_available' => class_exists(\App\Services\AdminNavigationRbacService::class),
        'admin_route_guards_available' => class_exists(\App\Services\AdminNavigationRbacService::class),
        'admin_menu_permission_filtering_available' => class_exists(\App\Services\AdminNavigationRbacService::class),
        'admin_dashboard_module_tiles_available' => class_exists(\App\Services\AdminPanelService::class),
        'admin_dashboard_modules_permission_filtered' => class_exists(\App\Services\AdminPanelService::class)
            && class_exists(\App\Services\AdminNavigationRbacService::class),
        'admin_dashboard_modules_active_role_aware' => class_exists(\App\Services\AdminPanelService::class)
            && class_exists(\App\Services\AuthorizationService::class)
            && class_exists(\App\Services\AccessService::class),
        'admin_visual_module_launcher_available' => class_exists(\App\Services\AdminPanelService::class)
            && class_exists(\App\Support\AdminIcon::class),
        'admin_module_hub_pages_available' => true,
        'admin_local_icon_font_available' => is_readable(BASE_PATH . '/public/assets/admin/css/icons.css'),
        'admin_module_actions_permission_filtered' => class_exists(\App\Services\AdminPanelService::class)
            && class_exists(\App\Services\AdminNavigationRbacService::class),
        'admin_sidebar_module_level_navigation' => class_exists(\App\Services\AdminPanelService::class),
        'admin_sidebar_duplicate_child_links_removed' => true,
        'admin_sidebar_child_route_active_mapping' => true,
        'admin_active_role_permission_context' => class_exists(\App\Services\AuthorizationService::class)
            && class_exists(\App\Services\AccessService::class),
        'admin_active_access_switch_available' => class_exists(\App\Services\AccessService::class),
        'admin_active_access_switch_self_service' => true,
        'admin_dashboard_account_cards_removed' => true,
        'admin_dashboard_access_summary_removed' => true,
        'admin_profile_access_page_available' => true,
        'admin_profile_self_service_role_switch_available' => true,
        'admin_profile_security_status_available' => true,
        'admin_runtime_version_in_footer' => true,
        'admin_users_menu_available' => true,
        'admin_org_units_menu_available' => true,
        'admin_positions_menu_available' => true,
        'admin_users_permissions_seeded' => $adminUsersPermissionsSeeded,
        'admin_users_list_available' => class_exists(\App\Services\AdminUserService::class)
            && class_exists(\App\Repositories\AdminUserRepository::class),
        'admin_users_search_available' => class_exists(\App\Services\AdminUserService::class),
        'admin_users_pagination_available' => class_exists(\App\Services\AdminUserService::class),
        'admin_users_sensitive_fields_protected' => true,
        'admin_user_detail_available' => true,
        'admin_user_detail_roles_available' => true,
        'admin_user_detail_org_assignments_available' => true,
        'admin_user_detail_security_summary_available' => true,
        'admin_user_detail_sensitive_fields_protected' => true,
        'admin_user_detail_semantic_lookups_available' => true,
        'admin_raw_foreign_keys_hidden_from_ui' => true,
        'admin_reference_titles_resolved' => true,
        'admin_org_units_list_available' => class_exists(\App\Services\AdminOrgUnitService::class)
            && class_exists(\App\Repositories\AdminOrgUnitRepository::class),
        'admin_org_units_search_available' => class_exists(\App\Services\AdminOrgUnitService::class),
        'admin_org_units_pagination_available' => class_exists(\App\Services\AdminOrgUnitService::class),
        'admin_org_units_hierarchy_display_available' => true,
        'admin_positions_list_available' => class_exists(\App\Services\AdminPositionService::class)
            && class_exists(\App\Repositories\AdminPositionRepository::class),
        'admin_positions_search_available' => class_exists(\App\Services\AdminPositionService::class),
        'admin_positions_pagination_available' => class_exists(\App\Services\AdminPositionService::class),
        'admin_users_organization_foundation_available' => $orgUnitsTableExists
            && $positionsTableExists
            && $userOrgAssignmentsTableExists,
        'org_units_schema_available' => $orgUnitsTableExists,
        'positions_schema_available' => $positionsTableExists,
        'user_org_assignments_schema_available' => $userOrgAssignmentsTableExists,
        'person_extended_profile_schema_available' => $personProfilesTableExists,
        'person_contacts_schema_available' => $personContactsTableExists,
        'person_addresses_schema_available' => $personAddressesTableExists,
        'person_contact_types_schema_available' => $contactTypesTableExists,
        'person_address_types_schema_available' => $addressTypesTableExists,
        'person_sensitive_data_foundation_available' => $personProfilesTableExists
            && $contactTypesTableExists
            && $personContactsTableExists
            && $addressTypesTableExists
            && $personAddressesTableExists,
        'installer_available' => class_exists(\IPKF\Installer\Installer::class),
        'installed' => (new \IPKF\Installer\InstallationState())->installed(),
        'storage_writable' => is_writable(BASE_PATH . '/storage'),
        'routes_loaded_count' => $router->count(),
        'autoload' => [
            'vendor_autoload_exists' => is_readable(BASE_PATH . '/vendor/autoload.php'),
            'custom_loader_exists' => is_readable(BASE_PATH . '/vendor/ipkf_loader.php'),
            'application_class_loaded' => class_exists(\IPKF\Core\Application::class),
            'router_class_loaded' => class_exists(\IPKF\Routing\Router::class),
            'request_class_loaded' => class_exists(\IPKF\Http\Request::class),
        ],
        'timestamp' => date(DATE_ATOM),
    ]);
});

$router->get('/test', function ($req, $res) {
    return $res->send("Test Route OK");
});

$adminRender = function ($response, string $view, array $data = [], int $status = 200) {
    $path = BASE_PATH . '/resources/views/admin/' . $view . '.php';

    if (!is_readable($path)) {
        return $response->status(500)->send('Admin view not found.');
    }

    extract($data, EXTR_SKIP);
    ob_start();
    require $path;
    $content = ob_get_clean() ?: '';

    return $response
        ->status($status)
        ->header('Content-Type', 'text/html; charset=UTF-8')
        ->send($content);
};

$adminContext = fn (): ?array => (new \App\Services\AdminPanelService())->context();

$adminGuard = function ($response, string $path) use ($adminRender, $adminContext) {
    $context = $adminContext();

    if ($context === null) {
        return $response->redirect('/admin/login');
    }

    $userId = (int) $context['user_id'];

    if (!(new \App\Services\AdminNavigationRbacService())->canAccessPath($userId, $path)) {
        return $adminRender($response, 'forbidden', [
            'title' => html_entity_decode('&#x062F;&#x0633;&#x062A;&#x0631;&#x0633;&#x06CC; &#x063A;&#x06CC;&#x0631;&#x0645;&#x062C;&#x0627;&#x0632;', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'context' => $context,
        ], 403);
    }

    return $context;
};

$router->get('/admin', function ($request, $response) {
    return $response->redirect((new \App\Services\AuthService())->authenticated()
        ? '/admin/dashboard'
        : '/admin/login');
});

$router->get('/admin/login', function ($request, $response) use ($adminRender) {
    if ((new \App\Services\AuthService())->authenticated()) {
        return $response->redirect('/admin/dashboard');
    }

    return $adminRender($response, 'login', [
        'title' => 'ورود به پنل مدیریت',
        'error' => null,
        'login' => '',
    ]);
});

$router->get('/admin/forgot-password', function ($request, $response) use ($adminRender) {
    if ((new \App\Services\AuthService())->authenticated()) {
        return $response->redirect('/admin/dashboard');
    }

    return $adminRender($response, 'forgot-password', [
        'title' => 'بازیابی کلمه عبور',
        'sent' => false,
        'identifier' => '',
    ]);
});

$router->post('/admin/forgot-password', function ($request, $response) use ($adminRender) {
    if ((new \App\Services\AuthService())->authenticated()) {
        return $response->redirect('/admin/dashboard');
    }

    return $adminRender($response, 'forgot-password', [
        'title' => 'بازیابی کلمه عبور',
        'sent' => true,
        'identifier' => trim((string) $request->input('login', '')),
    ]);
});

$router->post('/admin/login', function ($request, $response) use ($adminRender) {
    $login = trim((string) $request->input('login', ''));
    $password = (string) $request->input('password', '');
    $auth = new \App\Services\AuthService();
    $user = $auth->attempt($login, $password);

    if ($user === null) {
        return $adminRender($response, 'login', [
            'title' => 'ورود به پنل مدیریت',
            'error' => 'اطلاعات ورود معتبر نیست.',
            'login' => $login,
        ], 422);
    }

    if (($user['mfa_required'] ?? false) === true) {
        return $response->redirect('/admin/mfa');
    }

    return $response->redirect('/admin/dashboard');
});

$router->get('/admin/mfa', function ($request, $response) use ($adminRender) {
    if ((new \App\Services\AuthService())->authenticated()) {
        return $response->redirect('/admin/dashboard');
    }

    if ((new \App\Services\MfaService())->pendingUserId() === null) {
        return $response->redirect('/admin/login');
    }

    return $adminRender($response, 'mfa', [
        'title' => 'رمز یکبارمصرف',
        'error' => null,
    ]);
});

$router->post('/admin/mfa', function ($request, $response) use ($adminRender) {
    $totpCode = trim((string) $request->input('code', ''));
    $recoveryCode = trim((string) $request->input('recovery_code', ''));
    $method = $recoveryCode !== '' ? 'recovery_code' : 'totp';
    $code = $recoveryCode !== '' ? $recoveryCode : $totpCode;
    $mfa = new \App\Services\MfaService();
    $userId = $mfa->verifyPendingChallenge($method, $code);

    if ($userId === null) {
        return $adminRender($response, 'mfa', [
            'title' => 'رمز یکبارمصرف',
            'error' => 'رمز وارد شده معتبر نیست.',
        ], 422);
    }

    (new \App\Services\AuthService())->completeMfaLogin($userId);

    return $response->redirect('/admin/dashboard');
});

$router->get('/admin/mfa/recovery', function ($request, $response) use ($adminRender) {
    if ((new \App\Services\AuthService())->authenticated()) {
        return $response->redirect('/admin/dashboard');
    }

    if ((new \App\Services\MfaService())->pendingUserId() === null) {
        return $response->redirect('/admin/login');
    }

    return $adminRender($response, 'mfa-recovery', [
        'title' => 'کد بازیابی',
        'error' => null,
    ]);
});

$router->post('/admin/mfa/recovery', function ($request, $response) use ($adminRender) {
    $code = trim((string) $request->input('recovery_code', ''));
    $userId = (new \App\Services\MfaService())->verifyPendingChallenge('recovery_code', $code);

    if ($userId === null) {
        return $adminRender($response, 'mfa-recovery', [
            'title' => 'کد بازیابی',
            'error' => 'کد بازیابی معتبر نیست.',
        ], 422);
    }

    (new \App\Services\AuthService())->completeMfaLogin($userId);

    return $response->redirect('/admin/dashboard');
});

$router->get('/admin/dashboard', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/dashboard');

    if (!is_array($context)) {
        return $context;
    }

    return $adminRender($response, 'dashboard', [
        'title' => 'داشبورد مدیریت',
        'context' => $context,
    ]);
});

$adminModuleHub = function (string $key, string $title) use ($adminRender, $adminContext) {
    return function ($request, $response) use ($adminRender, $adminContext, $key, $title) {
        $context = $adminContext();

        if ($context === null) {
            return $response->redirect('/admin/login');
        }

        $module = (new \App\Services\AdminPanelService())->moduleHub((int) $context['user_id'], $key);

        if ($module === null) {
            return $adminRender($response, 'forbidden', [
                'title' => html_entity_decode('&#x062F;&#x0633;&#x062A;&#x0631;&#x0633;&#x06CC; &#x063A;&#x06CC;&#x0631;&#x0645;&#x062C;&#x0627;&#x0632;', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'context' => $context,
            ], 403);
        }

        return $adminRender($response, 'module-hub', [
            'title' => $title,
            'context' => $context,
            'module' => $module,
        ]);
    };
};

$router->get('/admin/modules/users', $adminModuleHub('users', 'مدیریت کاربران'));
$router->get('/admin/modules/organization', $adminModuleHub('organization', 'ساختار سازمانی'));
$router->get('/admin/modules/system', $adminModuleHub('system', 'مدیریت سامانه'));

$router->get('/admin/profile', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/profile');

    if (!is_array($context)) {
        return $context;
    }

    return $adminRender($response, 'profile', [
        'title' => 'پروفایل کاربر',
        'context' => $context,
    ]);
});

$router->get('/admin/profile/access', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/profile/access');

    if (!is_array($context)) {
        return $context;
    }

    return $adminRender($response, 'profile-access', [
        'title' => 'نقش‌ها و دسترسی‌های من',
        'context' => $context,
        'status' => trim((string) $request->input('status', '')),
    ]);
});

$router->get('/admin/account', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/account');

    if (!is_array($context)) {
        return $context;
    }

    return $adminRender($response, 'account', [
        'title' => 'اطلاعات حساب',
        'context' => $context,
    ]);
});

$router->get('/admin/security', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/security');

    if (!is_array($context)) {
        return $context;
    }

    return $adminRender($response, 'security', [
        'title' => 'امنیت و ورود',
        'context' => $context,
    ]);
});

$router->get('/admin/password', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/password');

    if (!is_array($context)) {
        return $context;
    }

    return $adminRender($response, 'password', [
        'title' => 'تغییر کلمه عبور',
        'context' => $context,
        'status' => trim((string) $request->input('status', '')),
        'error' => '',
    ]);
});

$router->post('/admin/password', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/password');

    if (!is_array($context)) {
        return $context;
    }

    $currentPassword = (string) $request->input('current_password', '');
    $password = (string) $request->input('password', '');
    $confirmation = (string) $request->input('password_confirmation', '');

    if (strlen($password) < 8 || $password !== $confirmation) {
        return $adminRender($response, 'password', [
            'title' => 'تغییر کلمه عبور',
            'context' => $context,
            'status' => '',
            'error' => 'کلمه عبور جدید معتبر نیست یا با تکرار آن یکسان نیست.',
        ], 422);
    }

    $changed = (new \App\Services\AuthService())->changePassword((int) $context['user_id'], $currentPassword, $password);

    if (!$changed) {
        return $adminRender($response, 'password', [
            'title' => 'تغییر کلمه عبور',
            'context' => $context,
            'status' => '',
            'error' => 'کلمه عبور فعلی معتبر نیست.',
        ], 422);
    }

    return $response->redirect('/admin/password?status=updated');
});

$router->get('/admin/my-theme', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/my-theme');

    if (!is_array($context)) {
        return $context;
    }

    $theme = new \App\Services\AdminThemeService();

    return $adminRender($response, 'my-theme', [
        'title' => 'پوسته نمایشی من',
        'context' => $context,
        'theme' => $theme->personalTheme((int) $context['user_id']),
        'presets' => $theme->presets(),
        'fontOptions' => $theme->fontOptions(),
        'errors' => [],
        'status' => trim((string) $request->input('status', '')),
    ]);
});

$router->post('/admin/my-theme', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/my-theme');

    if (!is_array($context)) {
        return $context;
    }

    $theme = new \App\Services\AdminThemeService();
    $result = $theme->savePersonalTheme((int) $context['user_id'], $request->all());

    if (!$result['ok']) {
        return $adminRender($response, 'my-theme', [
            'title' => 'پوسته نمایشی من',
            'context' => $context,
            'theme' => $theme->personalTheme((int) $context['user_id']),
            'presets' => $theme->presets(),
            'fontOptions' => $theme->fontOptions(),
            'errors' => $result['errors'],
            'status' => '',
        ], 422);
    }

    return $response->redirect('/admin/my-theme?status=saved');
});

$router->get('/admin/access', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/access');

    if (!is_array($context)) {
        return $context;
    }

    return $adminRender($response, 'access', [
        'title' => 'سطح دسترسی فعال',
        'context' => $context,
        'status' => trim((string) $request->input('status', '')),
    ]);
});

$router->get('/admin/theme', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/theme');

    if (!is_array($context)) {
        return $context;
    }

    $theme = new \App\Services\AdminThemeService();

    return $adminRender($response, 'theme', [
        'title' => 'پوسته پنل',
        'context' => $context,
        'theme' => $theme->systemTheme(),
        'presets' => $theme->presets(),
        'fontOptions' => $theme->fontOptions(),
        'logoOptions' => $theme->logoOptions(),
        'avatarOptions' => $theme->avatarOptions(),
        'errors' => [],
        'status' => trim((string) $request->input('status', '')),
        'canManageTheme' => (new \App\Services\AuthorizationService())->hasPermission((int) $context['user_id'], 'admin.theme.manage'),
    ]);
});

$router->post('/admin/theme', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/theme');

    if (!is_array($context)) {
        return $context;
    }

    if (!(new \App\Services\AuthorizationService())->hasPermission((int) $context['user_id'], 'admin.theme.manage')) {
        return $adminRender($response, 'theme', [
            'title' => 'پوسته پنل',
            'context' => $context,
            'theme' => (new \App\Services\AdminThemeService())->systemTheme(),
            'presets' => (new \App\Services\AdminThemeService())->presets(),
            'fontOptions' => (new \App\Services\AdminThemeService())->fontOptions(),
            'logoOptions' => (new \App\Services\AdminThemeService())->logoOptions(),
            'avatarOptions' => (new \App\Services\AdminThemeService())->avatarOptions(),
            'errors' => ['forbidden'],
            'status' => '',
            'canManageTheme' => false,
        ], 403);
    }

    $theme = new \App\Services\AdminThemeService();
    $result = $theme->saveSystemTheme($request->all());

    if (!$result['ok']) {
        return $adminRender($response, 'theme', [
            'title' => 'پوسته پنل',
            'context' => $context,
            'theme' => $theme->systemTheme(),
            'presets' => $theme->presets(),
            'fontOptions' => $theme->fontOptions(),
            'logoOptions' => $theme->logoOptions(),
            'avatarOptions' => $theme->avatarOptions(),
            'errors' => $result['errors'],
            'status' => '',
            'canManageTheme' => true,
        ], 422);
    }

    return $response->redirect('/admin/theme?status=saved');
});

$router->post('/admin/theme/reset', function ($request, $response) use ($adminGuard) {
    $scope = trim((string) $request->input('scope', 'user'));
    $context = $adminGuard($response, $scope === 'system' ? '/admin/theme' : '/admin/my-theme');

    if (!is_array($context)) {
        return $context;
    }

    $theme = new \App\Services\AdminThemeService();

    if ($scope === 'system') {
        if (!(new \App\Services\AuthorizationService())->hasPermission((int) $context['user_id'], 'admin.theme.manage')) {
            return $response->redirect('/admin/theme?status=forbidden');
        }

        $theme->resetSystemTheme();

        return $response->redirect('/admin/theme?status=reset');
    }

    $theme->resetPersonalTheme((int) $context['user_id']);

    return $response->redirect('/admin/my-theme?status=reset');
});

$router->get('/admin/theme/debug', function ($request, $response) use ($adminGuard) {
    if (!\IPKF\Support\Env::isDebug()) {
        return $response->status(404)->send('404 - Route not found: /admin/theme/debug');
    }

    $context = $adminGuard($response, '/admin/theme/debug');

    if (!is_array($context)) {
        return $context;
    }

    $themeService = new \App\Services\AdminThemeService();
    $forensics = $themeService->forensics((int) $context['user_id'], $context);
    $h = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    $rowTable = static function (array $rows) use ($h): string {
        if ($rows === []) {
            return '<p class="admin-muted">No rows.</p>';
        }

        $html = '<table class="admin-table"><thead><tr><th>scope</th><th>user_id</th><th>setting_key</th><th>setting_value</th><th>value_type</th><th>updated_at</th></tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>'
                . '<td>' . $h($row['scope'] ?? '') . '</td>'
                . '<td>' . $h($row['user_id'] ?? '') . '</td>'
                . '<td>' . $h($row['setting_key'] ?? '') . '</td>'
                . '<td><code>' . $h($row['setting_value'] ?? '') . '</code></td>'
                . '<td>' . $h($row['value_type'] ?? '') . '</td>'
                . '<td>' . $h($row['updated_at'] ?? '') . '</td>'
                . '</tr>';
        }

        return $html . '</tbody></table>';
    };
    $list = static function (array $items) use ($h): string {
        $html = '<dl class="admin-field-list">';

        foreach ($items as $key => $value) {
            $html .= '<div><span>' . $h($key) . '</span><strong>' . $h(is_bool($value) ? ($value ? 'true' : 'false') : $value) . '</strong></div>';
        }

        return $html . '</dl>';
    };

    ob_start();
    ?>
    <!doctype html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Admin Theme Debug | IPKF</title>
        <link rel="stylesheet" href="<?= $h($forensics['assets']['admin_css'] ?? '/assets/admin/css/admin.css') ?>">
        <style id="admin-theme-vars"><?= "\n" . ($forensics['css_variables'] ?? '') . "\n" ?></style>
    </head>
    <body class="admin-auth-page" data-admin-theme="<?= $h($forensics['resolved_theme']['canonical_preset'] ?? 'official_emerald') ?>" data-admin-theme-source="<?= $h($forensics['resolved_theme']['resolved_source'] ?? 'default') ?>">
        <main class="admin-content" style="padding:18px">
            <section class="admin-section"><h1>Admin Theme Runtime Debug</h1><?= $list($forensics['runtime']) ?></section>
            <section class="admin-section"><h2>System theme rows</h2><?= $rowTable($forensics['system_rows']) ?></section>
            <section class="admin-section"><h2>Current user personal theme rows</h2><?= $rowTable($forensics['personal_rows']) ?></section>
            <section class="admin-section"><h2>Other users</h2><?= $list(['other_user_theme_row_count' => $forensics['other_user_theme_row_count']]) ?></section>
            <section class="admin-section"><h2>Token override policy</h2><?= $list([
                'token_override_rows_count' => $forensics['token_override_rows_count'] ?? 0,
                'personal_token_override_rows_count' => $forensics['personal_token_override_rows_count'] ?? 0,
                'token_override_rows_ignored' => $forensics['token_override_rows_ignored'] ?? true,
            ]) ?></section>
            <section class="admin-section"><h2>Resolved theme</h2><?= $list($forensics['resolved_theme']) ?></section>
            <section class="admin-section"><h2>Resolved visual tokens</h2><?= $list($forensics['visual_tokens']) ?></section>
            <section class="admin-section"><h2>Injected CSS variables</h2><pre dir="ltr"><?= $h($forensics['css_variables']) ?></pre></section>
            <section class="admin-section"><h2>Loaded admin assets</h2><?= $list($forensics['assets']) ?></section>
        </main>
    </body>
    </html>
    <?php
    $content = ob_get_clean() ?: '';

    return $response
        ->header('Content-Type', 'text/html; charset=UTF-8')
        ->send($content);
});

$router->post('/admin/access', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();

    if ($userId === null) {
        return $response->redirect('/admin/login');
    }

    $assignment = (new \App\Services\AccessService())->switchTo($userId, (int) $request->input('role_assignment_id', 0));

    return $response->redirect('/admin/access?status=' . ($assignment === null ? 'forbidden' : 'switched'));
});

$router->post('/admin/profile/access', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();

    if ($userId === null) {
        return $response->redirect('/admin/login');
    }

    $assignment = (new \App\Services\AccessService())->switchTo($userId, (int) $request->input('role_assignment_id', 0));

    return $response->redirect('/admin/profile/access?status=' . ($assignment === null ? 'forbidden' : 'switched'));
});

$adminPlaceholder = function ($path, $title, $message) use ($adminRender, $adminGuard) {
    return function ($request, $response) use ($adminRender, $adminGuard, $path, $title, $message) {
        $context = $adminGuard($response, $path);

        if (!is_array($context)) {
            return $context;
        }

        return $adminRender($response, 'placeholder', [
            'title' => $title,
            'context' => $context,
            'message' => $message,
        ]);
    };
};

$router->get('/admin/settings', $adminPlaceholder('/admin/settings', 'تنظیمات', 'تنظیمات سامانه در فازهای بعدی تکمیل می‌شود.'));
$router->get('/admin/users', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/users');

    if (!is_array($context)) {
        return $context;
    }

    $list = (new \App\Services\AdminUserService())->index([
        'q' => $request->input('q', ''),
        'page' => $request->input('page', 1),
    ]);

    return $adminRender($response, 'users', [
        'title' => 'کاربران',
        'context' => $context,
        'list' => $list,
    ]);
});

$router->get('/admin/users/{id}', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/users/{id}');

    if (!is_array($context)) {
        return $context;
    }

    $id = filter_var($request->route('id'), FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    if ($id === false) {
        return $adminRender($response, 'user-not-found', [
            'title' => 'کاربر پیدا نشد',
            'context' => $context,
        ], 404);
    }

    $detail = (new \App\Services\AdminUserService())->detail((int) $id);

    if ($detail === null) {
        return $adminRender($response, 'user-not-found', [
            'title' => 'کاربر پیدا نشد',
            'context' => $context,
        ], 404);
    }

    return $adminRender($response, 'user-detail', [
        'title' => 'جزئیات کاربر',
        'context' => $context,
        'detail' => $detail,
    ]);
});
$router->get('/admin/org-units', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/org-units');

    if (!is_array($context)) {
        return $context;
    }

    $list = (new \App\Services\AdminOrgUnitService())->index([
        'q' => $request->input('q', ''),
        'page' => $request->input('page', 1),
    ]);

    return $adminRender($response, 'org-units', [
        'title' => 'واحدهای سازمانی',
        'context' => $context,
        'list' => $list,
    ]);
});
$router->get('/admin/positions', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/positions');

    if (!is_array($context)) {
        return $context;
    }

    $list = (new \App\Services\AdminPositionService())->index([
        'q' => $request->input('q', ''),
        'page' => $request->input('page', 1),
    ]);

    return $adminRender($response, 'positions', [
        'title' => 'سمت‌ها',
        'context' => $context,
        'list' => $list,
    ]);
});
$router->get('/admin/pages', $adminPlaceholder('/admin/pages', 'صفحات داخلی', 'مدیریت صفحات داخلی هنوز فعال نشده است.'));
$router->get('/admin/reports', $adminPlaceholder('/admin/reports', 'گزارش‌ها', 'گزارش‌های مدیریتی در نسخه‌های بعدی اضافه می‌شود.'));
$router->get('/admin/support', $adminPlaceholder('/admin/support', 'پشتیبانی', 'مسیرهای پشتیبانی و راهنمای داخلی در فاز بعدی تکمیل می‌شود.'));

$router->get('/admin/navigation/debug', function ($request, $response) use ($adminGuard) {
    if (!\IPKF\Support\Env::isDebug()) {
        return $response->status(404)->send('404 - Route not found: /admin/navigation/debug');
    }

    $context = $adminGuard($response, '/admin/navigation/debug');

    if (!is_array($context)) {
        return $context;
    }

    $navigation = new \App\Services\AdminNavigationRbacService();

    if (!$navigation->debugRouteAvailable((int) $context['user_id'])) {
        return $response->status(403)->json([
            'status' => 'error',
            'message' => 'Forbidden.',
        ]);
    }

    return $response->json([
        'status' => 'ok',
        'active_role' => $context['active_assignment']['role_code'] ?? null,
        'debug' => $navigation->debug((int) $context['user_id']),
    ]);
});
$router->get('/admin/logout', function ($request, $response) {
    (new \App\Services\AuthService())->logout();

    return $response->redirect('/admin/login');
});

$router->post('/auth/login', function ($request, $response) {
    $login = trim((string) $request->input('login', ''));
    $password = (string) $request->input('password', '');

    if ($login === '' || $password === '') {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Invalid credentials.',
        ]);
    }

    $user = (new \App\Services\AuthService())->attempt($login, $password);

    if ($user === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Invalid credentials.',
        ]);
    }

    if (($user['mfa_required'] ?? false) === true) {
        return $response->json([
            'status' => 'ok',
            'authenticated' => false,
            'mfa_required' => true,
            'methods' => $user['methods'] ?? [],
        ]);
    }

    return $response->json([
        'status' => 'ok',
        'authenticated' => true,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
        ],
    ]);
});

$router->post('/auth/logout', function ($request, $response) {
    (new \App\Services\AuthService())->logout();

    return $response->json([
        'status' => 'ok',
        'authenticated' => false,
    ]);
});

$router->get('/mfa/status', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    $mfa = new \App\Services\MfaService();
    $methods = array_values(array_unique(array_map(
        fn (array $method): string => (string) $method['method'],
        $mfa->methodsForUser($userId)
    )));
    $recoveryCodesAvailable = $mfa->recoveryCodesAvailable($userId);
    $delivery = new \App\Services\MfaDeliveryChannelService();
    $availableMethods = array_values(array_unique(array_merge(
        $methods,
        array_values(array_diff($delivery->configuredMethods(), ['totp', 'recovery']))
    )));

    if ($recoveryCodesAvailable) {
        $availableMethods[] = 'recovery_code';
    }

    return $response->json([
        'status' => 'ok',
        'authenticated' => true,
        'mfa_enabled' => $mfa->enabled() && $methods !== [],
        'mfa_verified' => (bool) \IPKF\Support\Session::get('auth_mfa_verified', false),
        'methods' => array_values(array_unique($availableMethods)),
        'trusted_device' => (new \App\Services\TrustedDeviceService())->hasActiveTrustedDevice($userId),
        'recovery_codes_available' => $recoveryCodesAvailable,
        'enabled' => $mfa->enabled(),
        'enforcement' => $mfa->enforcement(),
    ]);
});

$router->post('/mfa/challenge', function ($request, $response) {
    $method = trim((string) $request->input('method', ''));
    $mfa = new \App\Services\MfaService();
    $userId = $mfa->pendingUserId() ?? (new \App\Services\AuthService())->currentUserId();

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    $result = (new \App\Services\MfaDeliveryChannelService())->createChallenge($userId, $method);

    if (($result['status'] ?? '') !== 'ok') {
        return $response->status(422)->json($result);
    }

    return $response->json(array_filter($result, fn ($value) => $value !== null));
});

$router->post('/mfa/totp/setup', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $user = $auth->currentUser();
    $userId = $auth->currentUserId();

    if ($user === null || $userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    $account = (string) ($user['email'] ?? $user['name'] ?? ('user-' . $userId));
    $setup = (new \App\Services\MfaService())->setupTotp($userId, $account);

    return $response->json([
        'status' => 'ok',
        'method' => 'totp',
        'method_id' => $setup['method_id'],
        'otpauth_uri' => $setup['otpauth_uri'],
    ]);
});

$router->post('/mfa/totp/confirm', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    $code = trim((string) $request->input('code', ''));

    $mfa = new \App\Services\MfaService();

    if (!$mfa->confirmTotp($userId, $code)) {
        return $response->status(422)->json([
            'status' => 'error',
            'confirmed' => false,
            'message' => 'Invalid MFA code.',
        ]);
    }

    return $response->json([
        'status' => 'ok',
        'confirmed' => true,
        'recovery_codes' => $mfa->ensureRecoveryCodes($userId),
    ]);
});

$router->post('/mfa/challenge/verify', function ($request, $response) {
    $method = trim((string) $request->input('method', 'totp'));
    $code = trim((string) $request->input('code', ''));
    $mfa = new \App\Services\MfaService();
    $userId = $mfa->verifyPendingChallenge($method, $code);

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Invalid MFA challenge.',
        ]);
    }

    $user = (new \App\Services\AuthService())->completeMfaLogin($userId);

    return $response->json([
        'status' => 'ok',
        'authenticated' => true,
        'mfa_verified' => true,
        'user' => $user,
    ]);
});

$router->post('/mfa/verify', function ($request, $response) {
    $method = trim((string) $request->input('method', 'totp'));
    $code = trim((string) $request->input('code', ''));
    $mfa = new \App\Services\MfaService();
    $userId = $mfa->verifyPendingChallenge($method, $code);

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Invalid MFA challenge.',
        ]);
    }

    $user = (new \App\Services\AuthService())->completeMfaLogin($userId);

    return $response->json([
        'status' => 'ok',
        'authenticated' => true,
        'mfa_verified' => true,
        'user' => $user,
    ]);
});

$router->post('/mfa/recovery-codes/regenerate', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    $code = trim((string) $request->input('code', ''));
    $codes = (new \App\Services\MfaService())->regenerateRecoveryCodes($userId, $code);

    if ($codes === null) {
        return $response->status(403)->json([
            'status' => 'error',
            'message' => 'Valid MFA code is required.',
        ]);
    }

    return $response->json([
        'status' => 'ok',
        'recovery_codes' => $codes,
    ]);
});

$router->post('/mfa/recovery/verify', function ($request, $response) {
    $code = trim((string) $request->input('recovery_code', ''));
    $mfa = new \App\Services\MfaService();
    $userId = $mfa->verifyPendingChallenge('recovery_code', $code);

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Invalid recovery code.',
        ]);
    }

    $user = (new \App\Services\AuthService())->completeMfaLogin($userId);

    return $response->json([
        'status' => 'ok',
        'authenticated' => true,
        'mfa_verified' => true,
        'recovery_code_consumed' => true,
        'trusted_device' => false,
        'user' => $user,
    ]);
});

$router->get('/mfa/trusted-devices', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    return $response->json([
        'status' => 'ok',
        'trusted_devices' => (new \App\Services\TrustedDeviceService())->listForUser($userId),
    ]);
});

$router->post('/mfa/trusted-devices/revoke', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    $deviceId = (int) $request->input('device_id', 0);

    return $response->json([
        'status' => 'ok',
        'revoked' => $deviceId > 0
            && (new \App\Services\TrustedDeviceService())->revoke($userId, $deviceId),
    ]);
});

$router->get('/auth/status', function ($request, $response) {
    return $response->json([
        'authenticated' => (new \App\Services\AuthService())->authenticated(),
    ]);
});

$router->post('/auth/login-token/issue', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $issuerId = $auth->currentUserId();
    $internalKey = (string) \IPKF\Support\Env::get('INTERNAL_SERVICE_KEY', '');
    $providedInternalKey = (string) ($_SERVER['HTTP_X_INTERNAL_SERVICE_KEY'] ?? '');
    $internalAllowed = $internalKey !== '' && hash_equals($internalKey, $providedInternalKey);

    if ($issuerId === null && !$internalAllowed) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    if (!$internalAllowed && !(new \App\Services\AuthorizationService())->hasPermission((int) $issuerId, 'auth.login_token.issue')) {
        return $response->status(403)->json([
            'status' => 'error',
            'message' => 'Forbidden.',
        ]);
    }

    $targetUserId = (int) $request->input('user_id', 0);

    if ($targetUserId <= 0) {
        return $response->status(422)->json([
            'status' => 'error',
            'message' => 'Invalid request.',
        ]);
    }

    $issued = (new \App\Services\LoginTokenService())->issue(
        $targetUserId,
        trim((string) $request->input('purpose', 'bot_login')),
        trim((string) $request->input('source', 'internal')),
        $request->input('redirect_path', null),
        $issuerId
    );

    return $response->json([
        'status' => 'ok',
        'login_url' => $issued['login_url'],
        'expires_at' => $issued['expires_at'],
        'expires_at_utc' => $issued['expires_at_utc'],
        'expires_at_local' => $issued['expires_at_local'],
        'timezone' => $issued['timezone'],
        'ttl_seconds' => $issued['ttl_seconds'],
    ]);
});

$router->post('/auth/token-login', function ($request, $response) {
    $token = trim((string) $request->input('token', ''));
    $record = (new \App\Services\LoginTokenService())->consume($token);

    if ($record === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Invalid credentials.',
        ]);
    }

    $mfa = new \App\Services\MfaService();
    $userId = (int) $record['user_id'];

    if ($mfa->requiresChallenge($userId)) {
        return $response->json([
            'status' => 'ok',
            'authenticated' => false,
            'mfa_required' => true,
            'methods' => $mfa->startPending($userId),
        ]);
    }

    $auth = new \App\Services\AuthService();
    $auth->login($userId);
    $user = $auth->currentUser();

    return $response->json([
        'status' => 'ok',
        'authenticated' => true,
        'user' => $user,
    ]);
});

$router->get('/auth/token-login', function ($request, $response) {
    $token = trim((string) $request->input('token', ''));
    $record = (new \App\Services\LoginTokenService())->consume($token);

    if ($record === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Invalid credentials.',
        ]);
    }

    $mfa = new \App\Services\MfaService();
    $userId = (int) $record['user_id'];

    if ($mfa->requiresChallenge($userId)) {
        return $response->json([
            'status' => 'ok',
            'authenticated' => false,
            'mfa_required' => true,
            'methods' => $mfa->startPending($userId),
        ]);
    }

    $auth = new \App\Services\AuthService();
    $auth->login($userId);
    $user = $auth->currentUser();

    return $response->json([
        'status' => 'ok',
        'authenticated' => true,
        'user' => $user,
    ]);
});

$router->get('/access/assignments', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    $access = new \App\Services\AccessService();
    $active = $access->activeAssignment($userId);

    return $response->json([
        'status' => 'ok',
        'active_role_assignment_id' => $active === null ? null : (int) $active['id'],
        'assignments' => array_map(fn (array $assignment): array => [
            'id' => (int) $assignment['id'],
            'role_code' => $assignment['role_code'],
            'role_title' => $assignment['role_title'],
            'priority' => (int) $assignment['priority'],
            'scope' => [
                'type' => $assignment['scope_type'],
                'id' => $assignment['scope_id'] === null ? null : (int) $assignment['scope_id'],
            ],
            'is_active' => $active !== null && (int) $active['id'] === (int) $assignment['id'],
        ], $access->assignments($userId)),
    ]);
});

$router->post('/access/switch', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    $assignment = (new \App\Services\AccessService())->switchTo($userId, (int) $request->input('role_assignment_id', 0));

    if ($assignment === null) {
        return $response->status(403)->json([
            'status' => 'error',
            'message' => 'Forbidden.',
        ]);
    }

    return $response->json([
        'status' => 'ok',
        'active_role_assignment' => [
            'id' => (int) $assignment['id'],
            'role_code' => $assignment['role_code'],
            'role_title' => $assignment['role_title'],
            'priority' => (int) $assignment['priority'],
        ],
    ]);
});

$router->post('/identity/change/request', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    $mfa = new \App\Services\MfaService();

    if ($mfa->methodsForUser($userId) !== [] && !\IPKF\Support\Session::get('auth_mfa_verified', false)) {
        return $response->status(403)->json([
            'status' => 'error',
            'message' => 'Recent MFA verification is required.',
        ]);
    }

    $result = (new \App\Services\IdentityChangeService())->request(
        $userId,
        trim((string) $request->input('field', '')),
        trim((string) $request->input('value', '')),
        (string) $request->input('password', '')
    );

    return (($result['status'] ?? '') === 'ok')
        ? $response->json(array_filter($result, fn ($value) => $value !== null))
        : $response->status(422)->json($result);
});

$router->post('/identity/change/confirm', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    $result = (new \App\Services\IdentityChangeService())->confirm(
        $userId,
        (int) $request->input('request_id', 0),
        trim((string) $request->input('token', ''))
    );

    return (($result['status'] ?? '') === 'ok')
        ? $response->json($result)
        : $response->status(422)->json($result);
});

$router->get('/me', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $user = $auth->currentUser();
    $userId = $auth->currentUserId();

    if ($user === null || $userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    $authorization = new \App\Services\AuthorizationService();
    $access = new \App\Services\AccessService();
    $active = $access->activeAssignment($userId);

    return $response->json([
        'status' => 'ok',
        'authenticated' => true,
        'user' => $user,
        'roles' => array_map(
            fn (array $role): array => [
                'id' => (int) $role['id'],
                'code' => $role['code'],
                'title' => $role['title'],
            ],
            $authorization->rolesForUser($userId)
        ),
        'assignments' => $access->assignments($userId),
        'active_role_assignment' => $active,
        'active_role_code' => $active['role_code'] ?? null,
        'active_role_title' => $active['role_title'] ?? null,
    ]);
});

$router->get('/admin-check', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    $permission = 'system.diagnostics.view';

    if (!(new \App\Services\AuthorizationService())->hasPermission($userId, $permission)) {
        return $response->status(403)->json([
            'status' => 'error',
            'message' => 'Forbidden.',
        ]);
    }

    return $response->json([
        'status' => 'ok',
        'permission' => $permission,
    ]);
});
