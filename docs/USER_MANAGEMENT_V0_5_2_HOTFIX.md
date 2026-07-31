# User Management v0.5.2 Hotfix

This hotfix closes the QA findings discovered after the v0.5.1 merge.

## Changes

- Birth date input is Jalali while storage remains Gregorian `DATE`.
- National code uniqueness is checked in the service layer in addition to the
  existing safe unique-index migration.
- Every authenticated user can view and edit their own identity and address.
- Primary address persistence no longer writes `NULL` into the required
  `address_line` column.
- Changing email or mobile always clears the previous verification state.
- OTP is sent to the new identity after administrator changes and before
  self-service identity changes are applied.
- Unverified current identities can request and confirm a new OTP.
- The account page contains change and verification workflows.
- Role table default sorting is by database role code.
- Role-table header and column alignment are polished.

## OTP configuration

Email delivery requires:

```text
MAIL_FROM_ADDRESS
PHP mail() support
```

SMS delivery requires:

```text
MFA_SMS_ENABLED=true
KAVENEGAR_API_KEY
KAVENEGAR_SENDER
PHP cURL support
```

Development OTP exposure is available only when all are true:

```text
APP_ENV=development
APP_DEBUG=true
IDENTITY_DEV_EXPOSE_TOKEN=true
```

## Storage contract

The UI accepts Jalali dates such as `1400/01/01`. The service converts and
stores the value as Gregorian `Y-m-d`, preserving the existing database
contract.
