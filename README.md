# IPKF Framework

IPKF Framework is a lightweight PHP framework foundation for the IPKF product line.
The current baseline is focused on the Foundation runtime and does not include
business modules.

## Version

Current version: `0.3.0-installer`

## Requirements

- PHP 8.4 tested on the development hosting environment.
- The web server document root must point to the application's `/public` directory.
- For this repository layout, deploy `public_html/` as the application root and point the domain document root to `public_html/public`.

## Installation Notes

1. Copy `.env.example` to the application `.env` file.
2. Set environment values for the target machine.
3. Ensure `storage/` is writable by the web server.
4. Configure the web server document root to the `public/` directory.
5. Deploy from the active feature branch for development work. The current installer release branch is `installer-v0.3`.

On cPanel Git deployment, this repository deploys `public_html/` to the configured application path. The active dev domain uses:

- Repository path: `/home/troca/repositories/troca`
- Application path: `/home/troca/dev.troca.ir`
- Document root: `/home/troca/dev.troca.ir/public`

## Development Route Tests

The Foundation baseline has been verified with:

- `GET /` returns `IPKF Framework Genesis OK`
- `GET /unknown` returns a clean 404 response
- `GET /health` returns JSON health status
- `GET /_diagnostics` returns JSON diagnostics when `APP_DEBUG=true`
- `GET /migrate.php?key=DEV_MAINTENANCE_KEY` runs dev migrations when `APP_DEBUG=true`
- `GET /seed.php?key=DEV_MAINTENANCE_KEY` runs dev seeders when `APP_DEBUG=true`
- `GET /install.php` returns safe installer JSON when installer access rules allow it

## Site Mode

Set `SITE_MODE=coming_soon` to show the public Persian Coming Soon page at `GET /`.
Set `SITE_MODE=app` to keep the framework/app home behavior for future application routing.

## Architecture Guardrails

- Framework core code lives under `system/`.
- Application code lives under `app/`.
- `IPKF\` maps to `system/`.
- `App\` maps to `app/`.
- Controllers should coordinate requests and responses.
- Services contain business logic.
- Repositories handle data access.
- Do not add business features unless explicitly requested.
