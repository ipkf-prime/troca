# IPKF Platform Audit Boundary

Status: Binding architecture decision from F1B2B.

## 1. Purpose

`platform_audit_events` is the durable audit store for platform-level
governance and security-sensitive administrative changes.

It complements existing domain event/audit tables. It does not replace them.

## 2. Storage authority

The Platform Audit Store belongs to:

- application: `core`
- connection: `core.primary`
- table: `platform_audit_events`

No Platform Audit table is duplicated into Automation, Work or Ticketing.

## 3. Platform audit examples

Platform Audit is appropriate for:

- role and permission governance
- user access changes
- system configuration changes
- theme/default appearance governance
- managed system content/help/announcement governance
- account-security administration
- MFA governance
- password/security policy administration
- API/security credential administration
- cross-platform administrative actions

## 4. Domain audit remains authoritative

Existing domain event streams remain in place, including:

- Automation correspondence events
- Work activity events
- Work module-reference audit events
- Ticketing lifecycle/events
- Ticketing SLA events
- notification/message domain audit tables

A domain event must not be copied into Platform Audit merely because it exists.

Dual recording is justified only when one action has both:

1. domain/business meaning; and
2. independent platform-governance meaning.

## 5. Correlation

Platform Audit records preserve:

- request_id
- correlation_id
- IP address
- User-Agent

These values originate from the shared RequestContext.

## 6. Actor

Audit actors may be:

- authenticated user
- system
- scheduler
- integration
- migration/maintenance process

A database foreign key to `users` is intentionally not required because
audit history must survive account lifecycle changes and system actors do
not necessarily have user rows.

## 7. Append-only application contract

Ordinary application code may INSERT audit records.

The durable audit adapter exposes no update or delete operation.

Controlled retention/purge, if introduced later, must use a dedicated
audited maintenance path.

## 8. Failure behavior

Durable audit is not best-effort operational logging.

`DatabaseAuditLogger` propagates storage failures.

Sensitive callers can therefore fail/rollback the governed action if the
corresponding audit record cannot be persisted.

When atomicity with a Core transaction is required, the caller must inject
the same PDO connection into `DatabaseAuditLogger`.

## 9. Sensitive values

Before/after/metadata fields are passed through the shared SecretMasker.

Passwords, tokens, API keys, Authorization/Cookie values, CSRF/session
identifiers and other recognized secrets must never be stored raw.

## 10. Retention

No platform-wide retention duration is invented in this foundation.

Each event may carry:

- retention_policy_code
- retain_until

Both are nullable until an explicit Platform retention policy is approved.

Existing domain retention settings do not automatically become Platform
Audit retention policy.

## 11. Dynamic module inheritance

Platform Audit is not configured module-by-module.

Every current or future module follows the same classification rule:

- domain/business actions remain in the module's domain event/audit store;
- platform-governance actions use the shared Platform Audit boundary.

Future modules must not create an alternative Platform Audit table.

Platform-governance code must depend on the `AuditLogger` abstraction rather
than writing `platform_audit_events` directly.

The durable adapter remains centrally replaceable without changing module
business logic.

## 12. Future-page rule

Adding a new administration page does not create a new audit policy.

If that page mutates platform governance state, its command/service must
cross the shared governance-audit boundary.

Read-only pages do not generate durable mutation audit merely by being viewed.
Operational request logging remains automatic through the HTTP middleware.
