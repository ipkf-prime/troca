# Organizational Minimal UX and Persian Datepicker

This increment refines the existing organizational setup and correspondence UI without changing the domain model or removing operational capabilities.

## Setup workflow

The existing five steps remain unchanged:

1. Organization
2. Organization unit
3. Organizational position
4. User-to-Person link
5. Summary and next actions

Each operational step now renders its saved records immediately below the form. Public UI does not expose raw database IDs.

## Date policy

- Admin users see and select Jalali dates only.
- Native browser Gregorian date controls are not used.
- Automation forms submit a normalized hidden Gregorian value for storage.
- Server-side services accept the Jalali companion value as a safe fallback.
- Appointment services continue converting Jalali input to standard database dates.
- A one-time idempotent migration repairs legacy appointment dates that were stored as raw Jalali year/month/day values in Gregorian `DATE` columns.
- Gregorian implementation details are not shown in the interface.

## Semantic colors

- Success and completed registration: green
- Warning, pending, draft, or review-required states: amber
- Error, cancellation, revocation, and destructive actions: red
- Informational and registered workflow states: blue
- Secondary actions: neutral or soft brand color

## Responsive policy

- Forms use two columns on desktop and one column on small screens.
- Saved-record tables become labeled cards on mobile.
- Correspondence party fields are grouped in collapsible cards.
- Fields are hidden contextually by party type without removing submitted array positions.
- Organization-chart actions use compact secondary controls.
- Saved-record tables are visually separated from data-entry forms with their own bordered surface, heading, count badge, and responsive cards.
- Correspondence draft creation uses four responsive tabs for base data, content, parties, and final review; validation automatically opens the tab containing an invalid field.
- Search bars, filter controls, numeric fields, and form grids use proportional desktop widths and collapse to full-width controls on mobile.
