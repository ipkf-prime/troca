# IPKF/Troca Public Site

Current branch: `v0.4.4-admin-panel-shell-dev`

## Homepage

`GET /` renders a static RTL Persian public landing page for IPKF/Troca.

The page includes:

- public header with brand, logo, navigation, and admin login CTA
- hero section
- capability cards
- automation roadmap preview
- development roadmap
- final admin login CTA
- footer

The landing page is static. It does not add CRM, Bot, ERP, Automation, Marketplace, page builder, or public content-management features.

## SITE_MODE

When `SITE_MODE=coming_soon`, the same polished landing page is shown with the visible badge:

`نسخه آزمایشی / در حال آماده‌سازی`

This keeps the homepage customer-presentable while the platform is still in development.

`SITE_MODE=app` also uses the professional landing page in this milestone so `/admin/login` can safely link back to `/`.

## Branding

The public page reads admin theme branding when available:

- `brand_name`
- `logo_url`

Fallbacks:

- brand: `سامانه هوشمند تروکا`
- logo: `/assets/admin/images/logos/default-logo.svg`

No CDN, remote images, or external font imports are used.

## Typography

The landing page uses a safe Persian-readable system stack:

`Tahoma, "Segoe UI", Arial, sans-serif`

`Vazirmatn` is not forced unless a real local font file is added and loaded correctly in a future milestone. No commercial font files and no CDN font imports are committed in this milestone.

## Admin Login Link

The public page links to:

`/admin/login`

The admin login page's “back to homepage” link points to:

`/`

## Assets

Public landing CSS lives at:

`public/assets/css/landing.css`

The view appends a local filemtime cache-busting query string.

## Limitations

- Automation is preview text only.
- No automation routes, tables, or business workflows are created.
- No public page builder is included.
- No dynamic marketing CMS is included.

## Diagnostics

Development diagnostics may expose only safe booleans:

- `public_landing_available`
- `coming_soon_landing_available`
