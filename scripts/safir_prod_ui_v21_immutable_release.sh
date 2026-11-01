#!/usr/bin/env bash
# IPKF Safir UI V2.1 — prepare immutable Portal-only release from reviewed Stage.
# No current swap, no database writes, no worker reload, no outbound messaging.
set -Eeuo pipefail
umask 077
fail(){ printf 'FAIL=%s\n' "$*" >&2; exit 1; }
[ "$(id -u)" -eq 0 ] || fail ROOT_REQUIRED
for bin in git rsync php runuser nginx stat readlink grep sort diff mktemp mv install sha256sum python3 cmp sed cut wc; do
  command -v "$bin" >/dev/null || fail "MISSING_TOOL:$bin"
done
BASE=/srv/troca/portal-test
ACTIVE="$BASE/releases/prod-safir-ui-v2-20261101-a9c8e01"
CURRENT="$BASE/current"
STAGE=/srv/troca/_staging/releases/safir-ui-v21-Pdveh4Kl/app
STAGE_RECEIPT=/srv/troca/_staging/releases/safir-ui-v21-Pdveh4Kl/meta/receipt.txt
CATALOG="$BASE/shared/storage/private/bale-safir/templates.json"
RELEASE_NAME=prod-safir-ui-v21-20261101-4a53e8c0
RELEASE="$BASE/releases/$RELEASE_NAME"
META="$BASE/releases/$RELEASE_NAME.safir-ui-v21-receipt"
[ -r /etc/ipkf-release/contract.env ] || fail CONTRACT_MISSING
# shellcheck source=/dev/null
source /etc/ipkf-release/contract.env
[ "${PORTAL_BASE:-}" = "$BASE" ] || fail PORTAL_CONTRACT_MISMATCH
[ "${PORTAL_USER:-}" = troca-portal-test ] || fail PORTAL_USER_UNEXPECTED
[ -L "$CURRENT" ] && [ "$(readlink -e "$CURRENT")" = "$ACTIVE" ] ||
  fail ACTIVE_RELEASE_CHANGED
[ -d "$STAGE" ] && [ ! -L "$STAGE" ] || fail STAGE_DIRECTORY_INVALID
[ -f "$STAGE_RECEIPT" ] && [ ! -L "$STAGE_RECEIPT" ] || fail STAGE_RECEIPT_INVALID
for receipt in \
 SAFIR_UI_V21_STAGE=PASS \
 SOURCE_COMMIT=4a53e8c034063b9e9d1cc8673a19a458a7bb31e7 \
 "ACTIVE_BEFORE=$ACTIVE" \
 "STAGE_PATH=$STAGE" \
 CHANGED_FILES=3 CUTOVER=NO DB_MUTATION=NO LIVE_SEND=NO; do
  grep -Fxq "$receipt" "$STAGE_RECEIPT" || fail "RECEIPT_MISMATCH:$receipt"
done
[ -f "$ACTIVE.safir-ui-v2-cutover" ] || fail CURRENT_UI_V2_CUTOVER_RECEIPT_MISSING
[ ! -e "$RELEASE" ] && [ ! -L "$RELEASE" ] || fail RELEASE_ALREADY_EXISTS
[ ! -e "$META" ] && [ ! -L "$META" ] || fail RELEASE_RECEIPT_ALREADY_EXISTS

# Check that live Production code has not changed since the successful staging.
PINS="$(dirname "$STAGE_RECEIPT")/three-file-pins.txt"
[ -f "$PINS" ] && [ ! -L "$PINS" ] || fail STAGE_PIN_MANIFEST_MISSING
check_count="$(wc -l < "$PINS" | tr -d ' ')"
[ "$check_count" -eq 3 ] || fail STAGE_PIN_COUNT_MISMATCH
while IFS='|' read -r path original overlay; do
  case "$path" in
    app/Services/DynamicAdminNavigationService.php|\
    resources/views/admin/partials/bale-safir-campaign.php|\
    routes/bale-safir-campaigns.php) : ;;
    *) fail "UNAPPROVED_MANIFEST_PATH:$path" ;;
  esac
  [ -f "$ACTIVE/$path" ] && [ ! -L "$ACTIVE/$path" ] ||
    fail "ACTIVE_FILE_MISSING:$path"
  [ -f "$STAGE/$path" ] && [ ! -L "$STAGE/$path" ] ||
    fail "STAGED_FILE_MISSING:$path"
  [ "$(git hash-object "$ACTIVE/$path")" = "$original" ] ||
    fail "ACTIVE_FILE_DIVERGED:$path"
  [ "$(git hash-object "$STAGE/$path")" = "$overlay" ] ||
    fail "STAGED_FILE_DIVERGED:$path"
done < "$PINS"
[ "$(git hash-object "$STAGE/resources/views/admin/partials/bale-safir-campaign.php")" = \
 e3329bbd06cbdfba53a802f8182eb08bad3da5b1 ] || fail UI_V21_VIEW_PIN_MISMATCH
[ "$(git hash-object "$ACTIVE/resources/views/admin/partials/bale-safir-campaign.php")" = \
 17583b885d0450a99f76e323bd24f6536b0ac6be ] || fail UI_V2_VIEW_PIN_MISMATCH
python3 - "$ACTIVE" "$STAGE" <<'PY'
from pathlib import Path
import sys
active,stage=Path(sys.argv[1]),Path(sys.argv[2])
labels=[
 ('app/Services/DynamicAdminNavigationService.php',
  "'title' => 'کمپین‌های سفیر بله',",
  "'title' => 'قالب‌های سفیر بله',"),
 ('routes/bale-safir-campaigns.php',
  "'title' => 'کمپین‌های سفیر بله',",
  "'title' => 'مدیریت قالب‌های سفیر بله',")
]
for path,old,new in labels:
  before=(active/path).read_bytes()
  after=(stage/path).read_bytes()
  o,n=old.encode(),new.encode()
  if before.count(o)!=1 or after != before.replace(o,n,1):
    raise SystemExit('FAIL=UNRELATED_CODE_CHANGE:'+path)
print('NAVIGATION_AND_ROUTE_LABEL_ONLY=PASS')
PY
echo STAGE_AND_LIVE_PIN_GATE=PASS

# Ignore only private runtime paths. No other divergence is permitted.
DIFF="$(rsync -rnic --delete \
 --exclude='/.env*' --exclude='**/.env*' \
 --exclude='/storage' --exclude='/public/uploads' \
 --exclude='/.git' --exclude='**/.git' \
 --exclude='**/backups' --exclude='**/backup' \
 --exclude='**/_admin' --exclude='**/*.pem' \
 --exclude='**/*.key' --exclude='**/*.p12' \
 --exclude='**/*.pfx' --exclude='**/*.sql' \
 --exclude='**/*.sql.gz' --exclude='**/*.dump' \
 --exclude='**/*.bak' --exclude='**/*.sqlite' \
 --out-format='%i|%n' "$ACTIVE/" "$STAGE/")" || fail STAGE_DIFF_FAILED
F="$(mktemp)"
G="$(mktemp)"
trap 'rm -f "$F" "$G"' EXIT
printf '%s\n' "$DIFF" | sed -n 's/^[^|]*|//p' | sort > "$F"
cut -d'|' -f1 "$PINS" | sort > "$G"
cmp -s "$F" "$G" || fail STAGE_IS_NOT_EXACT_THREE_FILE_PATCH
echo EXACT_THREE_FILE_CHANGE_SCOPE=PASS

[ -f "$ACTIVE/.env" ] && [ ! -L "$ACTIVE/.env" ] || fail LIVE_ENV_INVALID
[ "$(stat -c '%a' "$ACTIVE/.env")" = 600 ] || fail LIVE_ENV_MODE_INVALID
[ "$(stat -c '%U' "$ACTIVE/.env")" = "$PORTAL_USER" ] || fail LIVE_ENV_OWNER_INVALID
[ -f "$CATALOG" ] && [ ! -L "$CATALOG" ] || fail PERSISTENT_CATALOG_MISSING
[ -L "$ACTIVE/storage" ] && [ "$(readlink "$ACTIVE/storage")" = "$BASE/shared/storage" ] ||
  fail LIVE_STORAGE_LINK_MISMATCH
[ -L "$ACTIVE/public/uploads" ] && [ "$(readlink "$ACTIVE/public/uploads")" = "$BASE/shared/public/uploads" ] ||
  fail LIVE_UPLOAD_LINK_MISMATCH
[ ! -e "$STAGE/.env" ] && [ ! -L "$STAGE/.env" ] || fail STAGE_CONTAINS_PRIVATE_ENV
[ ! -e "$STAGE/storage" ] && [ ! -L "$STAGE/storage" ] || fail STAGE_CONTAINS_STORAGE
[ ! -e "$STAGE/public/uploads" ] && [ ! -L "$STAGE/public/uploads" ] || fail STAGE_CONTAINS_UPLOADS
echo PRIVATE_RUNTIME_BASELINE=PASS

MODE="$(stat -c '%a' "$ACTIVE")"
OWNER="$(stat -c '%U' "$ACTIVE")"
GROUP="$(stat -c '%G' "$ACTIVE")"
[ "$OWNER" = "$PORTAL_USER" ] || fail ACTIVE_RELEASE_OWNER_CHANGED
TMP="$(mktemp -d "$BASE/releases/.safir-ui-v21-candidate-XXXXXXXX")"
chmod 0700 "$TMP"
CANDIDATE_READY=NO
cleanup() {
  rc=$?
  if [ "$CANDIDATE_READY" != YES ] && [ -d "$TMP" ]; then
    rm -rf -- "$TMP"
  fi
  rm -f -- "$F" "$G"
  exit "$rc"
}
trap cleanup EXIT
rsync -a "$STAGE/" "$TMP/" || fail RELEASE_COPY_FAILED

# Attach runtime privately, without ever copying or exposing the existing
# shared catalog itself. The active .env already contains Safir settings.
install -o "$PORTAL_USER" -g "$(id -gn "$PORTAL_USER")" -m 0600 \
  "$ACTIVE/.env" "$TMP/.env"
ln -s "$BASE/shared/storage" "$TMP/storage"
ln -s "$BASE/shared/public/uploads" "$TMP/public/uploads"
chown "$OWNER:$GROUP" "$TMP"
chmod "$MODE" "$TMP"
echo SHARED_RUNTIME_AND_PRIVATE_ENV=PASS

for path in \
 app/Services/DynamicAdminNavigationService.php \
 resources/views/admin/partials/bale-safir-campaign.php \
 routes/bale-safir-campaigns.php; do
 php -l "$TMP/$path" >/dev/null || fail "PHP_LINT_FAILED:$path"
done
cmp -s "$ACTIVE/.env" "$TMP/.env" || fail PRIVATE_ENV_NOT_IDENTICAL
echo THREE_FILE_PHP_LINT_AND_RUNTIME_INHERITANCE=PASS

# CLI only, read-only. Bootstrap the actual route graph, verify both new
# routes exist and the current Sofir provider flags still fail closed.
runuser -u "$PORTAL_USER" -- php -r '
  define("BASE_PATH",$argv[1]);
  $app=require BASE_PATH."/bootstrap/app.php";
  $router=$app->router();
  $reflection=new ReflectionProperty($router,"routes");
  $routes=$reflection->getValue($router);
  foreach([
    ["GET","/admin/communications/safir"],
    ["POST","/admin/communications/settings/safir"],
    ["GET","/admin/communications/settings/safir/export"]
  ] as [$method,$route]) {
    if(!isset($routes[$method][$route])){
      fwrite(STDERR,"FAIL=REQUIRED_ROUTE_MISSING\n");exit(15);
    }
  }
  \IPKF\Support\Env::loadLayered(BASE_PATH."/.env");
  foreach(["BALE_SAFIR_SEND_ENABLED","BALE_SAFIR_PRODUCTION_APPROVED"] as $flag) {
    if(filter_var(\IPKF\Support\Env::get($flag,false),FILTER_VALIDATE_BOOLEAN)) {
      fwrite(STDERR,"FAIL=SEND_ENABLED\n");exit(16);
    }
  }
  $catalogPath=(string)\IPKF\Support\Env::get("BALE_SAFIR_CATALOG_PATH","");
  if($catalogPath!==$argv[2]) {
    fwrite(STDERR,"FAIL=CATALOG_PATH_CHANGED\n");exit(17);
  }
  if(count((new \App\Services\BaleSafirTemplateCatalogService())->all())<1) {
    fwrite(STDERR,"FAIL=CATALOG_EMPTY\n");exit(18);
  }
  echo "DEDICATED_SAFIR_ROUTE_GRAPH=PASS\n";
  echo "PRODUCTION_SEND_DISABLED=PASS\n";
  echo "PERSISTENT_TEMPLATE_CATALOG=PASS\n";
' "$TMP" "$CATALOG" || fail CANDIDATE_RUNTIME_FAILED
nginx -t >/dev/null 2>&1 || fail NGINX_CONFIG_INVALID
[ "$(readlink -e "$CURRENT")" = "$ACTIVE" ] || fail ACTIVE_CHANGED_BEFORE_FINALIZE

# Candidate is complete and verified before publishing to the releases dir.
mv -T "$TMP" "$RELEASE" || fail CANDIDATE_ATOMIC_PUBLISH_FAILED
CANDIDATE_READY=YES
{
 printf 'RELEASE_ID=%s\n' "$RELEASE_NAME"
 printf 'ACTIVE_BEFORE=%s\n' "$ACTIVE"
 printf 'RELEASE_PATH=%s\n' "$RELEASE"
 printf 'STAGE_PATH=%s\n' "$STAGE"
 printf 'SOURCE_COMMIT=%s\n' 4a53e8c034063b9e9d1cc8673a19a458a7bb31e7
 printf 'CUTOVER=NO\nDB_MUTATION=NO\nLIVE_SEND=NO\n'
} > "$META"
chmod 0600 "$META"
echo '================================================'
echo SAFIR_UI_V21_IMMUTABLE_CANDIDATE=PASS
echo EXISTING_ROUTE_GRAPH=PASS
echo ACTIVE_PORTAL_UNTOUCHED=YES
echo OTHER_MODULES_UNTOUCHED=YES
echo DB_MUTATION=NO
echo LIVE_SEND=NO
echo CUTOVER=NO
echo "CANDIDATE_RELEASE=$RELEASE"
echo "CURRENT_RELEASE=$ACTIVE"
echo "CANDIDATE_RECEIPT=$META"
echo NEXT=PORTAL_ONLY_UI_V21_CUTOVER_WITH_HTTP_AND_AUTHENTICATED_SMOKE
echo '================================================'
