<?php

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/bootstrap/app.php';

if (!\IPKF\Support\Env::isDebug()) {
    \IPKF\Support\Maintenance::deny('/seed.php');
}

if (!\IPKF\Support\Maintenance::keyIsValid($_GET['key'] ?? null)) {
    \IPKF\Support\Maintenance::deny('/seed.php');
}

$application = trim((string) ($_GET['application'] ?? ''));

if ($application !== '') {
    $allowedApplications = ['core', 'automation'];

    if (!in_array($application, $allowedApplications, true)) {
        \IPKF\Support\Maintenance::deny('/seed.php');
    }

    try {
        $registry = new \IPKF\Database\Connections\ConnectionRegistry();
        $resolver = new \IPKF\Database\Connections\ConnectionResolver($registry);
        $health = new \IPKF\Database\Connections\ConnectionHealthChecker($resolver);
        $seederRegistry = new \IPKF\Database\Application\ApplicationSeederRegistry($resolver);
        $groups = $seederRegistry->groups();
        $group = $groups[$application] ?? null;

        if (!is_array($group)) {
            throw new \RuntimeException('Application seeder group is not available.');
        }

        $connectionName = (string) ($group['connection'] ?? '');
        $definition = $registry->get($connectionName);
        $runtimeMode = new \App\Services\Automation\AutomationRuntimeMode($registry);

        if ($application === 'automation') {
            if (!$runtimeMode->provisioningAllowed()
                || $definition === null
                || $definition->usesFallback()
                || !$definition->configured()
            ) {
                throw new \RuntimeException('Dedicated automation connection is required.');
            }
        }

        if ($definition === null
            || !$health->available($connectionName)
            || !$health->utf8mb4Ready($connectionName)
            || !$health->utcTimezoneApplied($connectionName)
        ) {
            throw new \RuntimeException('Application connection is not ready.');
        }

        if ($application === 'core') {
            header('Content-Type: text/plain; charset=UTF-8');
            echo "APPLICATION SEED DONE\n";
            echo "application=core\n";
            echo "executed_count=0";
            exit;
        }

        $pdo = $resolver->resolve($connectionName);
        $tableExists = static function (string $table) use ($pdo): bool {
            $statement = $pdo->prepare("
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = ?
            ");
            $statement->execute([$table]);

            return (int) $statement->fetchColumn() > 0;
        };

        if ($application === 'automation'
            && (!$tableExists('lookup_domains')
                || !$tableExists('lookup_values')
                || !$tableExists('correspondences'))
        ) {
            throw new \RuntimeException('Standalone automation schema is not ready.');
        }

        $executed = (new \IPKF\Database\Application\ApplicationSeederRunner())
            ->run($seederRegistry->seedersFor($application));

        header('Content-Type: text/plain; charset=UTF-8');
        echo "APPLICATION SEED DONE\n";
        echo "application={$application}\n";
        echo "executed_count={$executed}";
    } catch (Throwable $exception) {
        $failureReference = (new \IPKF\Database\Migrations\MigrationFailureLogger())
            ->log("application_{$application}_seed", $exception);

        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "APPLICATION SEED FAILED\n";
        echo "failure_reference={$failureReference}";
    }

    exit;
}

try {
    $manager = new \IPKF\Database\DatabaseManager();
    $manager->seeders([
        new \IPKF\Database\Seeds\RuntimeCheckSeeder(),
        new \IPKF\Database\Seeds\AuthRbacSeeder(),
        new \IPKF\Database\Seeds\MultiSourceMetadataSeeder(),
        new \IPKF\Database\Seeds\AutomationCorrespondenceSeeder(),
        new \IPKF\Database\Seeds\AutomationCorrespondencePermissionsSeeder(),
        new \IPKF\Database\Seeds\CorrespondenceDocumentTemplateSeeder(),
        new \IPKF\Database\Seeds\OrganizationalIdentityPermissionsSeeder(),
        new \IPKF\Database\Seeds\PlatformCommercialFoundationSeeder(),
    ]);

    $manager->seed();

    header('Content-Type: text/plain; charset=UTF-8');
    echo "SEED DONE: foundation_v0_2, auth_rbac_schema, identity_access_foundation, admin_panel_shell, admin_users_organization, multi_source_metadata, ministry_geography_import_metadata, statistical_center_geography_import_metadata, ministry_canonical_geography_metadata, automation_correspondence_metadata, correspondence_document_templates, platform_commercial_metadata";
} catch (Throwable $exception) {
    http_response_code(500);
    echo "SEED FAILED";
}
