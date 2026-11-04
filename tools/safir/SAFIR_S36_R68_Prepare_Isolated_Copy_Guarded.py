#!/usr/bin/env python3
"""SAFIR R68: verify R67 and prepare an independent COPY; NEVER restore to live DB.

--check          strict read-only; verifies 879 original backup files.
--stage-prepare  copies original R67 backup into a new private folder and
                 runs mariadb-backup --prepare ONLY on that copy.
--verify-latest  verifies prepared-copy receipt and file digests (read only).

This tool NEVER starts/stops MariaDB, alters application env, modifies live
database, or invokes a provider API. It does NOT establish restore success.
"""
from __future__ import annotations
import argparse
from datetime import datetime, timezone
import hashlib
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import subprocess
import sys

ROOT = Path('/var/backups/ipkf/safir')
SOURCE = ROOT / 'r67-mariadb-full-20261104T024454Z-97f8a87b'
SOURCE_MANIFEST_SHA = '35277aef993dd0ad2fbe9ca825d7f151286fd1b807da832e315250bfdeaf5f5b'
SOURCE_FILE_COUNT = 879
R67_SCRIPT = Path('/home/troca/SAFIR_S36_R67_MariaDB_Mixed_Engine_Backup_Guarded_FIXED.py')
R67_SCRIPT_SHA = '145b2c6a660e472b3773aac1e7d073a500d263767d419497bdfc75fdab845f9e'
PREFIX = 'r68-prepare-'
MARKERS = ('mariadb_backup_checkpoints', 'xtrabackup_checkpoints')


def fail(reason):
    print('R68=FAIL')
    print('REASON=' + reason)
    print('PRODUCTION_DATABASE_MUTATION=NO')
    print('PROVIDER_SEND=NO')
    raise SystemExit(1)


def sha(path):
    result = hashlib.sha256()
    with path.open('rb') as stream:
        for buf in iter(lambda: stream.read(1024 * 1024), b''):
            result.update(buf)
    return result.hexdigest()


def check_private(path):
    if path.is_symlink() or not path.is_dir():
        fail('PRIVATE_DIRECTORY_MISSING_OR_SYMLINK')
    st = path.stat()
    if st.st_uid != 0 or st.st_mode & 0o077:
        fail('PRIVATE_DIRECTORY_OWNER_OR_MODE_INVALID')


def source_files():
    result = {}
    for directory, folders, files in os.walk(SOURCE, followlinks=False):
        for dirname in folders:
            if (Path(directory) / dirname).is_symlink():
                fail('SOURCE_SYMLINK_DIRECTORY')
        for name in files:
            path = Path(directory) / name
            if path.is_symlink() or not path.is_file():
                fail('SOURCE_SYMLINK_OR_SPECIAL_FILE')
            if path.parent == SOURCE and name in ('R67_MANIFEST.json', 'R67_RECEIPT.json'):
                continue
            result[path.relative_to(SOURCE).as_posix()] = path
    return result


def checkpoint(folder, required_status):
    found = [folder / n for n in MARKERS if (folder / n).is_file()]
    if len(found) != 1 or found[0].is_symlink():
        fail('CHECKPOINT_MARKER_UNIQUE_REQUIRED')
    raw = found[0].read_text(errors='replace')
    if not re.search(r'^\s*backup_type\s*=\s*' + re.escape(required_status) + r'\s*$', raw, re.M):
        fail('CHECKPOINT_STATUS_UNEXPECTED')
    return found[0]


def check_source():
    for parent in (ROOT.parent, ROOT):
        check_private(parent)
    check_private(SOURCE)
    manifest = SOURCE / 'R67_MANIFEST.json'
    receipt = SOURCE / 'R67_RECEIPT.json'
    if any(p.is_symlink() or not p.is_file() for p in (manifest, receipt)):
        fail('SOURCE_RECEIPTS_MISSING')
    if sha(manifest) != SOURCE_MANIFEST_SHA:
        fail('SOURCE_MANIFEST_PIN_MISMATCH')
    try:
        data = json.loads(manifest.read_text())
        stamp = json.loads(receipt.read_text())
    except (OSError, ValueError):
        fail('SOURCE_RECEIPTS_UNREADABLE')
    if stamp.get('manifest_sha256') != SOURCE_MANIFEST_SHA or not stamp.get('backup_complete'):
        fail('SOURCE_BACKUP_RECEIPT_INVALID')
    if data.get('kind') != 'SAFIR_R67_MARIADB_FULL_BACKUP' or len(data.get('files', [])) != SOURCE_FILE_COUNT:
        fail('SOURCE_BACKUP_RECEIPT_CONTRACT_INVALID')
    if data.get('prepared') is not False or data.get('restore_verified') is not False:
        fail('SOURCE_BACKUP_ORIGINAL_STATE_CHANGED')
    checkpoint(SOURCE, 'full-backuped')
    if not (SOURCE / 'mysql').is_dir() or not (SOURCE / 'ibdata1').is_file():
        fail('SOURCE_BACKUP_SYSTEM_FILES_MISSING')
    listed = source_files()
    expected = {}
    for item in data['files']:
        if not isinstance(item, dict):
            fail('SOURCE_MANIFEST_ITEM_INVALID')
        rel = item.get('path')
        if not isinstance(rel, str) or rel.startswith('/') or '..' in Path(rel).parts:
            fail('SOURCE_MANIFEST_RELATIVE_PATH_UNSAFE')
        if rel in expected:
            fail('SOURCE_MANIFEST_DUPLICATE_PATH')
        expected[rel] = item
    if set(listed) != set(expected):
        fail('SOURCE_FILE_SET_CHANGED')
    for rel, path in listed.items():
        item = expected[rel]
        if path.stat().st_size != item.get('bytes') or sha(path) != item.get('sha256'):
            fail('SOURCE_FILE_CONTENT_CHANGED')
    return listed, data


def validate_environment():
    if os.geteuid() != 0:
        fail('ROOT_REQUIRED')
    if R67_SCRIPT.is_symlink() or not R67_SCRIPT.is_file() or sha(R67_SCRIPT) != R67_SCRIPT_SHA:
        fail('R67_VERIFIER_NOT_PINNED')
    for option in ('--check', '--verify-latest'):
        try:
            process = subprocess.run(
                [sys.executable, str(R67_SCRIPT), option],
                stdin=subprocess.DEVNULL, stdout=subprocess.PIPE,
                stderr=subprocess.DEVNULL, timeout=100, text=True, check=False,
            )
        except (OSError, subprocess.TimeoutExpired):
            fail('R67_RECHECK_NOT_AVAILABLE')
        if process.returncode:
            fail('R67_RECHECK_FAILED_' + option.replace('-', '_'))
        if option == '--check' and 'R67_PREFLIGHT=PASS' not in process.stdout:
            fail('R67_PREFLIGHT_MISSING')
        if option == '--verify-latest':
            if 'R67_BACKUP_VERIFICATION=PASS' not in process.stdout or str(SOURCE) not in process.stdout:
                fail('R67_SOURCE_VERIFICATION_MISSING')
    binary = shutil.which('mariadb-backup') or shutil.which('mariabackup')
    if not binary:
        fail('MARIADB_BACKUP_BINARY_MISSING')
    try:
        p = subprocess.run([binary, '--version'], stdout=subprocess.PIPE,
                           stderr=subprocess.STDOUT, timeout=12, text=True, check=False)
    except (OSError, subprocess.TimeoutExpired):
        fail('BACKUP_BINARY_VERSION_ERROR')
    if p.returncode or not re.search(r'\b11\.8\.\d+\b', p.stdout):
        fail('MARIADB_BACKUP_VERSION_MISMATCH')
    return binary


def preflight():
    binpath = validate_environment()
    listed, details = check_source()
    total = sum(p.stat().st_size for p in listed.values())
    free = shutil.disk_usage(ROOT).free
    required = max(4 * 1024 ** 3, 2 * total + 1024 ** 3)
    if free < required:
        fail('R68_FREE_SPACE_INSUFFICIENT')
    print('=== SAFIR R68 ISOLATED BACKUP PREPARATION ===')
    print('SOURCE_R67_VERIFIED=PASS')
    print('SOURCE_MANIFEST_SHA256=' + SOURCE_MANIFEST_SHA)
    print('SOURCE_FILES=' + str(len(listed)))
    print('SOURCE_BYTES=' + str(total))
    print('AVAILABLE_BYTES=' + str(free))
    print('REQUIRED_BYTES=' + str(required))
    print('MARIADB_BACKUP_VERSION_11_8=PASS')
    print('TARGET_SCOPE=NEW_PRIVATE_COPY_ONLY')
    print('PRODUCTION_DATABASE_MUTATION=NO')
    print('R67_SOURCE_MODIFICATION=NO')
    print('ENV_MUTATION=NO')
    print('PROVIDER_SEND=NO')
    print('R68_PREFLIGHT=PASS')
    return binpath, listed, details


def work_files(root):
    files = {}
    for d, dirs, names in os.walk(root, followlinks=False):
        for dirname in dirs:
            if (Path(d) / dirname).is_symlink():
                fail('WORK_SYMLINK_DIR')
        for name in names:
            p = Path(d) / name
            if p.is_symlink() or not p.is_file():
                fail('WORK_SYMLINK_FILE')
            files[p.relative_to(root).as_posix()] = p
    return files


def stage_prepare():
    if not sys.stdin.isatty():
        fail('INTERACTIVE_CONFIRMATION_REQUIRED')
    binary, sources, data = preflight()
    print('WARNING=PREPARE_MODIFIES_ONLY_NEW_WORKING_COPY_NOT_ORIGINAL_R67')
    print('WARNING=PREPARE_REQUIRES_DISK_IO_ON_SAME_FILESYSTEM_AS_PRODUCTION')
    approval = input('Type exactly PREPARE R68 COPY to proceed: ').strip()
    if approval != 'PREPARE R68 COPY':
        fail('OPERATOR_CONFIRMATION_NOT_GIVEN')
    # Re-check the original manifest and master send flags before any filesystem writes.
    _, sources2, _ = preflight()
    if set(sources2) != set(sources):
        fail('SOURCE_DRIFT_AFTER_CONFIRM')
    stage = ROOT / (PREFIX + datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ') + '-' + secrets.token_hex(4))
    work = stage / 'work'
    if stage.exists() or stage.is_symlink():
        fail('STAGE_ALREADY_EXISTS')
    stage.mkdir(mode=0o700)
    os.chmod(stage, 0o700)
    print('R68_STAGE=' + str(stage))
    try:
        def without_receipts(folder, children):
            if Path(folder).resolve() == SOURCE.resolve():
                return ['R67_MANIFEST.json', 'R67_RECEIPT.json']
            return []
        shutil.copytree(SOURCE, work, ignore=without_receipts, symlinks=False)
        os.chmod(work, 0o700)
    except (OSError, shutil.Error):
        fail('R68_COPY_FAILED_PRIVATE_PARTIAL_RETAINED')
    copied = work_files(work)
    if set(copied) != set(sources):
        fail('R68_COPY_FILES_MISMATCH')
    for rel, original in sources.items():
        target = copied[rel]
        if original.stat().st_size != target.stat().st_size or sha(original) != sha(target):
            fail('R68_COPY_SHA256_MISMATCH')
    print('R68_INDEPENDENT_COPY_VERIFIED=PASS')
    logfile = stage / 'R68_PREPARE.log'
    command = [binary, '--no-defaults', '--prepare', '--target-dir=' + str(work), '--use-memory=256M']
    print('PREPARE_TARGET=' + str(work))
    print('PREPARE_ORIGINAL_R67=NO')
    try:
        with logfile.open('xb') as log:
            os.chmod(logfile, 0o600)
            p = subprocess.run(command, stdin=subprocess.DEVNULL, stdout=log,
                               stderr=subprocess.STDOUT, timeout=1800, check=False)
    except (OSError, subprocess.TimeoutExpired):
        fail('R68_PREPARE_START_OR_TIMEOUT_PRIVATE_COPY_RETAINED')
    print('MARIADB_PREPARE_RETURN_CODE=' + str(p.returncode))
    if p.returncode:
        fail('R68_PREPARE_FAILED_PRIVATE_COPY_RETAINED')
    chk = checkpoint(work, 'log-applied')
    if (work / 'innodb_corrupted_pages').exists():
        fail('R68_CORRUPTED_PAGES_MARKER_PRESENT')
    records = []
    for rel, item in sorted(work_files(work).items()):
        records.append({'path': rel, 'bytes': item.stat().st_size, 'sha256': sha(item)})
    manifest = stage / 'R68_PREPARED_MANIFEST.json'
    with manifest.open('x', encoding='utf-8') as output:
        os.chmod(manifest, 0o600)
        json.dump({'source_manifest_sha256': SOURCE_MANIFEST_SHA,
                   'source_backup': SOURCE.name,
                   'prepared_checkpoint': chk.name,
                   'files': records}, output, indent=2)
        output.flush(); os.fsync(output.fileno())
    receipt = stage / 'R68_PREPARED_RECEIPT.json'
    with receipt.open('x', encoding='utf-8') as output:
        os.chmod(receipt, 0o600)
        json.dump({'manifest_sha256': sha(manifest), 'prepared': True,
                   'isolated_restore_verified': False}, output, indent=2)
        output.flush(); os.fsync(output.fileno())
    print('R68_PREPARED_COPY=PASS')
    print('R68_PREPARED_FILES=' + str(len(records)))
    print('R68_PREPARED_MANIFEST_SHA256=' + sha(manifest))
    print('R67_SOURCE_UNCHANGED=YES')
    print('ISOLATED_RESTORE_VERIFIED=NO')
    print('R68=PASS')
    print('NEXT=R69_SEPARATE_DATABASE_INSTANCE_RESTORE_TEST')


def verify_latest():
    validate_environment()
    check_source()
    if not ROOT.is_dir():
        fail('R68_ROOT_MISSING')
    candidates = sorted([p for p in ROOT.iterdir() if p.name.startswith(PREFIX) and p.is_dir()
                         and (p / 'R68_PREPARED_RECEIPT.json').is_file()],
                        key=lambda p: p.name, reverse=True)
    if not candidates:
        fail('NO_COMPLETED_R68_PREPARE')
    stage = candidates[0]
    check_private(stage)
    work = stage / 'work'
    check_private(work)
    chk = checkpoint(work, 'log-applied')
    if (work / 'innodb_corrupted_pages').exists():
        fail('R68_CORRUPTED_PAGES_MARKER_PRESENT')
    try:
        receipt = json.loads((stage / 'R68_PREPARED_RECEIPT.json').read_text())
        manifest_path = stage / 'R68_PREPARED_MANIFEST.json'
        manifest = json.loads(manifest_path.read_text())
    except (OSError, ValueError):
        fail('R68_PREPARED_RECEIPT_INVALID')
    if not receipt.get('prepared') or receipt.get('isolated_restore_verified') is not False:
        fail('R68_RECEIPT_FLAGS_UNEXPECTED')
    if sha(manifest_path) != receipt.get('manifest_sha256'):
        fail('R68_PREPARED_RECEIPT_HASH_MISMATCH')
    if manifest.get('source_manifest_sha256') != SOURCE_MANIFEST_SHA:
        fail('R68_SOURCE_FINGERPRINT_MISMATCH')
    if manifest.get('prepared_checkpoint') != chk.name:
        fail('R68_CHECKPOINT_FILE_CHANGED')
    present = work_files(work)
    expected = {}
    for row in manifest.get('files', []):
        rel = row.get('path')
        if not isinstance(rel, str) or rel.startswith('/') or '..' in Path(rel).parts or rel in expected:
            fail('R68_PREPARED_MANIFEST_PATH_INVALID')
        expected[rel] = row
    if set(present) != set(expected):
        fail('R68_PREPARED_FILE_SET_CHANGED')
    for rel, item in present.items():
        if item.stat().st_size != expected[rel].get('bytes') or sha(item) != expected[rel].get('sha256'):
            fail('R68_PREPARED_FILE_CONTENT_CHANGED')
    print('R68_PREPARED_VERIFICATION=PASS')
    print('R68_STAGE=' + str(stage))
    print('R68_PREPARED_FILES=' + str(len(present)))
    print('R68_PREPARED_MANIFEST_SHA256=' + sha(manifest_path))
    print('R67_ORIGINAL_BACKUP_VERIFIED=PASS')
    print('ISOLATED_RESTORE_VERIFIED=NO')
    print('PRODUCTION_DATABASE_MUTATION=NO')
    print('PROVIDER_SEND=NO')


def main():
    parser = argparse.ArgumentParser()
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument('--check', action='store_true')
    group.add_argument('--stage-prepare', action='store_true')
    group.add_argument('--verify-latest', action='store_true')
    args = parser.parse_args()
    os.umask(0o077)
    if os.geteuid() != 0:
        fail('ROOT_REQUIRED')
    if args.check:
        preflight()
        print('R68=PASS')
        print('NEXT=STAGE_PREPARE_WITH_EXPLICIT_OPERATOR_APPROVAL')
    elif args.stage_prepare:
        stage_prepare()
    else:
        verify_latest()


if __name__ == '__main__':
    main()
