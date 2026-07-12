# IPKF Admin Users Organization Schema

Current version: `0.4.6-admin-users-organization-dev`

## Purpose

This phase adds the schema foundation required before Automation. It prepares internal organizational units, job positions, and user organizational assignments so later correspondence and cartable flows can route work to users or units.

This is schema foundation only. It does not add Automation, letter/correspondence tables, inbox/cartable tables, routing/referral tables, attachments, workflow history, CRM, ERP, Bot modules, or UI.

## Tables

### org_units

Stores internal organizational units and departments.

Important fields:

- `parent_id`
- `code`
- `title`
- `type`
- `path`
- `depth`
- `sort_order`
- `status`
- `description`
- timestamps and optional `deleted_at`

This structure can later support hierarchy-aware units such as دبیرخانه, management departments, expert teams, and operational units.

### positions

Stores job positions and titles used by future routing and automation.

Important fields:

- `code`
- `title`
- `description`
- `status`
- `sort_order`
- timestamps

Examples for future use include مدیر, کارشناس, دبیرخانه, and other role titles. This phase does not seed business-specific positions.

### user_org_assignments

Connects existing `users` to organizational units and optional positions.

Important fields:

- `user_id`
- `org_unit_id`
- `position_id`
- `is_primary`
- `status`
- `started_at`
- `ended_at`
- timestamps

The table is designed for future personal and unit cartables, routing work to a user, and routing work to a unit.

## Guardrails

- The existing `users` table is reused.
- No duplicate users table is introduced.
- Existing persons/users/RBAC schema remains unchanged.
- Text columns are utf8mb4-compatible.
- Migrations are idempotent.
- No automation features are implemented yet.

