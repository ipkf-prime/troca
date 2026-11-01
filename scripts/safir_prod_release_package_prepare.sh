#!/usr/bin/env bash
# Safir: build a verified-source package on an isolated Production snapshot.
# NO CUTOVER. NO API SEND. NO DATABASE WRITE. NO SERVICE RESTART.
set -Eeuo pipefail
umask 077
BASE=/srv/troca/portal-test
CURRENT="$BASE/current"
STAGING=/srv/troca/_staging/releases
PREFLIGHT_COMMIT=625e3d001caeb41b821f1bb9ca3a1982c14792fe
PREFLIGHT_BLOB=f0176c4195fef71559a3e5451fec42d96e1a6f93
fail() { printf 'FAIL=%s\n' "$*" >&2; exit 1; }
[ "$(id -u)" = 0 ] || fail ROOT_REQUIRED
for bin in git curl bash php python3 rsync tar sha256sum awk; do
  command -v "$bin" >/dev/null || fail "MISSING_TOOL:$bin"
done
[ -L "$CURRENT" ] || fail CURRENT_LINK_MISSING
ACTIVE="$(readlink -e "$CURRENT")" || fail CURRENT_NOT_ACCESSIBLE
case "$ACTIVE" in "$BASE"/releases/*) : ;; *) fail BAD_ACTIVE_TARGET ;; esac
[ -d "$STAGING" ] || fail STAGING_MISSING
[ -r /etc/ipkf-release/contract.env ] || fail RELEASE_CONTRACT_MISSING
# shellcheck disable=SC1091
source /etc/ipkf-release/contract.env

# The official deploy replaces all four modules. Fail closed on differences:
# never replace distinct code installed in Automation, Work or Ticketing.
for label in portal automation work service; do
  case "$label" in
    portal) MODULE_BASE="$PORTAL_BASE" ;;
    automation) MODULE_BASE="$AUTOMATION_BASE" ;;
    work) MODULE_BASE="$WORK_BASE" ;;
    service) MODULE_BASE="$SERVICE_BASE" ;;
  esac
  [ -L "$MODULE_BASE/current" ] || fail "MODULE_LINK_MISSING:$label"
  MODULE="$(readlink -e "$MODULE_BASE/current")" || fail "MODULE_TARGET_MISSING:$label"
  case "$MODULE" in "$MODULE_BASE"/releases/*) : ;; *) fail "MODULE_BAD_TARGET:$label" ;; esac
  if [ "$label" != portal ]; then
    DIFF="$(rsync -rnic --delete \
      --exclude='/.env*' --exclude='**/.env*' \
      --exclude='/storage' --exclude='/public/uploads' \
      --exclude='/.git' --exclude='**/.git' \
      --exclude='**/backups' --exclude='**/backup' \
      --exclude='**/_admin' --exclude='**/shared' \
      "$ACTIVE/" "$MODULE/")" || fail "MODULE_COMPARE_FAILED:$label"
    if [ -n "$DIFF" ]; then
      echo "MODULE_CODE_DIFFERS=$label"
      printf '%s\n' "$DIFF" | head -n 12
      fail "MULTIMODULE_DIVERGENCE:$label"
    fi
  fi
  echo "MODULE_BASELINE_$label=PASS"
done

SIZE="$(du -sk "$ACTIVE" | awk '{print $1}')"
FREE="$(df -Pk "$STAGING" | awk 'END{print $4}')"
[ "$FREE" -gt "$(( SIZE * 3 + 1048576 ))" ] || fail INSUFFICIENT_DISK_SPACE

WORK="$(mktemp -d "$STAGING/safir-package-prep-XXXXXXXX")"
chmod 700 "$WORK"
mkdir -p "$WORK/meta" "$WORK/app"
printf 'PREP_WORKSPACE=%s\n' "$WORK"

# Obtain and integrity-check the most recent review-pinned preflight.
curl -fLsS \
  "https://raw.githubusercontent.com/ipkf-prime/troca/$PREFLIGHT_COMMIT/scripts/safir_prod_overlay_preflight.sh" \
  -o "$WORK/preflight.sh" || fail PREFLIGHT_DOWNLOAD_FAILED
[ "$(git hash-object "$WORK/preflight.sh")" = "$PREFLIGHT_BLOB" ] || fail PREFLIGHT_BLOB_MISMATCH
bash -n "$WORK/preflight.sh" || fail PREFLIGHT_PARSE_FAILED

bash "$WORK/preflight.sh" > "$WORK/preflight.log" 2>&1 || {
  cat "$WORK/preflight.log"
  fail PREFLIGHT_FAILED
}
grep -Fxq SAFIR_PROD_OVERLAY_PREFLIGHT=PASS "$WORK/preflight.log" || fail PREFLIGHT_RECEIPT_MISSING
OVERLAY="$(sed -n 's/^OVERLAY_PATH=//p' "$WORK/preflight.log" | tail -n 1)"
SOURCE_COMMIT="$(sed -n 's/^SOURCE_COMMIT=//p' "$WORK/preflight.log" | tail -n 1)"
case "$OVERLAY" in "$STAGING"/safir-source-preflight-*/overlay) : ;; *) fail OVERLAY_OUTSIDE_STAGE ;; esac
printf '%s' "$SOURCE_COMMIT" | grep -Eq '^[a-f0-9]{40}$' || fail INVALID_COMMIT
[ -d "$OVERLAY/public_html" ] || fail OVERLAY_NOT_FOUND
echo PINNED_SAFIR_OVERLAY=PASS

# Source from live portal with all secrets and mutable runtime data excluded.
rsync -a \
  --exclude='/.env*' --exclude='**/.env*' \
  --exclude='/storage' --exclude='/public/uploads' \
  --exclude='/.git' --exclude='**/.git' \
  --exclude='**/backup' --exclude='**/backups' \
  --exclude='**/_admin' --exclude='**/shared' \
  --exclude='*.sql' --exclude='*.sql.gz' \
  --exclude='*.bak' --exclude='*.dump' \
  --exclude='*.mdb' --exclude='*.sqlite' \
  --exclude='*.pem' --exclude='*.key' \
  --exclude='*.p12' --exclude='*.pfx' \
  "$ACTIVE/" "$WORK/app/" || fail SNAPSHOT_COPY_FAILED
echo PROD_CODE_SNAPSHOT=PASS

# Apply only the nine explicitly reviewed files, not smoke dependencies.
for relative in \
  system/Routing/RouteLoader.php \
  app/Services/CommunicationSettingsService.php \
  app/Services/AdminNavigationRbacService.php \
  resources/views/admin/communication-settings.php \
  app/Services/BaleSafirCampaignService.php \
  app/Services/BaleSafirTemplateCatalogService.php \
  routes/bale-safir-campaigns.php \
  resources/views/admin/partials/bale-safir-campaign.php \
  resources/ui-content/bale-safir-templates.json
do
  [ -f "$OVERLAY/public_html/$relative" ] || fail "PATCH_FILE_MISSING:$relative"
  install -D -m 0644 "$OVERLAY/public_html/$relative" "$WORK/app/$relative"
done
echo ADDITIVE_NINE_FILE_PATCH=PASS

# No code or release metadata is changed in active Production.
[ "$(readlink -e "$CURRENT")" = "$ACTIVE" ] || fail ACTIVE_RELEASE_CHANGED
(
 cd "$ACTIVE"
 sha256sum -c - >/dev/null <<'SUMS'
ded5d77f129fb9493e2c4a69e935246ac7c97f143a1b92c0cee32cfe853e4135  public/index.php
f9824aaab334b4492f53d4ee6cc1cf0b14052182fa5792e70b8f8e98f2f31cdf  routes/web.php
76423289b6b47c87727c492cb0279712c3d46c3c8409cbb1bcce04b2e7d9ffee  routes/communication-center.php
bfc28fa0242a668b588122249db58ac01474736f2a7f9d8720f3b1a49841fd0a  system/Routing/RouteLoader.php
255289bb6e1619751a19c5eccc6bb0cab17d033d215698daefe7dc8918814855  app/Services/CommunicationSettingsService.php
c64c1672b4d4ef2f58903fbe7cc28b14ff75a2761bb803ff6d052c458a3660d0  app/Services/AdminNavigationRbacService.php
876966a04dac826324f39a0e5a87833c971ee086964eac7cc00e4460ec98444f  resources/views/admin/communication-settings.php
SUMS
) || fail PROD_BASELINE_CHANGED
echo PROD_UNTOUCHED_GATE=PASS

# Verify source archive is compatible with the official verifier.
for relative in \
  bootstrap/app.php public/index.php \
  system/Database/Application/ApplicationMigrationRegistry.php \
  system/Database/Application/ApplicationMigrationRunner.php
do
  [ -f "$WORK/app/$relative" ] || fail "APP_REQUIRED_FILE_MISSING:$relative"
done
if find "$WORK/app" -type l -print -quit | grep -q .; then
  fail UNREVIEWED_SYMLINK_IN_APP_ARCHIVE
fi
if find "$WORK/app" -type f \( \
  -name '.env' -o -name '.env.*' \
  -o -iname '*.sql' -o -iname '*.sql.gz' -o -iname '*.bak' \
  -o -iname '*.pem' -o -iname '*.pfx' -o -iname '*.p12' \
  -o -iname '*.key' -o -iname '*.dump' -o -iname '*.sqlite' \) -print -quit | grep -q .; then
  fail SECRET_OR_DATABASE_ARTIFACT_FOUND
fi
echo ARCHIVE_SAFETY=PASS

# Validate each PHP file in the full prospective release.
while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null || fail "PHP_LINT_FAILED:$file"
done < <(find "$WORK/app" -type f -name '*.php' -print0)
echo FULL_APP_PHP_LINT=PASS

SHORT="$(printf '%s' "$SOURCE_COMMIT" | cut -c 1-8)"
RELEASE_ID="prod-safir-dynamic-$(date -u +%Y%m%dT%H%M%SZ)-$SHORT"
printf '%s' "$RELEASE_ID" | grep -Eq '^[A-Za-z0-9._-]{8,100}$' || fail RELEASE_ID_INVALID
for label in portal automation work service; do
  case "$label" in
    portal) MODULE_BASE="$PORTAL_BASE" ;;
    automation) MODULE_BASE="$AUTOMATION_BASE" ;;
    work) MODULE_BASE="$WORK_BASE" ;;
    service) MODULE_BASE="$SERVICE_BASE" ;;
  esac
  [ ! -e "$MODULE_BASE/releases/$RELEASE_ID" ] || fail RELEASE_ID_ALREADY_USED
done
[ ! -e "$STAGING/$RELEASE_ID" ] || fail STAGE_ALREADY_USED

cat > "$WORK/meta/manifest.env" <<EOF
FORMAT=IPKF_RELEASE_V1
RELEASE_ID=$RELEASE_ID
SOURCE_COMMIT=$SOURCE_COMMIT
RUN_MIGRATIONS=NO
RUN_SYSTEM_SEEDS=NO
EOF
tar -C "$WORK/app" -czf "$WORK/meta/app.tar.gz" .
(
 cd "$WORK/meta"
 sha256sum manifest.env app.tar.gz > SHA256SUMS
 tar -czf "$WORK/ipkf-safir-dynamic-release.tar.gz" manifest.env app.tar.gz SHA256SUMS
)
[ -s "$WORK/ipkf-safir-dynamic-release.tar.gz" ] || fail PACKAGE_EMPTY
echo IMMUTABLE_PACKAGE_BUILT=PASS

# Stop before the official verifier. That tool creates server-wide staging
# directories and computes migration plans; review its full contract first.
echo '================================================================'
echo 'SAFIR_PRODUCTION_PACKAGE_PREP=PASS'
echo 'PRODUCTION_CUTOVER=NO'
echo 'DATABASE_MUTATION=NO'
echo 'LIVE_MESSAGE_SEND=NO'
echo "PACKAGE=$WORK/ipkf-safir-dynamic-release.tar.gz"
echo "RELEASE_ID=$RELEASE_ID"
echo "SOURCE_COMMIT=$SOURCE_COMMIT"
echo 'NEXT=OFFICIAL_VERIFY_THEN_SEPARATE_CUTOVER_APPROVAL'
echo '================================================================'
