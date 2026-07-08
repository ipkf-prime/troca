# IPKF Architecture

## Purpose

IPKF is a PHP framework foundation for building layered IPKF applications. The `0.2.0-foundation` milestone establishes the request lifecycle, routing, middleware pipeline, response handling, environment loading, configuration loading, database foundation, migration and seeder conventions, and operational diagnostics needed before business modules are added.

## Directory Structure

Repository-level files:

- `AGENTS.md`: permanent Codex instructions for this project.
- `docs/`: project-level documentation.
- `.cpanel.yml`: cPanel Git deployment tasks.
- `public_html/`: deployable application root.

Application directories under `public_html/`:

- `app/`: application code.
- `bootstrap/`: application bootstrap.
- `config/`: configuration files.
- `database/`: database utilities, migrations, and seeds.
- `public/`: web document root and front controller.
- `resources/`: views and presentation resources.
- `routes/`: route definitions.
- `storage/`: runtime storage for cache, logs, sessions, and uploads.
- `system/`: IPKF framework core.
- `tests/`: test assets.
- `vendor/`: autoloaders and vendor-level loaders.

## Namespace Rules

Composer-style namespace mapping:

- `IPKF\` maps to `system/`.
- `App\` maps to `app/`.

Framework classes belong under the `IPKF` namespace. Application classes belong under the `App` namespace.

## Request Lifecycle

1. The web server sends requests to `public/index.php`.
2. `public/index.php` defines `BASE_PATH` and loads `bootstrap/app.php`.
3. The bootstrap loads `vendor/autoload.php`, environment values, error handling, and configuration.
4. `IPKF\Core\Application` creates the container, router, and HTTP kernel.
5. `IPKF\Routing\RouteLoader` loads route definitions from `routes/web.php`.
6. `Application::run()` captures the current request and creates a response.
7. `IPKF\Http\Kernel` passes the request through the middleware pipeline.
8. `IPKF\Routing\Router` dispatches to the matched route action.
9. `IPKF\Http\Response` emits status, headers, and content.

## Layered Architecture

- Controllers coordinate HTTP input and output.
- Services contain business logic.
- Repositories handle data access.
- Models represent application data.
- Middleware handles cross-cutting request concerns.
- Framework core services stay in `system/`.

Controllers must not contain business logic. Business rules should move into services, and persistence logic should move into repositories.

## Env And Config Design

Environment values are loaded by `IPKF\Support\Env` from the application `.env` file when present. The `.env` file is optional, ignored by Git, and must not contain committed secrets. Safe defaults live in config files and `.env.example`.

Configuration files live in `public_html/config/` and are loaded by `IPKF\Support\Config`. Application code should read configuration through `config()` or the `Config` class instead of reading config files directly.

## Database Foundation Design

Database settings are read from `config/database.php`, backed by environment values such as `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, and `DB_PORT`. `IPKF\Database\Database` owns PDO connection creation and reports safe framework-level errors when configuration is incomplete or the connection fails.

The foundation supports MySQL/MariaDB. Full ORM behavior is intentionally deferred.

## Migration And Seeder Convention

Migrations extend `IPKF\Database\Migrations\Migration` and are run by `IPKF\Database\Migrations\MigrationRunner`. Seeders extend `IPKF\Database\Seeds\Seeder` and are run by `IPKF\Database\Seeds\SeederRunner`.

Foundation code must not add business tables. Permanent business migrations belong to later feature milestones.

## Service, Repository, And Model Layering

Application services implement service-level business workflows. Application repositories handle data access and may use framework database connections. Models represent records or entities and should stay free of controller-specific behavior.

## Core vs Application Boundary

The framework core lives under `public_html/system/` and should remain reusable. Application-specific behavior lives under `public_html/app/`, `public_html/routes/`, and `public_html/resources/`.

Do not recreate legacy `app/Core`. Do not duplicate core classes under application directories. Do not add Bot, CRM, ERP, Automation, or Marketplace code unless explicitly requested.
