# SAFIR R66 — Guarded Backup and Isolated Schema Rehearsal

Status: Local execution package prepared, NOT executed on the user's production host.

R65 result (Nov 3, 2026): MariaDB 11; InnoDB; main `users.id` type `bigint(20) unsigned`; the five SAFIR persistent campaign tables all NOT_APPLIED; both migration registries exist; full production send + pilot flags OFF.

Release package: `SAFIR_R66_Backup_Isolated_Rehearsal.zip`
SHA256: `a1b112eae823b274b630dc8396c5702fc5cfc676f7f6d3232975e4908acc16fa`
Script: `SAFIR_S36_R66_Backup_and_Isolated_Schema_Rehearsal.py`
SHA256: `0125c2c5f33cddc92f61dfa5346ba32f12dd8cb8b864155ad19b204b3d9a6862`
Design SQL: `SAFIR_R65_Durable_Campaign_MariaDB11_Design_ONLY.sql`
SHA256: `819be1e64a383e2591cfab754015ad39d3f80e2366d7cde1909eadea4923719a`

Modes:
- `--self-test`: offline pinned source check.
- `--check`: read-only actual active runtime flag, database and dump/client executable checks.
- `--backup`: root-only consistent single-transaction logical dump to `/var/backups/ipkf/safir/`, GZIP integrity and SHA256 receipt; database READ ONLY, backup file filesystem WRITE.
- `--rehearse`: requires verified backup receipt and explicit interactive confirmation. Creates a new isolated schema named `safir_r66_iso_YYYYMMDD_random`, applies the 5 pinned R65 DDL statements only there, verifies InnoDB tables and five foreign keys. Production database not mutated. No implicit cleanup.
  
**Important:** SHA+gzip proof is **not** a restore proof. Isolated DDL rehearsal is **not** full restore proof. Before any production DDL execute a real isolated restore of the full backup with dedicated DBA permissions and test read-back. Never fall back to production DB if isolated schema creation fails. Do not enable bulk or pilot flags in R66. Worker/queue and streaming import not yet implemented.

Code self-test, Python AST compile, embedded PHP 8.4 lint PASS in tool author's isolated environment; MariaDB live backup and DDL rehearsal remain pending host execution.
