#!/usr/bin/env python3
"""SAFIR R69: strict read-only viability check for a separate MariaDB instance.

This checks the verified R68 prepared copy, MariaDB version, OS mysql user,
space for a second isolated data copy and AppArmor constraints. It does NOT
copy data, start a daemon, allocate ports, change permissions, or touch prod.
"""
from __future__ import annotations

import hashlib
import os
from pathlib import Path
import pwd
import re
import shutil
import stat
import subprocess
import sys

R68_SCRIPT = Path('/home/troca/SAFIR_S36_R68_Prepare_Isolated_Copy_Guarded.py')
R68_SHA256 = 'af2e08d3afc1ddcc440d97df0bdf6bb611035f27da469f16602c5ded05aba64e'
R68_STAGE = Path('/var/backups/ipkf/safir/r68-prepare-20261104T035103Z-c7366a1c')
EXPECTED_R68_MANIFEST = 'd50ea2885ea269b74ef65e1c1d2277c6b1709939ce1ecf876f2f9f3cec8e9539'
DEST_BASE = Path('/var/tmp')


def fail(reason: str) -> None:
    print('R69=FAIL')
    print('REASON=' + reason)
    print('FILESYSTEM_MUTATION=NO')
    print('PRODUCTION_DATABASE_MUTATION=NO')
    print('PROVIDER_SEND=NO')
    raise SystemExit(1)


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open('rb') as source:
        for chunk in iter(lambda: source.read(1024 * 1024), b''):
            digest.update(chunk)
    return digest.hexdigest()


def private_dir(path: Path) -> None:
    if path.is_symlink() or not path.is_dir():
        fail('SOURCE_PRIVATE_DIR_MISSING')
    st = path.stat()
    if st.st_uid != 0 or st.st_mode & 0o077:
        fail('SOURCE_PRIVATE_DIR_MODE_INVALID')


def version(binary: str) -> bool:
    try:
        p = subprocess.run([binary, '--version'], capture_output=True,
                           text=True, stdin=subprocess.DEVNULL, timeout=12,
                           check=False)
    except (OSError, subprocess.TimeoutExpired):
        return False
    return p.returncode == 0 and bool(re.search(r'11\\.8\\.[0-9]+', p.stdout + p.stderr))


def apparmor_status() -> str:
    location = Path('/sys/kernel/security/apparmor/profiles')
    if location.is_file():
        try:
            profiles = location.read_text(errors='replace').splitlines()
        except OSError:
            return 'UNKNOWN'
        matched = [line for line in profiles
                   if re.search(r'(?:mariadbd|mysqld)', line, re.I)]
        if any('(enforce)' in line for line in matched):
            return 'ENFORCED_MARIADB_PROFILE'
        if any('(complain)' in line for line in matched):
            return 'COMPLAIN_MARIADB_PROFILE'
        return 'NO_MARIADB_PROFILE_IN_LOADED_LIST'
    enabled = Path('/sys/module/apparmor/parameters/enabled')
    if enabled.is_file():
        try:
            if enabled.read_text().strip().upper().startswith('N'):
                return 'DISABLED'
        except OSError:
            pass
    return 'UNKNOWN'


def main() -> None:
    if os.geteuid() != 0:
        fail('ROOT_REQUIRED')
    if R68_SCRIPT.is_symlink() or not R68_SCRIPT.is_file():
        fail('PINNED_R68_SCRIPT_NOT_FOUND')
    if sha256(R68_SCRIPT) != R68_SHA256:
        fail('R68_SCRIPT_HASH_MISMATCH')
    private_dir(R68_STAGE)
    private_dir(R68_STAGE / 'work')
    try:
        p = subprocess.run([sys.executable, str(R68_SCRIPT), '--verify-latest'],
                           stdout=subprocess.PIPE, stderr=subprocess.DEVNULL,
                           text=True, timeout=160, stdin=subprocess.DEVNULL,
                           check=False)
    except (OSError, subprocess.TimeoutExpired):
        fail('R68_VERIFY_CANNOT_RUN')
    if p.returncode != 0 or 'R68_PREPARED_VERIFICATION=PASS' not in p.stdout:
        fail('R68_PREPARED_VERIFICATION_FAILED')
    if ('R68_STAGE=' + str(R68_STAGE)) not in p.stdout:
        fail('R68_STAGE_NOT_EXACT_PIN')
    if ('R68_PREPARED_MANIFEST_SHA256=' + EXPECTED_R68_MANIFEST) not in p.stdout:
        fail('R68_PREPARED_MANIFEST_NOT_PINNED')
    if 'R67_ORIGINAL_BACKUP_VERIFIED=PASS' not in p.stdout:
        fail('R67_ORIGINAL_BACKUP_NOT_VERIFIED')

    database_server = shutil.which('mariadbd') or shutil.which('mysqld')
    if not database_server:
        for candidate in ('/usr/sbin/mariadbd', '/usr/sbin/mysqld'):
            if Path(candidate).is_file():
                database_server = candidate
                break
    if not database_server or not version(database_server):
        fail('MARIADBD_11_8_SERVER_BINARY_MISSING_OR_MISMATCH')
    client = shutil.which('mariadb') or shutil.which('mysql')
    if not client or not version(client):
        fail('MARIADB_11_8_CLIENT_BINARY_MISSING_OR_MISMATCH')
    try:
        mysql_user = pwd.getpwnam('mysql')
    except KeyError:
        fail('MYSQL_OS_ACCOUNT_MISSING')
    if mysql_user.pw_uid == 0:
        fail('MYSQL_OS_USER_MUST_NOT_BE_ROOT')
    if DEST_BASE.is_symlink() or not DEST_BASE.is_dir():
        fail('ISOLATION_STAGING_BASE_NOT_REAL_DIR')
    base_info = DEST_BASE.stat()
    if not (base_info.st_mode & stat.S_ISVTX) or not (base_info.st_mode & stat.S_IWOTH):
        fail('ISOLATION_STAGING_BASE_NOT_STICKY_TMP')
    data_path = R68_STAGE / 'work'
    db_size = 0
    files = 0
    for parent, dirs, names in os.walk(data_path, followlinks=False):
        for dirname in dirs:
            if (Path(parent) / dirname).is_symlink():
                fail('PREPARED_DATA_CONTAINS_SYMLINK_DIR')
        for name in names:
            item = Path(parent) / name
            if item.is_symlink() or not item.is_file():
                fail('PREPARED_DATA_CONTAINS_SPECIAL_FILE')
            db_size += item.stat().st_size
            files += 1
    available = shutil.disk_usage(DEST_BASE).free
    minimum = max(4 * 1024**3, 2 * db_size + 1024**3)
    if available < minimum:
        fail('ISOLATED_COPY_SPACE_INSUFFICIENT')
    status = apparmor_status()
    print('=== SAFIR R69 ISOLATED MARIA DB RESTORE HOST RECON ===')
    print('MODE=STRICT_READ_ONLY')
    print('R67_ORIGINAL_BACKUP_VERIFIED=PASS')
    print('R68_PREPARED_VERIFICATION=PASS')
    print('R68_PREPARED_MANIFEST_PIN=PASS')
    print('R68_PREPARED_STAGE=' + str(R68_STAGE))
    print('PREPARED_COPY_FILE_COUNT=' + str(files))
    print('PREPARED_COPY_BYTES=' + str(db_size))
    print('MARIADBD_SERVER_11_8=PASS')
    print('MARIADB_CLIENT_11_8=PASS')
    print('MYSQL_SERVICE_ACCOUNT=AVAILABLE_NONROOT')
    print('ISOLATED_NEW_INSTANCE_BASE=' + str(DEST_BASE))
    print('ISOLATION_AVAILABLE_BYTES=' + str(available))
    print('ISOLATION_REQUIRED_BYTES=' + str(minimum))
    print('APPARMOR_MARIADB_PROFILE_STATUS=' + status)
    print('PRODUCTION_SERVER_STOP_OR_RESTART=NO')
    print('ISOLATED_DAEMON_STARTED=NO')
    print('FILESYSTEM_MUTATION=NO')
    print('DATABASE_MUTATION=NO')
    print('PROVIDER_SEND=NO')
    print('R69_PREFLIGHT=PASS')
    if status in ('UNKNOWN', 'ENFORCED_MARIADB_PROFILE'):
        print('R69_NEXT=REVIEW_SECURITY_PROFILE_BEFORE_ISOLATED_START')
    else:
        print('R69_NEXT=GUARDED_ISOLATED_INSTANCE_START_AND_READ_TEST')


if __name__ == '__main__':
    main()
