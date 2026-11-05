#!/usr/bin/env python3
"""SAFIR R73C2: new disposable R68 185-table MariaDB restore DDL+FK rehearsal.
--check is strictly read-only. --run-isolated requires TTY consent.
NO production DB writes, R67/R68 changes, HTTP sends, workers or deployment.
Historical R66A live-freeze remains unchanged and the new live table is NOT
automatically approved. Test SQL is confined to the private socket/database.
"""
from __future__ import annotations
import argparse
from datetime import datetime, timezone
import fcntl
import hashlib
import importlib.util
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

BASE=Path('/home/troca')
PREF=BASE/'SAFIR_S36_R73C2_Historical_Isolated_Preflight_Rev2.py'
PREF_SHA='c73e79e614b47bfee4943e6089feafc452f883fa87ac1666bc2c5d93e8c4acff'
CORE=BASE/'SAFIR_S36_R72C_RealPDO_Isolated_Rehearsal_Guarded.py'
CORE_SHA='434113b07c84204cb164cbf6f9d9720f91a0ae2e8e72f217936cbeb4b227c83d'
R70=BASE/'SAFIR_R70_Isolated_CompositeFK_Queue_Design_ONLY.sql'
R70_SHA='af520182cac02cb6fbf24b303667583170def9f01113082d07accdfe0875dc04'
DDL=BASE/'SAFIR_R73C2_File_Receipt_Link_MariaDB11_Design_ONLY.sql'
DDL_SHA='c644ce02b2356e810c4cd099a25f1fec85ed96a6a0897782ad9ed785984e8676'
SOURCE=Path('/var/backups/ipkf/safir/r68-prepare-20261104T035103Z-c7366a1c/work')
OUT=Path('/var/tmp')
LOCK=Path('/run/lock/safir-r71-isolated-ddl.lock')
# R72C's SHA-pinned real-PDO test requires the fixed safir-r72c-pdo-
# stage prefix, in addition to datadir/socket identity and no-network guards.
# Reuse its recognized path pattern for this fresh R73C2 disposable copy;
# distinguish R73C2 by its own script/log markers, not by weakening PHP.
PREFIX='safir-r72c-pdo-'
TABLES=('safir_campaigns','safir_campaign_import_batches','safir_campaign_recipients',
        'safir_campaign_send_attempts','safir_suppressed_phones',
        'safir_campaign_import_files')

def fail(reason):
    print('R73C2_ISOLATED_REHEARSAL=FAIL')
    print('REASON='+reason)
    print('PRODUCTION_DATABASE_MUTATION=NO')
    print('R67_R68_SOURCE_MUTATION=NO')
    print('PROVIDER_SEND=NO')
    raise SystemExit(1)

def sha(path):
    h=hashlib.sha256()
    with path.open('rb') as f:
        for block in iter(lambda:f.read(1048576),b''):
            h.update(block)
    return h.hexdigest()

def pin(path,digest,label):
    if path.is_symlink() or not path.is_file() or sha(path)!=digest:
        fail('PIN_MISMATCH_'+label)

def import_core():
    spec=importlib.util.spec_from_file_location('safir_r72c_pinned_isolated_core',str(CORE))
    if not spec or not spec.loader:
        fail('R72C_PINNED_MODULE_UNAVAILABLE')
    mod=importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod

def preflight():
    if os.geteuid()!=0:
        fail('ROOT_REQUIRED')
    for path,digest,name in ((PREF,PREF_SHA,'REV2_PREFLIGHT'),
                             (CORE,CORE_SHA,'R72C_GUARD'),
                             (R70,R70_SHA,'R70_SQL'),
                             (DDL,DDL_SHA,'R73C2_SQL')):
        pin(path,digest,name)
    try:
        p=subprocess.run(
            [sys.executable,str(PREF),'--check'],stdin=subprocess.DEVNULL,
            stdout=subprocess.PIPE,stderr=subprocess.DEVNULL,text=True,check=False,
            timeout=360,env={'HOME':'/root','LANG':'C','MYSQL_HISTFILE':'/dev/null',
                             'PATH':'/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'})
    except (OSError,subprocess.TimeoutExpired):
        fail('REV2_PREFLIGHT_UNAVAILABLE')
    required={
        'R66B_PINNED_FULL_MANIFEST_RECHECK=PASS',
        'HISTORICAL_R67_879_R68_880_SHA256=PASS',
        'LIVE_SCHEMA_DRIFT_RECORDED_NOT_APPROVED=PASS',
        'R70_STATIC_FIVE_TABLE_DDL_CONTRACT=PASS',
        'R73C2_RECEIPT_SQL_SHA256=PASS',
        'R73C2_NEW_FOREIGN_KEYS=2',
        'R73C2_NEW_CHECKS=8',
        'ISOLATED_MARIADB_VERSION_11_8=PASS',
        'HISTORICAL_R66A_R67_R68_MODIFICATION=NO',
        'SQL_EXECUTED=NO',
        'PRODUCTION_DATABASE_MUTATION=NO',
        'ISOLATED_DATABASE_MUTATION=NO',
        'PROVIDER_SEND=NO',
        'R73C2_REV2_PREFLIGHT=PASS'
    }
    if p.returncode!=0 or not required.issubset(set(p.stdout.splitlines())):
        print('REV2_CHILD_EXIT_CODE='+str(p.returncode))
        for line in p.stdout.splitlines():
            if re.fullmatch(r'(?:R73C2_REV2_PREFLIGHT=FAIL|REASON=[A-Z0-9_]+)',line):
                print('REV2_CHILD_'+line)
        fail('REV2_HISTORICAL_PREFLIGHT_RECHECK_FAILED')
    if LOCK.is_symlink() or not LOCK.parent.is_dir():
        fail('ISOLATION_LOCK_LOCATION_UNSAFE')
    print('=== SAFIR R73C2 ADDITIONAL RECEIPT DDL GUARDED ===')
    print('MODE=STRICT_READ_ONLY_PREFLIGHT')
    print('R66B_HISTORICAL_879_880_FILE_SHA256=PASS')
    print('R70_FIVE_TABLE_DDL_AND_R72C_CORE_PINNED=PASS')
    print('R73C2_ADDITIVE_SCHEMA_SQL_PINNED=PASS')
    print('EXPECTED_FINAL_SAFIR_TABLES=6')
    print('EXPECTED_FOREIGN_KEYS=7')
    print('EXPECTED_CHECK_CONSTRAINTS=17')
    print('LIVE_SCHEMA_DRIFT_APPROVED=NO')
    print('SQL_EXECUTED=NO')
    print('PRODUCTION_DATABASE_MUTATION=NO')
    print('ISOLATED_DATABASE_MUTATION=NO')
    print('R67_R68_SOURCE_MUTATION=NO')
    print('PROVIDER_SEND=NO')
    print('R73C2_ISOLATED_PREFLIGHT=PASS')

def require(core,client,sock,stmt,schema=None):
    p=core.sql(client,sock,stmt,schema,timeout=30)
    if p.returncode:
        fail('ISOLATED_EXPECTED_SQL_FAILED')
    return p.stdout.strip()

def negative(core,client,sock,schema,stmt,err,marker):
    p=core.sql(client,sock,stmt,schema,timeout=25)
    if p.returncode==0 or not re.search(r'ERROR\s+'+str(err)+r'\b',p.stderr):
        print('NEGATIVE_TEST='+marker)
        print('CLIENT_EXIT_CODE='+str(p.returncode))
        found=re.findall(r'ERROR\s+\d+',p.stderr)
        print('SQL_ERROR_CODE='+(found[0] if found else 'NONE'))
        fail('NEGATIVE_CONSTRAINT_INVARIANT_FAILED')
    print('NEGATIVE_TEST_'+marker+'=PASS')

def test_files(core,client,sock,schema):
    if not re.fullmatch(r'[a-zA-Z0-9_]+',schema):
        fail('ISOLATED_SCHEMA_ID_INVALID')
    q=lambda name: chr(96)+schema+chr(96)+'.'+chr(96)+name+chr(96)
    files=q('safir_campaign_import_files')
    batch=q('safir_campaign_import_batches')
    camp=q('safir_campaigns')
    if require(core,client,sock,'SELECT COUNT(*) FROM '+camp+' WHERE id IN (1,2)')!='2':
        fail('R72C_SYNTHETIC_CAMPAIGNS_NOT_FOUND')
    if require(core,client,sock,'SELECT COUNT(*) FROM '+batch+
               ' WHERE import_file_id IS NOT NULL OR chunk_index IS NOT NULL')!='0':
        fail('LEGACY_IMPORT_BATCH_LINKAGE_CHANGED')
    print('LEGACY_R72B_BATCHES_STILL_UNLINKED=PASS')
    a,b,c,d,e,f=('a'*64,'b'*64,'c'*64,'d'*64,'e'*64,'f'*64)
    st1='safir-r73c-file-'+'a'*32
    st2='safir-r73c-file-'+'b'*32
    fields='(id,campaign_id,stage_id,source_sha256,template_sha256,chunk_manifest_sha256,source_bytes,source_rows,expected_chunk_count,preliminary_ready_rows,preliminary_skipped_rows)'
    row1="(8001,1,'"+st1+"','"+a+"','"+b+"','"+c+"',100,3,2,3,0)"
    row2="(8002,2,'"+st2+"','"+d+"','"+b+"','"+c+"',100,2,1,2,0)"
    require(core,client,sock,'INSERT INTO '+files+' '+fields+' VALUES '+row1+','+row2)
    print('ISOLATED_VERIFIED_TWO_FILE_RECEIPTS=PASS')
    negative(core,client,sock,schema,
             'INSERT INTO '+files+' '+fields+' VALUES '+
             "(8003,1,'"+st1+"','"+e+"','"+b+"','"+c+"',100,2,1,2,0)",
             1062,'DUPLICATE_STAGE_ID_REJECTED')
    negative(core,client,sock,schema,
             'INSERT INTO '+files+' '+fields+' VALUES '+
             "(8003,1,'safir-r73c-file-"+'c'*32+"','"+a+"','"+b+"','"+c+"',100,2,1,2,0)",
             1062,'DUPLICATE_CAMPAIGN_SOURCE_REJECTED')
    negative(core,client,sock,schema,
             'INSERT INTO '+files+' '+fields+' VALUES '+
             "(8003,1,'invalid','"+e+"','"+b+"','"+c+"',100,2,1,2,0)",
             4025,'INVALID_STAGE_ID_REJECTED')
    negative(core,client,sock,schema,
             'INSERT INTO '+files+' '+fields+' VALUES '+
             "(8003,1,'safir-r73c-file-"+'c'*32+"','"+e+"','"+b+"','"+c+"',100,2,1,1,0)",
             4025,'PRELIMINARY_ROW_TOTAL_REJECTED')
    negative(core,client,sock,schema,
             'UPDATE '+files+" SET state_code='failed' WHERE id=8001",
             4025,'FAILED_STATE_MISSING_REASON_REJECTED')

    cols='(id,campaign_id,source_sha256,state_code,source_rows,accepted_rows,rejected_rows,import_file_id,chunk_index)'
    negative(core,client,sock,schema,
             'INSERT INTO '+batch+' '+cols+' VALUES '+
             "(9100,2,'"+f+"','complete',1,1,0,8001,1)",
             1452,'CROSS_CAMPAIGN_FILE_LINK_REJECTED')
    negative(core,client,sock,schema,
             'INSERT INTO '+batch+' '+cols+' VALUES '+
             "(9100,1,'"+f+"','complete',1,1,0,8999,1)",
             1452,'NONEXISTENT_FILE_LINK_REJECTED')
    negative(core,client,sock,schema,
             'INSERT INTO '+batch+' '+cols+' VALUES '+
             "(9100,1,'"+f+"','complete',1,1,0,8001,NULL)",
             4025,'INCOMPLETE_LINK_PAIR_REJECTED')
    negative(core,client,sock,schema,
             'INSERT INTO '+batch+' '+cols+' VALUES '+
             "(9100,1,'"+f+"','complete',1,1,0,8001,0)",
             4025,'INVALID_CHUNK_NUMBER_REJECTED')
    require(core,client,sock,
            'INSERT INTO '+batch+' '+cols+' VALUES '+
            "(9101,1,'"+'9'*64+"','complete',2,2,0,8001,1)")
    negative(core,client,sock,schema,
             'INSERT INTO '+batch+' '+cols+' VALUES '+
             "(9102,1,'"+'8'*64+"','complete',1,1,0,8001,1)",
             1062,'DUPLICATE_CHUNK_NUMBER_REJECTED')
    require(core,client,sock,
            'UPDATE '+files+" SET state_code='importing',started_at=NOW(6),"+
            'imported_chunk_count=1,accepted_rows=2 WHERE id=8001')
    negative(core,client,sock,schema,
             'UPDATE '+files+" SET state_code='complete',completed_at=NOW(6) WHERE id=8001",
             4025,'PREMATURE_COMPLETION_REJECTED')
    require(core,client,sock,
            'INSERT INTO '+batch+' '+cols+' VALUES '+
            "(9102,1,'"+'8'*64+"','complete',1,1,0,8001,2)")
    require(core,client,sock,
            'UPDATE '+files+" SET state_code='complete',completed_at=NOW(6),"+
            'imported_chunk_count=2,accepted_rows=3 WHERE id=8001')
    if require(core,client,sock,
               'SELECT state_code,imported_chunk_count,accepted_rows,rejected_rows,source_rows FROM '+
               files+' WHERE id=8001')!='complete\t2\t3\t0\t3':
        fail('COMPLETED_RECEIPT_COUNTERS_INVALID')
    negative(core,client,sock,schema,
             'UPDATE '+files+' SET imported_chunk_count=3 WHERE id=8001',
             4025,'EXCESS_CHUNK_PROGRESS_REJECTED')
    negative(core,client,sock,schema,
             'UPDATE '+files+' SET completed_at=NULL WHERE id=8001',
             4025,'COMPLETED_TIMESTAMP_REQUIRED')
    negative(core,client,sock,schema,
             'DELETE FROM '+files+' WHERE id=8001',
             1451,'LINKED_FILE_DELETION_REJECTED')
    if require(core,client,sock,
               'SELECT COUNT(*) FROM '+batch+' WHERE import_file_id=8001')!='2':
        fail('COMPLETED_FILE_BATCH_LINKS_NOT_TWO')
    print('TWO_CHUNK_COMPLETED_FILE_WITH_FK_PROTECTION=PASS')
    # Deliberate schema-only invariant gap: no CHECK compares other-table rows.
    # Never use such a receipt to authorize actual sending.
    require(core,client,sock,
            'UPDATE '+files+" SET state_code='complete',completed_at=NOW(6),"+
            'imported_chunk_count=1,accepted_rows=2 WHERE id=8002')
    if require(core,client,sock,
               'SELECT COUNT(*) FROM '+batch+' WHERE import_file_id=8002')!='0':
        fail('CROSS_TABLE_SCHEMA_GAP_PROBE_UNEXPECTED')
    print('SCHEMA_ONLY_CROSS_TABLE_TERMINAL_ATTESTATION=INSUFFICIENT')
    print('R73C2_APPROVAL_TRANSACTION_GATE=NOT_IMPLEMENTED')
    print('R73C2_NEGATIVE_CONSTRAINT_TESTS=14_PASS')

def apply(core,client,sock,data):
    core.verify_identity(client,sock,data,'1')
    schema=core.restored_schema(client,sock)
    in_list="('"+ "','".join(TABLES) +"')"
    existing=require(core,client,sock,
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='"+
        schema+"' AND TABLE_NAME IN "+in_list)
    if existing!='0':
        fail('HISTORICAL_RESTORE_HAS_SAFIR_TABLE_COLLISION')
    # Reuse SHA-pinned original 5-table SQL, 5 negative constraint tests and
    # the previously passed real PDO integration strictly in this instance.
    core.apply_isolated(client,sock,data)
    core.verify_identity(client,sock,data,'1')
    print('R72C_FIVE_TABLE_DDL_AND_REAL_PDO=PASS')
    if require(core,client,sock,
               "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='"+
               schema+"' AND TABLE_NAME='safir_campaign_import_files'")!='0':
        fail('ISOLATED_FILE_TABLE_ALREADY_PRESENT')
    require(core,client,sock,'SET GLOBAL read_only=OFF')
    core.verify_identity(client,sock,data,'0')
    argv=[client,'--no-defaults','--protocol=socket','--socket='+str(sock),
          '--user=root','--connect-timeout=3','--batch','--database='+schema]
    p=core.command(argv,timeout=150,input_sql=
        'SET SESSION foreign_key_checks=1; SET SESSION check_constraint_checks=1;\n'+
        DDL.read_text(encoding='utf-8'))
    if p.returncode:
        codes=re.findall(r'ERROR\s+\d+',p.stderr)
        print('ISOLATED_DDL_CLIENT_EXIT='+str(p.returncode))
        print('ISOLATED_DDL_ERROR_CODE='+(codes[0] if codes else 'NONE'))
        fail('R73C2_ADDITIVE_DDL_FAILED')
    got=require(core,client,sock,
        "SELECT COUNT(*),SUM(ENGINE='InnoDB') FROM information_schema.TABLES WHERE "+
        "TABLE_SCHEMA='"+schema+"' AND TABLE_NAME IN "+in_list)
    if got!='6\t6':
        fail('SIX_INNODB_SAFIR_TABLES_MISSING')
    if require(core,client,sock,
               "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='"+
               schema+"' AND TABLE_TYPE='BASE TABLE'")!='191':
        fail('HISTORICAL_185_PLUS_SIX_TABLES_CHANGED')
    if require(core,client,sock,
               "SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE "+
               "CONSTRAINT_SCHEMA='"+schema+"' AND TABLE_NAME IN "+in_list)!='7':
        fail('ISOLATED_SEVEN_FOREIGN_KEYS_MISSING')
    if require(core,client,sock,
               "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE "+
               "CONSTRAINT_SCHEMA='"+schema+"' AND CONSTRAINT_TYPE='CHECK' "+
               "AND TABLE_NAME IN "+in_list)!='17':
        fail('ISOLATED_SEVENTEEN_CHECKS_MISSING')
    print('ISOLATED_HISTORICAL_185_PLUS_6_SAFIR_TABLES=PASS')
    print('ISOLATED_SIX_INNODB_SAFIR_TABLES=PASS')
    print('ISOLATED_SEVEN_FOREIGN_KEYS=PASS')
    print('ISOLATED_SEVENTEEN_CHECK_CONSTRAINTS=PASS')
    test_files(core,client,sock,schema)
    require(core,client,sock,'SET GLOBAL read_only=ON')
    core.verify_identity(client,sock,data,'1')
    print('ISOLATED_READONLY_RESTORED=PASS')

def stop(core,proc,client,sock):
    if proc is None or proc.poll() is not None:
        return True
    try:
        core.sql(client,sock,'SHUTDOWN;',timeout=10)
    except (OSError,subprocess.TimeoutExpired):
        pass
    try:
        proc.wait(timeout=15)
    except subprocess.TimeoutExpired:
        proc.terminate()
        try:
            proc.wait(timeout=15)
        except subprocess.TimeoutExpired:
            proc.kill();proc.wait(timeout=10)
    return proc.poll() is not None

def run():
    if not sys.stdin.isatty():
        fail('INTERACTIVE_OPERATOR_CONFIRMATION_REQUIRED')
    print('WARNING=WRITES_TO_NEW_PRIVATE_DISPOSABLE_R68_COPY_ONLY')
    print('WARNING=SENSITIVE_COPIED_DATABASE_RETAINED_PRIVATELY')
    if input('Type exactly DDL R73C2 ISOLATED to continue: ').strip()!='DDL R73C2 ISOLATED':
        fail('OPERATOR_CONFIRMATION_NOT_GIVEN')
    fd=None;proc=None;stage=None;client=None;sock=None;core=None;ok=False
    try:
        fd=os.open(str(LOCK),os.O_CREAT|os.O_RDWR|os.O_NOFOLLOW|os.O_CLOEXEC,0o600)
        lockstat=os.fstat(fd)
        if lockstat.st_uid!=0 or lockstat.st_mode & 0o077:
            fail('SHARED_ISOLATED_LOCK_UNSAFE')
        try:
            fcntl.flock(fd,fcntl.LOCK_EX|fcntl.LOCK_NB)
        except BlockingIOError:
            fail('ISOLATED_REHEARSAL_ALREADY_RUNNING')
        preflight()
        core=import_core()
        files=core.source_files(SOURCE)
        if len(files)!=880:
            fail('PINNED_R68_SOURCE_COUNT_CHANGED')
        stage=OUT/(PREFIX+datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ')+
                   '-'+secrets.token_hex(4))
        if stage.exists() or stage.is_symlink():
            fail('PRIVATE_STAGE_COLLISION')
        stage.mkdir(mode=0o700)
        stage.chmod(0o700)
        data=stage/'data';sock=stage/'db.sock';pid=stage/'db.pid';tmp=stage/'tmp'
        print('ISOLATED_R73C2_STAGE='+str(stage))
        shutil.copytree(SOURCE,data,symlinks=False)
        copied=core.source_files(data)
        if set(copied)!=set(files):
            fail('INDEPENDENT_COPY_FILE_SET_MISMATCH')
        for rel,path in files.items():
            if copied[rel].stat().st_size!=path.stat().st_size or sha(copied[rel])!=sha(path):
                fail('INDEPENDENT_COPY_SHA256_MISMATCH')
        print('INDEPENDENT_R68_COPY_SHA256=PASS')
        mysql=pwd.getpwnam('mysql')
        if mysql.pw_uid==0:
            fail('MYSQL_ACCOUNT_ROOT_FORBIDDEN')
        for parent,dirs,names in os.walk(data,followlinks=False):
            for item in dirs+names:
                os.chown(Path(parent)/item,mysql.pw_uid,mysql.pw_gid,follow_symlinks=False)
        os.chown(data,mysql.pw_uid,mysql.pw_gid)
        tmp.mkdir(mode=0o700);os.chown(tmp,mysql.pw_uid,mysql.pw_gid)
        os.chown(stage,mysql.pw_uid,mysql.pw_gid)
        stage.chmod(0o700)
        server=shutil.which('mariadbd') or '/usr/sbin/mariadbd'
        client=shutil.which('mariadb')
        cmd=[server,'--no-defaults','--datadir='+str(data),'--socket='+str(sock),
             '--pid-file='+str(pid),'--tmpdir='+str(tmp),'--skip-networking',
             '--skip-slave-start','--skip-log-bin','--event-scheduler=OFF',
             '--read-only=ON','--skip-name-resolve',
             '--innodb-buffer-pool-size=128M','--max-connections=8']
        log=stage/'R73C2_ISOLATED_MARIADB.log'
        with log.open('xb') as out:
            os.chmod(log,0o600)
            proc=subprocess.Popen(cmd,user=mysql.pw_uid,group=mysql.pw_gid,
                 stdin=subprocess.DEVNULL,stdout=out,stderr=subprocess.STDOUT,
                 start_new_session=True,env={'HOME':str(stage),'PATH':'/usr/bin:/bin',
                                            'TMPDIR':str(tmp),'LANG':'C'})
            for _ in range(120):
                if proc.poll() is not None:
                    fail('ISOLATED_MARIADB_DAEMON_EARLY_EXIT')
                if sock.is_socket():
                    try:
                        p=core.sql(client,sock,'SELECT 1',timeout=5)
                        if p.returncode==0 and p.stdout.strip()=='1':
                            break
                    except (OSError,subprocess.TimeoutExpired):
                        pass
                time.sleep(1)
            else:
                fail('ISOLATED_MARIADB_START_TIMEOUT')
            core.verify_identity(client,sock,data,'1')
            apply(core,client,sock,data)
            ok=True
    except (OSError,shutil.Error,subprocess.SubprocessError,ValueError) as ex:
        print('R73C2_OPERATION_ERROR_TYPE='+type(ex).__name__)
    finally:
        stopped=(proc is None or proc.poll() is not None) if core is None else stop(core,proc,client,sock)
        print('ISOLATED_DAEMON_STOPPED='+('YES' if stopped else 'NO'))
        if stage:
            try:
                os.chown(stage,0,0);stage.chmod(0o700)
            except OSError:
                print('ISOLATED_PRIVATE_STAGE_RESET_FAILED=YES')
                ok=False
            print('ISOLATED_COPY_RETAINED_PRIVATE='+str(stage))
        print('R67_R68_SOURCE_MUTATION=NO')
        print('PRODUCTION_DATABASE_MUTATION=NO')
        print('PROVIDER_SEND=NO')
        if fd is not None:
            os.close(fd)
        if not stopped:
            fail('ISOLATED_MARIADB_DAEMON_NOT_STOPPED')
    if not ok:
        fail('R73C2_ISOLATED_DDL_REHEARSAL_NOT_PASSED')
    print('R73C2_ISOLATED_REHEARSAL=PASS')
    print('R73C2_PRODUCTION_MIGRATION_APPROVED=NO')
    print('R73C2_APPROVAL_WORKER=NOT_IMPLEMENTED')
    print('NEXT=TRANSACTIONAL_FILE_IMPORT_AND_APPROVAL_GUARDS')

def main():
    parser=argparse.ArgumentParser(description=__doc__)
    choices=parser.add_mutually_exclusive_group(required=True)
    choices.add_argument('--check',action='store_true')
    choices.add_argument('--run-isolated',action='store_true')
    args=parser.parse_args()
    os.umask(0o077)
    preflight()
    if args.check:
        print('NEXT=EXPLICIT_ISOLATED_R73C2_DDL_REHEARSAL_ONLY')
    else:
        run()

if __name__=='__main__':
    main()
