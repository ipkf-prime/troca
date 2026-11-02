#!/usr/bin/env bash
# Safir V2.4 Portal-only atomic switch. No provider calls or environment edits.
set -Eeuo pipefail
umask 077
fail(){ echo "FAIL=$*" >&2; exit 1; }
[ "$(id -u)" = 0 ] || fail ROOT_REQUIRED
BASE=/srv/troca/portal-test
OLD="$BASE/releases/prod-safir-v23-testreport-20261102-af9b225e"
NEW="$BASE/releases/prod-safir-v24-pilot-rbac-20261102-6ae83579"
CURRENT="$BASE/current"
RECEIPT="$NEW.safir-v24-receipt"
SUCCESS="$NEW.safir-v24-cutover"
HOST=troca.corc.ir
CATALOG="$BASE/shared/storage/private/bale-safir/templates.json"
exec 9>/run/lock/ipkf-safir-portal-cutover.lock
flock -n 9 || fail LOCK_BUSY
source /etc/ipkf-release/contract.env
[ "$PORTAL_BASE" = "$BASE" ] && [ "$PORTAL_USER" = troca-portal-test ] || fail CONTRACT_INVALID
[ -L "$CURRENT" ] && [ "$(readlink -e "$CURRENT")" = "$OLD" ] || fail CURRENT_CHANGED
[ -f "$OLD.safir-v23-cutover" ] || fail OLD_RECEIPT_MISSING
[ -d "$NEW" ] && [ ! -L "$NEW" ] && [ -f "$RECEIPT" ] && [ ! -e "$SUCCESS" ] || fail CANDIDATE_INVALID
for entry in SAFIR_V24_CANDIDATE=PASS SOURCE_COMMIT=6ae83579e7e7458a8ba46dffbde71eaa4180b8f9 \
 "PREVIOUS_RELEASE=$OLD" "CANDIDATE_RELEASE=$NEW" CHANGED_FILES=3 \
 CUTOVER=NO DB_MUTATION=NO BULK_SEND=NO TEST_SEND=NO; do
 grep -Fxq "$entry" "$RECEIPT" || fail CANDIDATE_RECEIPT_INVALID
done
# Precise baseline + new blobs, checked before any current symlink mutation.
while IFS='|' read -r path old new; do
 [ -f "$NEW/$path" ] && [ ! -L "$NEW/$path" ] && [ "$(git hash-object "$NEW/$path")" = "$new" ] || fail "CANDIDATE_BLOB_INVALID:$path"
 if [ "$old" = NEW ]; then
  [ ! -e "$OLD/$path" ] && [ ! -L "$OLD/$path" ] || fail "UNEXPECTED_OLD_REPORT:$path"
 else
  [ -f "$OLD/$path" ] && [ "$(git hash-object "$OLD/$path")" = "$old" ] || fail "BASELINE_CHANGED:$path"
 fi
done <<'PINS'
app/Services/AdminNavigationRbacService.php|9db72cc23d292e9979fbcf0f7bbca348f3d48ce0|aafc313b2d6b21ffb73dfe655f85e3e7e2cb824b
app/Services/BaleSafirCampaignService.php|25d7810d5a4219f77f2d6939ae3667cc838c5e4c|96b6d8e4d4d0d9c393ae85a813354d1789c23d16
resources/views/admin/partials/bale-safir-campaign.php|6a713200176b9215ad72bdf5fc199ed0f0d2a1ce|bf15d0b990bc232dfa14f14ed9282d764ba4fc2c
PINS
echo SOURCE_INTEGRITY=PASS
[ -f "$CATALOG" ] && [ ! -L "$CATALOG" ] || fail CATALOG_MISSING
cmp -s "$OLD/.env" "$NEW/.env" || fail PRIVATE_ENV_MISMATCH
[ "$(stat -c %a "$NEW/.env")" = 600 ] && [ "$(stat -c %U "$NEW/.env")" = troca-portal-test ] || fail PRIVATE_ENV_MODE
for p in storage public/uploads; do
 [ -L "$NEW/$p" ] && [ "$(readlink "$NEW/$p")" = "$BASE/shared/$p" ] || fail SHARED_LINK_MISMATCH
done
runuser -u troca-portal-test -- php -r '
 define("BASE_PATH",$argv[1]);$a=require BASE_PATH."/bootstrap/app.php";
 $r=(new ReflectionProperty($a->router(),"routes"))->getValue($a->router());
 foreach([["GET","/admin/communications/safir"],["GET","/admin/communications/safir/test-report.csv"],
 ["POST","/admin/communications/settings/safir"]] as [$m,$p])if(!isset($r[$m][$p]))exit(11);
 $nav=new \App\Services\AdminNavigationRbacService();
 if(($nav->permissionForPath("/admin/communications/safir/test-report.csv")??"")!=="notifications.send.manage")exit(15);
 \IPKF\Support\Env::loadLayered(BASE_PATH."/.env");
 foreach(["BALE_SAFIR_SEND_ENABLED","BALE_SAFIR_PRODUCTION_APPROVED",
 "BALE_SAFIR_PILOT_ENABLED","BALE_SAFIR_PILOT_PRODUCTION_APPROVED"] as $flag){
 if(filter_var(\IPKF\Support\Env::get($flag,false),FILTER_VALIDATE_BOOLEAN))exit(12);
 }
 if((string)\IPKF\Support\Env::get("BALE_SAFIR_CATALOG_PATH","")!==$argv[2])exit(13);
 echo "ROUTE_RBAC_AND_BULK_PILOT_LOCKS=PASS\n";
' "$NEW" "$CATALOG" || fail RUNTIME_NOT_READY
nginx -t >/dev/null 2>&1 || fail NGINX_INVALID
[ "$(systemctl is-active nginx)" = active ] && [ "$(systemctl is-active php8.4-fpm)" = active ] || fail SERVER_NOT_ACTIVE
for site in /etc/nginx/sites-enabled/troca-portal-corc /etc/nginx/sites-enabled/troca-portal-test; do
 grep -Fq 'fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;' "$(readlink -e "$site")" || fail PORTAL_FASTCGI_STALE
done
echo RUNTIME_SAFETY=PASS
WORK="$(mktemp -d "$BASE/.safir-v24-cutover-XXXXXXXX")"
ARMED=NO
DONE=NO
probe(){ curl -sS --noproxy '*' --connect-timeout 3 --max-time 15 --resolve "$HOST:443:127.0.0.1" -H 'Cache-Control: no-cache' -o "$WORK/body" -w '%{http_code}' "https://$HOST$1"; }
health(){ [ "$(probe /health)" = 200 ] && php -r '$a=json_decode(file_get_contents($argv[1]),true);exit(($a["status"]??null)==="ok"?0:1);' "$WORK/body"; }
cleanup(){
 rc=$?
 trap - EXIT
 if [ "$ARMED" = YES ] && [ "$DONE" = NO ] && [ "$(readlink -e "$CURRENT" 2>/dev/null || true)" = "$NEW" ]; then
  echo AUTO_ROLLBACK=STARTED
  if ln -s "$OLD" "$WORK/rollback" && mv -Tf "$WORK/rollback" "$CURRENT" && health; then
   echo AUTO_ROLLBACK=PASS
  else
   echo AUTO_ROLLBACK=NEEDS_MANUAL_REVIEW >&2
   rc=97
  fi
 fi
 rm -rf -- "$WORK"
 exit "$rc"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
health || fail OLD_HEALTH
[ "$(probe /admin/login)" = 200 ] || fail OLD_LOGIN
[ "$(probe /admin/communications/safir)" = 302 ] || fail OLD_GUEST_ROUTE
echo BEFORE_HTTP=PASS
[ "$(readlink -e "$CURRENT")" = "$OLD" ] || fail CURRENT_MOVED
ln -s "$NEW" "$WORK/next"
ARMED=YES
mv -Tf "$WORK/next" "$CURRENT" || fail SWAP_FAILED
[ "$(readlink -e "$CURRENT")" = "$NEW" ] || fail NEW_NOT_ACTIVE
echo ATOMIC_PORTAL_SWITCH=PASS
health || fail NEW_HEALTH
[ "$(probe /admin/login)" = 200 ] || fail NEW_LOGIN
for url in /admin/communications/safir \
 '/admin/communications/safir?test=invite' /admin/communications/safir/test-report.csv; do
 [ "$(probe "$url")" = 302 ] || fail "NEW_GUEST_GUARD_FAILED:$url"
done
echo NEW_HTTP_GUEST_AND_REPORT_ROUTE=PASS
[ "$(readlink -e "$CURRENT")" = "$NEW" ] || fail POST_SWAP_CURRENT_CHANGED
( set -C; printf 'STATUS=SUCCESS\nACTIVE_BEFORE=%s\nCURRENT_RELEASE=%s\nBULK_SEND=DISABLED\nTEST_SEND=UNCHANGED\n' "$OLD" "$NEW" > "$SUCCESS" ) || fail RECEIPT_WRITE_FAILED
chmod 0600 "$SUCCESS"
DONE=YES
echo SAFIR_V24_PORTAL_CUTOVER=PASS
echo "CURRENT_RELEASE=$NEW"
echo "PREVIOUS_RELEASE=$OLD"
echo OTHER_MODULES_UNTOUCHED=YES
echo DB_MUTATION=NO
echo BULK_SEND=NO
echo MANUAL_TEST_SEND=NO
echo NEXT=CHECK_AUTHENTICATED_CSV_THEN_CONFIGURE_LIMITED_PILOT
