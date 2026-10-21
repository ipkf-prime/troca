# Kavenegar Verify Lookup — DEV gate 1

Base: ipkf-ticket-work-c4-dev / dbb62d387904ac873569cd7636eff041742f6e8e

## Scope

- Add a separate SMS provider type `kavenegar_lookup`.
- UI-managed lookup template names: registration, login, approval.
- API key stored through the existing encrypted provider secret store.
- Provider test send uses **registration** template and requires an operator-entered six-digit test token.
- A provider test audit retains only provider message reference, duration, status, purpose, and the last four digits of the recipient. It must never record an OTP/token or API key.
- Lookup adapter rejects generic free-text gateway calls without an explicit `lookup_token`. The existing Send provider remains unchanged.
- SMS delivery-hours policy remains in effect.

## Out of scope pending real DEV smoke test

- Do not enable automatic registration / MFA OTP routing yet.
- No changes to production databases, credentials, deployment, cron, or DNS.
- No background retry or arbitrary message routing through Lookup.

## DEV checks

1. Verify local/remote Git HEAD before applying this branch to the correct DEV directory.
2. Run PHP syntax checks and the existing notification provider regression tests.
3. Execute a **Core DEV-only migration** with existing guarded operational procedures.
4. In /admin/communications/settings?section=providers create new account:
   Channel SMS; provider 'کاوه‌نگار - Lookup خدماتی'; title 'کاوه‌نگار خدماتی';
   register_template=ipkfregister; login_template=ipkflogin;
   approval_template=ipkfapprove; API key in the confidential tab.
5. Keep the account disabled. Use its "تست ارسال" button with your own number
   and a manually chosen 6-digit code. Confirm Kavenegar accepted it and the
   handset actually received the SMS. Confirm logs contain no code or key.
6. Repeat with a number that has opted out of advertising SMS.
7. Do not deploy to PROD or enable automatic OTP before stage 2 and a complete
   DEV E2E registration + login + MFA test.

## Notes

- Kavenegar Lookup `verify/lookup.json` requires an approved template and active advanced service.
- Lookup does not take a sender parameter.
- A response status 200 is API acceptance, not handset delivery.
