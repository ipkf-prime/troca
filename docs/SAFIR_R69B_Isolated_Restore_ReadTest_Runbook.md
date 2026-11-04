# SAFIR R69B — guarded isolated MariaDB restored-instance read test

STATUS=SOURCE_PREPARED; NOT_RUN_ON_PRODUCTION_HOST.

Operator evidence (2026-11-04): R69 host preflight PASS; R68 prepared-copy verification PASS; prepared stage /var/backups/ipkf/safir/r68-prepare-20261104T035103Z-c7366a1c; 880 prepared files and manifest SHA256 d50ea2885ea269b74ef65e1c1d2277c6b1709939ce1ecf876f2f9f3cec8e9539; MariaDB 11.8 server and client; mysql OS user; AppArmor loaded MariaDB profile: none; >60GB disk available; no isolated instance started yet.

Pinned source (published on GitHub, not executed against the host):
- Path: tools/safir/SAFIR_S36_R69B_Isolated_ReadTest_Guarded.py
- Commit: c7a7b119c03d7f153bbcb018c6f800667d6f77cf
- Source SHA256: 996c402deb44a9d9fe2d65da3c3d094ddebb48889515f9e68512a19536f04d2a
- Git blob SHA1: 228a6f7e52369a37e25a10c50385b8704933ad20

Two modes:
1. --check: strictly read-only, re-runs pinned R69 host gate, checks prepared-manifest hash, copies' projected space, MariaDB binary and AppArmor.
2. --run-isolated: explicit typed approval RESTORE TEST R69B; copies prepared R68 data into a unique private /var/tmp/safir-r69b-restore-* directory; SHA256-verifies the copy; changes ownership only there to nonroot mysql; launches a new mariadbd with --no-defaults, independent datadir/socket/pid/tmpdir, --skip-networking, --skip-slave-start, --skip-log-bin, --event-scheduler=OFF and --read-only=ON; connects only to its unique socket; checks actual server variables, 185 core tables/141 InnoDB/44 MyISAM and representative read-only row counts; then issues SHUTDOWN to that socket (fallback terminates only launched subprocess); retains private isolated copy and private log for diagnosis.
No production service restart, no production datadir changes, no R67/R68 source edits, no provider send, no R65 schema DDL. The success flag ISOLATED_RESTORE_VERIFIED=YES means a *limited read-test of the separate MariaDB instance*, not an independently proven disaster-recovery RTO/RPO or external-storage backup.

SECURITY/OPS:
- Contains production-derived sensitive data in private /var/tmp, so retain restricted ownership and perform later controlled cleanup. Do not print DB contents or backup private log in chats.
- The test can add disk IO while running on the same physical host; operator must choose an approved low-traffic window before --run-isolated.
- If startup fails, do not blindly retry; preserve stage logs, collect sanitized findings and review confinement, MariaDB startup configuration, memory and exit status.
- Stop and review if the isolated daemon cannot be stopped; never change the primary mariadb.service in response.
- Keep all SAFIR bulk and pilot send flags OFF until durable queue, migration, worker and rate limiting tests pass.
