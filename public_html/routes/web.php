<?php

/** @var \IPKF\Routing\Router $router */

$router->get('/', function ($request, $response) {
    if (\IPKF\Support\Env::get('SITE_MODE', 'coming_soon') === 'coming_soon') {
        $view = BASE_PATH . '/resources/views/site/coming-soon.php';

        if (is_readable($view)) {
            ob_start();
            require $view;
            $content = ob_get_clean() ?: '';

            return $response
                ->header('Content-Type', 'text/html; charset=UTF-8')
                ->send($content);
        }
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
        } catch (\Throwable $exception) {
            $adminUserExists = false;
            $superAdminRoleExists = false;
            $superAdminAssignmentExists = false;
        }
    }

    return $response->json([
        'php_version' => PHP_VERSION,
        'base_path' => BASE_PATH,
        'app_env' => \IPKF\Support\Env::get('APP_ENV', 'production'),
        'app_debug' => $debug,
        'site_mode' => \IPKF\Support\Env::get('SITE_MODE', 'coming_soon'),
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

    return $response->json([
        'status' => 'ok',
        'authenticated' => true,
        'mfa_enabled' => $mfa->enabled() && $methods !== [],
        'mfa_verified' => (bool) \IPKF\Support\Session::get('auth_mfa_verified', false),
        'methods' => $methods,
        'trusted_device' => (new \App\Services\TrustedDeviceService())->hasActiveTrustedDevice($userId),
        'recovery_codes_available' => $recoveryCodesAvailable,
        'enabled' => $mfa->enabled(),
        'enforcement' => $mfa->enforcement(),
    ]);
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
