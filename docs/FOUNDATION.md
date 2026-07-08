# IPKF v0.2.0 Foundation

## Goal

The v0.2.0 Foundation milestone stabilizes framework infrastructure before Auth, RBAC, Bot Engine, CRM, Automation, ERP, and Marketplace work begins.

No business features are included in this milestone.

## Foundation Audit

| Area | Status | Notes |
| --- | --- | --- |
| Env loader | OK | Loads `.env` when present, skips comments and malformed lines, supports safe defaults, and exposes loaded state. |
| Config loader | OK | Loads `config/*.php`, supports dot access through `config()`, exposes loaded state, and keeps safe defaults. |
| Application container | OK | Container is initialized by `IPKF\Core\Application` and stores core framework instances. |
| Database layer | Needs cleanup | PDO connection is centralized and safe, but remains minimal and MySQL-focused. |
| QueryBuilder | Needs cleanup | Has a connection wrapper only; full query API is intentionally deferred. |
| Migration runner | OK | Provides a generic runner and no longer hardcodes business migrations. |
| Seeder runner | OK | Provides a generic runner and no longer hardcodes business seeders. |
| Base Model | OK | Generic base model skeleton is available. |
| Service layer convention | OK | `BaseService` and `ServiceInterface` define the application service convention. |
| Repository layer convention | OK | `BaseRepository` and `RepositoryInterface` define the application repository convention. |
| Installer preparation | Missing | Installer flow is not implemented in this milestone; it remains a future milestone. |

## Env And Config

- `.env` is optional and must not be committed.
- `.env.example` contains safe sample values only.
- `APP_ENV` and `APP_DEBUG` are read through `IPKF\Support\Env`.
- Config files are loaded from `config/*.php`.
- The `config()` helper reads values through `IPKF\Support\Config`.

## Database Foundation

- Database configuration is read from `config/database.php`.
- MySQL/MariaDB is the supported foundation driver.
- PDO connections are created through `IPKF\Database\Database`.
- Missing or failed database connections throw safe framework-level errors.
- Diagnostics report database availability without exposing secrets.

## Migration And Seeder Convention

- Migrations extend `IPKF\Database\Migrations\Migration`.
- Migration execution is coordinated by `IPKF\Database\Migrations\MigrationRunner`.
- Seeders extend `IPKF\Database\Seeds\Seeder`.
- Seeder execution is coordinated by `IPKF\Database\Seeds\SeederRunner`.
- No business tables or permanent framework test tables are added in this milestone.

## Service, Repository, And Model Layers

- Controllers coordinate HTTP request and response behavior.
- Services contain business logic.
- Repositories handle data access.
- Models represent database records or entities.
- Framework core stays under `system/`; application conventions stay under `app/`.
