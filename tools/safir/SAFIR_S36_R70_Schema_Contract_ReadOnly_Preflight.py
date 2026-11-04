#!/usr/bin/env python3
"""SAFIR R70: strict READ-ONLY review of repaired 5-table SAFIR queue DDL.

--check: checks pinned SQL, R69B restore readiness, exact table names,
composite same-campaign foreign keys, constraints and InnoDB-only DDL.
Does NOT run SQL, start a server, copy a backup or send provider messages.
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
R69B = BASE / 'SAFIR_S36_R69B_Isolated_ReadTest_Guarded.py'
R69B_SHA = '996c402deb44a9d9fe2d65da3c3d094ddebb48889515f9e68512a19536f04d2a'
SQL = BASE / 'SAFIR_R70_Isolated_CompositeFK_Queue_Design_ONLY.sql'
SQL_SHA = 'af520182cac02cb6fbf24b303667583170def9f01113082d07accdfe0875dc04'
TABLES = (
    'safir_campaigns',
    'safir_campaign_import_batches',
    'safir_campaign_recipients',
    'safir_campaign_send_attempts',
    'safir_suppressed_phones',
)
EXPECTED_FOREIGN_KEYS = (
    'safir_batch_campaign_fk',
    'safir_recipient_campaign_fk',
    'safir_recipient_batch_fk',
    'safir_attempt_campaign_fk',
    'safir_attempt_recipient_fk',
)
EXPECTED_CHECKS = (
    'safir_campaign_template_json_chk',
    'safir_campaign_state_chk',
    'safir_campaign_interval_chk',
    'safir_batch_state_chk',
    'safir_recipient_fields_json_chk',
    'safir_recipient_phone_chk',
    'safir_recipient_state_chk',
    'safir_attempt_state_chk',
    'safir_suppressed_phone_chk',
)


def fail(reason: str) -> None:
    print('R70=FAIL')
    print('REASON=' + reason)
    print('SQL_EXECUTED=NO')
    print('PRODUCTION_DATABASE_MUTATION=NO')
    print('PROVIDER_SEND=NO')
    raise SystemExit(1)


def sha(path: Path) -> str:
    h = hashlib.sha256()
    with path.open('rb') as stream:
        for part in iter(lambda: stream.read(1048576), b''):
            h.update(part)
    return h.hexdigest()


def pinned(path: Path, expected: str, title: str) -> None:
    if path.is_symlink() or not path.is_file():
        fail(title + '_MISSING_OR_SYMLINK')
    if sha(path) != expected:
        fail(title + '_SHA256_MISMATCH')


def probe_existing_r69b() -> None:
    pinned(R69B, R69B_SHA, 'R69B_SCRIPT')
    try:
        proc = subprocess.run(
            [sys.executable, str(R69B), '--check'],
            stdin=subprocess.DEVNULL, stdout=subprocess.PIPE,
            stderr=subprocess.DEVNULL, text=True, check=False, timeout=300
        )
    except (OSError, subprocess.TimeoutExpired):
        fail('R69B_PREFLIGHT_CANNOT_RUN')
    if proc.returncode or 'R69B_PREFLIGHT=PASS' not in proc.stdout or 'R69B=PASS' not in proc.stdout:
        fail('R69B_PREFLIGHT_FAILED')
    if 'ORIGINAL_BACKUPS_MODIFICATION=NO' not in proc.stdout:
        fail('R69B_BACKUP_MUTATION_GUARD_MISSING')
    if 'PRODUCTION_DATABASE_MUTATION=NO' not in proc.stdout:
        fail('R69B_PRODUCTION_MUTATION_GUARD_MISSING')
    if 'PROVIDER_SEND=NO' not in proc.stdout:
        fail('R69B_SEND_GUARD_MISSING')


def validate_sql_contract() -> None:
    pinned(SQL, SQL_SHA, 'R70_SQL')
    source = SQL.read_text(encoding='utf-8')
    if not source.endswith(';\n'):
        fail('R70_SQL_ENDS_UNEXPECTEDLY')
    cleaned = re.sub(r'(?m)^\s*--[^\n]*(?:\n|$)', '', source).strip()
    statements = [x.strip() for x in cleaned.split(';') if x.strip()]
    if len(statements) != 5:
        fail('EXPECTED_EXACTLY_FIVE_STATEMENTS')
    actual = []
    for command in statements:
        head = re.match(r'^CREATE\s+TABLE\s+([a-z_]+)\s*\(', command, re.I)
        if not head:
            fail('DDL_NOT_EXCLUSIVE_CREATE_TABLE')
        actual.append(head.group(1).lower())
        if not re.search(r'\)\s+ENGINE=InnoDB\s+DEFAULT\s+CHARSET=utf8mb4\s+COLLATE=utf8mb4_unicode_ci\s*$', command, re.I):
            fail('NON_INNODB_OR_COLLATION_DRIFT')
        if re.search(r'\b(?:CREATE\s+TRIGGER|CREATE\s+EVENT|SELECT\s+INTO\s+OUTFILE|LOAD\s+DATA)\b', command, re.I):
            fail('DDL_CONTAINS_UNEXPECTED_OPERATION')
    if tuple(actual) != TABLES:
        fail('R70_TABLE_CREATION_ORDER_CHANGED')
    fks = re.findall(r'\bCONSTRAINT\s+(\w+)\s+FOREIGN\s+KEY\b', cleaned, re.I)
    checks = re.findall(r'\bCONSTRAINT\s+(\w+)\s+CHECK\b', cleaned, re.I)
    if set(fks) != set(EXPECTED_FOREIGN_KEYS) or len(fks) != 5:
        fail('FIVE_FOREIGN_KEY_CONTRACT_MISMATCH')
    if set(checks) != set(EXPECTED_CHECKS) or len(checks) != 9:
        fail('NINE_CHECK_CONSTRAINTS_MISMATCH')
    required = (
        'UNIQUE KEY safir_batch_campaign_id_unique (campaign_id, id)',
        'UNIQUE KEY safir_recipient_campaign_id_unique (campaign_id, id)',
        'FOREIGN KEY (campaign_id, import_batch_id)',
        'REFERENCES safir_campaign_import_batches (campaign_id, id)',
        'FOREIGN KEY (campaign_id, recipient_id)',
        'REFERENCES safir_campaign_recipients (campaign_id, id)',
        'UNIQUE KEY safir_recipient_campaign_phone_unique (campaign_id, phone_normalized)',
        'UNIQUE KEY safir_attempt_request_unique (request_id)',
        'UNIQUE KEY safir_suppressed_phone_unique (phone_normalized)',
    )
    if any(source.count(fragment) != 1 for fragment in required):
        fail('COMPOSITE_FK_OR_UNIQUENESS_CONTRACT_MISMATCH')
    if 'FOREIGN KEY (import_batch_id)' in source or 'FOREIGN KEY (recipient_id)' in source:
        fail('WEAK_CROSS_CAMPAIGN_FOREIGN_KEY_PRESENT')
    print('SQL_SHA256_PIN=PASS')
    print('SQL_STATEMENTS=5_CREATE_TABLE_ONLY')
    print('NEW_INNODB_TABLES=5')
    print('FOREIGN_KEYS=5')
    print('CHECK_CONSTRAINTS=9')
    print('CROSS_CAMPAIGN_BATCH_FK_PROTECTION=PASS')
    print('CROSS_CAMPAIGN_ATTEMPT_FK_PROTECTION=PASS')
    print('R65_ORIGINAL_SQL_NOT_EXECUTED=YES')
    print('R70_SQL_NOT_EXECUTED=YES')


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--check', action='store_true', required=True)
    parser.parse_args()
    if os.geteuid() != 0:
        fail('ROOT_REQUIRED')
    print('=== SAFIR R70 FIVE TABLE SCHEMA CONTRACT READ-ONLY CHECK ===')
    print('MODE=STRICT_READ_ONLY')
    probe_existing_r69b()
    print('R69B_PREFLIGHT=PASS')
    validate_sql_contract()
    print('PRODUCTION_SCHEMA_MUTATION=NO')
    print('ISOLATED_SCHEMA_MUTATION=NO')
    print('BACKUP_MODIFICATION=NO')
    print('PROVIDER_SEND=NO')
    print('R70_PREFLIGHT=PASS')
    print('R70=PASS')
    print('NEXT=GUARDED_ISOLATED_DDL_REHEARSAL_ONLY')


if __name__ == '__main__':
    main()
