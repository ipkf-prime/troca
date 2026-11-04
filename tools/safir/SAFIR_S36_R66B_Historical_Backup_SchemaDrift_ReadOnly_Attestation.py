#!/usr/bin/env python3
"""SAFIR R66B — independent historical R67/R68 SHA evidence after later schema drift.

Read-only. Neither replaces R67's live-schema gate nor approves an R73C2
migration. Re-verifies each historical file against pinned manifests and
observes the current MariaDB schema over local Unix socket only.
No backup mutation, database mutation, HTTP, network provider or daemon start.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import stat
import subprocess
import sys

ROOT = Path('/var/backups/ipkf/safir')
R67 = ROOT / 'r67-mariadb-full-20261104T024454Z-97f8a87b'
R68 = ROOT / 'r68-prepare-20261104T035103Z-c7366a1c'
R67_MANIFEST_SHA = '35277aef993dd0ad2fbe9ca825d7f151286fd1b807da832e315250bfdeaf5f5b'
R68_MANIFEST_SHA = 'd50ea2885ea269b74ef65e1c1d2277c6b1709939ce1ecf876f2f9f3cec8e9539'
SCHEMA = 'ipkf_core_stage_c5001883'
TABLE = 'organization_data_review_index'
EXPECTED_ENGINES = (142, 44, 186)
EXPECTED_R67_FILES = 879
EXPECTED_R68_FILES = 880
EXPECTED_BACKUP_ENGINES = (141, 44, 185)


def stop(reason: str) -> None:
    print('R66B_ATTESTATION=FAIL')
    print('REASON=' + reason)
    print('BACKUP_MUTATION=NO')
    print('DATABASE_MUTATION=NO')
    print('PROVIDER_SEND=NO')
    raise SystemExit(1)


def safe_private_dir(path: Path) -> None:
    if path.is_symlink() or not path.is_dir() or path.stat().st_uid != 0:
        stop('PRIVATE_BACKUP_DIRECTORY_NOT_TRUSTED')
    if path.stat().st_mode & 0o077:
        stop('PRIVATE_BACKUP_DIRECTORY_PERMISSIONS_CHANGED')


def hash_file(path: Path) -> str:
    h = hashlib.sha256()
    with path.open('rb') as stream:
        for block in iter(lambda: stream.read(1048576), b''):
            h.update(block)
    return h.hexdigest()


def load_json_regular(path: Path, limit: int = 300000) -> dict:
    if path.is_symlink() or not path.is_file() or path.stat().st_size > limit:
        stop('HISTORICAL_RECEIPT_MISSING_OR_UNTRUSTED')
    try:
        obj = json.loads(path.read_text('utf-8'))
    except (OSError, ValueError, UnicodeError):
        stop('HISTORICAL_RECEIPT_INVALID')
    if not isinstance(obj, dict):
        stop('HISTORICAL_RECEIPT_NOT_OBJECT')
    return obj


def validate_relative(name: object) -> str:
    if not isinstance(name, str) or not name or name.startswith('/') \
       or '\\' in name or '\x00' in name or any(
           segment in ('', '.', '..') for segment in name.split('/')
       ):
        stop('MANIFEST_FILE_PATH_INVALID')
    return name


def scan(root: Path, excluded: frozenset[str] = frozenset()) -> dict[str, Path]:
    result: dict[str, Path] = {}
    for parent, dirs, files in os.walk(root, followlinks=False):
        for dirname in dirs:
            if (Path(parent) / dirname).is_symlink():
                stop('BACKUP_DIRECTORY_SYMLINK')
        for filename in files:
            path = Path(parent) / filename
            rel = path.relative_to(root).as_posix()
            if rel in excluded:
                continue
            if path.is_symlink() or not path.is_file():
                stop('BACKUP_FILE_SPECIAL_OR_SYMLINK')
            if rel in result:
                stop('BACKUP_FILE_DUPLICATE')
            result[rel] = path
    return result


def full_manifest_check(
    source: Path, manifest: dict, count: int, excluded: frozenset[str]
) -> tuple[int, set[str]]:
    entries = manifest.get('files')
    if not isinstance(entries, list) or len(entries) != count:
        stop('HISTORICAL_MANIFEST_ENTRY_COUNT_CHANGED')
    expected: dict[str, dict] = {}
    for item in entries:
        if not isinstance(item, dict):
            stop('HISTORICAL_MANIFEST_ENTRY_NOT_OBJECT')
        name = validate_relative(item.get('path'))
        if name in expected or not isinstance(item.get('bytes'), int) \
           or not isinstance(item.get('sha256'), str) \
           or not re.fullmatch(r'[a-f0-9]{64}', item['sha256']):
            stop('HISTORICAL_MANIFEST_ENTRY_INVALID')
        expected[name] = item
    actual = scan(source, excluded)
    if set(actual) != set(expected):
        stop('HISTORICAL_FILE_SET_CHANGED')
    total = 0
    for name, path in actual.items():
        entry = expected[name]
        if path.stat().st_size != entry['bytes'] \
           or hash_file(path) != entry['sha256']:
            stop('HISTORICAL_BACKUP_FILE_HASH_CHANGED')
        total += path.stat().st_size
    return total, set(actual)


def requires_absence(paths: set[str]) -> None:
    suffixes = tuple('.' + ext for ext in (
        'frm', 'ibd', 'isl', 'MYD', 'MYI', 'MAD', 'MAI', 'sdi', 'par'
    ))
    matches = [
        p for p in paths
        if p.startswith(SCHEMA + '/') and (
            p.split('/')[-1] == TABLE
            or p.split('/')[-1].startswith(TABLE + '.')
        )
    ]
    if matches:
        stop('POST_BACKUP_TABLE_PRESENT_IN_HISTORICAL_FILES')


def historical_check() -> None:
    for folder in (ROOT.parent, ROOT, R67, R68, R68 / 'work'):
        safe_private_dir(folder)

    r67_manifest_file = R67 / 'R67_MANIFEST.json'
    if hash_file(r67_manifest_file) != R67_MANIFEST_SHA:
        stop('R67_PINNED_MANIFEST_SHA_CHANGED')
    source_receipt = load_json_regular(R67 / 'R67_RECEIPT.json')
    r67_manifest = load_json_regular(r67_manifest_file, 500000)
    if source_receipt.get('manifest_sha256') != R67_MANIFEST_SHA \
       or source_receipt.get('backup_complete') is not True \
       or r67_manifest.get('kind') != 'SAFIR_R67_MARIADB_FULL_BACKUP' \
       or r67_manifest.get('prepared') is not False \
       or r67_manifest.get('restore_verified') is not False:
        stop('R67_RECEIPT_CONTRACT_CHANGED')
    r67_size, r67_paths = full_manifest_check(
        R67, r67_manifest, EXPECTED_R67_FILES,
        frozenset(('R67_MANIFEST.json', 'R67_RECEIPT.json'))
    )
    requires_absence(r67_paths)
    print('R67_PINNED_MANIFEST_SHA256=PASS')
    print('R67_ORIGINAL_RECEIPT=PASS')
    print(f'R67_VERIFIED_SOURCE_FILES={len(r67_paths)}')
    print(f'R67_VERIFIED_SOURCE_BYTES={r67_size}')

    r68_manifest_file = R68 / 'R68_PREPARED_MANIFEST.json'
    if hash_file(r68_manifest_file) != R68_MANIFEST_SHA:
        stop('R68_PINNED_MANIFEST_SHA_CHANGED')
    prepared = load_json_regular(r68_manifest_file, 500000)
    r68_receipt = load_json_regular(R68 / 'R68_PREPARED_RECEIPT.json')
    if r68_receipt.get('manifest_sha256') != R68_MANIFEST_SHA \
       or r68_receipt.get('prepared') is not True \
       or r68_receipt.get('isolated_restore_verified') is not False \
       or prepared.get('source_manifest_sha256') != R67_MANIFEST_SHA \
       or prepared.get('source_backup') != R67.name:
        stop('R68_RECEIPT_CONTRACT_CHANGED')
    chk = prepared.get('prepared_checkpoint')
    if chk not in ('mariadb_backup_checkpoints', 'xtrabackup_checkpoints'):
        stop('R68_CHECKPOINT_NAME_CHANGED')
    checkpoint = R68 / 'work' / chk
    if checkpoint.is_symlink() or not checkpoint.is_file() \
       or not re.search(
           r'^\s*backup_type\s*=\s*log-applied\s*$',
           checkpoint.read_text(errors='replace'), re.M
       ):
        stop('R68_CHECKPOINT_NOT_PREPARED')
    if (R68 / 'work' / 'innodb_corrupted_pages').exists():
        stop('R68_CORRUPTED_PAGES_MARKER')
    r68_size, r68_paths = full_manifest_check(
        R68 / 'work', prepared, EXPECTED_R68_FILES, frozenset()
    )
    requires_absence(r68_paths)
    print('R68_PINNED_MANIFEST_SHA256=PASS')
    print('R68_PREPARED_RECEIPT=PASS')
    print(f'R68_VERIFIED_PREPARED_FILES={len(r68_paths)}')
    print(f'R68_VERIFIED_PREPARED_BYTES={r68_size}')
    print('POST_BACKUP_TABLE_ABSENT_FROM_R67_R68=PASS')


def observe_current() -> None:
    client = shutil.which('mariadb')
    if not client or not Path(client).is_file():
        stop('MARIADB_READONLY_METADATA_CLI_MISSING')
    query = (
        "SELECT COALESCE(SUM(ENGINE='InnoDB'),0),"
        "COALESCE(SUM(ENGINE='MyISAM'),0),COUNT(*) "
        "FROM information_schema.TABLES "
        "WHERE TABLE_TYPE='BASE TABLE' AND TABLE_SCHEMA='" + SCHEMA + "'; "
        "SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES "
        "WHERE TABLE_TYPE='BASE TABLE' AND TABLE_SCHEMA='" + SCHEMA + "' "
        "AND TABLE_NAME='" + TABLE + "';"
    )
    try:
        r = subprocess.run(
            [client, '--no-defaults', '--protocol=socket', '--connect-timeout=3',
             '--batch', '--skip-column-names', '--execute', query],
            stdin=subprocess.DEVNULL, stdout=subprocess.PIPE,
            stderr=subprocess.DEVNULL, text=True, check=False, timeout=25,
            env={'PATH':'/usr/sbin:/usr/bin:/sbin:/bin', 'HOME':'/root',
                 'LANG':'C', 'MYSQL_HISTFILE':'/dev/null'}
        )
    except (OSError, subprocess.TimeoutExpired):
        stop('LIVE_METADATA_READONLY_UNAVAILABLE')
    rows = r.stdout.splitlines()
    if r.returncode != 0 or len(rows) != 2:
        stop('LIVE_METADATA_READONLY_FAILED')
    if tuple(int(n) for n in rows[0].split('\t')) != EXPECTED_ENGINES \
       or rows[1].split('\t') != [TABLE, 'InnoDB']:
        stop('LIVE_ENGINE_OR_NEW_TABLE_DRIFT_CHANGED')
    print('LIVE_SCHEMA_SCOPED_READONLY=PASS')
    print('R66A_EXPECTED_INNODB=141')
    print('R66A_EXPECTED_MYISAM=44')
    print('R66A_EXPECTED_TOTAL=185')
    print('LIVE_INNODB=142')
    print('LIVE_MYISAM=44')
    print('LIVE_TOTAL=186')
    print('POST_BACKUP_INNODB_TABLE_PRESENT=PASS')
    print('DRIFT_DELTA_INNODB=+1')
    print('DRIFT_SCOPE_MANUALLY_APPROVED=NO')


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--check', action='store_true', required=True)
    parser.parse_args()
    if os.geteuid() != 0:
        stop('ROOT_REQUIRED')
    os.umask(0o077)
    print('=== SAFIR R66B HISTORICAL BACKUP / POST-BACKUP SCHEMA DRIFT ===')
    print('MODE=STRICT_READ_ONLY')
    historical_check()
    observe_current()
    print('BACKUP_MUTATION=NO')
    print('DATABASE_MUTATION=NO')
    print('SCHEMA_MUTATION=NO')
    print('PROVIDER_SEND=NO')
    print('R66B_HISTORICAL_ATTESTATION=PASS')
    print('R73C2_PRODUCTION_MIGRATION_APPROVED=NO')
    print('NEXT=MANUAL_REVIEW_AND_SEPARATE_ISOLATED_SCHEMA_REHEARSAL')


if __name__ == '__main__':
    main()
