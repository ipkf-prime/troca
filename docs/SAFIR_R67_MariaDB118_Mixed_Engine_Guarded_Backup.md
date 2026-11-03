# SAFIR R67 — Guarded MariaDB 11.8 mixed-engine full backup

R66C evidence: MariaDB 11.8 runs on the same Linux host as PHP; matching mariadb-backup version; actual Data Directory, socket and local root socket access are present; root grant hints show needed backup privileges. R66A: 141 InnoDB tables + 44 MyISAM tables (185 base tables). All production bulk/pilot SAFIR flags are OFF. No persistent SAFIR campaign queue schema exists.

**R67 local release artifact:** `SAFIR_S36_R67_MariaDB_Mixed_Engine_Backup_Guarded.py`; SHA256 `286f4923c0d2bf6c4f014928b70b4d23a54d6faf43886937f2caf860b9333a7e`.

Modes:
- `--check` is strictly read-only; confirms active R62 runtime, engine baseline 141+44, MariaDB 11.8 server and backup tool, socket root backup privilege hints, Datadir locality, and conservatively available disk space. No backup, schema or ENV changes.
- `--backup` prompts for exact operator token `BACKUP R67`, after warning of temporary MyISAM write/DDL blocking and FULL-SERVER physical backup scope. Executes `mariadb-backup --backup` with socket root auth, pinned actual datadir, and default backup locks active (never `--no-lock`). Uses bounded FTWRL wait of 30s. Creates unique root-private 0700 backup directory outside webroot at `/var/backups/ipkf/safir/r67-mariadb-full-...`, logs beside that directory because mariadb-backup needs an initially empty target. On success creates SHA256 file manifest and receipt, performs verification. Leaves failed partial backup directories private and explicitly reports failure.
- `--verify-latest` rehashes latest R67 receipt + all files and detects tampering.

**Validation on assistant execution container:** Python compilation PASS; embedded PHP parse/lint PASS; test-manifest generation, verification PASS; intentional tampering detection PASS. This is not a live execution against the user's database. NEVER report R67 production success until operator shows runtime results.

**Important distinction:** R67 provides physical backup and checksum integrity verification only. Neither `--prepare` nor an isolated MariaDB restore is executed by R67; both are mandatory separate gates prior to ANY DDL in Production. The current five-table schema remains design-only; Worker/import not implemented; do not enable SAFIR bulk or pilot flags. Root socket is used for this guarded one-off host backup and should be replaced with reviewed least-privilege backup identity in future.
