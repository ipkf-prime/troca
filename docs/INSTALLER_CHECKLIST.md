# IPKF v0.3.0 Installer Checklist

Version: `0.3.0-installer`

## Verified Checks

- `/install.php` works in the development environment.
- Installer returns JSON only.
- Installer does not expose secrets.
- Installer checks PHP version.
- Installer checks PDO availability.
- Installer checks `pdo_mysql` availability.
- Installer checks storage writable status.
- Installer checks `.env`, config, and database config availability.
- Installer checks migration and seeder system availability.

## Security Checks

- `APP_DEBUG=false` and `IPKF_INSTALLED=true` must block the installer.
- Installer must not overwrite `.env`.
- Installer must not create admin users yet.
- Installer must not expose the database password.
- Installer must not expose `DEV_MAINTENANCE_KEY`.

## Release Boundaries

- Auth is not part of this release.
- RBAC is not part of this release.
- Bot, CRM, ERP, Automation, and Marketplace modules are not part of this release.
- User and admin tables are not created in this release.
