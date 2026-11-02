#!/usr/bin/env bash
# IPKF Safir — prepare one immutable Portal release from the successful Stage.
# Never switches current, reloads services, touches DB, or invokes Safir API.
set -Eeuo pipefail
umask 077
fail() { printf 'FAIL=%s\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || fail ROOT_REQUIRED
for program in sha256sum git rsync php runuser nginx stat readlink grep find install date; do
    command -v "$program" >/dev/null || fail "PROGRAM_MISSING:$program"
done

BASE='/srv/troca/portal-test'
CURRENT="$BASE/current"
STAGE='/srv/troca/_staging/releases/safir-portal-stage-NgauG45p/app'
RECEIPT='/srv/troca/_staging/releases/safir-portal-stage-NgauG45p/meta/receipt.txt'
EXPECTED_SOURCE='92749b72dcf3e1163c61cdc66d350f55a9996f18'
CATALOG="$BASE/shared/storage/private/bale-safir/templates.json"

[ -r /etc/ipkf-release/contract.env ] || fail DEPLOY_CONTRACT_NOT_FOUND
# shellcheck source=/dev/null
source /etc/ipkf-release/contract.env
[ "${PORTAL_BASE:-}" = "$BASE" ] || fail PORTAL_BASE_CONTRACT_CHANGED
[ -n "${PORTAL_USER:-}" ] || fail PORTAL_USER_NOT_DEFINED
[ -L "$CURRENT" ] || fail CURRENT_NOT_SYMLINK
ACTIVE="$(readlink -e "$CURRENT")" || fail ACTIVE_TARGET_MISSING
case "$ACTIVE" in "$BASE"/releases/*) : ;; *) fail ACTIVE_TARGET_OUTSIDE_RELEASES ;; esac
[ -d "$STAGE" ] && [ ! -L "$STAGE" ] || fail STAGE_INVALID
[ -f "$RECEIPT" ] || fail STAGE_RECEIPT_NOT_FOUND
grep -Fxq "PORTAL_BASELINE=$ACTIVE" "$RECEIPT" || fail BASELINE_RECEIPT_MISMATCH
grep -Fxq "SAFIR_SOURCE_COMMIT=$EXPECTED_SOURCE" "$RECEIPT" || fail SOURCE_RECEIPT_MISMATCH
grep -Fxq "STAGE_PATH=$STAGE" "$RECEIPT" || fail STAGE_RECEIPT_PATH_MISMATCH
grep -Fxq "PRODUCTION_CUTOVER=NO" "$RECEIPT" || fail CUTOVER_RECEIPT_INVALID
grep -Fxq "LIVE_SEND=NO" "$RECEIPT" || fail SEND_RECEIPT_INVALID

# Verify active code is still exactly the known safe Production baseline.
(
 cd "$ACTIVE"
 sha256sum -c - >/dev/null <<'SHA'
ded5d77f129fb9493e2c4a69e935246ac7c97f143a1b92c0cee32cfe853e4135  public/index.php
f9824aaab334b4492f53d4ee6cc1cf0b14052182fa5792e70b8f8e98f2f31cdf  routes/web.php
76423289b6b47c87727c492cb0279712c3d46c3c8409cbb1bcce04b2e7d9ffee  routes/communication-center.php
bfc28fa0242a668b588122249db58ac01474736f2a7f9d8720f3b1a49841fd0a  system/Routing/RouteLoader.php
255289bb6e1619751a19c5eccc6bb0cab17d033d215698daefe7dc8918814855  app/Services/CommunicationSettingsService.php
c64c1672b4d4ef2f58903fbe7cc28b14ff75a2761bb803ff6d052c458a3660d0  app/Services/AdminNavigationRbacService.php
876966a04dac826324f39a0e5a87833c971ee086964eac7cc00e4460ec98444f  resources/views/admin/communication-settings.php
SHA
) || fail ACTIVE_BASELINE_HASH_MISMATCH
echo ACTIVE_PORTAL_BASELINE=PASS

# Verify the five unmodified new Safir artifacts against pinned Git blob IDs.
check_blob() {
    local relative="$1" expected="$2"
    [ -f "$STAGE/$relative" ] || fail "NEW_FILE_MISSING:$relative"
    [ "$(git hash-object "$STAGE/$relative")" = "$expected" ] ||
        fail "NEW_FILE_HASH_MISMATCH:$relative"
}
check_blob app/Services/BaleSafirCampaignService.php f2b55479a702fbe09562aa3c182fba737a079e60
check_blob app/Services/BaleSafirTemplateCatalogService.php ed09578761a4c83e28d8da7807843d0fd16308f5
check_blob routes/bale-safir-campaigns.php 7f6cac60063b7e13cb38d6e361a3368a3fdd4385
check_blob resources/views/admin/partials/bale-safir-campaign.php 0ead7d17db61752f8d53ed7664db7be9873b86d4
check_blob resources/ui-content/bale-safir-templates.json b1e4235c13a70d1e03f0afc4da8ed9cf5d530c49
echo PINNED_SAFIR_ARTIFACTS=PASS

# Ensure *only* the nine reviewed source files differ from active Production.
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
    --out-format='%i|%n' \
    "$ACTIVE/" "$STAGE/")" || fail LIVE_VS_STAGE_DIFF_FAILED
while IFS= read -r record; do
    [ -n "$record" ] || continue
    case "$record" in *'|'*) : ;; *) fail BAD_RSYNC_RECORD ;; esac
    relative="${record#*|}"
    case "$relative" in
      system/Routing/RouteLoader.php|app/Services/CommunicationSettingsService.php|\
      app/Services/AdminNavigationRbacService.php|resources/views/admin/communication-settings.php|\
      app/Services/BaleSafirCampaignService.php|app/Services/BaleSafirTemplateCatalogService.php|\
      routes/bale-safir-campaigns.php|resources/views/admin/partials/bale-safir-campaign.php|\
      resources/ui-content/bale-safir-templates.json) : ;;
      *) fail "UNRELATED_SOURCE_CHANGE:$relative" ;;
    esac
done <<< "$DIFF"
echo NINE_FILE_CHANGE_SCOPE=PASS

# Check staged runtime and private catalog before creating an immutable release.
[ -f "$STAGE/.env" ] && [ ! -L "$STAGE/.env" ] || fail STAGED_ENV_INVALID
[ "$(stat -c '%a' "$STAGE/.env")" = '600' ] || fail STAGED_ENV_MODE_INVALID
[ "$(stat -c '%U' "$STAGE/.env")" = "$PORTAL_USER" ] || fail STAGED_ENV_OWNER_INVALID
[ -L "$STAGE/storage" ] && [ "$(readlink "$STAGE/storage")" = "$BASE/shared/storage" ] ||
    fail STAGED_STORAGE_LINK_INVALID
[ -L "$STAGE/public/uploads" ] && [ "$(readlink "$STAGE/public/uploads")" = "$BASE/shared/public/uploads" ] ||
    fail STAGED_UPLOAD_LINK_INVALID
[ -f "$CATALOG" ] && [ ! -L "$CATALOG" ] || fail CATALOG_INVALID
runuser -u "$PORTAL_USER" -- test -r "$CATALOG" || fail CATALOG_NOT_READABLE_BY_RUNTIME
runuser -u "$PORTAL_USER" -- test -w "$(dirname "$CATALOG")" ||
    fail CATALOG_DIRECTORY_NOT_WRITABLE
echo SHARED_RUNTIME_PRECHECK=PASS

# Never run an unreviewed deployment tool for this one-module release.
RELEASE_ID="prod-safir-portal-$(date -u +%Y%m%dT%H%M%SZ)-92749b72"
RELEASE="$BASE/releases/$RELEASE_ID"
[ ! -e "$RELEASE" ] && [ ! -L "$RELEASE" ] || fail RELEASE_ID_ALREADY_PRESENT
[ -d "$BASE/releases" ] && [ ! -L "$BASE/releases" ] || fail RELEASES_PARENT_INVALID

# Retain the same owner/group/mode as the live release root. rsync -a
# preserves the application content ownership/permissions from the safe Stage.
MODE="$(stat -c '%a' "$ACTIVE")"
OWNER="$(stat -c '%U' "$ACTIVE")"
GROUP="$(stat -c '%G' "$ACTIVE")"
install -d -o "$OWNER" -g "$GROUP" -m "$MODE" "$RELEASE"
rsync -a "$STAGE/" "$RELEASE/" || fail IMMUTABLE_RELEASE_COPY_FAILED
chown "$OWNER:$GROUP" "$RELEASE"
chmod "$MODE" "$RELEASE"

# Do not proceed with an unsafe runtime; no source data is printed.
runuser -u "$PORTAL_USER" -- php -r '
  define("BASE_PATH", $argv[1]);
  require_once BASE_PATH."/vendor/autoload.php";
  require_once BASE_PATH."/system/Support/Env.php";
  \IPKF\Support\Env::loadLayered(BASE_PATH."/.env");
  $get=static fn($key)=>\IPKF\Support\Env::get($key, "");
  foreach(["BALE_SAFIR_SEND_ENABLED","BALE_SAFIR_PRODUCTION_APPROVED"] as $flag) {
    if(filter_var($get($flag),FILTER_VALIDATE_BOOLEAN)) {
      fwrite(STDERR,"PRODUCTION_SEND_FLAG_ENABLED\n");exit(11);
    }
  }
  if((string)$get("BALE_SAFIR_CATALOG_PATH")!==$argv[2]) {
    fwrite(STDERR,"WRONG_CATALOG_PATH\n");exit(12);
  }
  $all=(new \App\Services\BaleSafirTemplateCatalogService())->all();
  if(count($all)<1){fwrite(STDERR,"CATALOG_EMPTY\n");exit(13);}
  echo "PORTAL_USER_RUNTIME=PASS\n";
  echo "SEND_FLAGS_DISABLED=PASS\n";
  echo "PERSISTENT_CATALOG=PASS\n";
' "$RELEASE" "$CATALOG" || fail RELEASE_RUNTIME_SMOKE_FAILED

[ "$(readlink -e "$CURRENT")" = "$ACTIVE" ] || fail ACTIVE_RELEASE_CHANGED
nginx -t >/dev/null 2>&1 || fail NGINX_CONFIG_CHECK_FAILED
echo NGINX_CONFIG=PASS

# Store provenance outside webroot; no credentials in receipt.
META="$BASE/releases/$RELEASE_ID.safir-receipt"
[ ! -e "$META" ] || fail RECEIPT_ALREADY_PRESENT
{
  printf 'RELEASE_ID=%s\n' "$RELEASE_ID"
  printf 'ACTIVE_BEFORE=%s\n' "$ACTIVE"
  printf 'RELEASE_PATH=%s\n' "$RELEASE"
  printf 'SAFIR_SOURCE_COMMIT=%s\n' "$EXPECTED_SOURCE"
  printf 'CUTOVER=NO\nDATABASE_MUTATION=NO\nLIVE_SEND=NO\n'
} > "$META"
chmod 0600 "$META"

echo '================================================='
echo 'SAFIR_IMMUTABLE_PORTAL_RELEASE=PASS'
echo 'ACTIVE_PRODUCTION_UNTOUCHED=YES'
echo 'AUTOMATION_WORK_TICKETING_UNTOUCHED=YES'
echo 'DATABASE_MUTATION=NO'
echo 'LIVE_SEND=NO'
echo 'CUTOVER=NO'
echo "CANDIDATE_RELEASE=$RELEASE"
echo "CURRENT_RELEASE=$ACTIVE"
echo "RECEIPT=$META"
echo 'NEXT=PORTAL_ONLY_CUTOVER_AND_HEALTH_ROLLBACK_GATE'
echo '================================================='
