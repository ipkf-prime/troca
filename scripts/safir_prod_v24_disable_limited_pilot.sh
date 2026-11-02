#!/usr/bin/env bash
# Safir V2.4: close pilot and bulk gates; no provider call.
set -Eeuo pipefail
set +x
umask 077
fail(){ echo "FAIL=$*" >&2; exit 1; }
[ "$(id -u)" = 0 ] || fail ROOT_REQUIRED
BASE=/srv/troca/portal-test
REL="$BASE/releases/prod-safir-v24-rbac-preserved-20261102-13d45156"
ENVFILE="$REL/.env"
exec 9>/run/lock/ipkf-safir-portal-cutover.lock
flock -n 9 || fail LOCK_BUSY
[ -L "$BASE/current" ] && [ "$(readlink -e "$BASE/current")" = "$REL" ] || fail V24_NOT_ACTIVE
[ -f "$REL.safir-v24-cutover" ] || fail RECEIPT_MISSING
[ -f "$ENVFILE" ] && [ ! -L "$ENVFILE" ] || fail ENV_MISSING
[ "$(stat -c %a "$ENVFILE")" = 600 ] && [ "$(stat -c %U "$ENVFILE")" = troca-portal-test ] || fail ENV_PERMISSIONS
BACKUP_DIR=/var/backups/ipkf-safir
install -d -m 0700 "$BACKUP_DIR"
BACKUP="$BACKUP_DIR/portal-env-v24-before-disable-$(date -u +%Y%m%dT%H%M%SZ).bak"
install -o root -g root -m 0600 "$ENVFILE" "$BACKUP"
python3 - "$ENVFILE" <<'PY'
import os,re,sys,tempfile
path=sys.argv[1]
update={'BALE_SAFIR_PILOT_ENABLED':'false','BALE_SAFIR_PILOT_PRODUCTION_APPROVED':'false','BALE_SAFIR_PILOT_PHONES':'','BALE_SAFIR_SEND_ENABLED':'false','BALE_SAFIR_PRODUCTION_APPROVED':'false'}
seen=set();out=[]
for line in open(path,encoding='utf-8').read().splitlines():
 m=re.match(r'^([A-Za-z_][A-Za-z0-9_]*)\s*=',line)
 if m and m.group(1) in update:
  k=m.group(1)
  if k in seen:raise SystemExit('FAIL=DUPLICATE_ENV:'+k)
  seen.add(k);out.append(k+'='+update[k])
 else:out.append(line)
for k,v in update.items():
 if k not in seen:out.append(k+'='+v)
fd,tmp=tempfile.mkstemp(prefix='.safir-disable-',dir=os.path.dirname(path))
try:
 st=os.stat(path);os.fchmod(fd,0o600);os.fchown(fd,st.st_uid,st.st_gid)
 with os.fdopen(fd,'w',encoding='utf-8') as f:
  f.write('\n'.join(out)+'\n');f.flush();os.fsync(f.fileno())
 os.replace(tmp,path)
except:
 try:os.unlink(tmp)
 except OSError:pass
 raise
PY
if ! runuser -u troca-portal-test -- php -r '
 define("BASE_PATH",$argv[1]);require BASE_PATH."/system/Support/Env.php";
 \IPKF\Support\Env::loadLayered(BASE_PATH."/.env");
 $g=static fn($k)=>(string)\IPKF\Support\Env::get($k,"");
 foreach(["BALE_SAFIR_PILOT_ENABLED","BALE_SAFIR_PILOT_PRODUCTION_APPROVED",
         "BALE_SAFIR_SEND_ENABLED","BALE_SAFIR_PRODUCTION_APPROVED"] as $k)
  if(filter_var($g($k),FILTER_VALIDATE_BOOLEAN))exit(11);
 if($g("BALE_SAFIR_PILOT_PHONES")!=="")exit(12);
 echo "PILOT_DISABLED=PASS\nBULK_DISABLED=PASS\n";
' "$REL"; then
 install -o troca-portal-test -g troca-portal-test -m 0600 "$BACKUP" "$ENVFILE"
 fail DISABLE_VALIDATION_FAILED_AND_ROLLED_BACK
fi
echo "NO_MESSAGE_SEND=YES"
echo "ENV_BACKUP=$BACKUP"
