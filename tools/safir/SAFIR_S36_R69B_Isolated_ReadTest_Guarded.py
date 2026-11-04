#!/usr/bin/env python3
"""SAFIR R69B isolated MariaDB recovery smoke test.

--check: read-only preflight; --run-isolated: one guarded ephemeral database
instance from an independent copy of the pinned R68 prepared backup.
Never modifies the live instance or original R67/R68 backup.
"""
from __future__ import annotations

import argparse
from datetime import datetime, timezone
import fcntl
import hashlib
import json
import os
from pathlib import Path
import pwd
import re
import secrets
import shutil
import stat
import subprocess
import sys
import time

R69 = Path('/home/troca/SAFIR_S36_R69_Isolated_Restore_Host_ReadOnly_Preflight.py')
R69_SHA = 'cdb1ed1f3f90f04b00a651cbe1793dc4c111b85f54ab1cea4cffea7385113bad'
R68 = Path('/var/backups/ipkf/safir/r68-prepare-20261104T035103Z-c7366a1c')
R68_SHA = 'd50ea2885ea269b74ef65e1c1d2277c6b1709939ce1ecf876f2f9f3cec8e9539'
BASE = Path('/var/tmp')
PREFIX = 'safir-r69b-restore-'
LOCK = Path('/run/lock/safir-r69b-restore.lock')


def fail(reason):
    print('R69B=FAIL')
    print('REASON=' + reason)
    print('PRODUCTION_DATABASE_MUTATION=NO')
    print('PROVIDER_SEND=NO')
    raise SystemExit(1)


def sha(path):
    h = hashlib.sha256()
    with path.open('rb') as reader:
        for chunk in iter(lambda: reader.read(1024 * 1024), b''):
            h.update(chunk)
    return h.hexdigest()


def cli(args, timeout=15):
    return subprocess.run(args, text=True, stdout=subprocess.PIPE,
                          stderr=subprocess.DEVNULL, stdin=subprocess.DEVNULL,
                          timeout=timeout, check=False)


def source_inventory(source):
    found = {}
    for directory, subdirs, filenames in os.walk(source, followlinks=False):
        for name in subdirs:
            if (Path(directory) / name).is_symlink():
                fail('SOURCE_SYMLINK_DIRECTORY')
        for name in filenames:
            file = Path(directory) / name
            if file.is_symlink() or not file.is_file():
                fail('SOURCE_SYMLINK_OR_NONREGULAR_FILE')
            found[file.relative_to(source).as_posix()] = file
    return found


def preflight():
    if os.geteuid() != 0:
        fail('ROOT_REQUIRED')
    if R69.is_symlink() or not R69.is_file() or sha(R69) != R69_SHA:
        fail('R69_PINNED_SCRIPT_MISMATCH')
    p = cli([sys.executable, str(R69)], timeout=240)
    if p.returncode != 0 or 'R69_PREFLIGHT=PASS' not in p.stdout:
        fail('R69_PREFLIGHT_FAILED')
    if ('R68_PREPARED_STAGE=' + str(R68)) not in p.stdout or 'R68_PREPARED_MANIFEST_PIN=PASS' not in p.stdout:
        fail('R68_STAGE_PIN_CHANGED')
    if ('APPARMOR_MARIADB_PROFILE_STATUS=NO_MARIADB_PROFILE_IN_LOADED_LIST' not in p.stdout
            and 'APPARMOR_MARIADB_PROFILE_STATUS=DISABLED' not in p.stdout):
        fail('APPARMOR_PROFILE_REQUIRES_REVIEW')
    manifest = R68 / 'R68_PREPARED_MANIFEST.json'
    source = R68 / 'work'
    if manifest.is_symlink() or not manifest.is_file() or sha(manifest) != R68_SHA:
        fail('PREPARED_MANIFEST_SHA_PIN_MISMATCH')
    if not source.is_dir() or source.is_symlink():
        fail('PREPARED_DATA_DIRECTORY_INVALID')
    data = json.loads(manifest.read_text(encoding='utf-8'))
    declared = data.get('files', [])
    if not isinstance(declared, list) or len(declared) < 879:
        fail('MANIFEST_FILE_COUNT_INVALID')
    expected = {}
    for item in declared:
        rel = item.get('path')
        if not isinstance(rel, str) or rel.startswith('/') or '..' in Path(rel).parts or rel in expected:
            fail('MANIFEST_PATH_INVALID')
        expected[rel] = item
    files = source_inventory(source)
    if set(files) != set(expected):
        fail('PREPARED_FILE_LIST_CHANGED')
    server = shutil.which('mariadbd') or '/usr/sbin/mariadbd'
    client = shutil.which('mariadb')
    if not Path(server).is_file() or not client:
        fail('SERVER_OR_CLIENT_BINARY_MISSING')
    v = cli([server, '--version'], timeout=12)
    if v.returncode != 0 or not re.search(r'11\.8\.[0-9]+', v.stdout):
        fail('MARIADBD_NOT_11_8')
    try:
        mysql = pwd.getpwnam('mysql')
    except KeyError:
        fail('MYSQL_OS_ACCOUNT_MISSING')
    if mysql.pw_uid == 0:
        fail('MYSQL_PROCESS_CANNOT_RUN_AS_ROOT')
    if BASE.is_symlink() or not BASE.is_dir():
        fail('ISOLATION_BASE_INVALID')
    info = BASE.stat()
    if not (info.st_mode & stat.S_ISVTX) or not (info.st_mode & stat.S_IWOTH):
        fail('STAGING_BASE_STICKY_BIT_REQUIRED')
    free = shutil.disk_usage(BASE).free
    total = sum(int(item.get('bytes', 0)) for item in declared)
    needed = max(4 * 1024 ** 3, 2 * total + 1024 ** 3)
    if free < needed:
        fail('INSUFFICIENT_SPACE_FOR_ISOLATED_COPY')
    print('=== SAFIR R69B SEPARATE MARIADB READ TEST ===')
    print('R69_HOST_PREFLIGHT=PASS')
    print('R68_SOURCE_MANIFEST_PIN=PASS')
    print('PREPARED_FILES=' + str(len(files)))
    print('COPY_ESTIMATED_BYTES=' + str(total))
    print('FREE_BYTES=' + str(free))
    print('REQUIRED_BYTES=' + str(needed))
    print('MARIADB_VERSION_11_8=PASS')
    print('APPARMOR_GUARD=PASS')
    print('NETWORKING=DISABLED_FOR_TEST')
    print('EVENT_SCHEDULER=OFF_FOR_TEST')
    print('REPLICATION_AUTO_START=DISABLED_FOR_TEST')
    print('ORIGINAL_BACKUPS_MODIFICATION=NO')
    print('PRODUCTION_DATABASE_MUTATION=NO')
    print('PROVIDER_SEND=NO')
    print('R69B_PREFLIGHT=PASS')
    return server, client, mysql, source, files, expected


def isolated_sql(client, sock, statement, timeout=20):
    return subprocess.run([client, '--no-defaults', '--protocol=socket',
                           '--socket=' + str(sock), '--user=root',
                           '--connect-timeout=2', '--batch', '--raw',
                           '--skip-column-names', '--execute=' + statement],
                          stdin=subprocess.DEVNULL, stdout=subprocess.PIPE,
                          stderr=subprocess.DEVNULL, text=True, timeout=timeout,
                          check=False,
                          env={'PATH': '/usr/bin:/bin', 'HOME': '/root', 'LANG': 'C'})


def inspect_isolated_db(client, sock, datadir):
    p = isolated_sql(client, sock,
                     'SELECT @@datadir,@@socket,@@skip_networking,@@read_only,@@event_scheduler,@@log_bin,@@version;')
    parts = p.stdout.strip().split('\t')
    if p.returncode or len(parts) != 7:
        fail('RESTORED_SERVER_VARIABLES_UNAVAILABLE')
    dbdir, actual_sock, no_tcp, readonly, events, binlog, version = parts
    if Path(dbdir).resolve() != datadir.resolve() or Path(actual_sock) != sock:
        fail('ISOLATED_INSTANCE_IDENTIFIERS_DO_NOT_MATCH')
    if (no_tcp, readonly, events.upper(), binlog) != ('1', '1', 'OFF', '0'):
        fail('ISOLATED_INSTANCE_SECURITY_GATES_FAILED')
    if not re.match(r'11\.8\.[0-9]+', version) or 'MariaDB' not in version:
        fail('RESTORED_INSTANCE_VERSION_MISMATCH')
    p = isolated_sql(client, sock,
                     "SELECT TABLE_SCHEMA,COUNT(*),SUM(UPPER(ENGINE)='INNODB'),SUM(UPPER(ENGINE)='MYISAM') "
                     "FROM information_schema.TABLES WHERE TABLE_TYPE='BASE TABLE' GROUP BY TABLE_SCHEMA;")
    if p.returncode:
        fail('RESTORED_SCHEMA_INVENTORY_QUERY_FAILED')
    matches = []
    for row in p.stdout.splitlines():
        cells = row.split('\t')
        if len(cells) != 4:
            continue
        try:
            values = tuple(map(int, cells[1:]))
        except ValueError:
            continue
        if values == (185, 141, 44):
            matches.append(cells[0])
    if len(matches) != 1 or not re.fullmatch(r'[A-Za-z0-9_]+', matches[0]):
        fail('RESTORED_CORE_SCHEMA_COUNT_MISMATCH')
    schema = matches[0]
    for table in ('users', 'notification_outbox', 'geographic_import_rows', 'migrations'):
        quoted = chr(96) + schema + chr(96) + '.' + chr(96) + table + chr(96)
        p = isolated_sql(client, sock, 'SELECT COUNT(*) FROM ' + quoted + ';', timeout=45)
        if p.returncode or not re.fullmatch(r'[0-9]+', p.stdout.strip()):
            fail('RESTORED_TABLE_READ_FAILED_' + table.upper())
        rows = int(p.stdout.strip())
        if table in ('users', 'geographic_import_rows') and rows == 0:
            fail('RESTORED_DATA_EMPTY_' + table.upper())
        print('RESTORED_' + table.upper() + '_ROWS=' + str(rows))
    print('ISOLATED_TCP_DISABLED=PASS')
    print('ISOLATED_READ_ONLY=PASS')
    print('ISOLATED_EVENTS_DISABLED=PASS')
    print('ISOLATED_BINARY_LOG_DISABLED=PASS')
    print('RESTORED_CORE_TABLE_COUNT=185')
    print('RESTORED_INNODB_COUNT=141')
    print('RESTORED_MYISAM_COUNT=44')
    print('R69B_RESTORED_READ_CHECK=PASS')


def stop_isolated(proc, client, sock):
    if proc is None or proc.poll() is not None:
        return True
    try:
        isolated_sql(client, sock, 'SHUTDOWN;', timeout=12)
    except (OSError, subprocess.TimeoutExpired):
        pass
    try:
        proc.wait(timeout=12)
    except subprocess.TimeoutExpired:
        proc.terminate()
        try:
            proc.wait(timeout=15)
        except subprocess.TimeoutExpired:
            proc.kill()
            proc.wait(timeout=10)
    return proc.poll() is not None


def execute_isolated():
    if not sys.stdin.isatty():
        fail('INTERACTIVE_APPROVAL_REQUIRED')
    print('CONFIRM_SCOPE=ONE_EPHEMERAL_LOCAL_COPY_WITH_SOCKET_ONLY')
    print('WARNING=LOCAL_DISK_IO_DURING_COPY_AND_RESTORE_STARTUP')
    answer = input('Type exactly RESTORE TEST R69B to proceed: ').strip()
    if answer != 'RESTORE TEST R69B':
        fail('OPERATOR_DECLINED')
    lockfd = None
    proc = None
    stage = None
    client = None
    sock = None
    passed = False
    try:
        lockfd = os.open(str(LOCK), os.O_CREAT | os.O_RDWR | os.O_NOFOLLOW | os.O_CLOEXEC, 0o600)
        if os.fstat(lockfd).st_uid != 0 or os.fstat(lockfd).st_mode & 0o077:
            fail('LOCK_FILE_UNSAFE')
        try:
            fcntl.flock(lockfd, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            fail('ANOTHER_R69B_ALREADY_RUNNING')
        server, client, mysql, source, files, expected = preflight()
        stage = BASE / (PREFIX + datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ')
                        + '-' + secrets.token_hex(4))
        if stage.exists() or stage.is_symlink():
            fail('STAGE_ALREADY_EXISTS')
        stage.mkdir(mode=0o700)
        datadir = stage / 'data'
        sock = stage / 'isolated.sock'
        pid = stage / 'isolated.pid'
        tmpdir = stage / 'tmp'
        print('ISOLATED_STAGE=' + str(stage))
        shutil.copytree(source, datadir, symlinks=False)
        copied = source_inventory(datadir)
        if set(copied) != set(expected):
            fail('RESTORED_DATA_COPY_FILE_SET_DIFFERENT')
        for rel, path in copied.items():
            row = expected[rel]
            if row.get('bytes') != path.stat().st_size or row.get('sha256') != sha(path):
                fail('RESTORED_DATA_COPY_SHA256_INVALID')
        print('ISOLATED_COPY_SHA256_VERIFIED=PASS')
        for folder, dirs, names in os.walk(datadir, followlinks=False):
            for name in dirs + names:
                os.chown(Path(folder) / name, mysql.pw_uid, mysql.pw_gid, follow_symlinks=False)
        os.chown(datadir, mysql.pw_uid, mysql.pw_gid)
        tmpdir.mkdir(mode=0o700)
        os.chown(tmpdir, mysql.pw_uid, mysql.pw_gid)
        # mysql needs write access to create its private socket and PID files.
        os.chown(stage, mysql.pw_uid, mysql.pw_gid)
        stage.chmod(0o700)
        logfile = stage / 'R69B_MARIADB_PRIVATE.log'
        command = [server, '--no-defaults', '--datadir=' + str(datadir),
                   '--socket=' + str(sock), '--pid-file=' + str(pid),
                   '--tmpdir=' + str(tmpdir), '--skip-networking',
                   '--skip-slave-start', '--skip-log-bin', '--event-scheduler=OFF',
                   '--read-only=ON', '--skip-name-resolve',
                   '--innodb-buffer-pool-size=128M', '--max-connections=8']
        with logfile.open('xb') as log:
            os.chmod(logfile, 0o600)
            proc = subprocess.Popen(command, user=mysql.pw_uid, group=mysql.pw_gid,
                                    stdout=log, stderr=subprocess.STDOUT,
                                    stdin=subprocess.DEVNULL, start_new_session=True,
                                    env={'HOME': str(stage), 'PATH': '/usr/bin:/bin',
                                         'TMPDIR': str(tmpdir), 'LANG': 'C'})
            for _ in range(120):
                if proc.poll() is not None:
                    fail('ISOLATED_DAEMON_EXITED_DURING_STARTUP')
                if sock.is_socket():
                    r = isolated_sql(client, sock, 'SELECT 1;', timeout=5)
                    if r.returncode == 0 and r.stdout.strip() == '1':
                        break
                time.sleep(1)
            else:
                fail('ISOLATED_DAEMON_STARTUP_TIMEOUT')
            inspect_isolated_db(client, sock, datadir)
            passed = True
    except (OSError, shutil.Error, subprocess.SubprocessError):
        print('R69B_OPERATION_ERROR=YES')
    finally:
        stopped = stop_isolated(proc, client, sock)
        print('ISOLATED_DAEMON_STOPPED=' + ('YES' if stopped else 'NO'))
        if stage is not None:
            print('ISOLATED_COPY_RETAINED=' + str(stage))
        print('R67_R68_ORIGINAL_MUTATION=NO')
        print('PRODUCTION_DATABASE_MUTATION=NO')
        print('PROVIDER_SEND=NO')
        if lockfd is not None:
            os.close(lockfd)
        if not stopped:
            fail('ISOLATED_DAEMON_NOT_STOPPED')
    if not passed:
        fail('RESTORE_READ_TEST_NOT_PASSED')
    print('R69B=PASS')
    print('ISOLATED_RESTORE_VERIFIED=YES')
    print('NEXT=ISOLATED_SAFIR_SCHEMA_DDL_REHEARSAL')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    args = parser.add_mutually_exclusive_group(required=True)
    args.add_argument('--check', action='store_true')
    args.add_argument('--run-isolated', action='store_true')
    opts = parser.parse_args()
    os.umask(0o077)
    if opts.check:
        preflight()
        print('R69B=PASS')
        print('NEXT=EXPLICIT_ISOLATED_RESTORE_READ_TEST')
    else:
        preflight()
        execute_isolated()


if __name__ == '__main__':
    main()