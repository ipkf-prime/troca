# IPKF Installer

## Purpose

The IPKF installer skeleton provides safe read-only runtime checks before future installation phases are implemented. It reports environment, storage, database, migration, and seeder readiness without writing files, overwriting `.env`, creating users, or creating business data.

## Status

Current version: `0.3.0-installer`

This is a skeleton only. It performs read-only environment checks and does not perform installation writes yet.

## Access Rules

The installer entry point is:

- `/install.php`

It is accessible only when either condition is true:

- `APP_DEBUG=true`
- `IPKF_INSTALLED=false`

If `APP_DEBUG=false` and `IPKF_INSTALLED=true`, the installer returns a 404-style response.

## Environment Behavior

Development:

- `APP_DEBUG=true`
- `IPKF_INSTALLED=false`

Production:

- `APP_DEBUG=false`
- `IPKF_INSTALLED=true`

The installer must not expose secrets, database passwords, maintenance keys, tokens, or production-only values. It does not write `.env` and does not create users or admins in this release.

## Current Checks

The installer JSON reports:

- PHP version
- Required PHP version support
- PDO extension availability
- `pdo_mysql` extension availability
- Storage writable status
- `.env` loaded status
- Config loaded status
- Database config loaded status
- Database connection availability
- Migration system availability
- Seeder system availability

## Future Installer Phases

Future phases may include:

- Web UI
- Environment setup
- Database setup
- Admin creation
- Installation lock

Auth, RBAC, Bot, CRM, ERP, Automation, and Marketplace setup are not part of this skeleton.
