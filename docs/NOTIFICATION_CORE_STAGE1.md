# IPKF Notification Core — Stage 1 (v0.6.0)

This package is rebased on the completed `v0.5.2` user-management hotfix.

## Preserved foundations

The package deliberately preserves:

- authentication login history;
- dynamic geography and person-address repair;
- self-service profile and identity routes;
- account-security routes;
- administrator user-management routes.

## Scope

- Transactional notification event and outbox
- Idempotency key to prevent duplicate events
- Worker claim locking and stale-lock recovery
- Retry with exponential backoff
- In-app notification materialization
- User inbox with read/unread state
- Delivery records and delivery-attempt audit
- Channel registry for in-app, email, SMS and Bale
- Template registry
- Per-user notification preference schema
- CLI outbox worker
- CLI test notification

## Runtime flow

```text
Module event
→ NotificationPublisherService
→ notification_events + notification_outbox
→ process-notification-outbox.php
→ notifications + recipients + deliveries
→ /admin/notifications
```

Email, SMS and Bale are registered but inactive in Stage 1. Their provider
adapters and workers are added after the in-app flow is verified.

## Deployment order

1. Apply this package on `v0.6.0-notification-core-dev`.
2. Run the structural tests.
3. Commit and deploy the branch.
4. Run the protected Core migration endpoint.
5. Run the protected Core seed endpoint.
6. Run the CLI test notification.
7. Open `/admin/notifications`.
8. Add the outbox worker to cron only after the smoke test.

## Worker

```bash
php scripts/process-notification-outbox.php --limit=50
```

## Test notification

```bash
php scripts/create-test-notification.php \
  --confirm=CREATE-TEST-NOTIFICATION \
  --user-id=1
```

## Deferred access work

Scoped assignment of province, county, company and organization roles remains a
separate access-control stage. Notification Core does not change that contract.
