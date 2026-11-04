#!/usr/bin/env python3
"""SAFIR R67 — local MariaDB 11.8 mixed-engine physical backup with hard safety gates.

--check         strict read-only; no filesystem changes.
--backup        operator-confirmed physical backup with MariaDB's native backup locks.
--verify-latest verify latest locally completed R67 backup against SHA256 manifest.

Never mutates application tables, changes privileges, or sends provider requests.
It does NOT perform a restore. Production migration is NOT approved by R67 alone.
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
import signal
import stat
import subprocess
import sys

BASE = Path('/srv/troca/portal-test')
SERVICE_USER = 'troca-portal-test'
BACKUP_ROOT = Path('/var/backups/ipkf/safir')
PREFIX = 'r67-mariadb-full-'
FLAGS = ('BALE_SAFIR_SEND_ENABLED', 'BALE_SAFIR_PRODUCTION_APPROVED',
         'BALE_SAFIR_PILOT_ENABLED', 'BALE_SAFIR_PILOT_PRODUCTION_APPROVED')
TABLES = ('safir_campaigns', 'safir_campaign_import_batches',
          'safir_campaign_recipients', 'safir_campaign_send_attempts',
          'safir_suppressed_phones')
PHP = r'''$base=(string)($argv[1]??'');
if (!is_file($base.'/vendor/autoload.php')) exit(31);
define('BASE_PATH',$base);
require BASE_PATH.'/vendor/autoload.php';
\IPKF\Support\Env::loadLayered(BASE_PATH.'/.env');
\IPKF\Support\Config::load();
try {
 $flags=[];
 foreach(['BALE_SAFIR_SEND_ENABLED','BALE_SAFIR_PRODUCTION_APPROVED',
          'BALE_SAFIR_PILOT_ENABLED','BALE_SAFIR_PILOT_PRODUCTION_APPROVED'] as $k) {
   $flags[$k]=filter_var(\IPKF\Support\Env::get($k,false),FILTER_VALIDATE_BOOLEAN);
 }
 if(in_array(true,$flags,true)) exit(32);
 $db=\IPKF\Database\Database::connect();
 $db->exec('START TRANSACTION READ ONLY');
 $server=$db->query('SELECT @@hostname AS hostname, @@datadir AS datadir, '
                   .'@@socket AS socket, @@version AS version, @@port AS port')
            ->fetch(\PDO::FETCH_ASSOC);
 $rows=$db->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES "
                  ."WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")
          ->fetchAll(\PDO::FETCH_ASSOC);
 $db->rollBack();
 $engines=[]; $names=[];
 foreach($rows as $row){
   $engine=strtoupper((string)($row['ENGINE']??''));
   $engines[$engine]=($engines[$engine]??0)+1;
   $names[]=(string)$row['TABLE_NAME'];
 }
 $cfg=\IPKF\Database\Database::config();
 $fp=hash('sha256',(string)($cfg['host']??'').'|'.(string)($cfg['port']??'').'|'.(string)($cfg['database']??''));
 echo json_encode(['flags'=>$flags,'server'=>$server,'engines'=>$engines,
   'base_count'=>count($rows),'safir_collisions'=>array_values(array_intersect($names,
      ['safir_campaigns','safir_campaign_import_batches','safir_campaign_recipients',
       'safir_campaign_send_attempts','safir_suppressed_phones'])),
   'fingerprint'=>$fp], JSON_THROW_ON_ERROR);
} catch(\Throwable $e){
 if(isset($db)&&$db instanceof \PDO&&$db->inTransaction()) $db->rollBack();
 exit(33);
}'''


def fail(reason: str) -> None:
    print('R67=FAIL')
    print('REASON=' + re.sub(r'[^A-Z0-9_]', '_', reason.upper())[:120])
    print('PROVIDER_SEND=NO')
    print('PRODUCTION_SCHEMA_MUTATION=NO')
    raise SystemExit(1)


def yes(v: bool) -> str:
    return 'YES' if v else 'NO'


def file_sha(path: Path) -> str:
    h = hashlib.sha256()
    with path.open('rb') as handle:
        while data := handle.read(1024 * 1024):
            h.update(data)
    return h.hexdigest()


def active_release() -> Path:
    try:
        active = (BASE / 'current').resolve(strict=True)
    except (OSError, RuntimeError):
        fail('ACTIVE_RELEASE_MISSING')
    if active.parent != BASE / 'releases' or 'safir-r62-form-action' not in active.name:
        fail('ACTIVE_RELEASE_OUTSIDE_EXPECTED_PINNED_CONTRACT')
    for fname in ('vendor/autoload.php', '.env', 'config/database.php',
                  'system/Database/Database.php'):
        if not (active / fname).is_file():
            fail('ACTIVE_RUNTIME_CONTRACT_MISSING')
    return active


def runtime_metadata(active: Path) -> dict:
    if os.geteuid() != 0:
        fail('RUN_AS_ROOT_REQUIRED')
    if not shutil.which('php') or not shutil.which('runuser'):
        fail('REQUIRED_RUNTIME_CLI_MISSING')
    try:
        r = subprocess.run(['runuser', '-u', SERVICE_USER, '--', 'php',
                            '-d', 'display_errors=0', '-d', 'log_errors=0',
                            '-r', PHP, str(active)], stdout=subprocess.PIPE,
                           stderr=subprocess.DEVNULL, text=True,
                           stdin=subprocess.DEVNULL, timeout=40, check=False)
        if r.returncode != 0:
            fail('PHP_METADATA_RC_' + str(r.returncode))
        data = json.loads(r.stdout)
    except (ValueError, OSError, subprocess.TimeoutExpired):
        fail('PHP_METADATA_PROBE_FAILED')
    if not isinstance(data, dict) or any(data.get('flags', {}).get(x) is not False for x in FLAGS):
        fail('SEND_OR_PILOT_FLAGS_NOT_ALL_OFF')
    if data.get('engines') != {'INNODB': 141, 'MYISAM': 44} or data.get('base_count') != 185:
        fail('R66A_ENGINE_BASELINE_CHANGED_REQUIRE_REVIEW')
    if data.get('safir_collisions'):
        fail('SAFIR_QUEUE_SCHEMA_ALREADY_EXISTS')
    server = data.get('server', {})
    version = str(server.get('version', ''))
    m = re.match(r'(11\.8)\.', version)
    if not m or 'MariaDB' not in version:
        fail('MARIADB_SERVER_VERSION_NOT_11_8')
    host = str(server.get('hostname', '')).split('.')[0].lower()
    if not host or host != os.uname().nodename.split('.')[0].lower():
        fail('MARIADB_HOST_NOT_THIS_OS')
    datadir = Path(str(server.get('datadir', '')))
    if not datadir.is_absolute() or not datadir.is_dir() or not (datadir / 'mysql').is_dir():
        fail('MARIADB_DATADIR_NOT_LOCAL')
    socket_path = str(server.get('socket', ''))
    if not socket_path.startswith('/') or not Path(socket_path).is_socket():
        fail('MARIADB_SOCKET_MISSING')
    data['datadir'] = str(datadir.resolve())
    data['socket'] = socket_path
    return data


def binary_version(backup_binary: str) -> str:
    try:
        r = subprocess.run([backup_binary, '--version'], stdout=subprocess.PIPE,
                           stderr=subprocess.STDOUT, text=True, stdin=subprocess.DEVNULL,
                           timeout=8, check=False)
        s = r.stdout
    except (OSError, subprocess.TimeoutExpired):
        return 'UNKNOWN'
    m = re.search(r'\b11\.8\.\d+\b', s)
    return '11.8' if r.returncode == 0 and m else 'UNKNOWN'


def root_socket(root_client: str, socket_path: str, hostname: str) -> None:
    cmd = [root_client, '--protocol=socket', '--socket=' + socket_path,
           '--user=root', '--connect-timeout=5', '--batch', '--skip-column-names',
           '--execute=SELECT @@hostname; SHOW GRANTS;']
    try:
        r = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.DEVNULL,
                           text=True, stdin=subprocess.DEVNULL, timeout=15, check=False)
    except (OSError, subprocess.TimeoutExpired):
        fail('LOCAL_ROOT_SQL_SOCKET_FAILED')
    if r.returncode or not r.stdout.splitlines():
        fail('LOCAL_ROOT_SQL_SOCKET_UNAVAILABLE')
    lines = r.stdout.splitlines()
    if lines[0].split('.')[0].lower() != hostname.split('.')[0].lower():
        fail('ROOT_SOCKET_DIFFERENT_DATABASE_SERVER')
    grants = '\n'.join(lines[1:])
    if not (re.search(r'GRANT\s+ALL\s+PRIVILEGES\s+ON\s+\*\.\*', grants, re.I) or
            all(re.search(r'\b' + re.escape(k) + r'\b', grants, re.I)
                for k in ('RELOAD', 'PROCESS', 'LOCK TABLES', 'BINLOG MONITOR'))):
        fail('ROOT_BACKUP_PRIVILEGES_NOT_CONFIRMED')


def directory_size(root: Path) -> int:
    tool = shutil.which('du')
    if not tool:
        fail('DU_MISSING')
    try:
        r = subprocess.run([tool, '-s', '-B1', '--', str(root)],
                           stdout=subprocess.PIPE, stderr=subprocess.DEVNULL,
                           text=True, timeout=45, check=False)
        if r.returncode:
            fail('DATADIR_SIZE_DISCOVERY_FAILED')
        return int(r.stdout.split()[0])
    except (ValueError, OSError, subprocess.TimeoutExpired, IndexError):
        fail('DATADIR_SIZE_DISCOVERY_FAILED')
    return 0


def checked_backup_root(create: bool) -> Path:
    # Never write under a symlinked backup root. /var/backups itself may be
    # normally 0755, but dedicated child directories must be root-owned 0700.
    for folder in (Path('/var/backups/ipkf'), BACKUP_ROOT):
        if folder.is_symlink():
            fail('BACKUP_PARENT_SYMLINK_FORBIDDEN')
        if folder.exists():
            s = folder.stat()
            if not stat.S_ISDIR(s.st_mode) or s.st_uid != 0 or s.st_mode & 0o077:
                fail('BACKUP_PARENT_NOT_ROOT_PRIVATE_0700')
        elif create:
            try:
                folder.mkdir(mode=0o700)
            except OSError:
                fail('BACKUP_PARENT_CREATE_FAILED')
    return BACKUP_ROOT


def preflight() -> tuple[dict, str, Path, int]:
    active = active_release()
    data = runtime_metadata(active)
    tool = shutil.which('mariadb-backup') or shutil.which('mariabackup')
    if not tool or binary_version(tool) != '11.8':
        fail('MARIADB_BACKUP_11_8_MATCH_REQUIRED')
    client = shutil.which('mariadb') or shutil.which('mysql')
    if not client:
        fail('MARIADB_SQL_CLI_MISSING')
    root_socket(client, data['socket'], str(data['server']['hostname']))
    datadir = Path(data['datadir'])
    backup_root = checked_backup_root(create=False)
    resolved_root = backup_root.resolve(strict=False)
    if datadir == resolved_root or datadir in resolved_root.parents or resolved_root in datadir.parents:
        fail('BACKUP_PATH_OVERLAPS_DB_DATADIR')
    size = directory_size(datadir)
    probe_path = backup_root if backup_root.is_dir() else Path('/var/backups')
    free = shutil.disk_usage(probe_path).free
    required = max(4 * 1024**3, 2 * size + 1024**3)
    print('=== SAFIR R67 GUARDED MIXED-ENGINE PHYSICAL BACKUP ===')
    print('MODE=STRICT_READ_ONLY_PREFLIGHT')
    print('ACTIVE_RELEASE=' + str(active))
    for k in FLAGS:
        print(k + '=OFF')
    print('ENGINE_INNODB=141')
    print('ENGINE_MYISAM=44')
    print('LOCAL_MARIADB_ROOT_SOCKET=PASS')
    print('MARIADB_BACKUP_11_8_VERSION_MATCH=PASS')
    print('DB_FINGERPRINT_SHA256_PREFIX=' + str(data['fingerprint'])[:16])
    print('DB_ALLOCATED_BYTES=' + str(size))
    print('BACKUP_AVAILABLE_BYTES=' + str(free))
    print('BACKUP_REQUIRED_BYTES=' + str(required))
    print('BACKUP_SAME_FILESYSTEM_AS_DATADIR=' + yes(datadir.stat().st_dev == probe_path.stat().st_dev))
    print('PRODUCTION_SCHEMA_MUTATION=NO')
    print('PROVIDER_SEND=NO')
    if size <= 0 or free < required:
        fail('INSUFFICIENT_BACKUP_DISK_SPACE')
    print('R67_PREFLIGHT=PASS')
    return data, tool, active, size


def enumerate_files(directory: Path) -> list[Path]:
    result = []
    for root, dirs, files in os.walk(directory, followlinks=False):
        if any((Path(root) / d).is_symlink() for d in dirs):
            fail('BACKUP_CONTAINS_SYMLINKED_DIRECTORY')
        dirs.sort()
        for name in sorted(files):
            p = Path(root) / name
            if p.is_symlink() or not p.is_file():
                fail('BACKUP_CONTAINS_SYMLINK_OR_NONFILE')
            if p.name not in ('R67_MANIFEST.json', 'R67_RECEIPT.json'):
                result.append(p)
    return result


def checkpoints_ok(folder: Path) -> None:
    chk = folder / 'xtrabackup_checkpoints'
    if not chk.is_file() or not (folder / 'backup-my.cnf').is_file() or not (folder / 'mysql').is_dir():
        fail('MARIADB_BACKUP_MARKER_MISSING')
    content = chk.read_text(errors='replace')
    if not re.search(r'^backup_type\s*=\s*full-backuped\s*$', content, re.M):
        fail('FULL_BACKUP_STATUS_NOT_RECORDED')


def create_manifest(folder: Path, data: dict, created: str) -> None:
    checkpoints_ok(folder)
    files = enumerate_files(folder)
    records = []
    for p in files:
        rel = p.relative_to(folder).as_posix()
        records.append({'path': rel, 'bytes': p.stat().st_size, 'sha256': file_sha(p)})
    receipt = {'kind': 'SAFIR_R67_MARIADB_FULL_BACKUP', 'created_utc': created,
               'db_fingerprint_sha256': data['fingerprint'],
               'db_version': str(data['server']['version']),
               'innodb_tables_precheck': 141, 'myisam_tables_precheck': 44,
               'files': records, 'prepared': False, 'restore_verified': False}
    manifest = folder / 'R67_MANIFEST.json'
    with manifest.open('x', encoding='utf-8') as handle:
        os.chmod(manifest, 0o600)
        json.dump(receipt, handle, ensure_ascii=False, indent=2)
        handle.flush(); os.fsync(handle.fileno())
    checksum = file_sha(manifest)
    note = {'manifest_sha256': checksum, 'file_count': len(records),
            'created_utc': created, 'backup_complete': True,
            'prepared': False, 'restore_verified': False}
    receipt_file = folder / 'R67_RECEIPT.json'
    with receipt_file.open('x', encoding='utf-8') as handle:
        os.chmod(receipt_file, 0o600)
        json.dump(note, handle, indent=2)
        handle.flush(); os.fsync(handle.fileno())
    print('BACKUP_FILE_COUNT=' + str(len(records)))
    print('BACKUP_MANIFEST_SHA256=' + checksum)


def verified_folder(folder: Path) -> None:
    if not folder.is_dir() or folder.parent != BACKUP_ROOT or not folder.name.startswith(PREFIX):
        fail('BACKUP_NOT_IN_R67_PRIVATE_PATH')
    if folder.is_symlink() or folder.stat().st_uid != 0 or folder.stat().st_mode & 0o077:
        fail('BACKUP_PERMISSIONS_INSECURE')
    receipt = folder / 'R67_RECEIPT.json'
    manifest = folder / 'R67_MANIFEST.json'
    if not receipt.is_file() or not manifest.is_file():
        fail('BACKUP_RECEIPT_MISSING')
    try:
        meta = json.loads(receipt.read_text())
        details = json.loads(manifest.read_text())
        if file_sha(manifest) != meta['manifest_sha256'] or not meta['backup_complete']:
            fail('BACKUP_RECEIPT_HASH_INVALID')
        records = details['files']
        expected = set()
        for item in records:
            rel = item['path']
            p = folder / rel
            if p.resolve(strict=False).parent == folder.parent:
                fail('INVALID_BACKUP_FILE_RELATIVE_PATH')
            if not p.is_file() or p.is_symlink() or folder not in p.resolve(strict=True).parents:
                fail('BACKUP_MANIFEST_FILE_PATH_UNSAFE')
            expected.add(rel)
            if p.stat().st_size != item['bytes'] or file_sha(p) != item['sha256']:
                fail('BACKUP_FILE_CHECKSUM_CHANGED')
        present = {p.relative_to(folder).as_posix() for p in enumerate_files(folder)}
        if expected != present:
            fail('BACKUP_FILE_LIST_CHANGED')
        checkpoints_ok(folder)
    except (OSError, ValueError, KeyError, TypeError):
        fail('BACKUP_MANIFEST_INVALID')
    print('R67_BACKUP_VERIFICATION=PASS')
    print('BACKUP_FOLDER=' + str(folder))
    print('BACKUP_MANIFEST_SHA256=' + str(meta['manifest_sha256']))
    print('BACKUP_PREPARED=NO')
    print('ISOLATED_RESTORE_VERIFIED=NO')
    print('PRODUCTION_SCHEMA_MUTATION=NO')
    print('PROVIDER_SEND=NO')


def choose_latest() -> Path:
    checked_backup_root(create=False)
    if not BACKUP_ROOT.is_dir():
        fail('BACKUP_DIRECTORY_MISSING')
    folders = sorted([p for p in BACKUP_ROOT.iterdir() if p.name.startswith(PREFIX)
                      and p.is_dir() and (p / 'R67_RECEIPT.json').is_file()],
                     key=lambda x: x.name, reverse=True)
    if not folders:
        fail('NO_COMPLETED_R67_BACKUP')
    return folders[0]


def do_backup(data: dict, backup_bin: str, active: Path) -> None:
    if not sys.stdin.isatty():
        fail('INTERACTIVE_TTY_CONFIRMATION_REQUIRED')
    print('WARNING=BACKUP_MAY_TEMPORARILY_BLOCK_MYISAM_WRITES_AND_DDL')
    print('REQUIRED_OPERATOR_CHECK=MAINTENANCE_WINDOW_OR_APPROVED_LOCK_IMPACT')
    print('SCOPE=PHYSICAL_BACKUP_ALL_DATABASES_NOT_ONLY_IPKF')
    answer = input('Type exactly BACKUP R67 to authorize a physical backup: ').strip()
    if answer != 'BACKUP R67':
        fail('OPERATOR_DID_NOT_APPROVE')
    # Re-check active release and engine counts immediately after confirmation.
    data2 = runtime_metadata(active_release())
    if data2['fingerprint'] != data['fingerprint']:
        fail('DB_FINGERPRINT_CHANGED_BEFORE_BACKUP')
    if active_release() != active:
        fail('ACTIVE_RELEASE_CHANGED_BEFORE_BACKUP')
    checked_backup_root(create=True)
    name = PREFIX + datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ') + '-' + secrets.token_hex(4)
    target = BACKUP_ROOT / name
    try:
        target.mkdir(mode=0o700)
        os.chmod(target, 0o700)
    except OSError:
        fail('BACKUP_TARGET_CREATE_FAILED')
    print('BACKUP_TARGET=' + str(target))
    print('BACKUP_STATE=RUNNING')
    print('MYSQL_BACKUP_LOCKING=ENABLED')
    print('NO_LOCK_OPTION_USED=NO')
    print('MARIADB_BACKUP_TIMEOUT_WAIT_FOR_LOCK_SECONDS=30')
    cmd = [backup_bin, '--backup', '--target-dir=' + str(target),
           '--user=root', '--host=localhost', '--socket=' + data['socket'],
           '--datadir=' + data['datadir'],
           '--ftwrl-wait-timeout=30', '--ftwrl-wait-threshold=5']
    # mariadb-backup requires an empty target-dir at startup. Keep logs beside it.
    logfile = BACKUP_ROOT / (name + '.log')
    print('PRIVATE_BACKUP_LOG=' + str(logfile))
    try:
        with logfile.open('xb') as handle:
            os.chmod(logfile, 0o600)
            process = subprocess.Popen(cmd, stdout=handle, stderr=subprocess.STDOUT,
                                       stdin=subprocess.DEVNULL, start_new_session=True)
            try:
                rc = process.wait(timeout=1800)
            except subprocess.TimeoutExpired:
                os.killpg(process.pid, signal.SIGTERM)
                try:
                    process.wait(timeout=20)
                except subprocess.TimeoutExpired:
                    os.killpg(process.pid, signal.SIGKILL)
                    process.wait(timeout=10)
                fail('BACKUP_TIMED_OUT_PRIVATE_PARTIAL_RETAINED')
    except OSError:
        fail('BACKUP_PROCESS_START_FAILED_PRIVATE_PARTIAL_RETAINED')
    if rc:
        print('PARTIAL_BACKUP_RETAINED_PRIVATE=' + str(target))
        fail('MARIADB_BACKUP_FAILED_RC_' + str(rc))
    print('MARIADB_BACKUP_RETURN_CODE=0')
    create_manifest(target, data, datetime.now(timezone.utc).isoformat())
    verified_folder(target)
    print('R67_BACKUP=PASS')
    print('NEXT=PREPARE_COPY_AND_RESTORE_ON_ISOLATED_INSTANCE_R68')


def main() -> None:
    parser = argparse.ArgumentParser(description='SAFIR R67 guarded MariaDB mixed-engine physical backup')
    modes = parser.add_mutually_exclusive_group(required=True)
    modes.add_argument('--check', action='store_true')
    modes.add_argument('--backup', action='store_true')
    modes.add_argument('--verify-latest', action='store_true')
    args = parser.parse_args()
    os.umask(0o077)
    if os.geteuid() != 0:
        fail('RUN_AS_ROOT_REQUIRED')
    if args.verify_latest:
        verified_folder(choose_latest())
        return
    data, tool, active, size = preflight()
    if args.check:
        print('R67=PASS')
        print('NEXT=BACKUP_IN_APPROVED_LOW_TRAFFIC_WINDOW')
        return
    do_backup(data, tool, active)


if __name__ == '__main__':
    main()