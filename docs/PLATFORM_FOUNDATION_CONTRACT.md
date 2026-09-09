# IPKF Platform Foundation Contract

Status: Binding platform contract from v0.8.0.

## 1. Locale and direction

User-facing application UI is Persian:

- locale: `fa-IR`
- direction: `rtl`
- technical identifiers may remain ASCII/LTR where appropriate.

Backend/domain/database values are not Persian-localized.

## 2. Numeric presentation

User-visible ordinary numbers use Persian digits.

Inputs that represent numeric/date values must accept Persian, Arabic and
ASCII digits. Values are normalized to ASCII before entering application/API
processing.

Technical identifiers, hashes, GUIDs, URLs, filenames, API keys and machine
codes must not be transformed merely for presentation consistency.

Canonical platform facade:

`App\Support\PlatformPresentation`

## 3. Date and time

Storage/domain/API:

- date-only: Gregorian ISO `YYYY-MM-DD`
- instant/timestamp: UTC / platform Clock conventions.

Presentation:

- Jalali/Persian calendar
- Persian digits
- display timezone through existing platform Clock rules.

Raw HTML `input[type=date]` is prohibited in Persian application pages.

## 4. Theme and design tokens

Existing `AdminThemeService` remains the theme authority.

The platform layer exposes canonical `--ui-*` aliases. Existing
`--admin-*` tokens remain compatibility inputs during migration.

Pages must not create independent theme palettes.

## 5. Controls

New/migrated interfaces use shared Foundation contracts for:

- input
- select
- textarea
- button
- checkbox
- switch
- form grid
- card
- alert
- badge
- table wrapper
- pagination
- dialog

Control height, radius, spacing, typography and focus behavior come from
Foundation tokens.

## 6. Select contract

Native selects inherit the platform font and normalized control metrics.

Complex searchable selections must use an internal accessible combobox
implementation; no runtime CDN dependency is permitted.

## 7. Responsive contract

Responsive behavior is defined in shared Foundation assets.

Individual pages must not invent independent breakpoint systems.

Large tables must use controlled overflow or responsive alternatives.

## 8. Accessibility

Foundation components include:

- keyboard usability
- `:focus-visible`
- ARIA hooks where applicable
- disabled/read-only states
- sufficient contrast
- reduced-motion support.

Color must not be the sole status signal.

## 9. Runtime dependencies

Runtime UI must not depend on:

- Google Fonts
- CDNJS
- jsDelivr
- unpkg
- Bootstrap CDN
- Font Awesome CDN
- other external runtime UI CDNs.

Fonts/icons/assets are deployment-local.

## 10. Help and system content

Fundamental UI labels remain application resources.

Editable guidance, notices, announcements and help content use managed,
keyed content rather than scattered page hardcoding.

## 11. Logging and audit

Platform logging is divided conceptually into:

- operational/application log
- security log
- durable audit trail.

Minimum abstractions are:

- `SystemLogger`
- `AuditLogger`

Audit history must not exist only in a rotating operational log.

Raw secrets must never be logged.

F1B2 implements the first shared logging/audit runtime contract.

## 12. Guard policy

Automated Foundation guards prohibit introduction of:

- external runtime CDN assets
- raw `input[type=date]`
- runtime debug dump calls
- duplicate shared Foundation assets.

Existing migration debt is reduced incrementally rather than mass-replaced.

## 13. Dynamic inheritance

Platform Foundation is mandatory-by-default.

Every future user-facing page and every future active module inherits the
current Platform Foundation contract automatically.

There is no per-page or per-module opt-out for:

- locale and RTL direction
- theme/design-token inheritance
- shared UI control rules
- date/number presentation rules
- accessibility/responsive baseline
- runtime dependency restrictions
- request/correlation logging
- production error-safety rules

Dynamic modules use the shared Admin module shell rather than copying a
layout or Foundation assets into the module itself.

A newly added page/module must pass the platform guards before it is
considered Foundation-compliant.

## 14. Legacy-debt baseline

Historical UI debt is grandfathered only as an explicit migration baseline.

The automated debt guard follows a ratchet rule:

- existing debt may decrease
- existing debt must not increase
- a new file starts with zero allowed legacy debt

This prevents new pages/modules from reintroducing page-local styling,
raw legacy controls, inline event handlers or independent visual palettes
while allowing controlled migration of the existing application.

A visual HTML control is Foundation-compliant when it uses the canonical
shared class for that control, such as `ui-input`, `ui-select`,
`ui-textarea`, `ui-button` or `ui-checkbox`.

Hidden inputs are non-visual and are not counted as UI-control debt.

Existing literal HTML documents that still use a pre-Foundation locale
declaration are recorded as explicit baseline debt. New files have zero
allowance and therefore must declare `fa-IR` and `rtl`.

The committed debt baseline is an architecture artifact. Increasing it
requires explicit Platform Foundation review. Feature development must not
regenerate the baseline merely to make a guard pass.

## 15. Governance mutations

Platform-governance mutations must use the shared Platform Audit boundary.

A domain module keeps its domain audit/event stream.

A future module does not create a private platform-governance audit store.
