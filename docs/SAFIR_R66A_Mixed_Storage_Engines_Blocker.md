# SAFIR R66A — MariaDB mixed storage engine blocker and safe diagnostic

User executed R66 on 2026-11-03. Offline self-test passed. Runtime --check, --backup, --rehearse all stopped safely with the hard gate `NON_TRANSACTIONAL_TABLES_REQUIRE_DBA_BACKUP_STRATEGY` or `NON_TRANSACTIONAL_TABLES_REQUIRE_DBA_REVIEW`. No backup was created; no isolated schema was created; provider sends were never invoked; production DB did not change. Source R66 runtime reported 185 base tables and 44 non-InnoDB tables. This is **not** proof that every non-InnoDB table is nontransactional.

R66A proposes read-only inventory of the 44 tables, including actual engine, `information_schema.ENGINES.TRANSACTIONS`, estimated rows and sizes without reading business data. The inventory does not create any backup, schema or provider request. SAFIR bulk and pilot master flags must remain all OFF. Never bypass R66 gates by patching the count; never blindly convert storage engines.

Local R66A script artifact (not checked into the repository): `SAFIR_S36_R66A_NonInnoDB_Engine_Inventory_ReadOnly.sh`, SHA256 `f41a09d01e82c956d872e93b5fea9685d72fcf5376bbeaf324ff7eb1b88a92a4`. Verified Bash syntax, PHP 8.4 CLI syntax; **not yet run on the user's host**.

Next plan depends on R66A results: classify which tables are actually nontransactional, critical and under write traffic; select a consistent online backup mechanism appropriate to both InnoDB and the nontransactional tables, or plan a maintenance window with explicit locking. Record backup/restore tests independently. Prefer a dedicated DBA-controlled isolated database for full restore before SAFIR Production DDL. Do not enable public SAFIR sends as a side effect of resolving the backup.
