# SAFIR R66B — historical snapshot attestation vs live schema drift

Status: read-only investigation; no production migration; no approval for R73C2 writes.

## Evidence from operator, 2026-11-04
- R67 `--check` reported `R66A_ENGINE_BASELINE_CHANGED_REQUIRE_REVIEW`.
- Historical R66A counts: InnoDB=141, MyISAM=44, base tables=185.
- Scoped current schema `ipkf_core_stage_c5001883`: InnoDB=142, MyISAM=44, base tables=186.
- Live table `organization_data_review_index`: InnoDB, reported CREATE_TIME 2026-11-04 18:43:01, estimated TABLE_ROWS=11364; contains source review metadata with 12 columns.
- The operator checked and found zero files for `organization_data_review_index.*` under both the original R67 backup and prepared R68 copy.
- Original R72C isolated restore/schema/real-PDO rehearsal previously passed with 185 original tables; no production DDL/sends.

## Why the old preflights now fail
R67 `--check` is a *current live schema freeze* check, not only a historical physical backup integrity check. New legitimate application migrations can change that live freeze after a backup was made. R68 `--verify-latest` invokes R67 `--check`; R69/R69B/R70/R72C/R73C2 transitively inherit the failure.

The changed table is consistent with the observed +1 InnoDB delta, but no source-derived authorization or precise full-name inventory comparison has yet proved that it is the *only* structural difference. Metadata CREATE_TIME is contextual evidence, not an audit log or authorization receipt.

## Source-only response — R66B
`tools/safir/SAFIR_S36_R66B_Historical_Backup_SchemaDrift_ReadOnly_Attestation.py`:
- fixed R67 and R68 snapshot paths;
- independently validates exact pinned SHA256 of original and prepared manifests, receipt linkage, checkpoints and **all** 879 R67 plus 880 R68 source files against their stored SHA256 and byte counts;
- requires original root-owned private paths and no symlink files in inventory;
- verifies the post-backup table is not present as a named file in either historical snapshot;
- separately reads live metadata through a local Unix-socket MariaDB client, constrained to the known database, expected 142/44/186 count and named InnoDB table;
- **does not execute R67 `--check`**, change historical Baselines, rewrite receipts, run SQL DDL, send messages or create a database.
- A PASS attests *snapshot-file integrity under that manifest* and the *specific observed live counts*; it does NOT authorize the new table's provenance or imply the complete old/new table-name inventories are identical apart from this table.

## Next engineering gates
1. Run R66B `--check`, record hashes and summary, and validate the table's introduction with the owner of the IPKF organizational-data/MDM task.
2. Design separately pinned **historical-backup** preflight for isolated rehearsal, independent from R67's **live freeze** gate, retaining every original R67/R68 manifest/content/hash/ownership control. Keep original scripts unchanged.
3. Rehearse R73C2 CREATE/ALTER against a disposable copy of the **historical** isolated 185-table dataset only. Never apply it to live/stage DB without explicit approval.
4. Approval/queue transition remains blocked until the import-file finalization repository and whole-file terminal checks have been implemented and tested.

No production DB migration, app deploy, worker, cron or provider send follows from this evidence.
