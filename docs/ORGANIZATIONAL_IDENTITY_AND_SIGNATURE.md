# Organizational identity and bilingual signature foundation

This foundation separates authentication identity, person identity, RBAC authorization, organizational appointment, and document-signing authority.

## Resolution chain

`User -> Person -> Active Appointment -> Organization Position -> Unit -> Organization`

A user with one active appointment is resolved automatically. A user with multiple active appointments must select one of their own active appointments. The selection is session-scoped and auditable. Organizational context never grants RBAC permissions by itself.

## Bilingual signature policy

Signature assets are Core-owned private files with metadata in `signature_assets`. Persian (`fa`) and English (`en`) signatures are distinct. A shared asset may be used only when the explicit authorization enables shared fallback.

Signing authority is appointment-scoped through `signature_authorizations`; owning a signature asset does not grant unrestricted signing authority.

Automation consumes only the read-only organizational identity contract and immutable signature snapshot values. No cross-database join or foreign key is introduced.

## Deployment

1. Deploy the code.
2. Run the normal Core migration endpoint once.
3. Run the normal Core seeder once.
4. Confirm the new boolean diagnostics.
5. Do not upload real signature files until private upload and preview controllers are enabled.
