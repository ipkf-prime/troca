# IPKF v0.2.0 Foundation Release Checklist

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
