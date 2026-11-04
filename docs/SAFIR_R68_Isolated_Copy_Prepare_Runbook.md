# SAFIR R68 — Prepare only a verified independent copy of R67 backup

Status: source committed, **not executed on the user's MariaDB server**. Production schema and SAFIR send flags remain untouched.

R67 verified success (2026-11-04): source `/var/backups/ipkf/safir/r67-mariadb-full-20261104T024454Z-97f8a87b`; 879 files; SHA256 manifest `35277aef993dd0ad2fbe9ca825d7f151286fd1b807da832e315250bfdeaf5f5b`; `R67_BACKUP_VERIFICATION=PASS`, `BACKUP_PREPARED=NO`, `ISOLATED_RESTORE_VERIFIED=NO`.

R68 executable source path: `tools/safir/SAFIR_S36_R68_Prepare_Isolated_Copy_Guarded.py`; exact commit `e149cc765f468ef3991a08c9533cf10f2b389b32`; SHA256 `af2e08d3afc1ddcc440d97df0bdf6bb611035f27da469f16602c5ded05aba64e`.

Modes:
- `--check`: only reads R67 source, verifies all 879 SHA256s, ensures the pinned R67 verifier's `--check` and `--verify-latest` pass, checks MariaDB 11.8 backup binary, permissions and available disk. No write.
- `--stage-prepare`: requires interactive approval `PREPARE R68 COPY`. Revalidates R67; creates a unique root-only directory `/var/backups/ipkf/safir/r68-prepare-.../work`, copies original raw files, rechecks each SHA256; invokes `mariadb-backup --no-defaults --prepare --target-dir=<R68 work> --use-memory=256M` **only on the copy**. Saves a private log and SHA256 receipt for prepared files; on error retains private incomplete working copy and reports failure. Does NOT use `--copy-back`, `--move-back`, stop/start MariaDB, or write original source.
- `--verify-latest`: read-only verification of the latest prepared R68 work directory.

Required before `--stage-prepare`: user reviewed output `R68_PREFLIGHT=PASS` and availability of disk. The working copy is on the same filesystem as MariaDB data directory, so monitor IO; original R67 backup should also be copied to separate storage for disaster recovery. Neither R67 nor R68 proves that the backup is restorable: a separate isolated MariaDB server startup/read-only data verification (R69) is mandatory before production DDL.

MariaDB official documentation: `--prepare` modifies the target backup and moves checkpoint backup_type to `log-applied`. The physical backup requires `--prepare` before `--copy-back`. `--no-defaults` must appear as first option.
