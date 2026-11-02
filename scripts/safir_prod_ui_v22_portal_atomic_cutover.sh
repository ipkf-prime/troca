#!/usr/bin/env bash
# Safir UI v2.2: Portal-only atomic release, guarded and reversible.
set -Eeuo pipefail
umask 077
fail(){ echo "FAIL=$*" >&2; exit 1; }
[ "$(id -u)" = 0 ] || fail ROOT_REQUIRED
BASE=/srv/troca/portal-test
OLD="$BASE/releases/prod-safir-ui-v21-20261101-4a53e8c0"
NEW="$BASE/releases/prod-safir-ui-v22-20261102-3a567228"
STAGE=/srv/troca/_staging/releases/safir-ui-v22-test-RNe6McIK/app
PINS=/srv/troca/_staging/releases/safir-ui-v22-test-RNe6McIK/meta/pins.txt
CURRENT="$BASE/current"
CATALOG="$BASE/shared/storage/private/bale-safir/templates.json"
HOST=troca.corc.ir
exec 9>/run/lock/ipkf-safir-portal-cutover.lock
flock -n 9 || fail CUTOVER_BUSY
source /etc/ipkf-release/contract.env
[ "$PORTAL_BASE" = "$BASE" ] && [ "$PORTAL_USER" = troca-portal-test ] || fail CONTRACT_INVALID
[ -L "$CURRENT" ] && [ "$(readlink -e "$CURRENT")" = "$OLD" ] || fail CURRENT_RELEASE_CHANGED
[ -f "$OLD.safir-ui-v21-cutover" ] || fail V21_SUCCESS_RECEIPT_ABSENT
[ -f "$NEW.safir-ui-v22-receipt" ] && [ ! -e "$NEW.safir-ui-v22-cutover" ] || fail NEW_RELEASE_RECEIPT_INVALID
for entry in "ACTIVE_BEFORE=$OLD" "RELEASE_PATH=$NEW" "STAGE_PATH=$STAGE" "SOURCE_COMMIT=3a56722861d673099d36a5b4e97d995ef8313152" CUTOVER=NO DB_MUTATION=NO LIVE_SEND=NO; do
 grep -Fxq "$entry" "$NEW.safir-ui-v22-receipt" || fail CANDIDATE_RECEIPT_MISMATCH
done
[ -f "$PINS" ] && [ "$(wc -l < "$PINS")" = 3 ] || fail STAGE_PINS_INVALID
EXPECTED_PINS="$(mktemp)"
cat > "$EXPECTED_PINS" <<'PINSET'
app/Services/BaleSafirCampaignService.php|f2b55479a702fbe09562aa3c182fba737a079e60|9767a4a061cd525be22b7f126b9e5b4d5dbd16b4
resources/views/admin/partials/bale-safir-campaign.php|e3329bbd06cbdfba53a802f8182eb08bad3da5b1|ff8c614b64dcaed25b91b8c39765976bdaf82cfa
routes/bale-safir-campaigns.php|574708a4b8a05d850c8a07373a6aa249dc5115fe|528fea17780163e298a8da8cf9c0402a490235d9
PINSET
if ! cmp -s "$EXPECTED_PINS" "$PINS"; then
 rm -f "$EXPECTED_PINS"
 fail STAGE_PIN_CONTENT_CHANGED
fi
rm -f "$EXPECTED_PINS"

count=0
while IFS='|' read -r name before after; do
 case "$name" in
  app/Services/BaleSafirCampaignService.php|resources/views/admin/partials/bale-safir-campaign.php|routes/bale-safir-campaigns.php) : ;;
  *) fail UNRECOGNIZED_PIN_PATH ;;
 esac
 [ -f "$OLD/$name" ] && [ -f "$NEW/$name" ] && [ -f "$STAGE/$name" ] || fail SOURCE_NOT_FOUND
 [ "$(git hash-object "$OLD/$name")" = "$before" ] || fail OLD_SOURCE_CHANGED
 [ "$(git hash-object "$NEW/$name")" = "$after" ] || fail NEW_SOURCE_CHANGED
 cmp -s "$STAGE/$name" "$NEW/$name" || fail NEW_SOURCE_NOT_REVIEWED
 php -l "$NEW/$name" >/dev/null || fail PHP_SYNTAX_FAILED
 count=$((count+1))
done < "$PINS"
[ "$count" = 3 ] || fail PIN_COUNT_CHANGED
[ "$(git hash-object "$NEW/resources/views/admin/partials/bale-safir-campaign.php")" = ff8c614b64dcaed25b91b8c39765976bdaf82cfa ] || fail VIEW_BLOB_INVALID
grep -Fq 'const dispatchForm' "$NEW/resources/views/admin/partials/bale-safir-campaign.php" || fail TAB_JS_PARSE_FIX_MISSING
grep -Fq 'data-safir-manual-test' "$NEW/resources/views/admin/partials/bale-safir-campaign.php" || fail MANUAL_TEST_FORM_MISSING
grep -Fq 'data-safir-test-preview-button' "$NEW/resources/views/admin/partials/bale-safir-campaign.php" || fail MANUAL_PREVIEW_MISSING
grep -Fq 'BALE_SAFIR_TEST_PRODUCTION_APPROVED' "$NEW/app/Services/BaleSafirCampaignService.php" || fail MANUAL_SEND_GUARD_MISSING
grep -Fq "case 'manual_test':" "$NEW/routes/bale-safir-campaigns.php" || fail MANUAL_TEST_ROUTE_MISSING
echo UI_V22_THREE_FILE_INTEGRITY=PASS
cmp -s "$OLD/.env" "$NEW/.env" || fail PRIVATE_ENV_CHANGED
[ "$(stat -c %a "$NEW/.env")" = 600 ] && [ "$(stat -c %U "$NEW/.env")" = troca-portal-test ] || fail PRIVATE_ENV_PERMISSIONS
[ -f "$CATALOG" ] || fail CATALOG_MISSING
for item in storage public/uploads; do
 [ -L "$NEW/$item" ] && [ "$(readlink "$NEW/$item")" = "$BASE/shared/$item" ] || fail SHARED_LINK_INVALID
done
runuser -u troca-portal-test -- php -r '
 define("BASE_PATH",$argv[1]);$app=require BASE_PATH."/bootstrap/app.php";
 $r=(new ReflectionProperty($app->router(),"routes"))->getValue($app->router());
 foreach([["GET","/admin/communications/safir"],["POST","/admin/communications/settings/safir"],["GET","/admin/communications/settings/safir/export"]] as [$m,$p])if(!isset($r[$m][$p]))exit(11);
 \IPKF\Support\Env::loadLayered(BASE_PATH."/.env");
 foreach(["BALE_SAFIR_SEND_ENABLED","BALE_SAFIR_PRODUCTION_APPROVED","BALE_SAFIR_TEST_SEND_ENABLED","BALE_SAFIR_TEST_PRODUCTION_APPROVED"] as $key)if(filter_var(\IPKF\Support\Env::get($key,false),FILTER_VALIDATE_BOOLEAN))exit(12);
 if((string)\IPKF\Support\Env::get("BALE_SAFIR_CATALOG_PATH","")!==$argv[2])exit(13);
 echo "SAFIR_V22_BULK_AND_MANUAL_SEND_DISABLED=PASS\n";
' "$NEW" "$CATALOG" || fail SAFIR_RUNTIME_INVALID
nginx -t >/dev/null 2>&1 || fail NGINX_CONFIGURATION
[ "$(systemctl is-active nginx)" = active ] && [ "$(systemctl is-active php8.4-fpm)" = active ] || fail SERVER_NOT_ACTIVE
for site in /etc/nginx/sites-enabled/troca-portal-corc /etc/nginx/sites-enabled/troca-portal-test; do
 grep -Fq 'fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;' "$(readlink -e "$site")" || fail NGINX_FASTCGI_PATH
done
echo V22_RUNTIME_AND_NGINX_GATES=PASS
DIR="$(mktemp -d "$BASE/.safir-v22-cutover-XXXXXXXX")"
ARMED=0
DONE=0
probe(){ curl -sS --noproxy '*' --connect-timeout 3 --max-time 12 --resolve "$HOST:443:127.0.0.1" -H 'Cache-Control: no-cache' -o "$DIR/body" -w '%{http_code}' "https://$HOST$1"; }
health(){ [ "$(probe /health)" = 200 ] && php -r '$d=json_decode(file_get_contents($argv[1]),true);exit(($d["status"]??null)==="ok"?0:1);' "$DIR/body"; }
cleanup(){
 rc=$?
 trap - EXIT
 if [ "$ARMED" = 1 ] && [ "$DONE" = 0 ]; then
  if [ "$(readlink -e "$CURRENT" 2>/dev/null || true)" = "$NEW" ]; then
   echo AUTO_ROLLBACK=STARTING
   if ln -s "$OLD" "$DIR/rollback" && mv -Tf "$DIR/rollback" "$CURRENT" && health; then
    echo AUTO_ROLLBACK=PASS
   else
    echo AUTO_ROLLBACK=FAILED_REVIEW_CURRENT >&2
    rc=97
   fi
  fi
 fi
 rm -rf -- "$DIR"
 exit "$rc"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
health || fail BEFORE_HEALTH
[ "$(probe /admin/login)" = 200 ] || fail BEFORE_LOGIN
[ "$(probe /admin/communications/safir)" = 302 ] || fail BEFORE_GUEST_AUTH
echo BEFORE_HTTP=PASS
[ "$(readlink -e "$CURRENT")" = "$OLD" ] || fail CURRENT_CHANGED_BEFORE_SWAP
ln -s "$NEW" "$DIR/next"
ARMED=1
mv -Tf "$DIR/next" "$CURRENT" || fail SWITCH_FAILED
[ "$(readlink -e "$CURRENT")" = "$NEW" ] || fail SWITCH_NOT_ACTIVE
echo ATOMIC_PORTAL_SWITCH=PASS
health || fail NEW_HEALTH
[ "$(probe /admin/login)" = 200 ] || fail NEW_LOGIN
for uri in /admin/communications/safir '/admin/communications/safir?mode=create' '/admin/communications/safir?test=invite' /admin/communications/settings/safir/export; do
 [ "$(probe "$uri")" = 302 ] || fail "NEW_GUEST_AUTH:$uri"
done
echo NEW_HTTP_AND_GUEST_AUTH=PASS
[ "$(readlink -e "$CURRENT")" = "$NEW" ] || fail CURRENT_CHANGED_AFTER_SWAP
( set -C; printf 'STATUS=SUCCESS\nACTIVE_BEFORE=%s\nCURRENT_RELEASE=%s\nSAFIR_SEND=DISABLED\n' "$OLD" "$NEW" > "$NEW.safir-ui-v22-cutover" ) || fail SUCCESS_RECEIPT_WRITE
chmod 0600 "$NEW.safir-ui-v22-cutover"
DONE=1
echo SAFIR_UI_V22_PORTAL_CUTOVER=PASS
echo "CURRENT_RELEASE=$NEW"
echo "PREVIOUS_RELEASE=$OLD"
echo OTHER_MODULES_UNTOUCHED=YES
echo DB_MUTATION=NO
echo LIVE_SEND=NO
echo MANUAL_TEST_SEND=NO
echo NEXT=AUTHENTICATED_TABS_AND_MANUAL_PREVIEW_ACCEPTANCE
