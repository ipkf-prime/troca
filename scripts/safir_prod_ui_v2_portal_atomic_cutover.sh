#!/usr/bin/env bash
# IPKF Safir UI V2 - ONLY Portal current symlink cutover.
# Locked, pinned, HTTP-probed and automatically rolled back on failure.
# No PHP-FPM/Nginx reload, provider send, DB migration, or shared-module deploy.
set -Eeuo pipefail
umask 077
fail(){ printf 'FAIL=%s\n' "$*" >&2; exit 1; }
for bin in flock nginx php curl git sha256sum ln mv readlink runuser systemctl stat grep mktemp cmp sort cut rsync; do
  command -v "$bin" >/dev/null || fail "MISSING_TOOL:$bin"
done
[ "$(id -u)" -eq 0 ] || fail ROOT_REQUIRED

BASE=/srv/troca/portal-test
CURRENT="$BASE/current"
OLD="$BASE/releases/prod-safir-portal-20261101T130944Z-92749b72"
CAND="$BASE/releases/prod-safir-ui-v2-20261101-a9c8e01"
RECEIPT="$CAND.safir-ui-v2-receipt"
SUCCESS_RECEIPT="$CAND.safir-ui-v2-cutover"
STAGE=/srv/troca/_staging/releases/safir-ui-v2-patched-QriAUWxk/app
PINS=/srv/troca/_staging/releases/safir-ui-v2-patched-QriAUWxk/meta/pins.txt
CATALOG="$BASE/shared/storage/private/bale-safir/templates.json"
HOST=troca.corc.ir

exec 9>/run/lock/ipkf-safir-portal-cutover.lock
flock -n 9 || fail CUTOVER_ALREADY_RUNNING
[ -r /etc/ipkf-release/contract.env ] || fail CONTRACT_FILE_MISSING
# shellcheck source=/dev/null
source /etc/ipkf-release/contract.env
[ "$PORTAL_BASE" = "$BASE" ] || fail PORTAL_BASE_CONTRACT_MISMATCH
[ "$PORTAL_USER" = troca-portal-test ] || fail PORTAL_USER_CONTRACT_MISMATCH

[ -L "$CURRENT" ] || fail CURRENT_NOT_SYMLINK
[ "$(readlink -e "$CURRENT")" = "$OLD" ] || fail ACTIVE_RELEASE_CHANGED
[ -f "$OLD.safir-cutover" ] || fail CURRENT_RELEASE_RECEIPT_MISSING
[ -d "$CAND" ] && [ ! -L "$CAND" ] || fail CANDIDATE_INVALID
[ -f "$RECEIPT" ] && [ ! -L "$RECEIPT" ] || fail CANDIDATE_RECEIPT_MISSING
[ ! -e "$SUCCESS_RECEIPT" ] || fail ALREADY_CUTOVER
[ -d "$STAGE" ] && [ ! -L "$STAGE" ] || fail STAGE_NOT_FOUND
[ -f "$PINS" ] && [ ! -L "$PINS" ] || fail PIN_MANIFEST_MISSING

for entry in \
 "ACTIVE_BEFORE=$OLD" "RELEASE_PATH=$CAND" \
 "STAGE_PATH=$STAGE" \
 "SOURCE_COMMIT=a9c8e01fea84ba62e771c48a70a9a6c455b8f03a" \
 CUTOVER=NO DB_MUTATION=NO LIVE_SEND=NO; do
  grep -Fxq "$entry" "$RECEIPT" || fail "CANDIDATE_RECEIPT_MISMATCH:$entry"
done

# Verify the previous release against the precise stage-time pins and
# ensure every candidate code file is identical to the reviewed Stage.
[ "$(wc -l < "$PINS")" -eq 8 ] || fail MANIFEST_COUNT_CHANGED
while IFS='|' read -r relative old_blob new_blob; do
  case "$relative" in
    app/Services/BaleSafirTemplateCatalogService.php|\
    app/Services/AdminNavigationRbacService.php|\
    app/Services/DynamicAdminNavigationService.php|\
    resources/views/admin/communication-settings.php|\
    resources/views/admin/partials/bale-safir-campaign.php|\
    resources/views/admin/safir-management.php|\
    routes/bale-safir-campaigns.php|routes/communication-center.php) : ;;
    *) fail "UNEXPECTED_MANIFEST_PATH:$relative" ;;
  esac
  if [ "$old_blob" = NEW ]; then
    [ "$relative" = resources/views/admin/safir-management.php ] ||
      fail "INVALID_NEW_FILE:$relative"
    [ ! -e "$OLD/$relative" ] || fail "OLD_RELEASE_NEW_FILE_EXISTS:$relative"
  else
    [ -f "$OLD/$relative" ] && [ ! -L "$OLD/$relative" ] ||
      fail "OLD_SOURCE_MISSING:$relative"
    [ "$(git hash-object "$OLD/$relative")" = "$old_blob" ] ||
      fail "OLD_SOURCE_CHANGED:$relative"
  fi
  [ -f "$CAND/$relative" ] && [ ! -L "$CAND/$relative" ] ||
    fail "CANDIDATE_SOURCE_MISSING:$relative"
  [ -f "$STAGE/$relative" ] && [ ! -L "$STAGE/$relative" ] ||
    fail "STAGE_SOURCE_MISSING:$relative"
  cmp -s "$STAGE/$relative" "$CAND/$relative" ||
    fail "CANDIDATE_DIFFERS_FROM_REVIEWED_STAGE:$relative"
  case "$new_blob" in
    PATCH_*) : ;;
    *) [ "$(git hash-object "$CAND/$relative")" = "$new_blob" ] ||
         fail "CANDIDATE_PIN_MISMATCH:$relative" ;;
  esac
done < "$PINS"
echo EIGHT_FILE_CANDIDATE_AND_BASELINE=PASS

[ -f "$OLD/.env" ] && [ -f "$CAND/.env" ] ||
  fail PRIVATE_ENV_MISSING
[ ! -L "$CAND/.env" ] || fail CANDIDATE_ENV_IS_SYMLINK
[ "$(stat -c '%a' "$CAND/.env")" = 600 ] || fail CANDIDATE_ENV_MODE_INVALID
[ "$(stat -c '%U' "$CAND/.env")" = "$PORTAL_USER" ] ||
  fail CANDIDATE_ENV_OWNER_INVALID
cmp -s "$OLD/.env" "$CAND/.env" || fail PRIVATE_ENV_CHANGED
[ -f "$CATALOG" ] && [ ! -L "$CATALOG" ] || fail SHARED_CATALOG_MISSING
for rel in storage public/uploads; do
  [ -L "$CAND/$rel" ] || fail "SHARED_LINK_MISSING:$rel"
  [ "$(readlink "$CAND/$rel")" = "$BASE/shared/$rel" ] ||
    fail "SHARED_LINK_TARGET_MISMATCH:$rel"
done
echo PRIVATE_ENV_AND_SHARED_CATALOG=PASS

# Validate new routes and provider flags as the actual Portal runtime user.
runuser -u "$PORTAL_USER" -- php -r '
  define("BASE_PATH", $argv[1]);
  $app=require BASE_PATH."/bootstrap/app.php";
  $p=new ReflectionProperty($app->router(),"routes");
  $routes=$p->getValue($app->router());
  foreach([
    ["GET","/admin/communications/safir"],
    ["POST","/admin/communications/settings/safir"],
    ["GET","/admin/communications/settings/safir/export"]
  ] as [$method,$route]){
    if(!isset($routes[$method][$route])) {
      fwrite(STDERR,"FAIL=ROUTE_MISSING\n");exit(12);
    }
  }
  \IPKF\Support\Env::loadLayered(BASE_PATH."/.env");
  foreach(["BALE_SAFIR_SEND_ENABLED","BALE_SAFIR_PRODUCTION_APPROVED"] as $flag) {
    if(filter_var(\IPKF\Support\Env::get($flag,false),FILTER_VALIDATE_BOOLEAN)){
      fwrite(STDERR,"FAIL=SEND_FLAG_ENABLED\n");exit(13);
    }
  }
  if((string)\IPKF\Support\Env::get("BALE_SAFIR_CATALOG_PATH","")!==$argv[2]){
    fwrite(STDERR,"FAIL=CATALOG_PATH_CHANGED\n");exit(14);
  }
  if(count((new \App\Services\BaleSafirTemplateCatalogService())->all())<1){
    fwrite(STDERR,"FAIL=CATALOG_EMPTY\n");exit(15);
  }
  echo "SAFIR_NEW_ROUTES_AND_SEND_FLAGS=PASS\n";
' "$CAND" "$CATALOG" || fail CANDIDATE_RUNTIME_FAILED

[ "$(systemctl is-active nginx)" = active ] || fail NGINX_NOT_ACTIVE
[ "$(systemctl is-active php8.4-fpm)" = active ] || fail PHP_FPM_NOT_ACTIVE
nginx -t >/dev/null 2>&1 || fail NGINX_CONFIG_INVALID
for site in /etc/nginx/sites-enabled/troca-portal-corc /etc/nginx/sites-enabled/troca-portal-test; do
  file="$(readlink -e "$site")" || fail NGINX_PORTAL_SITE_MISSING
  grep -Fq 'root /srv/troca/portal-test/current/public;' "$file" ||
    fail "PORTAL_ROOT_CHANGED:$site"
  grep -Fq 'fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;' "$file" ||
    fail "PORTAL_FASTCGI_REALPATH_MISSING:$site"
  if grep -Fq 'fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;' "$file"; then
    fail "PORTAL_FASTCGI_STALE_MAPPING:$site"
  fi
done
echo NGINX_PORTAL_REALPATH=PASS

WORK="$(mktemp -d "$BASE/.safir-ui-v2-cutover-XXXXXXXX")"
chmod 0700 "$WORK"
ARMED=0
SUCCESS=0

probe(){
  local path="$1" out="$2"
  curl --fail-with-body --silent --show-error --noproxy '*' \
    --connect-timeout 3 --max-time 12 \
    --resolve "$HOST:443:127.0.0.1" \
    -H 'Cache-Control: no-cache' \
    --output "$out" --write-out '%{http_code}' \
    "https://$HOST$path"
}
# Unlike curl -f, do not treat an expected guest 404 as a transport failure.
probe_status(){
  local path="$1" out="$2"
  curl --silent --show-error --noproxy '*' \
    --connect-timeout 3 --max-time 12 \
    --resolve "$HOST:443:127.0.0.1" \
    -H 'Cache-Control: no-cache' \
    --output "$out" --write-out '%{http_code}' \
    "https://$HOST$path"
}
health_json(){
  php -r '$j=json_decode(file_get_contents($argv[1]),true);
    exit(is_array($j)&&($j["status"]??null)==="ok"?0:1);' "$1"
}

restore_on_failure(){
  rc=$?
  trap - EXIT INT TERM
  if [ "$SUCCESS" != 1 ] && [ "$ARMED" = 1 ]; then
    target="$(readlink -e "$CURRENT" 2>/dev/null || true)"
    if [ "$target" = "$CAND" ]; then
      echo AUTO_ROLLBACK=STARTED
      if ln -s "$OLD" "$WORK/restore" && mv -Tf "$WORK/restore" "$CURRENT"; then
        if [ "$(readlink -e "$CURRENT" 2>/dev/null || true)" = "$OLD" ]; then
          back="$(probe_status /health "$WORK/rollback-health.json" || true)"
          if [ "$back" = 200 ] && health_json "$WORK/rollback-health.json"; then
            echo AUTO_ROLLBACK=PASS
          else
            echo AUTO_ROLLBACK=LINK_RESTORED_HTTP_HEALTH_UNVERIFIED >&2
            rc=96
          fi
        else
          echo AUTO_ROLLBACK=FAILED_VERIFY_CURRENT >&2
          rc=97
        fi
      else
        echo AUTO_ROLLBACK=FAILED_RENAME_MANUAL_ACTION_REQUIRED >&2
        rc=98
      fi
    elif [ "$target" = "$OLD" ]; then
      echo AUTO_ROLLBACK=ALREADY_ON_PREVIOUS
    else
      echo AUTO_ROLLBACK=UNKNOWN_CURRENT_REFUSED >&2
      rc=99
    fi
  fi
  rm -rf -- "$WORK"
  exit "$rc"
}
trap restore_on_failure EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

# Baseline HTTP: new route does not exist on current UI V1.
old_status="$(probe_status /health "$WORK/old-health.json")" ||
  fail OLD_HTTPS_HEALTH_UNAVAILABLE
[ "$old_status" = 200 ] && health_json "$WORK/old-health.json" ||
  fail OLD_HEALTH_INVALID
old_login="$(probe_status /admin/login "$WORK/old-login.html")" ||
  fail OLD_LOGIN_UNREACHABLE
[ "$old_login" = 200 ] || fail "OLD_LOGIN_STATUS:$old_login"
old_route="$(probe_status /admin/communications/safir "$WORK/old-route.html")" ||
  fail OLD_ROUTE_UNREACHABLE
[ "$old_route" = 404 ] || fail "NEW_ROUTE_NOT_ABSENT_IN_OLD:$old_route"
echo BEFORE_HEALTH_AND_ROUTE=PASS

[ "$(readlink -e "$CURRENT")" = "$OLD" ] || fail CURRENT_CHANGED_BEFORE_SWAP
ln -s "$CAND" "$WORK/switch"
ARMED=1
mv -Tf "$WORK/switch" "$CURRENT" || fail ATOMIC_SYMLINK_SWAP_FAILED
[ "$(readlink -e "$CURRENT")" = "$CAND" ] || fail CURRENT_NOT_ON_CANDIDATE
echo ATOMIC_PORTAL_SWITCH=PASS

new_status="$(probe_status /health "$WORK/new-health.json")" ||
  fail NEW_HTTPS_HEALTH_UNAVAILABLE
[ "$new_status" = 200 ] && health_json "$WORK/new-health.json" ||
  fail "NEW_HEALTH_INVALID:$new_status"
echo NEW_HEALTH=PASS
new_login="$(probe_status /admin/login "$WORK/new-login.html")" ||
  fail NEW_LOGIN_UNREACHABLE
[ "$new_login" = 200 ] || fail "NEW_LOGIN_STATUS:$new_login"
echo NEW_LOGIN=PASS

for uri in /admin/communications/safir '/admin/communications/safir?mode=create' /admin/communications/settings/safir/export; do
  status="$(probe_status "$uri" "$WORK/guest-route.txt")" ||
    fail "NEW_GUEST_ROUTE_UNREACHABLE:$uri"
  [ "$status" = 302 ] || fail "NEW_GUEST_ROUTE_HTTP:$uri:$status"
done
echo SAFIR_V2_GUEST_ROUTE_AND_RBAC=PASS
[ "$(readlink -e "$CURRENT")" = "$CAND" ] || fail CURRENT_CHANGED_AFTER_HTTP
[ "$(systemctl is-active nginx)" = active ] || fail NGINX_STOPPED
[ "$(systemctl is-active php8.4-fpm)" = active ] || fail PHP_FPM_STOPPED

# Receipt created last; previous release is retained as-is for manual rollback.
(
  set -C
  printf 'STATUS=SUCCESS\nACTIVE_BEFORE=%s\nCURRENT_RELEASE=%s\nCUTOVER_UTC=%s\nSAFIR_SEND=DISABLED\n' \
    "$OLD" "$CAND" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$SUCCESS_RECEIPT"
) || fail SUCCESS_RECEIPT_FAILED
chmod 0600 "$SUCCESS_RECEIPT"
SUCCESS=1
echo '=========================================='
echo SAFIR_UI_V2_PORTAL_CUTOVER=PASS
echo NEW_HEALTH=PASS
echo NEW_LOGIN=PASS
echo NEW_SAFIR_ROUTE=PASS
echo SEND_ENABLED=NO
echo OTHER_MODULES_UNTOUCHED=YES
echo NO_DB_MIGRATIONS=YES
echo "CURRENT_RELEASE=$CAND"
echo "PREVIOUS_RELEASE=$OLD"
echo ROLLBACK_RELEASE_PRESERVED=YES
echo NEXT=AUTHENTICATED_ADMIN_UI_ACCEPTANCE
echo '=========================================='
