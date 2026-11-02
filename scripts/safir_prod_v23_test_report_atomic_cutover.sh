#!/usr/bin/env bash
# Safir V2.3 Portal-only atomic switch. No provider calls or configuration edits.
set -Eeuo pipefail
umask 077
fail(){ echo "FAIL=$*" >&2; exit 1; }
[ "$(id -u)" = 0 ] || fail ROOT_REQUIRED
BASE=/srv/troca/portal-test
OLD="$BASE/releases/prod-safir-ui-v22-20261102-3a567228"
NEW="$BASE/releases/prod-safir-v23-testreport-20261102-af9b225e"
CURRENT="$BASE/current"
RECEIPT="$NEW.safir-v23-receipt"
SUCCESS="$NEW.safir-v23-cutover"
HOST=troca.corc.ir
CATALOG="$BASE/shared/storage/private/bale-safir/templates.json"
exec 9>/run/lock/ipkf-safir-portal-cutover.lock
flock -n 9 || fail LOCK_BUSY
source /etc/ipkf-release/contract.env
[ "$PORTAL_BASE" = "$BASE" ] && [ "$PORTAL_USER" = troca-portal-test ] || fail CONTRACT_INVALID
[ -L "$CURRENT" ] && [ "$(readlink -e "$CURRENT")" = "$OLD" ] || fail CURRENT_CHANGED
[ -f "$OLD.safir-ui-v22-cutover" ] || fail OLD_RECEIPT_MISSING
[ -d "$NEW" ] && [ ! -L "$NEW" ] && [ -f "$RECEIPT" ] && [ ! -e "$SUCCESS" ] || fail CANDIDATE_INVALID
for entry in SAFIR_V23_CANDIDATE=PASS SOURCE_COMMIT=af9b225e6392c72a7f7f686d8862bdc7820123de \
 "PREVIOUS_RELEASE=$OLD" "CANDIDATE_RELEASE=$NEW" CHANGED_FILES=4 \
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
app/Services/BaleSafirCampaignService.php|9767a4a061cd525be22b7f126b9e5b4d5dbd16b4|25d7810d5a4219f77f2d6939ae3667cc838c5e4c
app/Services/BaleSafirTestReportService.php|NEW|07cc8a3fe10dc8aa293aa84ba5c3a85d4a58c3a6
resources/views/admin/partials/bale-safir-campaign.php|ff8c614b64dcaed25b91b8c39765976bdaf82cfa|6a713200176b9215ad72bdf5fc199ed0f0d2a1ce
routes/bale-safir-campaigns.php|528fea17780163e298a8da8cf9c0402a490235d9|52c38ee3b582b75c291a664c256f89a7c02049e3
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
 \IPKF\Support\Env::loadLayered(BASE_PATH."/.env");
 foreach(["BALE_SAFIR_SEND_ENABLED","BALE_SAFIR_PRODUCTION_APPROVED",
 "BALE_SAFIR_TEST_SEND_ENABLED","BALE_SAFIR_TEST_PRODUCTION_APPROVED"] as $flag){
 if(filter_var(\IPKF\Support\Env::get($flag,false),FILTER_VALIDATE_BOOLEAN))exit(12);
 }
 if((string)\IPKF\Support\Env::get("BALE_SAFIR_CATALOG_PATH","")!==$argv[2])exit(13);
 echo "ROUTE_AND_FOUR_DISABLED_SEND_GATES=PASS\n";
' "$NEW" "$CATALOG" || fail RUNTIME_NOT_READY
nginx -t >/dev/null 2>&1 || fail NGINX_INVALID
[ "$(systemctl is-active nginx)" = active ] && [ "$(systemctl is-active php8.4-fpm)" = active ] || fail SERVER_NOT_ACTIVE
for site in /etc/nginx/sites-enabled/troca-portal-corc /etc/nginx/sites-enabled/troca-portal-test; do
 grep -Fq 'fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;' "$(readlink -e "$site")" || fail PORTAL_FASTCGI_STALE
done
echo RUNTIME_SAFETY=PASS
WORK="$(mktemp -d "$BASE/.safir-v23-cutover-XXXXXXXX")"
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
( set -C; printf 'STATUS=SUCCESS\nACTIVE_BEFORE=%s\nCURRENT_RELEASE=%s\nBULK_SEND=DISABLED\nTEST_SEND=DISABLED\n' "$OLD" "$NEW" > "$SUCCESS" ) || fail RECEIPT_WRITE_FAILED
chmod 0600 "$SUCCESS"
DONE=YES
echo SAFIR_V23_PORTAL_CUTOVER=PASS
echo "CURRENT_RELEASE=$NEW"
echo "PREVIOUS_RELEASE=$OLD"
echo OTHER_MODULES_UNTOUCHED=YES
echo DB_MUTATION=NO
echo BULK_SEND=NO
echo MANUAL_TEST_SEND=NO
echo NEXT=CONFIGURE_SINGLE_TEST_RECIPIENT_AND_CREDENTIALS
