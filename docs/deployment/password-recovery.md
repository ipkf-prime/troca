# Password Recovery Deployment Contract

Password recovery uses independent delivery contracts for SMS, email, and Bale.

Required production settings:

PASSWORD_RECOVERY_SMS_ENABLED=true

PASSWORD_RECOVERY_BALE_SECRET_FILE=<private environment-specific ipkfbot configuration file>

Operational rules:

- PASSWORD_RECOVERY_SMS_ENABLED controls password-recovery SMS only.
- MFA_SMS_ENABLED remains independent from password-recovery SMS.
- PASSWORD_RECOVERY_EMAIL_ENABLED must not be introduced.
- Recovery email uses the verified-email and configured email-provider contract.
- PASSWORD_RECOVERY_BALE_SECRET_FILE must reference the dedicated private ipkfbot configuration for the environment.
- BALE_BOT_TOKEN must not be used by password recovery.
- Secret values, provider credentials, bot tokens, OTP values, passwords, and user identifiers must never be committed.
- Production deployment verifies configuration presence without printing secret values.

Management metadata note:

The current Dev database does not contain notification_template_definitions rows for auth.password_reset.email_otp or auth.password_reset.bale_otp. Their runtime notification_templates rows are production-critical and are source-persisted by SeedPasswordRecoveryEmailBaleTemplates. Administrator-management definitions for those two codes are deferred as post-production hardening and are not required for runtime delivery.
