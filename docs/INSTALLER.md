# IPKF Installer

## Purpose

The IPKF installer skeleton provides safe read-only runtime checks before future installation phases are implemented. It reports environment, storage, database, migration, and seeder readiness without writing files, overwriting `.env`, creating users, or creating business data.

## Status

Current version: `0.3.0-installer-dev`

This is a development skeleton only.

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

The installer must not expose secrets, database passwords, maintenance keys, tokens, or production-only values.

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

- Environment file validation
- Database connection setup
- Migration execution flow
- Seeder execution flow
- Installation lock/state persistence
- Admin setup, only when explicitly requested in a later milestone

Auth, RBAC, Bot, CRM, ERP, Automation, and Marketplace setup are not part of this skeleton.
