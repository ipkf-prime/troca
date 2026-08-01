# Communication Center Stage 2 R1 Repair

This repair addresses a partial Stage 2 deployment where new files were
committed but integration changes were not applied to existing registries and
the shared layout.

It repairs:

- `ApplicationMigrationRegistry`
- `public/migrate.php`
- `ApplicationSeederRegistry`
- `public/seed.php`
- `RouteLoader`
- `AuthService`
- dynamic sidebar and topbar navigation
- dynamic notification channel lookup
- administrator user update-route helper capture

The communication diagnostic now reports `migration_required` instead of
throwing when the new schema has not been migrated.

The Work database and service are not modified. A separate CLI diagnostic is
included to compare Core and Work deployment environments.
