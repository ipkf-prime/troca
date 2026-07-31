# Admin User Complete Profile

This slice completes the current create/edit form against the existing IPKF identity schema.

- Saves identity fields to `persons` and `person_profiles`
- Synchronizes account email/mobile into `person_contacts`
- Saves the primary address into `person_addresses`
- Updates `persons.province_id`, `persons.county_id`, and `persons.city_id`
- Keeps existing global role synchronization and protected-role safeguards
- Adds account, contact/address, and access tabs with responsive alignment
- Adds a safe detail fallback so account email/mobile are shown even before a contact row exists

No migration or seed is required because the current identity tables are reused.
