# IPKF Platform Commercial Foundation

Current development version: `0.4.8-platform-commercial-foundation-dev`

## Purpose

The platform commercial foundation evolves IPKF from a single-deployment modular application into a multi-application commercial platform. This phase is schema, metadata, and contract only. It does not provision infrastructure, switch runtime databases, enforce licenses on routes, or create operational business records.

## Architecture Decisions

No conflicting framework architecture was found during this phase. The implementation reuses the existing IPKF migration runner, seeder runner, configuration/bootstrap flow, diagnostics route, `IPKF\` core namespace, and `App\` application service namespace.

No second framework, ORM, service container, router, or configuration system is introduced.

## Platform Ownership

The platform core owns identity, authentication, MFA, RBAC, persons, organizations, organizational units, positions, appointments, geography, installation registry, application/module catalog, topology registry, and licensing metadata.

Specialized applications own their operational data. The first specialized application registered by this phase is `automation`. Future application codes such as `crm`, `erp`, `hr`, `finance`, `marketplace`, `integration`, and `reporting` must be possible without schema changes.

## Catalogs

The application catalog is stored in `platform_applications`.

The module catalog is stored in `platform_modules`.

Module dependencies are stored in `platform_module_dependencies` and reject self-reference. Duplicate dependencies are blocked by a unique key.

Seeded applications:

- `core`
- `automation`

Seeded core modules:

- `core.identity`
- `core.access`
- `core.organization`
- `core.geography`
- `core.platform_registry`
- `core.licensing`

Seeded automation modules include `automation.core`, `automation.correspondence`, `automation.secretariat`, `automation.cartable`, `automation.workflow`, `automation.forms`, request modules, document generation, archive, QR verification, digital signature, and notifications.

## Installation and Topology

Installations and environments are modeled separately by:

- `platform_installations`
- `platform_environments`

Topology records are installation-specific and environment-specific:

- `platform_domains`
- `platform_database_endpoints`
- `platform_storage_endpoints`
- `platform_service_endpoints`

Domains support primary and alias records, HTTPS requirement, verification status, enabled status, application association, and environment association.

Database endpoint purposes include:

- `primary`
- `read_replica`
- `reporting`
- `archive`

Endpoint records are metadata only. They do not drive the current runtime connection bootstrap in this phase.

## Secret-Reference Policy

Topology tables must not store database passwords, storage secrets, tokens, private keys, or provider secrets in plaintext. Endpoint tables store only credential or secret references, such as `credential_secret_reference`.

Diagnostics must never expose domains, hosts, database names, endpoint details, customer references, license manifests, secret references, or internal IDs.

## Licensing Contract

The foundation separates:

- module installed state
- module enabled state
- licensed entitlement
- RBAC permission
- organization/user scope

The `ModuleGate` contract distinguishes:

- `module_not_installed`
- `module_disabled`
- `module_unlicensed`
- `license_expired`
- `dependency_blocked`
- `allowed`

Licenses support installation binding, customer reference, issue and validity dates, grace period, status, edition, signed manifest reference, online/offline activation mode, revocation metadata, module entitlements, and quantitative limits.

This phase does not implement cryptographic signing, online activation, offline activation files, usage metering, or runtime route enforcement.

## Provisioning Lifecycle

Provisioning is modeled as resumable and auditable:

- `platform_provisioning_runs`
- `platform_provisioning_steps`

Step statuses are:

- `pending`
- `running`
- `succeeded`
- `failed`
- `skipped`
- `rolled_back`

The schema records ordered steps and safe metadata only. It does not create DNS, databases, database users, SSL certificates, or storage buckets.

## Deferred Work

Deferred items include installer UI, DNS creation, database creation, database user creation, SSL provisioning, runtime connection switching, multi-database routing, moving automation tables to another database, sales quotation/order/invoice/payment, online license server, cryptographic license signing, offline activation, usage metering, runtime license enforcement, correspondence application services, workflow engine, document/PDF/JPG generation, QR generation, and operational data.
