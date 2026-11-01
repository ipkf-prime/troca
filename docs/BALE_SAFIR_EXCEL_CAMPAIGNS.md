# IPKF — Bale Safir dynamic template management / Excel campaigns

## Purpose
This is **one provider adapter and an extensible template catalog**, not a single hardcoded invitation message. Each Safir template belongs to a user-configured definition in the Persian IPKF communication-center panel.

Entry point after a controlled deployment:
`/admin/communications/settings?section=safir`

Requires the existing `notifications.send.manage` permission for all mutating operations and reports. Every POST is CSRF-guarded.

## Dynamic template catalog
Use **+ تعریف قالب جدید** to create additional templates, or expand a saved template to edit it.

Per template:
- Unique internal code and title
- Safir bot ID and **provider-approved template ID**
- Provider message type and rate in IRR (optional informational metadata)
- Provider registration date (optional informational metadata)
- Exact provider-approved body, including any `{{variable_name}}` placeholders
- Configurable Excel column for destination phone number
- A variable-to-Excel mapping for **every** placeholder; multiple Excel columns can be concatenated with ` + `
- Active/inactive switch and separate administrator confirmation that the body and ID are approved at Safir

Example: one template can use `{{name}}`, `{{sherkat}}`; another can use `{{tracking_code}}`, `{{amount}}` or completely different placeholders. No transport-code changes are required.

**Provider approval is external**: editing a message in IPKF does not create, modify, submit or approve a Safir service template. An administrator must first create/approve it in the Safir panel, then enter the exact `template_id` and message text in IPKF. The UI's provider-approved checkbox is an administrator declaration, not an API verification.

Default catalog data only:
`public_html/resources/ui-content/bale-safir-templates.json`

The initial `invite` is **seed data, not hardcoded business logic**. Catalog edits are stored in an absolute persistent JSON file, selected by `BALE_SAFIR_CATALOG_PATH`. This must be outside immutable release folders and outside any web document root. Updates use an exclusive file lock and atomic file replacement. Templates are not lost during a later release cutover provided this persistent path remains configured.

## Excel import
When uploading XLSX, select the template first. The file's first row contains **the actual column headings**, which are matched against that template's phone column and variable mappings. There are no fixed `نام`/`نام خانوادگی`/`نام شرکت` requirements for other templates. The sample invite maps `name = نام + نام خانوادگی` and `sherkat = نام شرکت`.

Accepts Iranian mobile numbers in formats `09...`, `989...`, `+989...`, `9...`, and `00989...`, including Persian/Arabic digits. Invalid/duplicate destinations and missing variable data are marked as skipped. Rejects formulas and duplicate header names.

Import guardrails (technical, not template-type restrictions): 5,000 input rows by default (configurable with BALE_SAFIR_MAX_IMPORT_ROWS), 2 MB compressed XLSX, 256 columns. Campaign previews and results are currently kept in the **authenticated server-side PHP session** and are not yet suitable for durable high-volume production operations.

For each row, the application sends:
```json
{
  "bot_id": "<from selected catalog item>",
  "phone_number": "<normalized mobile>",
  "message_data": {
    "template_message": {
      "template_id": "<from selected catalog item>",
      "text_fields": {
        "<variable1>": "<mapped Excel value>",
        "<variable2>": "<mapped Excel value>"
      }
    }
  }
}
```
This is **illustrative JSON**, not a literal payload. Variables are taken from the selected template, not from PHP hardcoded keys; no undocumented request IDs are sent to Safir. Requests use the configured HTTPS `https://safir.bale.ai/api/v3/send_message` endpoint and the rotated access key from the private server environment. The existing Bale Bot `chat_id` adapter is unaffected.

## Required shared/private environment
Set in private server configuration, **not in Git**:
```dotenv
BALE_SAFIR_CATALOG_PATH=/absolute/persistent/private/path/bale-safir-templates.json
BALE_SAFIR_MAX_TEMPLATES=200
BALE_SAFIR_MAX_IMPORT_ROWS=5000
BALE_SAFIR_API_ACCESS_KEY=
BALE_SAFIR_SEND_ENABLED=false
BALE_SAFIR_PRODUCTION_APPROVED=false
BALE_SAFIR_TEST_PHONE=
BALE_SAFIR_INTERVAL_MS=1500
```
Replace the example catalog path with the actual approved persistent path. A nonempty, **rotated** key is required for any real sending. The key shared in chat previously must be revoked. Leaving the toggles as shown ensures **NO live API dispatch**. Production requires two distinct enable flags, plus explicit in-panel confirmation and a successful test before a bulk send.

## Acceptance and rollout
1. Validate catalog CRUD, arbitrary placeholder detection, duplicate-variable handling and mapping configuration.
2. Import separate Excel samples for at least two templates; verify preview and variable mapping.
3. Check RBAC and CSRF, safe-disabled sending, malformed XLSX rejection, and formula/duplicate-header rejection.
4. After the final release package is reviewed, deploy only through IPKF's official **verified immutable release** mechanism; preserve active Production code and a rollback link.
5. Test actual Safir service acceptance with a **single allowlisted phone** only after new-key rotation.
6. Require durable persistence, retry/audit reconciliation and opt-out management before permitting routine high-volume Production sends.

## Delivery semantics
- `sent` is currently provider API acceptance with a message reference, **not proof of delivery/opening**.
- Timeout or ambiguous response is `unknown` and must not be automatically retried.
- All sending is disabled by default and CI smoke tests make **zero** provider calls.
- Current campaign state is session-scoped. A production-grade DB-backed queue, audit history, concurrent admin handling and delivery reconciliation is a separate required hardening step.

## Offline contract checks
```bash
php -l public_html/app/Services/BaleSafirCampaignService.php
php -l public_html/app/Services/BaleSafirTemplateCatalogService.php
php -l public_html/routes/bale-safir-campaigns.php
php tests/bale-safir-campaign-smoke.php
```
