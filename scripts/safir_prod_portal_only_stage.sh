#!/usr/bin/env bash
# SAFIR PORTAL-ONLY STAGE — no deploy, no current switch, no DB or API calls.
set -Eeuo pipefail
umask 077
fail(){ printf 'FAIL=%s\n' "$*" >&2; exit 1; }
[ "$(id -u)" -eq 0 ] || fail ROOT_REQUIRED
for bin in git curl rsync php sha256sum awk; do command -v "$bin" >/dev/null || fail "MISSING_$bin"; done
BASE=/srv/troca/portal-test
CURRENT="$BASE/current"
STAGING=/srv/troca/_staging/releases
PIN=3495b67e127dc856cfc7e681814f7005015550d1
PIN_BLOB=b6352ae76c683ace2faf59b7a259596cb0c52e3a
[ -L "$CURRENT" ] || fail CURRENT_NOT_SYMLINK
ACTIVE="$(readlink -e "$CURRENT")" || fail ACTIVE_NOT_READABLE
case "$ACTIVE" in "$BASE"/releases/*) ;; *) fail ACTIVE_OUTSIDE_RELEASE_TREE;; esac
[ -d "$STAGING" ] || fail NO_STAGE_DIRECTORY

WORK="$(mktemp -d "$STAGING/safir-portal-stage-XXXXXXXX")"
chmod 0700 "$WORK"
mkdir -p "$WORK/app" "$WORK/meta"
curl -fLsS "https://raw.githubusercontent.com/ipkf-prime/troca/$PIN/scripts/safir_prod_overlay_preflight.sh" \
  -o "$WORK/pinned-preflight.sh" || fail PINNED_SOURCE_FETCH_FAILED
[ "$(git hash-object "$WORK/pinned-preflight.sh")" = "$PIN_BLOB" ] || fail PINNED_SOURCE_HASH_MISMATCH
bash -n "$WORK/pinned-preflight.sh"
bash "$WORK/pinned-preflight.sh" > "$WORK/preflight.log" 2>&1 || {
  cat "$WORK/preflight.log"
  fail OVERLAY_PREFLIGHT_FAILED
}
grep -Fxq SAFIR_PROD_OVERLAY_PREFLIGHT=PASS "$WORK/preflight.log" || fail NO_PREFLIGHT_PASS
OVERLAY="$(sed -n 's/^OVERLAY_PATH=//p' "$WORK/preflight.log" | tail -1)"
SOURCE="$(sed -n 's/^SOURCE_COMMIT=//p' "$WORK/preflight.log" | tail -1)"
case "$OVERLAY" in "$STAGING"/safir-source-preflight-*/overlay) ;; *) fail INVALID_OVERLAY_PATH;; esac
[ -d "$OVERLAY/public_html" ] || fail OVERLAY_MISSING
echo REVIEWED_SAFIR_OVERLAY=PASS

# Snapshot current Portal, without runtime data or credentials.
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
echo PORTAL_ONLY_SNAPSHOT=PASS

# Copy ONLY known Safir integration files, never an entire repository.
for relative in \
 system/Routing/RouteLoader.php \
 app/Services/CommunicationSettingsService.php \
 app/Services/AdminNavigationRbacService.php \
 resources/views/admin/communication-settings.php \
 app/Services/BaleSafirCampaignService.php \
 app/Services/BaleSafirTemplateCatalogService.php \
 routes/bale-safir-campaigns.php \
 resources/views/admin/partials/bale-safir-campaign.php \
 resources/ui-content/bale-safir-templates.json; do
 [ -f "$OVERLAY/public_html/$relative" ] || fail "MISSING_PATCH:$relative"
 install -D -m 0644 "$OVERLAY/public_html/$relative" "$WORK/app/$relative"
done
echo PORTAL_ONLY_NINE_FILE_PATCH=PASS

# Any non-Safir source difference blocks the stage.
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
 "$ACTIVE/" "$WORK/app/")" || fail DIFF_CHECK_FAILED
while IFS= read -r line; do
 [ -n "$line" ] || continue
 case "$line" in *'|'*) ;; *) fail "INVALID_RSYNC_RECORD" ;; esac
 relative="${line#*|}"
 case "$relative" in
  system/Routing/RouteLoader.php|app/Services/CommunicationSettingsService.php|\
  app/Services/AdminNavigationRbacService.php|resources/views/admin/communication-settings.php|\
  app/Services/BaleSafirCampaignService.php|app/Services/BaleSafirTemplateCatalogService.php|\
  routes/bale-safir-campaigns.php|resources/views/admin/partials/bale-safir-campaign.php|\
  resources/ui-content/bale-safir-templates.json) ;;
  *) fail "UNEXPECTED_SOURCE_DIFF:$relative";;
 esac
done <<< "$DIFF"
echo NO_UNRELATED_CODE_CHANGES=PASS

find "$WORK/app" -type f -name '*.php' -print0 |
 while IFS= read -r -d '' item; do
  php -l "$item" >/dev/null || fail "PHP_LINT:$item"
 done
echo STAGED_PORTAL_PHP_LINT=PASS
[ "$(readlink -e "$CURRENT")" = "$ACTIVE" ] || fail PORTAL_RELEASE_MOVED
cat > "$WORK/meta/receipt.txt" <<EOF
PORTAL_BASELINE=$ACTIVE
SAFIR_SOURCE_COMMIT=$SOURCE
STAGE_PATH=$WORK/app
DATABASE_MUTATION=NO
PRODUCTION_CUTOVER=NO
LIVE_SEND=NO
EOF
printf 'SAFIR_PORTAL_ONLY_STAGE=PASS\nACTIVE_PROD_UNTOUCHED=YES\nOTHER_MODULES_UNTOUCHED=YES\nDB_MUTATION=NO\nLIVE_SEND=NO\n'
printf 'STAGE_PATH=%s\nSOURCE_COMMIT=%s\nNEXT=REVIEW_SCOPED_PORTAL_CUTOVER\n' "$WORK/app" "$SOURCE"
