# SAFIR R67A — Reconcile successful MariaDB 11.8 physical copy with missing receipt

Operator evidence 2026-11-04: original R67 `--backup` obtained `MARIADB_BACKUP_RETURN_CODE=0`, but `R67=FAIL REASON=MARIADB_BACKUP_MARKER_MISSING`. Read-only diagnostic found that backup directory `/var/backups/ipkf/safir/r67-mariadb-full-20261104T024454Z-97f8a87b` exists with `mariadb_backup_checkpoints`, `mariadb_backup_info`, `backup-my.cnf`, `ibdata1`, and `mysql/`. Checkpoint contains `backup_type = full-backuped`, `from_lsn = 0`, `to_lsn = 926292872`; private R67 backup log contains `completed OK` and no error. The original script only accepted legacy `xtrabackup_checkpoints`. No manifest/receipt existed and `--verify-latest` correctly reported NO_COMPLETED_R67_BACKUP.

Patch commit `29890041dc52a94b3ea53e07c1f8cdcb465b3fff` updates `tools/safir/SAFIR_S36_R67_MariaDB_Mixed_Engine_Backup_Guarded.py`. Git blob SHA `476654fa97b3068ed47a6c52c74e963c6eb89162`; raw source SHA256 `145b2c6a660e472b3773aac1e7d073a500d263767d419497bdfc75fdab845f9e`.

Changes: checkpoint check supports exactly one of `mariadb_backup_checkpoints` or `xtrabackup_checkpoints` plus `from_lsn=0`. Operator-confirmed `--adopt-existing` acts ONLY on the known R67 backup path; checks the complete marker, original private log and filesystem ownership/permissions; creates SHA manifest and receipt without recreating/copying raw backup and verifies them. Requires the operator to type `ADOPT R67 BACKUP`. Re-running the same adoption is rejected. Existing `--backup` behavior unchanged.

Offline testing: Python syntax PASS; synthetic successful adoption PASS; second adoption denied; deliberate file tamper detected by SHA. The patch artifact matches Git blob ID for the GitHub commit. No live server execution of the patched script has yet been observed.

IMPORTANT: no `--prepare` or restore test has run, so backed-up data are not yet proven restorable. R68 must prepare a **copy** in a separate private directory and test isolated restore; no `--copy-back`, `--move-back`, live datadir replacement, MyISAM conversion or SAFIR production send enabling is permitted.
