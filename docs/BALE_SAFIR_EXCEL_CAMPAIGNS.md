# IPKF — Bale Safir invitation campaigns (development)

## Entry point
After a controlled deployment to Core **development**, open:
`/admin/communications/settings?section=safir`

Requires existing `notifications.send.manage` permission. It appears as a tab next to other communication-center sections.

## Input
XLSX with headers in row 1:

| نام | نام خانوادگی | نام شرکت | شماره همراه مدیرعامل |
|---|---|---|---|
| علی | محمدی | شرکت تعاونی نمونه | 09123333170 |

Accepted: 09xxxxxxxxx, 989xxxxxxxxx, +989xxxxxxxxx, 9xxxxxxxxx (Excel numeric cells), 00989xxxxxxxxx; Persian/Arabic digits accepted. Maximum 500 rows and 2 MB compressed XLSX. Formulas are rejected. Uploaded files are parsed, **not saved**. The generated campaign is kept in the **authenticated server-side PHP session** (not browser storage).

## Template
Default data definition:
`public_html/resources/ui-content/bale-safir-invite.json`

Template ID, bot ID and preview body can be edited by an authorized administrator in the IPKF panel; overrides are stored under `public_html/storage/private/bale_safir_settings.json`, outside the web document root and with restricted file permissions. The panel does **not** edit the previously approved message in the Safir provider panel. Only `text_fields.name` and `text_fields.sherkat` are sent; the Safir server renders the fixed template.

## Environment configuration
Add to the **server's private shared env**, never to source control:

```dotenv
BALE_SAFIR_API_ACCESS_KEY=REPLACE_WITH_NEW_ROTATED_SECRET
BALE_SAFIR_SEND_ENABLED=false
BALE_SAFIR_PRODUCTION_APPROVED=false
BALE_SAFIR_TEST_PHONE=989XXXXXXXXX
BALE_SAFIR_INTERVAL_MS=1500
```

The API key previously pasted in chat **must be revoked/rotated**. The checked-in example is empty and safe-disabled. Production requires two separate environment toggles before dispatch, after a later explicit deployment approval. No message can be sent through this module by importing a file alone.

## Workflow
1. Upload Excel — preview and error checking; zero API calls.
2. Review recipient count, duplicates, preview and company spelling.
3. Enable test sending in **dev only** after key rotation and an allowlisted test number; click test and tick confirmation.
4. Check the returned message ID and delivered message in Bale.
5. Tick approval, click start. Browser dispatches batches of 3 with a 2-second pause between requests, plus server-side message interval. Close page to stop; reopen to continue while the PHP session exists. A failed or unknown result halts the browser loop.
6. Download CSV report (UTF-8 BOM, Excel compatible).

### Delivery semantics
- `sent` means Safir responded with a non-empty `message_id` and empty `error_data`; it does **not** prove a recipient opened the message.
- Timeouts, invalid response or server error are marked `unknown` and **not automatically retried**.
- A stable `request_id` per campaign row is sent to Safir.
- The provider's message API requires `phone_number`, not Bot API `chat_id`.
- Existing `NotificationBaleGatewayAdapter` remains unchanged.

## Limitations before production approval
- Campaign state and reports live in the current user's PHP session and expire with it. Long-term audit, durable queues, cancellation, retries, multiple admins, opt-out management, delivery reconciliation and archival reporting need a database-backed second phase **before enabling production**.
- Default parser accepts the first worksheet's standard `xl/worksheets/sheet1.xml` path; export a regular one-sheet XLSX if a workbook has unusual worksheet numbering.
- This PR does **not** deploy to any host or send actual messages.

## Offline checks
```bash
php -l public_html/app/Services/BaleSafirCampaignService.php
php -l public_html/routes/bale-safir-campaigns.php
php tests/bale-safir-campaign-smoke.php
```

See GitHub Actions workflow `.github/workflows/bale-safir-offline.yml`.
