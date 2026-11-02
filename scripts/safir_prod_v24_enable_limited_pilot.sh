#!/usr/bin/env bash
# Safir v2.4: exactly 2 or 3 approved phone numbers, no sends.
set -Eeuo pipefail
set +x
umask 077
fail(){ printf 'FAIL=%s\n' "$*" >&2; exit 1; }
[ "$(id -u)" = 0 ] || fail ROOT_REQUIRED
BASE=/srv/troca/portal-test
REL="$BASE/releases/prod-safir-v24-pilot-rbac-20261102-6ae83579"
ENVFILE="$REL/.env"
exec 9>/run/lock/ipkf-safir-portal-cutover.lock
flock -n 9 || fail LOCK_BUSY
[ -L "$BASE/current" ] && [ "$(readlink -e "$BASE/current")" = "$REL" ] || fail V24_NOT_ACTIVE
[ -f "$REL.safir-v24-cutover" ] || fail V24_RECEIPT_MISSING
[ -f "$ENVFILE" ] && [ ! -L "$ENVFILE" ] || fail ENV_MISSING
[ "$(stat -c %a "$ENVFILE")" = 600 ] && [ "$(stat -c %U "$ENVFILE")" = troca-portal-test ] || fail ENV_PERMISSIONS
if grep -Eq '^BALE_SAFIR_(SEND_ENABLED|PRODUCTION_APPROVED|PILOT_ENABLED|PILOT_PRODUCTION_APPROVED)=(true|1|yes|on)$' "$ENVFILE"; then
 fail EXISTING_SEND_FLAG_ENABLED
fi
runuser -u troca-portal-test -- php -r '
 define("BASE_PATH",$argv[1]);require BASE_PATH."/system/Support/Env.php";
 \IPKF\Support\Env::loadLayered(BASE_PATH."/.env");
 $g=static fn($k)=>(string)\IPKF\Support\Env::get($k,"");
 if(strlen($g("BALE_SAFIR_API_ACCESS_KEY"))<12)exit(11);
 foreach(["BALE_SAFIR_SEND_ENABLED","BALE_SAFIR_PRODUCTION_APPROVED","BALE_SAFIR_PILOT_ENABLED","BALE_SAFIR_PILOT_PRODUCTION_APPROVED"] as $f)
  if(filter_var($g($f),FILTER_VALIDATE_BOOLEAN))exit(12);
 echo "PILOT_BEFORE=LOCKED\nBULK_BEFORE=LOCKED\n";
' "$REL" || fail EFFECTIVE_BASELINE_UNSAFE

read -r -p 'تعداد شماره های تست گروهی (فقط 2 یا 3): ' COUNT
case "$COUNT" in 2|3) :;; *) fail PILOT_COUNT_INVALID;; esac
PHONES_CSV=""
for ((i=1;i<=COUNT;i++)); do
 read -r -p "شماره مجاز $i (09...): " INPUT
 PHONE="$(python3 - "$INPUT" <<'PY'
import sys,re
v=sys.argv[1].strip().translate(str.maketrans('۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩','01234567890123456789'))
v=re.sub(r'[\s()+-]','',v)
if re.fullmatch(r'09[0-9]{9}',v):v='98'+v[1:]
elif re.fullmatch(r'9[0-9]{9}',v):v='98'+v
elif re.fullmatch(r'00989[0-9]{9}',v):v=v[2:]
if not re.fullmatch(r'989[0-9]{9}',v):sys.exit(1)
print(v)
PY
)" || fail INVALID_PHONE
 case ",$PHONES_CSV," in *,"$PHONE",*) fail DUPLICATE_PILOT_PHONE;; esac
 if [ -z "$PHONES_CSV" ]; then PHONES_CSV="$PHONE"; else PHONES_CSV="$PHONES_CSV,$PHONE"; fi
 echo "PILOT_NUMBER_$i=98*****$(printf %s "$PHONE" | tail -c 4)"
done
unset INPUT PHONE
read -r -p 'برای تأیید فقط همین فهرست بنویس APPROVE_2_OR_3_SAFIR_PILOT: ' APPROVAL
[ "$APPROVAL" = APPROVE_2_OR_3_SAFIR_PILOT ] || fail APPROVAL_NOT_GIVEN
unset APPROVAL
BACKUP_DIR=/var/backups/ipkf-safir
install -d -m 0700 "$BACKUP_DIR"
BACKUP="$BACKUP_DIR/portal-env-v24-before-pilot-$(date -u +%Y%m%dT%H%M%SZ).bak"
install -o root -g root -m 0600 "$ENVFILE" "$BACKUP"
python3 - "$ENVFILE" "$PHONES_CSV" <<'PY'
import os,re,sys,tempfile
path,phones=sys.argv[1:]
items=phones.split(',')
if not 2<=len(items)<=3 or len(set(items))!=len(items) or any(not re.fullmatch(r'989[0-9]{9}',i) for i in items):raise SystemExit('FAIL=INVALID_ALLOWLIST')
changes={'BALE_SAFIR_PILOT_PHONES':phones,'BALE_SAFIR_PILOT_ENABLED':'true','BALE_SAFIR_PILOT_PRODUCTION_APPROVED':'true','BALE_SAFIR_SEND_ENABLED':'false','BALE_SAFIR_PRODUCTION_APPROVED':'false'}
seen=set();out=[]
for line in open(path,encoding='utf-8').read().splitlines():
 m=re.match(r'^([A-Za-z_][A-Za-z0-9_]*)\s*=',line)
 if m and m.group(1) in changes:
  key=m.group(1)
  if key in seen:raise SystemExit('FAIL=DUPLICATE_ENV:'+key)
  seen.add(key);out.append(key+'='+changes[key])
 else:out.append(line)
for k,v in changes.items():
 if k not in seen:out.append(k+'='+v)
fd,tmp=tempfile.mkstemp(prefix='.safir-pilot-',dir=os.path.dirname(path))
try:
 st=os.stat(path);os.fchmod(fd,0o600);os.fchown(fd,st.st_uid,st.st_gid)
 with os.fdopen(fd,'w',encoding='utf-8') as fh:
  fh.write('\n'.join(out)+'\n');fh.flush();os.fsync(fh.fileno())
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
 if(!hash_equals($argv[2],$g("BALE_SAFIR_PILOT_PHONES")))exit(11);
 foreach(["BALE_SAFIR_PILOT_ENABLED","BALE_SAFIR_PILOT_PRODUCTION_APPROVED"] as $f)
  if(!filter_var($g($f),FILTER_VALIDATE_BOOLEAN))exit(12);
 foreach(["BALE_SAFIR_SEND_ENABLED","BALE_SAFIR_PRODUCTION_APPROVED"] as $f)
  if(filter_var($g($f),FILTER_VALIDATE_BOOLEAN))exit(13);
 if(strlen($g("BALE_SAFIR_API_ACCESS_KEY"))<12)exit(14);
 echo "PILOT_ALLOWLIST_RUNTIME=PASS\nBULK_SEND_DISABLED=PASS\n";
' "$REL" "$PHONES_CSV"; then
 install -o troca-portal-test -g troca-portal-test -m 0600 "$BACKUP" "$ENVFILE"
 fail PILOT_CONFIG_FAILED_ROLLED_BACK
fi
unset PHONES_CSV
echo SAFIR_LIMITED_PILOT_READY=PASS
echo PROVIDER_POST_DURING_SETUP=NO
echo BULK_SEND_DISABLED=YES
echo "ENV_BACKUP=$BACKUP"
echo NEXT=UPLOAD_EXACT_TWO_OR_THREE_ROW_XLSX
