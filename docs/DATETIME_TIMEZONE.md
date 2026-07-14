# DateTime and Timezone Contract

IPKF stores instant timestamps in UTC and converts them exactly once for display.

## Contract

- Instant timestamps are persisted in UTC.
- MySQL/PDO sessions are explicitly configured with `time_zone = '+00:00'`.
- PHP runtime default timezone is UTC for deterministic persistence and logs.
- `APP_TIMEZONE` controls display only. The current dev value is `Asia/Tehran`.
- Jalali conversion happens after the stored UTC instant is converted once to `APP_TIMEZONE`.
- Date-only fields remain timezone-neutral and must not receive timezone conversion.
- Hardcoded offsets such as `+03:30` are prohibited.

## Affected Instant Fields

These fields are treated as UTC instants:

- `users.created_at`
- `users.updated_at`
- `users.last_login_at`
- login/session timestamps
- MFA/security timestamps
- token `expires_at`, `used_at`, `revoked_at`, and similar audit fields
- role/access assignment validity timestamps where stored as timestamp/datetime
- organizational appointment timestamps where stored as timestamp/datetime
- source snapshot and import batch timestamps

## Date-Only Fields

These values are timezone-neutral:

- `persons.birth_date`
- registration dates stored as `DATE`
- `organization_appointments.valid_from`
- `organization_appointments.valid_to`

Date-only values are converted to Jalali using the same calendar date. They are not shifted by timezone.

## Root Cause Fixed

The observed `+03:30` display error was caused by an ambiguous lifecycle:

1. MySQL `TIMESTAMP` columns could be read in the database session timezone.
2. Admin display helpers then interpreted the returned value as UTC.
3. The value was converted to `Asia/Tehran`, adding `+03:30` a second time.

The fix makes the MySQL session timezone explicit UTC and parses stored instants as UTC before a single display conversion.

## Shared Clock

`IPKF\Support\Clock` is the canonical helper for:

- `nowUtc()`
- `databaseTimestamp()`
- `parseStoredInstant()`
- `convertToDisplayTimezone()`
- `formatDate()`
- `formatDateTime()`

Admin Jalali formatting uses `App\Support\AdminFormat`, which delegates instant parsing and display timezone conversion to `Clock`.

## Legacy Data

Existing rows are not mass-modified in this phase.

Most affected columns are `TIMESTAMP` columns. With the database session now forced to UTC, MySQL returns those stored instants in UTC and the admin formatter displays them correctly in `APP_TIMEZONE`.

If future audits find a true `DATETIME` column previously populated with local Tehran wall-clock time, that data must be classified separately and normalized through a reviewed, non-destructive migration. Do not subtract `03:30` blindly.

## Verification

The deterministic verification uses:

- input UTC instant: `2026-07-13 12:00:00`
- expected Tehran display instant: `2026-07-13 15:30:00`
- date-only input: `2026-07-13`

The date-only value must remain the same Gregorian calendar date before Jalali conversion.
