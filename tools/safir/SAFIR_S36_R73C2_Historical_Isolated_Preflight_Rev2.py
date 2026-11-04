#!/usr/bin/env python3
"""SAFIR R73C2 REV2: historical-snapshot-anchored read-only schema preflight.

Purpose: allow **independent isolated** R73C2 rehearsal planning after a
documented +1 table live drift. Does NOT reinterpret/change historical R66A,
R67, R68 or the existing frozen R72C verifier. NO DDL, database writes, copy,
daemon start, provider call, deployment, worker or production approval.

Prerequisites: R66B independent R67+R68 full manifest verifier succeeds, original
R70 five-table design+contract remain byte-for-byte pinned, R73C2 design remains
pinned, operator reviews new live table provenance outside this script.
"""
from __future__ import annotations
import argparse
import hashlib
import importlib.util
import os
from pathlib import Path
import pwd
import re
import shutil
import stat
import subprocess
import sys

BASE = Path('/home/troca')
R66B = BASE / 'SAFIR_S36_R66B_Historical_Backup_SchemaDrift_ReadOnly_Attestation.py'
R66B_SHA256 = '8903d4e773184226e8384f1a43a9ee02d697e5a4a89065f6ce1f62430c2e9714'
R70_CHECK = BASE / 'SAFIR_S36_R70_Schema_Contract_ReadOnly_Preflight.py'
R70_CHECK_SHA256 = 'e63943d7c0fbe61017d12789ae7ae607ff678c24f65b3e1f4283f40be2e19f37'
R70_SQL = BASE / 'SAFIR_R70_Isolated_CompositeFK_Queue_Design_ONLY.sql'
R70_SQL_SHA256 = 'af520182cac02cb6fbf24b303667583170def9f01113082d07accdfe0875dc04'
R73C2_SQL = BASE / 'SAFIR_R73C2_File_Receipt_Link_MariaDB11_Design_ONLY.sql'
R73C2_SQL_SHA256 = 'c644ce02b2356e810c4cd099a25f1fec85ed96a6a0897782ad9ed785984e8676'
R72C_GUARD = BASE / 'SAFIR_S36_R72C_RealPDO_Isolated_Rehearsal_Guarded.py'
R72C_GUARD_SHA256 = '434113b07c84204cb164cbf6f9d9720f91a0ae2e8e72f217936cbeb4b227c83d'
R72A_POLICY = BASE / 'BaleSafirDurableCampaignPolicy.php'
R72A_POLICY_SHA256 = '2262354eecf1eda38537dc9bf68f26f534fb82fc4f8e6a42de197b76ee813edc'
R72B_REPO = BASE / 'SAFIR_R72B_DurableCampaignRepository.php'
R72B_REPO_SHA256 = '0cbf4d654c14576dc837c102ce176acce8fe45eaebfda49b862ed60f8e68a4b9'
R72C_PDO = BASE / 'SAFIR_R72C_Isolated_RealPDO_Integration.php'
R72C_PDO_SHA256 = 'f6c1debf23cdfb58d0e41a135d57c70a045c5e2bc704015c0c5975ade272a1d3'
R73C1_STAGE = BASE / 'BaleSafirDurableCsvPrivateStage.php'
R73C1_STAGE_SHA256 = '7db752234e91879b52fbc742b4904a39d2b6a1a49bec92a3717c3c4c2962badf'
R73C1_TEST = BASE / 'SAFIR_R73C1_Private_Staging_StandaloneTests.php'
R73C1_TEST_SHA256 = 'cd7797995690ea4a424df2fa5a1d85f043cb92dc6b4b4017234797239b864c2f'
R68 = Path('/var/backups/ipkf/safir/r68-prepare-20261104T035103Z-c7366a1c')
EXPECTED_CHECKS = frozenset((
    'safir_file_stage_id_chk','safir_file_state_chk','safir_file_size_chk',
    'safir_file_preliminary_counts_chk','safir_file_progress_chk',
    'safir_file_completed_chk','safir_file_failed_chk',
    'safir_batch_file_pair_chk',
))
EXPECTED_FKS = frozenset((
    'safir_file_campaign_fk','safir_batch_same_campaign_file_fk',
))


def fail(reason: str) -> None:
    print('R73C2_REV2_PREFLIGHT=FAIL')
    print('REASON=' + reason)
    print('SQL_EXECUTED=NO')
    print('BACKUP_MUTATION=NO')
    print('PRODUCTION_DATABASE_MUTATION=NO')
    print('ISOLATED_DATABASE_MUTATION=NO')
    print('PROVIDER_SEND=NO')
    raise SystemExit(1)


def sha256(path: Path) -> str:
    h = hashlib.sha256()
    with path.open('rb') as src:
        for block in iter(lambda: src.read(1048576), b''):
            h.update(block)
    return h.hexdigest()


def pin(path: Path, expected: str, name: str) -> None:
    if path.is_symlink() or not path.is_file() or sha256(path) != expected:
        fail('PIN_MISMATCH_' + name)


def imported_pinned_module(path: Path, name: str):
    # Executable import is permitted only after the source sha256 pin is checked.
    spec = importlib.util.spec_from_file_location(name, str(path))
    if spec is None or spec.loader is None:
        fail('PINNED_STATIC_CONTRACT_MODULE_UNAVAILABLE')
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def historical_attestation() -> None:
    pin(R66B, R66B_SHA256, 'R66B_HISTORICAL')
    try:
        result = subprocess.run(
            [sys.executable, str(R66B), '--check'], stdin=subprocess.DEVNULL,
            stdout=subprocess.PIPE, stderr=subprocess.DEVNULL, text=True,
            timeout=330, check=False,
            env={'HOME':'/root','PATH':'/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
                 'LANG':'C','MYSQL_HISTFILE':'/dev/null'}
        )
    except (OSError, subprocess.TimeoutExpired):
        fail('R66B_HISTORICAL_ATTESTATION_UNAVAILABLE')
    required = frozenset((
        'R66B_HISTORICAL_ATTESTATION=PASS',
        'R67_PINNED_MANIFEST_SHA256=PASS',
        'R67_VERIFIED_SOURCE_FILES=879',
        'R68_PINNED_MANIFEST_SHA256=PASS',
        'R68_VERIFIED_PREPARED_FILES=880',
        'POST_BACKUP_TABLE_ABSENT_FROM_R67_R68=PASS',
        'LIVE_SCHEMA_SCOPED_READONLY=PASS',
        'DRIFT_DELTA_INNODB=+1',
        'DRIFT_SCOPE_MANUALLY_APPROVED=NO',
        'BACKUP_MUTATION=NO',
        'DATABASE_MUTATION=NO',
        'SCHEMA_MUTATION=NO',
        'PROVIDER_SEND=NO',
        'R73C2_PRODUCTION_MIGRATION_APPROVED=NO',
    ))
    found = set(result.stdout.splitlines())
    if result.returncode != 0 or not required.issubset(found):
        print('R66B_CHILD_EXIT_CODE=' + str(result.returncode))
        for ln in result.stdout.splitlines():
            if re.fullmatch(r'(?:R66B_ATTESTATION=FAIL|REASON=[A-Z0-9_]+)', ln):
                print('R66B_CHILD_' + ln)
        fail('R66B_HISTORICAL_RECHECK_FAILED')
    print('R66B_PINNED_FULL_MANIFEST_RECHECK=PASS')
    print('HISTORICAL_R67_879_R68_880_SHA256=PASS')
    print('LIVE_SCHEMA_DRIFT_RECORDED_NOT_APPROVED=PASS')


def original_r70_schema_contract() -> None:
    pin(R70_SQL, R70_SQL_SHA256, 'R70_DESIGN')
    pin(R70_CHECK, R70_CHECK_SHA256, 'R70_CONTRACT_SOURCE')
    r70 = imported_pinned_module(R70_CHECK, 'safir_r70_pinned_static_contract')
    # Invoke the already-reviewed ONLY-static R70 contract; intentionally
    # do not call R70.main() or probe_existing_r69b(): the latter uses R67
    # LIVE schema freeze, which is invalid for a historical snapshot rehearsal.
    if not callable(getattr(r70, 'validate_sql_contract', None)):
        fail('R70_CONTRACT_FUNCTION_MISSING')
    r70.validate_sql_contract()
    print('R70_STATIC_FIVE_TABLE_DDL_CONTRACT=PASS')
    print('R70_ORIGINAL_FIVE_FK_NINE_CHECKS=PASS')


def r73c2_schema_contract() -> None:
    pin(R73C2_SQL, R73C2_SQL_SHA256, 'R73C2_DESIGN')
    pin(R72C_GUARD, R72C_GUARD_SHA256, 'R72C_PREVIOUS_GUARD_SOURCE')
    for path, expected, name in (
        (R72A_POLICY, R72A_POLICY_SHA256, 'R72A_POLICY'),
        (R72B_REPO, R72B_REPO_SHA256, 'R72B_REPOSITORY'),
        (R72C_PDO, R72C_PDO_SHA256, 'R72C_REAL_PDO'),
        (R73C1_STAGE, R73C1_STAGE_SHA256, 'R73C1_PRIVATE_STAGE'),
        (R73C1_TEST, R73C1_TEST_SHA256, 'R73C1_PRIVATE_STAGE_TEST'),
    ):
        pin(path, expected, name)
    raw = R73C2_SQL.read_text(encoding='utf-8')
    clean = re.sub(r'(?m)^\s*--[^\n]*(?:\n|$)', '', raw).strip()
    statements = [x.strip() for x in clean.split(';') if x.strip()]
    if len(statements) != 2:
        fail('R73C2_EXACTLY_TWO_STATEMENTS_REQUIRED')
    create, alter = statements
    if not re.match(r'^CREATE\s+TABLE\s+safir_campaign_import_files\s*\(', create, re.I):
        fail('R73C2_CREATE_RECEIPT_TABLE_INVALID')
    if not re.match(r'^ALTER\s+TABLE\s+safir_campaign_import_batches\s+', alter, re.I):
        fail('R73C2_BATCH_LINK_ALTER_INVALID')
    if not re.search(
        r'\)\s+ENGINE\s*=\s*InnoDB\s+DEFAULT\s+CHARSET\s*=\s*utf8mb4'
        r'\s+COLLATE\s*=\s*utf8mb4_unicode_ci\s*$', create, re.I
    ):
        fail('R73C2_TABLE_ENGINE_CHANGED')
    if re.search(
        r'\b(?:DROP\s+TABLE|TRUNCATE|DELETE\s+FROM|INSERT\s+INTO|'
        r'UPDATE\s+\w+\s+SET|CREATE\s+(?:TRIGGER|EVENT|PROCEDURE)|'
        r'GRANT|REVOKE|RENAME\s+TABLE)\b', clean, re.I
    ):
        fail('R73C2_UNEXPECTED_SQL_OPERATION')
    fks = re.findall(r'\bCONSTRAINT\s+(\w+)\s+FOREIGN\s+KEY\b', clean, re.I)
    checks = re.findall(r'\bCONSTRAINT\s+(\w+)\s+CHECK\s*\(', clean, re.I)
    if len(fks) != 2 or set(fks) != EXPECTED_FKS:
        fail('R73C2_FK_SET_CHANGED')
    if len(checks) != 8 or set(checks) != EXPECTED_CHECKS:
        fail('R73C2_CHECK_SET_CHANGED')
    markers = (
        'UNIQUE KEY safir_file_stage_unique (stage_id)',
        'UNIQUE KEY safir_file_campaign_sha_unique (campaign_id, source_sha256)',
        'UNIQUE KEY safir_file_campaign_id_unique (campaign_id, id)',
        'ADD COLUMN import_file_id BIGINT UNSIGNED NULL',
        'ADD COLUMN chunk_index INT UNSIGNED NULL',
        'ADD UNIQUE KEY safir_batch_file_chunk_unique (import_file_id, chunk_index)',
        'FOREIGN KEY (campaign_id, import_file_id)',
        'REFERENCES safir_campaign_import_files (campaign_id, id)',
        '(import_file_id IS NULL AND chunk_index IS NULL)',
        'import_file_id IS NOT NULL AND chunk_index IS NOT NULL AND chunk_index > 0',
        'imported_chunk_count = expected_chunk_count',
        'accepted_rows + rejected_rows = source_rows',
        'preliminary_ready_rows + preliminary_skipped_rows = source_rows',
        "state_code <> 'complete'",
    )
    if any(clean.count(marker) != 1 for marker in markers):
        fail('R73C2_INVARIANT_MISSING_OR_REPEATED')
    print('R73C2_RECEIPT_SQL_SHA256=PASS')
    print('R73C2_TABLE_CREATE_STATEMENTS=1')
    print('R73C2_BATCH_ALTER_STATEMENTS=1')
    print('R73C2_NEW_FOREIGN_KEYS=2')
    print('R73C2_NEW_CHECKS=8')
    print('R73C2_FILE_CHUNK_SAME_CAMPAIGN_CONTRACT=PASS')
    print('R73C2_COMPLETION_COUNTER_CONTRACT=PASS')
    print('R73C2_RUNTIME_POLICY_AND_REPOSITORY_PINS=PASS')
    print('R73C2_RUNTIME_APPROVAL_AND_WORKER=NOT_IMPLEMENTED')


def isolated_environment() -> None:
    if R68.is_symlink() or not R68.is_dir() or (R68/'work').is_symlink() \
       or not (R68/'work').is_dir():
        fail('R68_ISOLATED_SOURCE_PATH_CHANGED')
    for folder in (R68, R68/'work'):
        st = folder.stat()
        if st.st_uid != 0 or st.st_mode & 0o077:
            fail('R68_PRIVATE_SOURCE_PERMISSION_CHANGED')
    server = shutil.which('mariadbd') or '/usr/sbin/mariadbd'
    client = shutil.which('mariadb')
    if not Path(server).is_file() or not client or not Path(client).is_file():
        fail('ISOLATED_MARIADB_BINARY_UNAVAILABLE')
    try:
        result = subprocess.run(
            [server, '--version'], stdin=subprocess.DEVNULL,
            stdout=subprocess.PIPE, stderr=subprocess.DEVNULL,
            timeout=10, text=True, check=False
        )
    except (OSError, subprocess.TimeoutExpired):
        fail('MARIADB_VERSION_CHECK_UNAVAILABLE')
    if result.returncode != 0 or not re.search(r'\b11\.8\.\d+\b', result.stdout):
        fail('MARIADB_SERVER_VERSION_CHANGED')
    try:
        acct = pwd.getpwnam('mysql')
    except KeyError:
        fail('MYSQL_ACCOUNT_MISSING')
    if acct.pw_uid == 0:
        fail('MYSQL_ACCOUNT_UNSAFE')
    temp = Path('/var/tmp')
    if temp.is_symlink() or not temp.is_dir():
        fail('PRIVATE_ISOLATION_ROOT_NOT_DIRECTORY')
    st = temp.stat()
    if not (st.st_mode & stat.S_IWOTH and st.st_mode & stat.S_ISVTX):
        fail('PRIVATE_ISOLATION_ROOT_NOT_STICKY')
    used = sum(x.stat().st_size for x in (R68/'work').rglob('*') if x.is_file())
    required = max(4*1024**3, 2*used + 1024**3)
    free = shutil.disk_usage(temp).free
    if free < required:
        fail('ISOLATED_STORAGE_INSUFFICIENT')
    print('ISOLATED_MARIADB_VERSION_11_8=PASS')
    print('ISOLATED_MYSQL_NONROOT_ACCOUNT=PASS')
    print('ISOLATED_PRIVATE_TMP_GUARD=PASS')
    print('ISOLATED_R68_PREPARED_BYTES=' + str(used))
    print('ISOLATED_AVAILABLE_BYTES=' + str(free))
    print('ISOLATED_REQUIRED_BYTES=' + str(required))


def main() -> None:
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--check', action='store_true', required=True)
    p.parse_args()
    if os.geteuid() != 0:
        fail('ROOT_REQUIRED')
    os.umask(0o077)
    print('=== SAFIR R73C2 REV2 HISTORICAL ISOLATED SCHEMA PREFLIGHT ===')
    print('MODE=STRICT_READ_ONLY_HISTORICAL_ISOLATION')
    historical_attestation()
    original_r70_schema_contract()
    r73c2_schema_contract()
    isolated_environment()
    print('HISTORICAL_R66A_R67_R68_MODIFICATION=NO')
    print('LIVE_R67_FREEZE_OVERRIDE=NO')
    print('SQL_EXECUTED=NO')
    print('BACKUP_MUTATION=NO')
    print('PRODUCTION_DATABASE_MUTATION=NO')
    print('ISOLATED_DATABASE_MUTATION=NO')
    print('PROVIDER_SEND=NO')
    print('R73C2_REV2_PREFLIGHT=PASS')
    print('NEXT=SEPARATE_GUARDED_DISPOSABLE_MARIADB_DDL_REHEARSAL_ONLY')


if __name__ == '__main__':
    main()
