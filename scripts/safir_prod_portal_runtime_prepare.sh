#!/usr/bin/env bash
# IPKF Safir — configure private runtime only for a staged Portal release.
# Does not change current, running services, DB, shared env or other modules.
set -Eeuo pipefail
umask 077

fail() { printf 'FAIL=%s\n' "$*" >&2; exit 1; }
BASE='/srv/troca/portal-test'
CURRENT="$BASE/current"
STAGE='/srv/troca/_staging/releases/safir-portal-stage-NgauG45p/app'
RECEIPT='/srv/troca/_staging/releases/safir-portal-stage-NgauG45p/meta/receipt.txt'
SHARED="$BASE/shared/storage"
CATALOG_DIR="$SHARED/private/bale-safir"
CATALOG="$CATALOG_DIR/templates.json"

[ "$(id -u)" -eq 0 ] || fail ROOT_REQUIRED
[ -r /etc/ipkf-release/contract.env ] || fail RELEASE_CONTRACT_NOT_FOUND
# shellcheck source=/dev/null
source /etc/ipkf-release/contract.env
[ "\${PORTAL_BASE:-}" = "$BASE" ] || fail PORTAL_BASE_MISMATCH
[ -n "\${PORTAL_USER:-}" ] || fail PORTAL_USER_NOT_SET
id "$PORTAL_USER" >/dev/null 2>&1 || fail PORTAL_USER_UNKNOWN
[ -L "$CURRENT" ] || fail CURRENT_NOT_SYMLINK
ACTIVE="$(readlink -e "$CURRENT")" || fail CURRENT_UNRESOLVED
case "$ACTIVE" in "$BASE"/releases/*) : ;; *) fail CURRENT_TARGET_INVALID ;; esac
[ -f "$RECEIPT" ] || fail STAGING_RECEIPT_NOT_FOUND
[ -d "$STAGE" ] || fail STAGE_NOT_FOUND
[ "$(stat -c %u "$STAGE" )" -eq 0 ] || fail STAGE_NOT_ROOT_OWNED
[ ! -L "$STAGE" ] || fail STAGE_IS_SYMLINK
[ -f "$STAGE/app/Services/BaleSafirTemplateCatalogService.php" ] || fail SAFIR_SERVICE_MISSING
[ -f "$STAGE/resources/ui-content/bale-safir-templates.json" ] || fail INITIAL_CATALOG_MISSING
[ -f "$ACTIVE/.env" ] || fail ACTIVE_DESCRIPTOR_MISSING
[ ! -L "$ACTIVE/.env" ] || fail ACTIVE_DESCRIPTOR_SYMLINK
[ "$(stat -c %a "$ACTIVE/.env")" = '600' ] || fail ACTIVE_ENV_MODE_UNEXPECTED
[ "$(stat -c %U "$ACTIVE/.env")" = "$PORTAL_USER" ] || fail ACTIVE_ENV_OWNER_MISMATCH
[ -d "$SHARED" ] || fail SHARED_STORAGE_NOT_FOUND
[ ! -L "$SHARED" ] || fail SHARED_STORAGE_SYMLINK_UNEXPECTED

# The staged source must have the expected receipt, and Production must not
# have changed since the pinned baseline review.
grep -Fxq "PORTAL_BASELINE=$ACTIVE" "$RECEIPT" || fail RECEIPT_BASELINE_MISMATCH
(
  cd "$ACTIVE"
  sha256sum -c - >/dev/null <<'SHA256'
ded5d77f129fb9493e2c4a69e935246ac7c97f143a1b92c0cee32cfe853e4135  public/index.php
f9824aaab334b4492f53d4ee6cc1cf0b14052182fa5792e70b8f8e98f2f31cdf  routes/web.php
76423289b6b47c87727c492cb0279712c3d46c3c8409cbb1bcce04b2e7d9ffee  routes/communication-center.php
bfc28fa0242a668b588122249db58ac01474736f2a7f9d8720f3b1a49841fd0a  system/Routing/RouteLoader.php
255289bb6e1619751a19c5eccc6bb0cab17d033d215698daefe7dc8918814855  app/Services/CommunicationSettingsService.php
c64c1672b4d4ef2f58903fbe7cc28b14ff75a2761bb803ff6d052c458a3660d0  app/Services/AdminNavigationRbacService.php
876966a04dac826324f39a0e5a87833c971ee086964eac7cc00e4460ec98444f  resources/views/admin/communication-settings.php
SHA256
) || fail ACTIVE_BASELINE_CHANGED
printf 'ACTIVE_RELEASE_HASH_GATE=PASS\n'

# Do not alter any existing private directory permissions or existing catalog.
PARENT="$SHARED/private"
if [ ! -e "$PARENT" ]; then
  install -d -o "$PORTAL_USER" -g "$(id -gn "$PORTAL_USER")" -m 0700 "$PARENT"
fi
[ -d "$PARENT" ] && [ ! -L "$PARENT" ] || fail PRIVATE_PARENT_INVALID
runuser -u "$PORTAL_USER" -- test -w "$PARENT" || fail PRIVATE_PARENT_NOT_WRITABLE
runuser -u "$PORTAL_USER" -- test -x "$PARENT" || fail PRIVATE_PARENT_NOT_TRAVERSABLE
if [ ! -e "$CATALOG_DIR" ]; then
  install -d -o "$PORTAL_USER" -g "$(id -gn "$PORTAL_USER")" -m 0700 "$CATALOG_DIR"
fi
[ -d "$CATALOG_DIR" ] && [ ! -L "$CATALOG_DIR" ] || fail CATALOG_DIR_INVALID
runuser -u "$PORTAL_USER" -- test -w "$CATALOG_DIR" || fail CATALOG_DIR_NOT_WRITABLE
runuser -u "$PORTAL_USER" -- test -x "$CATALOG_DIR" || fail CATALOG_DIR_NOT_TRAVERSABLE

# Seed only when missing; no overwrites of a prior admin-maintained catalog.
if [ ! -e "$CATALOG" ]; then
  install -o "$PORTAL_USER" -g "$(id -gn "$PORTAL_USER")" -m 0600 \
    "$STAGE/resources/ui-content/bale-safir-templates.json" "$CATALOG"
  echo CATALOG_INITIAL_SEED=CREATED
else
  echo CATALOG_INITIAL_SEED=EXISTING_PRESERVED
fi
[ -f "$CATALOG" ] && [ ! -L "$CATALOG" ] || fail CATALOG_FILE_INVALID
runuser -u "$PORTAL_USER" -- test -r "$CATALOG" || fail CATALOG_NOT_READABLE
runuser -u "$PORTAL_USER" -- test -w "$CATALOG_DIR" || fail CATALOG_NOT_WRITABLE
php -r '$p=$argv[1];$j=json_decode(file_get_contents($p),true,512,JSON_THROW_ON_ERROR); if(!is_array($j["templates"]??null))exit(12); echo "CATALOG_JSON=PASS\n";' "$CATALOG" || fail CATALOG_JSON_INVALID

# Reattach only the known shared data symlinks to the *staged* release.
for entry in \
  'storage|/srv/troca/portal-test/shared/storage' \
  'public/uploads|/srv/troca/portal-test/shared/public/uploads'; do
  relative="\${entry%%|*}"
  target="\${entry#*|}"
  [ -d "$target" ] || fail "RUNTIME_TARGET_MISSING:$relative"
  path="$STAGE/$relative"
  if [ -L "$path" ]; then
    [ "$(readlink "$path")" = "$target" ] || fail "STAGE_LINK_DIFFERS:$relative"
  elif [ ! -e "$path" ]; then
    ln -s "$target" "$path"
  else
    fail "STAGE_PATH_ALREADY_OCCUPIED:$relative"
  fi
done
echo SHARED_RUNTIME_LINKS=PASS

# Create a private new-release descriptor from the active, leaving the active
# descriptor and server-owned shared env untouched. Prevent ambiguous duplicates.
if [ -e "$STAGE/.env" ] || [ -L "$STAGE/.env" ]; then
  fail STAGE_ENV_ALREADY_EXISTS_REVIEW_REQUIRED
fi
for var in BALE_SAFIR_CATALOG_PATH BALE_SAFIR_SEND_ENABLED BALE_SAFIR_PRODUCTION_APPROVED BALE_SAFIR_MAX_IMPORT_ROWS; do
  if grep -Eq "^[[:space:]]*\${var}[[:space:]]*=" "$ACTIVE/.env"; then
    fail "ACTIVE_ENV_ALREADY_CONTAINS:$var"
  fi
done

install -o "$PORTAL_USER" -g "$(id -gn "$PORTAL_USER")" -m 0600 "$ACTIVE/.env" "$STAGE/.env"
{
  printf '\n# Bale Safir dynamic templates: staged Portal-only configuration\n'
  printf 'BALE_SAFIR_CATALOG_PATH=%s\n' "$CATALOG"
  printf 'BALE_SAFIR_MAX_IMPORT_ROWS=5000\n'
  printf 'BALE_SAFIR_SEND_ENABLED=false\n'
  printf 'BALE_SAFIR_PRODUCTION_APPROVED=false\n'
} >> "$STAGE/.env"
chmod 0600 "$STAGE/.env"
echo STAGE_PRIVATE_ENV=READY

# Same Env loader as Portal runtime; do not print env values or tokens.
php -r '
  define("BASE_PATH",$argv[1]);
  require BASE_PATH."/system/Support/Env.php";
  require BASE_PATH."/app/Services/BaleSafirTemplateCatalogService.php";
  \IPKF\Support\Env::loadLayered(BASE_PATH."/.env");
  $get=static fn($x)=>\IPKF\Support\Env::get($x, "");
  $safe=static fn($x)=>!filter_var($get($x),FILTER_VALIDATE_BOOLEAN);
  if(!$safe("BALE_SAFIR_SEND_ENABLED") || !$safe("BALE_SAFIR_PRODUCTION_APPROVED")){
    fwrite(STDERR,"SEND_FLAGS_UNSAFE\n"); exit(15);
  }
  if((string)$get("BALE_SAFIR_CATALOG_PATH")!==$argv[2]){
    fwrite(STDERR,"CATALOG_PATH_NOT_EFFECTIVE\n"); exit(16);
  }
  $templates=(new \App\Services\BaleSafirTemplateCatalogService())->all();
  if(count($templates)<1){fwrite(STDERR,"NO_TEMPLATES\n");exit(17);}
  echo "SAFE_FLAGS=PASS\nCATALOG_PATH_EFFECTIVE=PASS\nCATALOG_TEMPLATES_READ=PASS\n";
' "$STAGE" "$CATALOG" || fail RUNTIME_ENV_VALIDATION_FAILED

[ "$(readlink -e "$CURRENT")" = "$ACTIVE" ] || fail ACTIVE_RELEASE_SWITCHED
printf '\nSAFIR_STAGE_RUNTIME_PREP=PASS\nACTIVE_RELEASE_UNTOUCHED=YES\nOTHER_MODULES_UNTOUCHED=YES\nDB_MUTATION=NO\nLIVE_SEND=NO\nCUTOVER=NO\n'
printf 'STAGE_PATH=%s\nNEXT=PREPARE_IMMUTABLE_PORTAL_CANDIDATE\n' "$STAGE"
