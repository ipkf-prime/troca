#!/usr/bin/env bash
# IPKF Safir / Portal-only atomic cutover with automatic rollback on failures.
# NOT a shared four-module deployment. No DB migration, no sending, no restart.
set -Eeuo pipefail
umask 077
fail(){ printf 'FAIL=%s\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || fail ROOT_REQUIRED
for bin in flock nginx php curl git sha256sum ln mv readlink runuser systemctl stat grep mktemp; do
  command -v "$bin" >/dev/null || fail "MISSING_TOOL:$bin"
done
BASE=/srv/troca/portal-test
CURRENT="$BASE/current"
CAND="$BASE/releases/prod-safir-portal-20261101T130944Z-92749b72"
RECEIPT="$CAND.safir-receipt"
EXPECTED_OLD="$BASE/releases/prod-affiliation-choice-r15-20261025T025142Z-294349-a13-parentfirst-test-a15-core-affiliation-a16-core-reviewer-renewal-a21-scoped-staff-nav-a25-provincial-test-a29-province-priority-a33-http-fail-closed-a41r3-scope-hardening"
HOST=troca.corc.ir
CATALOG="$BASE/shared/storage/private/bale-safir/templates.json"
LOCK=/run/lock/ipkf-safir-portal-cutover.lock

exec 9>"$LOCK"
flock -n 9 || fail ANOTHER_SAFIR_CUTOVER_IN_PROGRESS
[ -L "$CURRENT" ] || fail CURRENT_NOT_SYMLINK
OLD="$(readlink -e "$CURRENT")" || fail CURRENT_TARGET_NOT_RESOLVABLE
[ "$OLD" = "$EXPECTED_OLD" ] || fail ACTIVE_RELEASE_CHANGED
[ -d "$CAND" ] && [ ! -L "$CAND" ] || fail CANDIDATE_RELEASE_INVALID
[ -f "$RECEIPT" ] && [ ! -L "$RECEIPT" ] || fail CANDIDATE_RECEIPT_MISSING
grep -Fxq "ACTIVE_BEFORE=$OLD" "$RECEIPT" || fail BASELINE_RECEIPT_CHANGED
grep -Fxq "RELEASE_PATH=$CAND" "$RECEIPT" || fail CANDIDATE_RECEIPT_CHANGED
grep -Fxq CUTOVER=NO "$RECEIPT" || fail CANDIDATE_IS_NOT_PRECUTOVER
[ ! -e "$CAND.safir-cutover" ] || fail CUTOVER_ALREADY_COMPLETED
[ -f "$CAND/.env" ] && [ ! -L "$CAND/.env" ] || fail CANDIDATE_ENV_MISSING
[ "$(stat -c %a "$CAND/.env")" = 600 ] || fail ENV_MODE_CHANGED
[ "$(stat -c %U "$CAND/.env")" = troca-portal-test ] || fail ENV_OWNER_CHANGED
[ -f "$CATALOG" ] && [ ! -L "$CATALOG" ] || fail CATALOG_MISSING
for relative in storage public/uploads; do
  [ -L "$CAND/$relative" ] || fail "CANDIDATE_LINK_MISSING:$relative"
  [ "$(readlink "$CAND/$relative")" = "$BASE/shared/$relative" ] ||
    fail "CANDIDATE_LINK_MISMATCH:$relative"
done

# Recheck the exact known previous Production code before link swap.
(
 cd "$OLD"
 sha256sum -c - >/dev/null <<'SHA256'
ded5d77f129fb9493e2c4a69e935246ac7c97f143a1b92c0cee32cfe853e4135  public/index.php
f9824aaab334b4492f53d4ee6cc1cf0b14052182fa5792e70b8f8e98f2f31cdf  routes/web.php
76423289b6b47c87727c492cb0279712c3d46c3c8409cbb1bcce04b2e7d9ffee  routes/communication-center.php
bfc28fa0242a668b588122249db58ac01474736f2a7f9d8720f3b1a49841fd0a  system/Routing/RouteLoader.php
255289bb6e1619751a19c5eccc6bb0cab17d033d215698daefe7dc8918814855  app/Services/CommunicationSettingsService.php
c64c1672b4d4ef2f58903fbe7cc28b14ff75a2761bb803ff6d052c458a3660d0  app/Services/AdminNavigationRbacService.php
876966a04dac826324f39a0e5a87833c971ee086964eac7cc00e4460ec98444f  resources/views/admin/communication-settings.php
SHA256
) || fail BASELINE_HASH_GATE_FAILED

# Pin new source identities: the staged Safir files were already reviewed.
for spec in \
  f2b55479a702fbe09562aa3c182fba737a079e60:app/Services/BaleSafirCampaignService.php \
  ed09578761a4c83e28d8da7807843d0fd16308f5:app/Services/BaleSafirTemplateCatalogService.php \
  7f6cac60063b7e13cb38d6e361a3368a3fdd4385:routes/bale-safir-campaigns.php \
  0ead7d17db61752f8d53ed7664db7be9873b86d4:resources/views/admin/partials/bale-safir-campaign.php \
  b1e4235c13a70d1e03f0afc4da8ed9cf5d530c49:resources/ui-content/bale-safir-templates.json
do
  IFS=: read -r expected relative <<< "$spec"
  [ -f "$CAND/$relative" ] || fail "SAFIR_FILE_MISSING:$relative"
  [ "$(git hash-object "$CAND/$relative")" = "$expected" ] ||
    fail "SAFIR_FILE_CHANGED:$relative"
done
echo RELEASE_INTEGRITY=PASS

# Run Safir safety verification AS the actual Portal user.
runuser -u troca-portal-test -- php -r '
define("BASE_PATH",$argv[1]);
require BASE_PATH."/vendor/autoload.php";
\IPKF\Support\Env::loadLayered(BASE_PATH."/.env");
$g=static fn($k)=>(string)\IPKF\Support\Env::get($k,"");
foreach(["BALE_SAFIR_SEND_ENABLED","BALE_SAFIR_PRODUCTION_APPROVED"] as $name){
 if(filter_var($g($name),FILTER_VALIDATE_BOOLEAN)){
    fwrite(STDERR,"FAIL=SEND_FLAGS_ENABLED\n");exit(18);
 }
}
if($g("BALE_SAFIR_CATALOG_PATH")!==$argv[2]){fwrite(STDERR,"FAIL=CATALOG_PATH_CHANGED\n");exit(19);}
$p=parse_url($g("APP_URL"));
if(($p["host"]??"")!==$argv[3]||($p["scheme"]??"")!=="https"){
 fwrite(STDERR,"FAIL=APP_URL_CHANGED\n");exit(20);
}
if(count((new \App\Services\BaleSafirTemplateCatalogService())->all())<1){
 fwrite(STDERR,"FAIL=EMPTY_CATALOG\n");exit(21);
}
echo "PORTAL_RUNTIME_AND_SEND_GATES=PASS\n";
' "$CAND" "$CATALOG" "$HOST" || fail CANDIDATE_RUNTIME_SAFETY_FAILED

[ "$(systemctl is-active nginx)" = active ] || fail NGINX_NOT_RUNNING
[ "$(systemctl is-active php8.4-fpm)" = active ] || fail PHP_FPM_NOT_RUNNING
nginx -t >/dev/null 2>&1 || fail NGINX_CONFIG_INVALID

WORK="$(mktemp -d "$BASE/.safir-cutover-XXXXXXXX")"
chmod 0700 "$WORK"
SWITCH_ARMED=0
SUCCESS=0
rollback_and_clean() {
  local code=$?
  trap - EXIT INT TERM
  if [ "$SUCCESS" != 1 ] && [ "$SWITCH_ARMED" = 1 ]; then
    local actual
    actual="$(readlink -e "$CURRENT" 2>/dev/null || true)"
    if [ "$actual" = "$CAND" ]; then
      echo "CUTOVER_FAILED_ROLLBACK=STARTING"
      if ln -s "$OLD" "$WORK/rollback" && mv -Tf "$WORK/rollback" "$CURRENT"; then
        echo "AUTOMATIC_ROLLBACK=PASS"
      else
        echo "AUTOMATIC_ROLLBACK=FAILED_MANUAL_ACTION_REQUIRED" >&2
        code=95
      fi
    elif [ "$actual" = "$OLD" ]; then
      echo "AUTOMATIC_ROLLBACK=ALREADY_ON_PREVIOUS"
    else
      echo "AUTOMATIC_ROLLBACK=REFUSED_UNEXPECTED_CURRENT" >&2
      code=96
    fi
  fi
  rm -rf -- "$WORK"
  exit "$code"
}
trap rollback_and_clean EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

probe() {
  local path="$1" out="$2"
  curl --silent --show-error --noproxy '*' --connect-timeout 3 --max-time 15 \
    --resolve "$HOST:443:127.0.0.1" \
    --output "$out" --write-out '%{http_code}' \
    "https://$HOST$path"
}

# Baseline is currently LIVE. Fail closed unless the new route is absent.
PRE_HEALTH="$(probe /health "$WORK/old-health.json")" || fail BASELINE_HEALTH_UNAVAILABLE
[ "$PRE_HEALTH" = 200 ] || fail BASELINE_HEALTH_BAD_STATUS
php -r '$d=json_decode(file_get_contents($argv[1]),true);exit(is_array($d)&&($d["status"]??"")==="ok"?0:1);' "$WORK/old-health.json" ||
  fail BASELINE_HEALTH_BAD_JSON
PRE_ROUTE="$(probe /admin/communications/settings/safir/export "$WORK/old-route.out")" ||
  fail BASELINE_NEW_ROUTE_PROBE_FAILED
[ "$PRE_ROUTE" = 404 ] || fail "BASELINE_SAFIR_ROUTE_NOT_ABSENT:$PRE_ROUTE"
echo "BEFORE_HEALTH=PASS"
echo "BEFORE_SAFIR_ROUTE_ABSENT=PASS"

# Repeat the current-link check immediately before the atomic rename.
[ "$(readlink -e "$CURRENT")" = "$OLD" ] || fail CURRENT_CHANGED_DURING_PREFLIGHT
ln -s "$CAND" "$WORK/switch"
SWITCH_ARMED=1
mv -Tf "$WORK/switch" "$CURRENT" || fail ATOMIC_CURRENT_RENAME_FAILED
[ "$(readlink -e "$CURRENT")" = "$CAND" ] || fail CURRENT_NOT_ON_CANDIDATE
echo ATOMIC_PORTAL_CURRENT_SWITCH=PASS

# Health alone does not distinguish old/new code. Safir export route must
# now exist and redirect the unauthenticated request to the login page.
POST_HEALTH="$(probe /health "$WORK/new-health.json")" || fail NEW_HEALTH_UNAVAILABLE
[ "$POST_HEALTH" = 200 ] || fail "NEW_HEALTH_BAD_STATUS:$POST_HEALTH"
php -r '$d=json_decode(file_get_contents($argv[1]),true);exit(is_array($d)&&($d["status"]??"")==="ok"?0:1);' "$WORK/new-health.json" ||
  fail NEW_HEALTH_BAD_JSON
echo NEW_PORTAL_HEALTH=PASS

POST_LOGIN="$(probe /admin/login "$WORK/new-login.html")" || fail NEW_LOGIN_UNAVAILABLE
[ "$POST_LOGIN" = 200 ] || fail "NEW_LOGIN_BAD_STATUS:$POST_LOGIN"
echo NEW_PORTAL_LOGIN=PASS

POST_SAFIR="$(probe /admin/communications/settings/safir/export "$WORK/new-route.out")" ||
  fail NEW_SAFIR_ROUTE_UNAVAILABLE
[ "$POST_SAFIR" = 302 ] || fail "NEW_SAFIR_ROUTE_BAD_STATUS:$POST_SAFIR"
echo NEW_SAFIR_ROUTE_GUEST_GUARD=PASS

[ "$(readlink -e "$CURRENT")" = "$CAND" ] || fail CURRENT_CHANGED_AFTER_CUTOVER
[ "$(systemctl is-active nginx)" = active ] || fail NGINX_DIED
[ "$(systemctl is-active php8.4-fpm)" = active ] || fail PHP_FPM_DIED

# Only store non-secret provenance, never overwrite the historical receipt.
(
  set -C
  printf 'STATUS=SUCCESS\nPREVIOUS_RELEASE=%s\nCURRENT_RELEASE=%s\nCUTOVER_UTC=%s\nSAFIR_SEND=DISABLED\n' \
    "$OLD" "$CAND" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$CAND.safir-cutover"
) || fail SUCCESS_RECEIPT_WRITE_FAILED
chmod 0600 "$CAND.safir-cutover"
SUCCESS=1
echo '========================================================'
echo 'SAFIR_PORTAL_ONLY_CUTOVER=PASS'
echo 'HTTP_HEALTH=PASS'
echo 'HTTP_LOGIN=PASS'
echo 'SAFIR_AUTH_ROUTE=PASS'
echo 'SEND_ENABLED=NO'
echo 'OTHER_MODULES_UNCHANGED=YES'
echo 'DB_MUTATION=NO'
echo "PREVIOUS_RELEASE=$OLD"
echo "CURRENT_RELEASE=$CAND"
echo 'ROLLBACK_RELEASE_PRESERVED=YES'
echo 'NEXT=AUTHENTICATED_ADMIN_PANEL_SMOKE_AND_MANUAL_REVIEW'
echo '========================================================'
