# SAFIR R66C — Physical backup locality and DBA identity gate

**Reason for gate:** R66B established that `mariadb-backup` is installed on `troca-php`, but that alone does not prove that the MariaDB 11 data directory or server daemon are local. `mariadb-backup` is a physical backup utility and must be run with access to the actual database-server filesystem. Existing application SQL grants include LOCK TABLES mention but no direct RELOAD or PROCESS mentions; these are only hints, not an effective grant audit.

R66C checks only nonmutating metadata via the active runtime's layered environment and core DB connection. It compares SQL server hostname with OS hostname, local MariaDB daemon process and data directory, socket presence, and MariaDB backup binary version. If and only if locality evidence looks promising, it probes local root socket authentication using a read-only SELECT and SHOW GRANTS, masking usernames and grant strings. No passwords or runtime configuration values printed; no backup or provider send; no privilege changes or schema mutation.

Downloaded artifact: `SAFIR_S36_R66C_DB_Host_Backup_Identity_ReadOnly.py`.
SHA256: `8bcec445fe3b3d00bac3be5263e943321b193a36fc004cb26c645cf96f0b6265`.
Syntax `python3 -m py_compile` PASS (local isolated execution, not yet production host).

Important interpretations:
- `R66C=PASS` means the diagnostic ran, **NOT** approval to run a production backup or a guarantee of privilege sufficiency.
- `PHYSICAL_BACKUP_LOCALITY_EVIDENCE=PROMISING` is just preliminary confidence, not proof. If `NOT_ESTABLISHED`, obtain MariaDB host access and do not run a physical backup on the PHP host.
- Local root privileges and source binary versions are only hints. Select dedicated backup credentials, verify mandatory privileges and a controlled backup window before backup.
- MariaDB docs: mariadb-backup requires RELOAD, PROCESS, LOCK TABLES, BINLOG MONITOR for typical deployments; for MyISAM consistency, **do not use --no-lock**.
- R66/R66A/R66B all indicate bulk and pilot flags OFF. Preserve.
- Use tested restore rehearsal before any SAFIR five-table production migration.
