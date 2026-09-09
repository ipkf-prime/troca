# IPKF Platform Logging and Audit Contract

Status: Binding platform contract from v0.8.0.

## 1. Log domains

The platform has three conceptual domains:

1. operational/application logging
2. security logging
3. durable audit trail

Operational and security logs may use the shared `SystemLogger`.

Audit history must use the `AuditLogger` contract and must not be stored
only in rotating operational log files.

## 2. Structured event schema

System log records use structured JSON Lines.

Canonical fields:

- timestamp_utc
- level
- channel
- event_code
- module
- action
- actor_user_id
- actor_user_reference
- actor_type
- target_type
- target_id
- correlation_id
- request_id
- ip
- user_agent
- result
- duration_ms
- metadata

Technical event codes are stable English identifiers.

Examples:

- `Http.Request.Completed`
- `Http.Request.Failed`
- `System.Exception.Unhandled`
- `Auth.Login.Failed`
- `Ticket.AutoClose.PolicyUpdated`

## 3. Request identity

Every request receives a server-generated Request ID.

A valid incoming correlation identifier may be propagated.

If no valid correlation identifier exists, the Request ID becomes the
Correlation ID.

Response headers expose:

- `X-Request-ID`
- `X-Correlation-ID`

## 4. Time

Logging timestamps are UTC.

UI presentation may convert timestamps to Jalali/local display time, but
stored log timestamps remain UTC.

## 5. Secret policy

Raw secrets must never be recorded in log metadata or exception context.

Sensitive values include at minimum:

- password / passwd
- secret
- token
- API key
- Authorization
- Cookie
- CSRF token
- session identifier

The platform SecretMasker is applied before serialization.

## 6. Failure behavior

Logging is best-effort.

Failure to write an operational log must not break the business request.

Logging failures must also not recursively invoke the same logger.

## 7. Exception policy

Production responses must not expose:

- exception message
- file path
- line number
- stack trace

The user receives a safe error message and a tracking identifier.

Diagnostic detail is available only in backend logs or debug mode.

## 8. Audit

`AuditLogger` is intentionally a separate abstraction.

F1B2A defines the contract only.

F1B2B provides durable storage and retention semantics.
