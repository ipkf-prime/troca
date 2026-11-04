#!/usr/bin/env python3
"""SAFIR R72C guarded MariaDB 11.8 five-table DDL rehearsal in an ISOLATED COPY.

--check: read-only pinned source, SQL and environment preflight.
--run-isolated: explicit TTY approval; NEW datadir from R68 prepared backup;
  socket-only MariaDB, no TCP, no replication, events off, binlog off;
  verifies exact isolated instance before creating five SAFIR tables.
NEVER runs SQL on production, modifies R67/R68, or sends messages.
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

BASE = Path('/home/troca')
R70 = BASE / 'SAFIR_S36_R70_Schema_Contract_ReadOnly_Preflight.py'
R70_SHA = 'e63943d7c0fbe61017d12789ae7ae607ff678c24f65b3e1f4283f40be2e19f37'
SQL = BASE / 'SAFIR_R70_Isolated_CompositeFK_Queue_Design_ONLY.sql'
SQL_SHA = 'af520182cac02cb6fbf24b303667583170def9f01113082d07accdfe0875dc04'
R68 = Path('/var/backups/ipkf/safir/r68-prepare-20261104T035103Z-c7366a1c')
R68_MANIFEST_SHA = 'd50ea2885ea269b74ef65e1c1d2277c6b1709939ce1ecf876f2f9f3cec8e9539'
OUT = Path('/var/tmp')
LOCK = Path('/run/lock/safir-r71-isolated-ddl.lock')
PREFIX = 'safir-r72c-pdo-'
NEW_TABLES = ('safir_campaigns', 'safir_campaign_import_batches',
              'safir_campaign_recipients', 'safir_campaign_send_attempts',
              'safir_suppressed_phones')


def fail(code: str) -> None:
    print('R72C=FAIL')
    print('REASON=' + code)
    print('PRODUCTION_DATABASE_MUTATION=NO')
    print('PROVIDER_SEND=NO')
    raise SystemExit(1)


def sha(path: Path) -> str:
    h = hashlib.sha256()
    with path.open('rb') as f:
        for chunk in iter(lambda: f.read(1048576), b''):
            h.update(chunk)
    return h.hexdigest()


def command(argv, timeout=30, input_sql=None):
    return subprocess.run(argv, input=input_sql, text=True,
                          stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                          timeout=timeout, check=False,
                          env={'HOME':'/root', 'PATH':'/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
                               'LANG':'C', 'MYSQL_HISTFILE':'/dev/null'})


def sql(client, sock, statement, schema=None, timeout=30):
    argv = [client, '--no-defaults', '--protocol=socket',
            '--socket=' + str(sock), '--user=root', '--connect-timeout=3',
            '--batch', '--raw', '--skip-column-names']
    if schema:
        argv.append('--database=' + schema)
    argv.append('--execute=' + statement)
    return command(argv, timeout=timeout)


def require_sql(client, sock, statement, schema=None, timeout=30):
    p = sql(client, sock, statement, schema, timeout)
    if p.returncode:
        fail('ISOLATED_SQL_FAILED')
    return p.stdout.strip()


def assert_error(client, sock, schema, statement, error_no, name):
    p = sql(client, sock, statement, schema, timeout=20)
    if p.returncode == 0 or not re.search(r'ERROR\s+' + str(error_no) + r'\b', p.stderr):
        fail('NEGATIVE_CONSTRAINT_TEST_FAILED_' + name)
    print('NEGATIVE_TEST_' + name + '=PASS')


def pinned(path, checksum):
    if path.is_symlink() or not path.is_file() or sha(path) != checksum:
        fail('PINNED_FILE_MISSING_OR_CHANGED')


def source_files(root):
    found = {}
    for folder, dirs, names in os.walk(root, followlinks=False):
        for name in dirs:
            if (Path(folder) / name).is_symlink():
                fail('SYMLINK_DIRECTORY_IN_BACKUP')
        for name in names:
            p = Path(folder) / name
            if p.is_symlink() or not p.is_file():
                fail('SPECIAL_FILE_IN_BACKUP')
            found[p.relative_to(root).as_posix()] = p
    return found


def preflight():
    if os.geteuid() != 0:
        fail('ROOT_REQUIRED')
    pinned(R70, R70_SHA)
    pinned(SQL, SQL_SHA)
    pinned(BASE / 'BaleSafirDurableCampaignPolicy.php',
        '2262354eecf1eda38537dc9bf68f26f534fb82fc4f8e6a42de197b76ee813edc')
    pinned(BASE / 'SAFIR_R72B_DurableCampaignRepository.php',
        '0cbf4d654c14576dc837c102ce176acce8fe45eaebfda49b862ed60f8e68a4b9')
    pinned(BASE / 'SAFIR_R72C_Isolated_RealPDO_Integration.php',
        'f6c1debf23cdfb58d0e41a135d57c70a045c5e2bc704015c0c5975ade272a1d3')
    php = shutil.which('php')
    if not php or not Path(php).is_file():
        fail('PHP_CLI_MISSING')
    try:
        extensions = command([php, '-r',
            'echo extension_loaded("pdo_mysql") && extension_loaded("mbstring") ? "PASS" : "FAIL";'],
            timeout=20)
    except (OSError, subprocess.TimeoutExpired):
        fail('PHP_EXTENSION_CHECK_UNAVAILABLE')
    if extensions.returncode or extensions.stdout.strip() != 'PASS':
        fail('PHP_PDO_MYSQL_OR_MBSTRING_MISSING')
    try:
        p = command([sys.executable, str(R70), '--check'], timeout=330)
    except (OSError, subprocess.TimeoutExpired):
        fail('R70_CONTRACT_RECHECK_TIMEOUT')
    if p.returncode or 'R70_PREFLIGHT=PASS' not in p.stdout or 'R70=PASS' not in p.stdout:
        fail('R70_CONTRACT_RECHECK_FAILED')
    manifest_path = R68 / 'R68_PREPARED_MANIFEST.json'
    pinned(manifest_path, R68_MANIFEST_SHA)
    stage = R68 / 'work'
    if not stage.is_dir() or stage.is_symlink():
        fail('PREPARED_R68_WORK_NOT_FOUND')
    try:
        meta = json.loads(manifest_path.read_text())
        rows = meta['files']
    except (OSError, ValueError, TypeError, KeyError):
        fail('R68_PREPARED_MANIFEST_INVALID')
    if not isinstance(rows, list) or len(rows) != 880:
        fail('R68_PREPARED_COUNT_CHANGED')
    found = source_files(stage)
    expected = {}
    for item in rows:
        rel = item.get('path') if isinstance(item, dict) else None
        if (not isinstance(rel, str) or not rel or rel.startswith('/')
                or '..' in Path(rel).parts or rel in expected):
            fail('R68_PREPARED_PATH_INVALID')
        expected[rel] = item
    if set(found) != set(expected):
        fail('R68_PREPARED_FILE_SET_CHANGED')
    for rel, p in found.items():
        if p.stat().st_size != expected[rel].get('bytes') or sha(p) != expected[rel].get('sha256'):
            fail('R68_PREPARED_SHA256_MISMATCH')
    server = shutil.which('mariadbd') or '/usr/sbin/mariadbd'
    client = shutil.which('mariadb')
    if not Path(server).is_file() or not client:
        fail('MARIADB_SERVER_OR_CLIENT_MISSING')
    try:
        v = command([server, '--version'], timeout=10)
    except (OSError, subprocess.TimeoutExpired):
        fail('SERVER_VERSION_NOT_VERIFIABLE')
    if v.returncode or not re.search(r'11\.8\.[0-9]+', v.stdout):
        fail('MARIADBD_11_8_REQUIRED')
    account = pwd.getpwnam('mysql')
    if account.pw_uid == 0:
        fail('MYSQL_NONROOT_ACCOUNT_REQUIRED')
    if OUT.is_symlink() or not OUT.is_dir():
        fail('ISOLATION_BASE_INVALID')
    st = OUT.stat()
    if not (st.st_mode & stat.S_ISVTX and st.st_mode & stat.S_IWOTH):
        fail('ISOLATION_BASE_NOT_STICKY')
    total = sum(p.stat().st_size for p in found.values())
    free = shutil.disk_usage(OUT).free
    needed = max(4*1024**3, 2*total + 1024**3)
    if free < needed:
        fail('INSUFFICIENT_ISOLATION_DISK')
    print('=== SAFIR R72C GUARDED ISOLATED DDL REHEARSAL ===')
    print('MODE=STRICT_READ_ONLY_PREFLIGHT')
    print('R70_CONTRACT=PASS')
    print('R69B_RESTORE_PREFLIGHT=PASS')
    print('R68_PREPARED_880_FILES_SHA256=PASS')
    print('R70_DESIGN_SQL_SHA256=PASS')
    print('R72B_REPOSITORY_SOURCE_SHA256=PASS')
    print('R72A_POLICY_SOURCE_SHA256=PASS')
    print('R72C_REAL_PDO_TEST_SOURCE_SHA256=PASS')
    print('PHP_CLI_PDO_MYSQL_MBSTRING=PASS')
    print('ISOLATED_FREE_BYTES=' + str(free))
    print('ISOLATED_COPY_BYTES=' + str(total))
    print('ISOLATED_REQUIRED_BYTES=' + str(needed))
    print('PRODUCTION_SCHEMA_MUTATION=NO')
    print('R67_R68_MUTATION=NO')
    print('PROVIDER_SEND=NO')
    print('R72C_PREFLIGHT=PASS')
    return server, client, account, stage, expected


def verify_identity(client, sock, datadir, readonly):
    out = require_sql(client, sock,
        'SELECT @@datadir,@@socket,@@skip_networking,@@read_only,'
        '@@event_scheduler,@@log_bin,@@version;')
    fields = out.split('\t')
    if len(fields) != 7:
        fail('ISOLATED_SERVER_IDENTITY_UNAVAILABLE')
    dbdir, socket, no_tcp, ro, events, binlog, ver = fields
    if Path(dbdir).resolve() != datadir.resolve() or Path(socket) != sock:
        fail('ISOLATED_PATH_IDENTITY_MISMATCH')
    if (no_tcp, ro, events.upper(), binlog) != ('1', readonly, 'OFF', '0'):
        fail('ISOLATED_NETWORK_OR_SCHEDULER_SAFETY_FAILED')
    if not re.match(r'11\.8\.[0-9]+', ver):
        fail('ISOLATED_SERVER_VERSION_MISMATCH')


def restored_schema(client, sock):
    output = require_sql(client, sock,
        "SELECT TABLE_SCHEMA,COUNT(*),SUM(ENGINE='InnoDB'),SUM(ENGINE='MyISAM') "
        "FROM information_schema.TABLES WHERE TABLE_TYPE='BASE TABLE' "
        "GROUP BY TABLE_SCHEMA")
    matches = []
    for row in output.splitlines():
        cells = row.split('\t')
        if len(cells) == 4 and cells[1:] == ['185','141','44']:
            matches.append(cells[0])
    if len(matches) != 1 or not re.fullmatch(r'[A-Za-z0-9_]+', matches[0]):
        fail('RESTORED_185_TABLE_SCHEMA_NOT_UNIQUE')
    return matches[0]


def test_schema(client, sock, schema):
    # Only new and empty SAFIR tables are touched; all changes are isolated.
    q = chr(96)
    prefix = q + schema + q + '.'
    camp = prefix + q + 'safir_campaigns' + q
    batch = prefix + q + 'safir_campaign_import_batches' + q
    rec = prefix + q + 'safir_campaign_recipients' + q
    attempt = prefix + q + 'safir_campaign_send_attempts' + q
    suppress = prefix + q + 'safir_suppressed_phones' + q
    h64 = 'a'*64
    # All test identifiers are synthetic and are kept only inside the private copy.
    a32, b32 = 'a'*32, 'b'*32
    require_sql(client, sock,
        "INSERT INTO " + camp +
        " (id, public_reference, owner_user_id, title, template_code, template_sha256, template_snapshot_json)"
        " VALUES (1,'" + a32 + "',1,'R72C synthetic A','R72C'," + "'" + h64 + "','{}'),"
        " (2,'" + b32 + "',1,'R72C synthetic B','R72C','" + h64 + "','{}')")
    require_sql(client, sock,
        "INSERT INTO " + batch +
        " (id,campaign_id,source_sha256,state_code) VALUES"
        " (101,1,'" + '1'*64 + "','complete'),"
        " (102,2,'" + '2'*64 + "','complete')")
    require_sql(client, sock,
        "INSERT INTO " + rec +
        " (id,campaign_id,import_batch_id,source_row_number,phone_normalized,text_fields_json)"
        " VALUES (1001,1,101,1,'989123456789','{}'),"
        " (1002,2,102,1,'989123456788','{}')")
    require_sql(client, sock,
        "INSERT INTO " + attempt +
        " (id,campaign_id,recipient_id,request_id,attempt_number)"
        " VALUES (2001,1,1001,'" + '3'*64 + "',1)")
    require_sql(client, sock,
        "INSERT INTO " + suppress +
        " (id,phone_normalized,reason_code) VALUES (3001,'989123456787','R72C_TEST')")
    print('ISOLATED_SYNTHETIC_VALID_INSERTS=PASS')
    assert_error(client,sock,schema,
        "INSERT INTO " + rec +
        " (campaign_id,import_batch_id,source_row_number,phone_normalized,text_fields_json)"
        " VALUES (1,102,4,'989123456786','{}')",
        1452,'CROSS_CAMPAIGN_BATCH_REJECTED')
    assert_error(client,sock,schema,
        "INSERT INTO " + attempt +
        " (campaign_id,recipient_id,request_id,attempt_number)"
        " VALUES (2,1001,'" + '4'*64 + "',2)",
        1452,'CROSS_CAMPAIGN_ATTEMPT_REJECTED')
    assert_error(client,sock,schema,
        "INSERT INTO " + rec +
        " (campaign_id,import_batch_id,source_row_number,phone_normalized,text_fields_json)"
        " VALUES (1,101,5,'989123456789','{}')",
        1062,'DUPLICATE_PHONE_REJECTED')
    assert_error(client,sock,schema,
        "INSERT INTO " + rec +
        " (campaign_id,import_batch_id,source_row_number,phone_normalized,text_fields_json)"
        " VALUES (1,101,5,'123456','{}')",
        4025,'INVALID_PHONE_REJECTED')
    assert_error(client,sock,schema,
        "DELETE FROM " + camp + " WHERE id=1",
        1451,'PARENT_DELETE_RESTRICTED')
    result = require_sql(client,sock,
        "SELECT (SELECT COUNT(*) FROM " + camp + "),"
        "(SELECT COUNT(*) FROM " + batch + "),"
        "(SELECT COUNT(*) FROM " + rec + "),"
        "(SELECT COUNT(*) FROM " + attempt + "),"
        "(SELECT COUNT(*) FROM " + suppress + ")")
    if result != '2\t2\t2\t1\t1':
        fail('ISOLATED_TEST_DATA_COUNTS_UNEXPECTED')
    print('ISOLATED_TEST_COUNTS=2,2,2,1,1')
    print('R72C_NEGATIVE_CONSTRAINT_TESTS=5_PASS')


def apply_isolated(client, sock, datadir):
    verify_identity(client, sock, datadir, '1')
    schema = restored_schema(client,sock)
    check = require_sql(client,sock,
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='" + schema +
        "' AND TABLE_NAME IN (" + ",".join("'" + x + "'" for x in NEW_TABLES) + ")")
    if check != '0':
        fail('SAFIR_TABLES_ALREADY_PRESENT_IN_ISOLATED_COPY')
    # Writable mode exists ONLY within a verified socket-only restored copy.
    require_sql(client,sock,'SET GLOBAL read_only=OFF')
    verify_identity(client,sock,datadir,'0')
    print('ISOLATED_WRITABLE_MODE_VERIFIED=PASS')
    script = SQL.read_text(encoding='utf-8')
    argv=[client,'--no-defaults','--protocol=socket',
          '--socket='+str(sock),'--user=root','--connect-timeout=3',
          '--batch','--database='+schema]
    p=command(argv,timeout=120,input_sql=
       'SET SESSION foreign_key_checks=1; SET SESSION check_constraint_checks=1;\n'+script)
    if p.returncode:
        print('DDL_CLIENT_EXIT_CODE='+str(p.returncode))
        print('DDL_ERROR_TAIL='+p.stderr[-350:].replace('\n',' '))
        fail('ISOLATED_FIVE_TABLE_CREATE_FAILED')
    info=require_sql(client,sock,
        "SELECT COUNT(*),SUM(ENGINE='InnoDB') FROM information_schema.TABLES "
        "WHERE TABLE_SCHEMA='"+schema+"' AND TABLE_NAME IN ("+
        ",".join("'"+x+"'" for x in NEW_TABLES)+")")
    if info != '5\t5':
        fail('ISOLATED_NEW_FIVE_INNODB_TABLES_MISSING')
    keys=require_sql(client,sock,
        "SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS "
        "WHERE CONSTRAINT_SCHEMA='"+schema+"' AND TABLE_NAME IN ("+
        ",".join("'"+x+"'" for x in NEW_TABLES)+")")
    if keys != '5':
        fail('ISOLATED_FIVE_FOREIGN_KEYS_MISSING')
    checks=require_sql(client,sock,
        "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS "
        "WHERE CONSTRAINT_SCHEMA='"+schema+"' AND CONSTRAINT_TYPE='CHECK' "
        "AND TABLE_NAME IN (" + ",".join("'"+x+"'" for x in NEW_TABLES)+")")
    if checks != '9':
        fail('ISOLATED_NINE_CHECK_CONSTRAINTS_MISSING')
    print('ISOLATED_DDL_FIVE_TABLES=PASS')
    print('ISOLATED_FOREIGN_KEYS_FIVE=PASS')
    print('ISOLATED_CHECK_CONSTRAINTS_NINE=PASS')
    test_schema(client,sock,schema)
    # Execute PHP real-PDO test only AFTER verifying exact isolated writable
    # data/socket identity, and only with pinned, operator-reviewed sources.
    php = shutil.which('php')
    probe = command([php,
        '-d', 'display_errors=0',
        str(BASE / 'SAFIR_R72C_Isolated_RealPDO_Integration.php'),
        str(sock), str(datadir), schema], timeout=150)
    if probe.returncode or 'R72C_REAL_PDO_INTEGRATION=PASS' not in probe.stdout:
        print('R72C_PHP_EXIT_CODE=' + str(probe.returncode))
        for line in probe.stderr.splitlines():
            if line.startswith('R72C=FAIL;REASON='):
                print(line)
        fail('R72C_REAL_PDO_INTEGRATION_FAILED')
    approved = {
        'R72C_REAL_PDO_SOCKET_ONLY=PASS',
        'R72C_SUPPRESSION_AND_FIRST_CHUNK=PASS',
        'R72C_REAL_DB_TWO_CHUNKS=PASS',
        'R72C_AUTH_AND_TRANSACTION_GUARDS=PASS',
        'R72C_CAMPAIGN_SCOPED_UNIQUENESS=PASS',
        'R72C_REAL_PDO_INTEGRATION=PASS',
        'R72C_PROVIDER_HTTP=NO',
        'R72C_PRODUCTION_MUTATION=NO',
    }
    reported = set(probe.stdout.splitlines())
    if not approved.issubset(reported):
        fail('R72C_PDO_EXPECTED_TEST_MARKERS_MISSING')
    for marker in sorted(approved):
        print(marker)
    print('R72C_REAL_PDO_CHECK=PASS')
    require_sql(client,sock,'SET GLOBAL read_only=ON')
    verify_identity(client,sock,datadir,'1')
    print('ISOLATED_READONLY_RESTORED=PASS')


def stop(proc,client,sock):
    if proc is None or proc.poll() is not None:
        return True
    try:
        sql(client,sock,'SHUTDOWN;',timeout=10)
    except (OSError,subprocess.TimeoutExpired):
        pass
    try:
        proc.wait(timeout=15)
    except subprocess.TimeoutExpired:
        proc.terminate()
        try:
            proc.wait(timeout=15)
        except subprocess.TimeoutExpired:
            proc.kill()
            proc.wait(timeout=10)
    return proc.poll() is not None


def run_rehearsal():
    if not sys.stdin.isatty():
        fail('INTERACTIVE_OPERATOR_CONFIRMATION_REQUIRED')
    print('WARNING=THIS_WRITES_ONLY_TO_A_DISPOSABLE_COPY_OF_R68_BACKUP')
    print('WARNING=LOCAL_DISK_IO_AND_SENSITIVE_DB_COPY_ON_SAME_HOST')
    if input('Type exactly DDL R72C ISOLATED to continue: ').strip() != 'DDL R72C ISOLATED':
        fail('OPERATOR_DID_NOT_APPROVE')
    fd=None
    proc=None
    stage=None
    client=None
    sock=None
    passed=False
    try:
        fd=os.open(str(LOCK),os.O_CREAT|os.O_RDWR|os.O_NOFOLLOW|os.O_CLOEXEC,0o600)
        st=os.fstat(fd)
        if st.st_uid!=0 or st.st_mode & 0o077:
            fail('ISOLATED_OPERATION_LOCK_NOT_PRIVATE')
        try:
            fcntl.flock(fd,fcntl.LOCK_EX|fcntl.LOCK_NB)
        except BlockingIOError:
            fail('ANOTHER_R72C_REHEARSAL_RUNNING')
        server,client,mysql,source,expected=preflight()
        stage=OUT/(PREFIX+datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ')
                   +'-'+secrets.token_hex(4))
        if stage.exists() or stage.is_symlink():
            fail('ISOLATED_TARGET_COLLISION')
        stage.mkdir(mode=0o700)
        data=stage/'data'
        sock=stage/'db.sock'
        pid=stage/'db.pid'
        tmp=stage/'tmp'
        print('ISOLATED_R72C_STAGE='+str(stage))
        shutil.copytree(source,data,symlinks=False)
        files=source_files(data)
        if set(files)!=set(expected):
            fail('R72C_COPY_FILE_SET_MISMATCH')
        for name,p in files.items():
            if p.stat().st_size!=expected[name].get('bytes') or sha(p)!=expected[name].get('sha256'):
                fail('R72C_COPY_SHA256_MISMATCH')
        print('R72C_INDEPENDENT_COPY_SHA256=PASS')
        for folder,dirs,names in os.walk(data,followlinks=False):
            for item in dirs+names:
                os.chown(Path(folder)/item,mysql.pw_uid,mysql.pw_gid,follow_symlinks=False)
        os.chown(data,mysql.pw_uid,mysql.pw_gid)
        tmp.mkdir(mode=0o700)
        os.chown(tmp,mysql.pw_uid,mysql.pw_gid)
        os.chown(stage,mysql.pw_uid,mysql.pw_gid)
        stage.chmod(0o700)
        log=stage/'R72C_ISOLATED_MARIADB.log'
        commandline=[server,'--no-defaults','--datadir='+str(data),
                     '--socket='+str(sock),'--pid-file='+str(pid),
                     '--tmpdir='+str(tmp),'--skip-networking','--skip-slave-start',
                     '--skip-log-bin','--event-scheduler=OFF','--read-only=ON',
                     '--skip-name-resolve','--innodb-buffer-pool-size=128M',
                     '--max-connections=8']
        with log.open('xb') as out:
            os.chmod(log,0o600)
            proc=subprocess.Popen(commandline,user=mysql.pw_uid,group=mysql.pw_gid,
                stdin=subprocess.DEVNULL,stdout=out,stderr=subprocess.STDOUT,
                start_new_session=True,
                env={'HOME':str(stage),'PATH':'/usr/bin:/bin',
                     'TMPDIR':str(tmp),'LANG':'C'})
            for _ in range(120):
                if proc.poll() is not None:
                    fail('ISOLATED_DAEMON_EXITED_ON_START')
                if sock.is_socket():
                    try:
                        p=sql(client,sock,'SELECT 1',timeout=5)
                        if p.returncode==0 and p.stdout.strip()=='1':
                            break
                    except (OSError,subprocess.TimeoutExpired):
                        pass
                time.sleep(1)
            else:
                fail('ISOLATED_DAEMON_START_TIMEOUT')
            apply_isolated(client,sock,data)
            passed=True
    except (OSError,shutil.Error,subprocess.SubprocessError,ValueError) as exc:
        print('R72C_OPERATION_ERROR_TYPE='+type(exc).__name__)
    finally:
        stopped=stop(proc,client,sock)
        print('ISOLATED_DAEMON_STOPPED='+('YES' if stopped else 'NO'))
        if stage:
            try:
                os.chown(stage,0,0)
                stage.chmod(0o700)
            except OSError:
                print('R72C_STAGE_PRIVACY_RESET_FAILED=YES')
            print('ISOLATED_COPY_RETAINED_PRIVATE='+str(stage))
        print('R67_R68_SOURCE_MUTATION=NO')
        print('PRODUCTION_DATABASE_MUTATION=NO')
        print('PROVIDER_SEND=NO')
        if fd is not None:
            os.close(fd)
        if not stopped:
            fail('ISOLATED_DAEMON_NOT_STOPPED')
    if not passed:
        fail('ISOLATED_DDL_REHEARSAL_NOT_PASSED')
    print('R72C=PASS')
    print('R72C_ISOLATED_DDL_REHEARSAL=PASS')
    print('PRODUCTION_DDL_APPROVED=NO')
    print('NEXT=REVIEW_TEST_RESULTS_AND_ENGINEERING_GATES')


def main():
    parser=argparse.ArgumentParser(description=__doc__)
    modes=parser.add_mutually_exclusive_group(required=True)
    modes.add_argument('--check',action='store_true')
    modes.add_argument('--run-isolated',action='store_true')
    args=parser.parse_args()
    os.umask(0o077)
    if args.check:
        preflight()
        print('R72C=PASS')
        print('NEXT=EXPLICIT_R72C_ISOLATED_REHEARSAL_ONLY')
    else:
        preflight()
        run_rehearsal()


if __name__=='__main__':
    main()
