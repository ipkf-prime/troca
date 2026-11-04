#!/usr/bin/env python3
"""SAFIR R73C2: strictly read-only pinned file receipt schema contract check.

No DDL execution, no database writes, no MariaDB start, no network provider.
Next separate gate is a guarded disposable-instance MariaDB DDL rehearsal.
"""
from __future__ import annotations
import argparse
import hashlib
import os
from pathlib import Path
import re
import subprocess
import sys

BASE = Path('/home/troca')
PREVIOUS = BASE / 'SAFIR_S36_R72C_RealPDO_Isolated_Rehearsal_Guarded.py'
PREVIOUS_SHA256 = '434113b07c84204cb164cbf6f9d9720f91a0ae2e8e72f217936cbeb4b227c83d'
STAGE = BASE / 'BaleSafirDurableCsvPrivateStage.php'
STAGE_SHA256 = '7db752234e91879b52fbc742b4904a39d2b6a1a49bec92a3717c3c4c2962badf'
STAGE_TEST = BASE / 'SAFIR_R73C1_Private_Staging_StandaloneTests.php'
STAGE_TEST_SHA256 = 'cd7797995690ea4a424df2fa5a1d85f043cb92dc6b4b4017234797239b864c2f'
DESIGN = BASE / 'SAFIR_R73C2_File_Receipt_Link_MariaDB11_Design_ONLY.sql'
DESIGN_SHA256 = 'c644ce02b2356e810c4cd099a25f1fec85ed96a6a0897782ad9ed785984e8676'

EXPECTED_CHECKS = frozenset((
    'safir_file_stage_id_chk',
    'safir_file_state_chk',
    'safir_file_size_chk',
    'safir_file_preliminary_counts_chk',
    'safir_file_progress_chk',
    'safir_file_completed_chk',
    'safir_file_failed_chk',
    'safir_batch_file_pair_chk',
))
EXPECTED_FKS = frozenset((
    'safir_file_campaign_fk',
    'safir_batch_same_campaign_file_fk',
))


def fail(reason: str) -> None:
    print('R73C2=FAIL')
    print('REASON=' + reason)
    print('PRODUCTION_DATABASE_MUTATION=NO')
    print('ISOLATED_DATABASE_MUTATION=NO')
    print('PROVIDER_SEND=NO')
    raise SystemExit(1)


def digest(path: Path) -> str:
    h = hashlib.sha256()
    with path.open('rb') as handle:
        for chunk in iter(lambda: handle.read(1048576), b''):
            h.update(chunk)
    return h.hexdigest()


def require_pin(path: Path, expected: str, label: str) -> None:
    if path.is_symlink() or not path.is_file() or digest(path) != expected:
        fail('PIN_MISMATCH_' + label)


def inherited_restore_and_ddl_gates() -> None:
    require_pin(PREVIOUS, PREVIOUS_SHA256, 'R72C')
    # Previous --check is explicitly read-only; it verifies R67/R68 source
    # manifests and R70 schema contract, with all production controls disabled.
    try:
        proc = subprocess.run(
            [sys.executable, str(PREVIOUS), '--check'],
            stdin=subprocess.DEVNULL,
            stdout=subprocess.PIPE,
            stderr=subprocess.DEVNULL,
            text=True,
            timeout=330,
            check=False,
        )
    except (OSError, subprocess.TimeoutExpired):
        fail('PREVIOUS_PREFLIGHT_CANNOT_RUN')
    required = ('R72C_PREFLIGHT=PASS', 'R72C=PASS',
                'R68_PREPARED_880_FILES_SHA256=PASS',
                'R70_CONTRACT=PASS', 'PRODUCTION_SCHEMA_MUTATION=NO',
                'PROVIDER_SEND=NO')
    if proc.returncode != 0 or not all(value in proc.stdout for value in required):
        fail('PREVIOUS_PREFLIGHT_RECHECK_FAILED')
    print('R72C_INHERITED_ISOLATION_PREFLIGHT=PASS')


def contract() -> None:
    require_pin(STAGE, STAGE_SHA256, 'R73C1_STAGE')
    require_pin(STAGE_TEST, STAGE_TEST_SHA256, 'R73C1_TEST')
    require_pin(DESIGN, DESIGN_SHA256, 'R73C2_SQL')
    raw = DESIGN.read_text(encoding='utf-8')
    clean = re.sub(r'(?m)^\s*--[^\n]*(?:\n|$)', '', raw).strip()
    statements = [s.strip() for s in clean.split(';') if s.strip()]
    if len(statements) != 2:
        fail('EXACTLY_TWO_DESIGN_STATEMENTS_REQUIRED')
    create, alter = statements
    if not re.match(r'^CREATE\s+TABLE\s+safir_campaign_import_files\s*\(', create, re.I):
        fail('FILE_RECEIPT_TABLE_CONTRACT_INVALID')
    if not re.match(r'^ALTER\s+TABLE\s+safir_campaign_import_batches\s+', alter, re.I):
        fail('BATCH_LINK_ALTER_CONTRACT_INVALID')
    if not re.search(
        r'\)\s+ENGINE\s*=\s*InnoDB\s+DEFAULT\s+CHARSET\s*=\s*utf8mb4\s+COLLATE\s*=\s*utf8mb4_unicode_ci\s*$',
        create, re.I
    ):
        fail('NEW_TABLE_ENGINE_OR_COLLATION_CHANGED')
    if re.search(r'\b(?:DROP\s+TABLE|TRUNCATE|DELETE\s+FROM|INSERT\s+INTO|'
                 r'UPDATE\s+\w+\s+SET|CREATE\s+(?:TRIGGER|EVENT|PROCEDURE)|'
                 r'GRANT|REVOKE|RENAME\s+TABLE)\b', clean, re.I):
        fail('UNEXPECTED_OPERATION_IN_DESIGN')
    fks = re.findall(r'\bCONSTRAINT\s+(\w+)\s+FOREIGN\s+KEY\b', clean, re.I)
    checks = re.findall(r'\bCONSTRAINT\s+(\w+)\s+CHECK\s*\(', clean, re.I)
    if len(fks) != 2 or set(fks) != EXPECTED_FKS:
        fail('FILE_LEVEL_FOREIGN_KEY_SET_MISMATCH')
    if len(checks) != 8 or set(checks) != EXPECTED_CHECKS:
        fail('FILE_LEVEL_CHECK_CONSTRAINT_SET_MISMATCH')
    invariants = (
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
    for invariant in invariants:
        if clean.count(invariant) != 1:
            fail('SCHEMA_INVARIANT_MISSING_OR_REPEATED')
    print('R73C1_STAGE_AND_TEST_SOURCE_PINS=PASS')
    print('R73C2_DESIGN_SQL_SHA256=PASS')
    print('DESIGN_ONLY_SQL_STATEMENTS=2')
    print('NEW_RECEIPT_INNODB_TABLE=1')
    print('ADDITIVE_BATCH_LINK_COLUMNS=2')
    print('SAME_CAMPAIGN_FILE_BATCH_FK=PASS')
    print('FILE_AND_BATCH_FOREIGN_KEYS=2')
    print('FILE_AND_BATCH_CHECKS=8')
    print('LEGACY_BATCH_COLUMNS_NULLABLE=PASS')
    print('COMPLETE_FILE_COUNT_CONTRACT=PASS')
    print('REVIEW_GATE=APPLICATION_APPROVAL_CHECK_STILL_REQUIRED')


def main() -> None:
    args = argparse.ArgumentParser(description=__doc__)
    args.add_argument('--check', action='store_true', required=True)
    args.parse_args()
    if os.geteuid() != 0:
        fail('ROOT_REQUIRED')
    print('=== SAFIR R73C2 FILE RECEIPT SCHEMA CONTRACT ===')
    print('MODE=STRICT_READ_ONLY')
    inherited_restore_and_ddl_gates()
    contract()
    print('SQL_EXECUTED=NO')
    print('PRODUCTION_DATABASE_MUTATION=NO')
    print('ISOLATED_DATABASE_MUTATION=NO')
    print('PROVIDER_SEND=NO')
    print('R73C2_PREFLIGHT=PASS')
    print('R73C2=PASS')
    print('NEXT=GUARDED_ISOLATED_R73C2_DDL_REHEARSAL_ONLY')


if __name__ == '__main__':
    main()
