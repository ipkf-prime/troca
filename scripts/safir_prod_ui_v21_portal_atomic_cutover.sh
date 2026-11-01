#!/usr/bin/env bash
# Safir UI v2.1: Portal-only atomic release, guarded and reversible.
set -Eeuo pipefail
umask 077
fail(){ echo "FAIL=$*" >&2; exit 1; }
[ "$(id -u)" = 0 ] || fail ROOT_REQUIRED
BASE=/srv/troca/portal-test
OLD="$BASE/releases/prod-safir-ui-v2-20261101-a9c8e01"
NEW="$BASE/releases/prod-safir-ui-v21-20261101-4a53e8c0"
STAGE=/srv/troca/_staging/releases/safir-ui-v21-Pdveh4Kl/app
PINS=/srv/troca/_staging/releases/safir-ui-v21-Pdveh4Kl/meta/three-file-pins.txt
CURRENT="$BASE/current"
CATALOG="$BASE/shared/storage/private/bale-safir/templates.json"
HOST=troca.corc.ir
exec 9>/run/lock/ipkf-safir-portal-cutover.lock
flock -n 9 || fail CUTOVER_BUSY
source /etc/ipkf-release/contract.env
[ "$PORTAL_BASE" = "$BASE" ] && [ "$PORTAL_USER" = troca-portal-test ] || fail CONTRACT_INVALID
[ -L "$CURRENT" ] && [ "$(readlink -e "$CURRENT")" = "$OLD" ] || fail CURRENT_RELEASE_CHANGED
[ -f "$OLD.safir-ui-v2-cutover" ] || fail V2_SUCCESS_RECEIPT_ABSENT
[ -f "$NEW.safir-ui-v21-receipt" ] && [ ! -e "$NEW.safir-ui-v21-cutover" ] || fail NEW_RELEASE_RECEIPT_INVALID
for entry in "ACTIVE_BEFORE=$OLD" "RELEASE_PATH=$NEW" "STAGE_PATH=$STAGE" "SOURCE_COMMIT=4a53e8c034063b9e9d1cc8673a19a458a7bb31e7" CUTOVER=NO DB_MUTATION=NO LIVE_SEND=NO; do
 grep -Fxq "$entry" "$NEW.safir-ui-v21-receipt" || fail CANDIDATE_RECEIPT_MISMATCH
done
[ -f "$PINS" ] && [ "$(wc -l < "$PINS")" = 3 ] || fail STAGE_PINS_INVALID
count=0
while IFS='|' read -r name before after; do
 case "$name" in
  app/Services/DynamicAdminNavigationService.php|resources/views/admin/partials/bale-safir-campaign.php|routes/bale-safir-campaigns.php) : ;;
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
[ "$(git hash-object "$NEW/resources/views/admin/partials/bale-safir-campaign.php")" = e3329bbd06cbdfba53a802f8182eb08bad3da5b1 ] || fail VIEW_BLOB_INVALID
grep -Fq "'title' => 'قالب‌های سفیر بله'" "$NEW/app/Services/DynamicAdminNavigationService.php" || fail MENU_LABEL
grep -Fq "'title' => 'مدیریت قالب‌های سفیر بله'" "$NEW/routes/bale-safir-campaigns.php" || fail ROUTE_LABEL
echo UI_V21_THREE_FILE_INTEGRITY=PASS
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
 foreach(["BALE_SAFIR_SEND_ENABLED","BALE_SAFIR_PRODUCTION_APPROVED"] as $key)if(filter_var(\IPKF\Support\Env::get($key,false),FILTER_VALIDATE_BOOLEAN))exit(12);
 if((string)\IPKF\Support\Env::get("BALE_SAFIR_CATALOG_PATH","")!==$argv[2])exit(13);
 echo "SAFIR_V21_RUNTIME_SEND_DISABLED=PASS\n";
' "$NEW" "$CATALOG" || fail SAFIR_RUNTIME_INVALID
nginx -t >/dev/null 2>&1 || fail NGINX_CONFIGURATION
[ "$(systemctl is-active nginx)" = active ] && [ "$(systemctl is-active php8.4-fpm)" = active ] || fail SERVER_NOT_ACTIVE
for site in /etc/nginx/sites-enabled/troca-portal-corc /etc/nginx/sites-enabled/troca-portal-test; do
 grep -Fq 'fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;' "$(readlink -e "$site")" || fail NGINX_FASTCGI_PATH
done
echo V21_RUNTIME_AND_NGINX_GATES=PASS
DIR="$(mktemp -d "$BASE/.safir-v21-cutover-XXXXXXXX")"
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
for uri in /admin/communications/safir '/admin/communications/safir?mode=create' /admin/communications/settings/safir/export; do
 [ "$(probe "$uri")" = 302 ] || fail "NEW_GUEST_AUTH:$uri"
done
echo NEW_HTTP_AND_GUEST_AUTH=PASS
[ "$(readlink -e "$CURRENT")" = "$NEW" ] || fail CURRENT_CHANGED_AFTER_SWAP
( set -C; printf 'STATUS=SUCCESS\nACTIVE_BEFORE=%s\nCURRENT_RELEASE=%s\nSAFIR_SEND=DISABLED\n' "$OLD" "$NEW" > "$NEW.safir-ui-v21-cutover" ) || fail SUCCESS_RECEIPT_WRITE
chmod 0600 "$NEW.safir-ui-v21-cutover"
DONE=1
echo SAFIR_UI_V21_PORTAL_CUTOVER=PASS
echo "CURRENT_RELEASE=$NEW"
echo "PREVIOUS_RELEASE=$OLD"
echo OTHER_MODULES_UNTOUCHED=YES
echo DB_MUTATION=NO
echo LIVE_SEND=NO
echo NEXT=AUTHENTICATED_ADMIN_UI_ACCEPTANCE
