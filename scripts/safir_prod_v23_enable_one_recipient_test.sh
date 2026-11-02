#!/usr/bin/env bash
# Safir V23: prompt for API key and the one permitted recipient, no API calls.
set -Eeuo pipefail
set +x
umask 077
die(){ printf 'FAIL=%s\n' "$*" >&2; exit 1; }
[ "$(id -u)" = 0 ] || die ROOT_REQUIRED
BASE=/srv/troca/portal-test
EXPECTED="$BASE/releases/prod-safir-v23-testreport-20261102-af9b225e"
ENVFILE="$EXPECTED/.env"
AUDIT="$BASE/shared/storage/private/bale-safir/manual-attempts"
exec 9>/run/lock/ipkf-safir-portal-cutover.lock
flock -n 9 || die PORTAL_LOCK_BUSY
[ -L "$BASE/current" ] && [ "$(readlink -e "$BASE/current")" = "$EXPECTED" ] || die V23_NOT_ACTIVE
[ -f "$EXPECTED.safir-v23-cutover" ] || die V23_RECEIPT_MISSING
[ -f "$ENVFILE" ] && [ ! -L "$ENVFILE" ] || die ENV_MISSING
[ "$(stat -c %a "$ENVFILE")" = 600 ] && [ "$(stat -c %U "$ENVFILE")" = troca-portal-test ] || die ENV_PERMISSION
[ -d "$(dirname "$AUDIT")" ] && [ ! -L "$(dirname "$AUDIT")" ] || die PRIVATE_CATALOG_MISSING
if grep -Eq '^BALE_SAFIR_(SEND_ENABLED|PRODUCTION_APPROVED)=(true|1|yes|on)$' "$ENVFILE"; then die BULK_SEND_ALREADY_ENABLED; fi
read -r -p 'شماره تنها مخاطب مجاز تست (مثل 0912xxxxxxxx): ' INPUT_PHONE
PHONE="$(python3 - "$INPUT_PHONE" <<'PY'
import sys,re
v=sys.argv[1].strip().translate(str.maketrans('۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩','01234567890123456789'))
v=re.sub(r'[\s()+-]','',v)
if re.fullmatch(r'09[0-9]{9}',v):v='98'+v[1:]
elif re.fullmatch(r'9[0-9]{9}',v):v='98'+v
elif re.fullmatch(r'00989[0-9]{9}',v):v=v[2:]
if not re.fullmatch(r'989[0-9]{9}',v):sys.exit(1)
print(v)
PY
)" || die INVALID_PHONE
echo "TEST_PHONE=98*****$(printf '%s' "$PHONE" | tail -c 4)"
read -r -p 'برای تأیید فقط همین شماره، بنویس APPROVE_ONE_TEST_PHONE: ' APPROVAL
[ "$APPROVAL" = APPROVE_ONE_TEST_PHONE ] || die APPROVAL_NOT_GIVEN
unset APPROVAL INPUT_PHONE
read -r -s -p 'API Access Key سفیر (مخفی): ' KEY
printf '\n'
[ "$(printf '%s' "$KEY" | wc -c)" -ge 12 ] || die API_KEY_TOO_SHORT
[ "$(printf '%s' "$KEY" | wc -c)" -le 512 ] || die API_KEY_TOO_LONG
install -d -o troca-portal-test -g troca-portal-test -m 0700 "$AUDIT"
[ ! -L "$AUDIT" ] || die AUDIT_SYMLINK_FORBIDDEN
BACKUP_DIR=/var/backups/ipkf-safir
install -d -m 0700 "$BACKUP_DIR"
BACKUP="$BACKUP_DIR/portal-env-v23-before-test-$(date -u +%Y%m%dT%H%M%SZ).bak"
install -o root -g root -m 0600 "$ENVFILE" "$BACKUP"
exec 3<<< "$KEY"
unset KEY
python3 - "$ENVFILE" "$PHONE" "$AUDIT" 3<&3 <<'PY'
import os,re,sys,tempfile
path,phone,audit=sys.argv[1:]
key=os.read(3,8192).decode().strip()
if not re.fullmatch(r'[A-Za-z0-9._~+/=-]{12,512}',key):
 raise SystemExit('FAIL=API_KEY_HAS_UNSUPPORTED_CHARACTERS')
update={
 'BALE_SAFIR_API_ACCESS_KEY':key,
 'BALE_SAFIR_TEST_PHONE':phone,
 'BALE_SAFIR_TEST_ATTEMPT_DIR':audit,
 'BALE_SAFIR_TEST_SEND_ENABLED':'true',
 'BALE_SAFIR_TEST_PRODUCTION_APPROVED':'true',
 'BALE_SAFIR_SEND_ENABLED':'false',
 'BALE_SAFIR_PRODUCTION_APPROVED':'false'
}
old=open(path,encoding='utf-8').read().splitlines()
seen=set();out=[]
for line in old:
 m=re.match(r'^([A-Za-z_][A-Za-z0-9_]*)\s*=',line)
 if m and m.group(1) in update:
  k=m.group(1)
  if k in seen:raise SystemExit('FAIL=DUPLICATE_ENV:'+k)
  seen.add(k);out.append(k+'='+update[k])
 else:out.append(line)
for k,v in update.items():
 if k not in seen:out.append(k+'='+v)
d=os.path.dirname(path);fd,tmp=tempfile.mkstemp(prefix='.safir-env-',dir=d)
try:
 st=os.stat(path);os.fchmod(fd,0o600);os.fchown(fd,st.st_uid,st.st_gid)
 with os.fdopen(fd,'w',encoding='utf-8') as file:
  file.write('\n'.join(out)+'\n');file.flush();os.fsync(file.fileno())
 os.replace(tmp,path)
except:
 try:os.unlink(tmp)
 except OSError:pass
 raise
PY
exec 3<&-
unset PHONE
if ! runuser -u troca-portal-test -- php -r '
 define("BASE_PATH",$argv[1]);require BASE_PATH."/system/Support/Env.php";
 \IPKF\Support\Env::loadLayered(BASE_PATH."/.env");
 $g=static fn($k)=>(string)\IPKF\Support\Env::get($k,"");
 $f=static fn($k)=>filter_var(\IPKF\Support\Env::get($k,false),FILTER_VALIDATE_BOOLEAN);
 if(strlen($g("BALE_SAFIR_API_ACCESS_KEY"))<12 || !preg_match("/^989[0-9]{9}$/",$g("BALE_SAFIR_TEST_PHONE")))exit(11);
 if(!$f("BALE_SAFIR_TEST_SEND_ENABLED") || !$f("BALE_SAFIR_TEST_PRODUCTION_APPROVED"))exit(12);
 if($f("BALE_SAFIR_SEND_ENABLED") || $f("BALE_SAFIR_PRODUCTION_APPROVED"))exit(13);
 if(!is_dir($g("BALE_SAFIR_TEST_ATTEMPT_DIR")) || !is_writable($g("BALE_SAFIR_TEST_ATTEMPT_DIR")))exit(14);
 echo "TEST_ONLY_ENV=PASS\nBULK_SEND_DISABLED=PASS\nAUDIT_READY=PASS\n";
' "$EXPECTED"; then
 install -o troca-portal-test -g troca-portal-test -m 0600 "$BACKUP" "$ENVFILE"
 die TEST_CONFIG_FAILED_AND_ROLLED_BACK
fi
echo SAFIR_SINGLE_NUMBER_TEST_READY=PASS
echo API_KEY=SET_PRIVATELY
echo TEST_ATTEMPT_REPORT=ENABLED
echo BULK_SEND=DISABLED
echo "ENV_BACKUP=$BACKUP"
echo NEXT=TEST_ONE_INVITE_MESSAGE_IN_AUTHENTICATED_PORTAL
