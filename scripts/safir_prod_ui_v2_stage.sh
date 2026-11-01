#!/usr/bin/env bash
# Safir UI v2 - eight-file Portal-only stage. No Production cutover or sends.
set -Eeuo pipefail
umask 077
fail(){ printf 'FAIL=%s\n' "$*" >&2; exit 1; }
[ "$(id -u)" -eq 0 ] || fail ROOT_REQUIRED
for bin in git curl rsync php; do command -v "$bin" >/dev/null || fail "MISSING:$bin"; done
BASE=/srv/troca/portal-test
ACTIVE="$BASE/releases/prod-safir-portal-20261101T130944Z-92749b72"
CURRENT="$BASE/current"
SOURCE=75c9045b4ae1366f4810b13ba6c4e12720b6e3d4
REMOTE="https://raw.githubusercontent.com/ipkf-prime/troca/$SOURCE/public_html"
STAGING=/srv/troca/_staging/releases
[ -L "$CURRENT" ] && [ "$(readlink -e "$CURRENT")" = "$ACTIVE" ] || fail PORTAL_BASELINE_CHANGED
[ -f "$ACTIVE.safir-cutover" ] || fail ORIGINAL_CUTOVER_RECEIPT_NOT_FOUND
[ -d "$STAGING" ] || fail STAGING_ROOT_MISSING
WORK="$(mktemp -d "$STAGING/safir-ui-v2-XXXXXXXX")"
chmod 0700 "$WORK"
mkdir -p "$WORK/app" "$WORK/meta"
cat > "$WORK/meta/pins.txt" <<'PINS'
app/Services/BaleSafirTemplateCatalogService.php|ed09578761a4c83e28d8da7807843d0fd16308f5|4e416a22578635af4c215882ecb1c6e8c4adcea1
app/Services/AdminNavigationRbacService.php|6ed28d4c205904298d3509da1f151c9b9127cbcd|9db72cc23d292e9979fbcf0f7bbca348f3d48ce0
app/Services/DynamicAdminNavigationService.php|109dc227636b5744d888848dd6d88cc88ce52c41|f5268c653f32df63b8514c829a603b34c538e43e
resources/views/admin/communication-settings.php|93895868520ab14c7972cc026b1ef505607a8100|63f0af2918b180dc0ff79e0653c762dcb05410b0
resources/views/admin/partials/bale-safir-campaign.php|0ead7d17db61752f8d53ed7664db7be9873b86d4|50ab7200ad5d70787d7b9f704d23fd40dec50594
resources/views/admin/safir-management.php|NEW|a4f4a41c253ead960c0a024d9c508a7f4c5be104
routes/bale-safir-campaigns.php|7f6cac60063b7e13cb38d6a361a3368a3fdd4385|0871781d9e69d5158d69d78ec72fe4f5557856a3
routes/communication-center.php|b8a03cfc2ca35cb382c52a5f8b2e26e87a896a58|150caeb730ff0fc6c8798f34b3df1f88f4651a98
PINS
cut -d'|' -f1 "$WORK/meta/pins.txt" > "$WORK/meta/allowed.txt"

while IFS='|' read -r path old new; do
  if [ "$old" = NEW ]; then
    [ ! -e "$ACTIVE/$path" ] && [ ! -L "$ACTIVE/$path" ] ||
      fail "UNEXPECTED_EXISTING_NEW_FILE:$path"
  else
    [ -f "$ACTIVE/$path" ] && [ ! -L "$ACTIVE/$path" ] || fail "BASELINE_FILE_MISSING:$path"
    actual="$(git hash-object "$ACTIVE/$path")"
    [ "$actual" = "$old" ] || fail "BASELINE_SOURCE_DIVERGED:$path"
  fi
done < "$WORK/meta/pins.txt"
echo PINNED_PRODUCTION_BASELINE=PASS

rsync -a \
  --exclude='/.env*' --exclude='**/.env*' \
  --exclude='/storage' --exclude='/public/uploads' \
  --exclude='/.git' --exclude='**/.git' \
  --exclude='**/backups' --exclude='**/backup' \
  --exclude='**/_admin' --exclude='**/*.pem' \
  --exclude='**/*.key' --exclude='**/*.p12' \
  --exclude='**/*.pfx' --exclude='**/*.sql' \
  --exclude='**/*.sql.gz' --exclude='**/*.dump' \
  --exclude='**/*.bak' --exclude='**/*.sqlite' \
  "$ACTIVE/" "$WORK/app/" || fail SNAPSHOT_COPY_FAILED
echo ACTIVE_PORTAL_SNAPSHOT=PASS

while IFS='|' read -r path old new; do
  source="$WORK/download"
  curl -fLsS "$REMOTE/$path" -o "$source" || fail "SOURCE_DOWNLOAD_FAILED:$path"
  [ "$(git hash-object "$source")" = "$new" ] || fail "SOURCE_BLOB_MISMATCH:$path"
  install -D -m 0644 "$source" "$WORK/app/$path"
  php -l "$WORK/app/$path" >/dev/null || fail "PHP_LINT_FAILED:$path"
done < "$WORK/meta/pins.txt"
rm -f "$WORK/download"
echo PINNED_EIGHT_FILE_PATCH_AND_PHP_LINT=PASS

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
  "$ACTIVE/" "$WORK/app/")" || fail CODE_DIFF_FAILED
while IFS= read -r record; do
  [ -n "$record" ] || continue
  case "$record" in *'|'*) :;; *) fail INVALID_DIFF_RECORD;; esac
  relative="$(printf '%s\n' "$record" | cut -d'|' -f2-)"
  grep -Fxq "$relative" "$WORK/meta/allowed.txt" ||
    fail "UNEXPECTED_SOURCE_DIFF:$relative"
done <<< "$DIFF"
echo EIGHT_FILE_SCOPE=PASS
[ "$(readlink -e "$CURRENT")" = "$ACTIVE" ] || fail CURRENT_CHANGED
cat > "$WORK/meta/receipt.txt" <<EOF
SAFIR_UI_V2_STAGE=PASS
SOURCE_COMMIT=$SOURCE
BASELINE=$ACTIVE
STAGE_PATH=$WORK/app
CUTOVER=NO
DB_MUTATION=NO
LIVE_SEND=NO
EOF
echo SAFIR_UI_V2_STAGE=PASS
echo ACTIVE_PRODUCTION_UNTOUCHED=YES
echo OTHER_MODULES_UNTOUCHED=YES
echo DB_MUTATION=NO
echo LIVE_SEND=NO
echo CUTOVER=NO
echo "STAGE_PATH=$WORK/app"
echo NEXT=PORTAL_UI_V2_CANDIDATE_RELEASE_REVIEW
