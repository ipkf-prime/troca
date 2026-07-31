# Address Dynamic Geography Fix

## Root cause

The user address forms queried legacy tables:

- `provinces`
- `counties`
- `cities`

The current IPKF geography source is the canonical dynamic model:

- `geographic_level_types`
- `geographic_locations`
- `geographic_location_relations`

The legacy tables may exist but contain no active data, so the form displayed
only “انتخاب نشده”. The `address_types` table also had no base records.

## Fix

- Load provinces, counties and cities from active dynamic geography.
- Resolve city → district → county → province through primary relations.
- Store the most specific selected location in
  `person_addresses.geographic_location_id`.
- Keep compatibility with old address rows.
- Seed four address types:
  - منزل
  - محل کار
  - نشانی مکاتبات
  - سایر
- Deactivate the primary address when the user clears every address field.
- Validate that province, county and city belong to the same hierarchy.

## Deployment

The patch requires the Core migration entry point after deployment.

The diagnostic command prints the active reference-data counts:

```bash
php scripts/check-address-reference-data.php
```
