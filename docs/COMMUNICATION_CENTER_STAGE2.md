# Communication Center — Stage 2

## Delivered

- Database-driven sidebar navigation with nested submenus
- Database-driven topbar unread-message alert
- Database-driven communication hub with nine submenu items
- Database-driven route permissions for communication routes
- Internal user-to-user messaging
- Inbox, compose, sent messages, threads and replies
- Recipient-policy registry
- Immediate in-app notification for new messages
- Login reminder when unread messages exist
- Dynamic notification-channel validation
- Provider catalog for SMTP, SMS, Bale, Telegram, Eitaa and WhatsApp
- Provider-instance, default-provider, balance and routing foundations
- Per-user channel preferences
- Delivery report

## Dynamic contract

Navigation title, route, icon, permission, order, active paths and badge source
are stored in `admin_navigation_items`.

Communication route permissions are stored in `admin_route_permissions`.

Provider configuration fields are described by
`notification_provider_types.config_schema_json`.

Recipient eligibility is resolved from `message_recipient_policies`. The
initial policy allows messaging all active users except the sender. Role,
organization, geography and assignment-scope restrictions are intentionally
deferred until their rules are provided.

## Secret handling

Provider passwords and API tokens must not be stored in configuration JSON.
Provider instances use `secret_reference` so the value can be resolved from
the shared environment or a future secret manager.

## Apply

After extracting the package, run:

```bash
php tools/apply-communication-center-stage2.php
```

Then run structural tests, deploy, migrate and seed.

## Diagnostics

```bash
php scripts/check-communication-center.php
```

`communication_submenus` must be at least 9, `provider_types` must be 7, and the event/routing counts must be greater than zero.
