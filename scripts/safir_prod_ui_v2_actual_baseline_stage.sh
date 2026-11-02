#!/usr/bin/env bash
# IPKF Safir UI V2: additive stage built against the verified LIVE Portal source.
# No symlink change, DB mutation, service reload, API send or private env copy.
set -Eeuo pipefail
umask 077
fail(){ printf 'FAIL=%s\n' "$*" >&2; exit 1; }
[ "$(id -u)" -eq 0 ] || fail ROOT_REQUIRED
for bin in git curl rsync php python3 sha256sum; do
 command -v "$bin" >/dev/null || fail "TOOL_MISSING:$bin"
done
BASE=/srv/troca/portal-test
CURRENT="$BASE/current"
ACTIVE="$BASE/releases/prod-safir-portal-20261101T130944Z-92749b72"
STAGING=/srv/troca/_staging/releases
SOURCE=a9c8e01fea84ba62e771c48a70a9a6c455b8f03a
REMOTE="https://raw.githubusercontent.com/ipkf-prime/troca/$SOURCE/public_html"
[ -L "$CURRENT" ] && [ "$(readlink -e "$CURRENT")" = "$ACTIVE" ] || fail ACTIVE_RELEASE_CHANGED
[ -f "$ACTIVE.safir-cutover" ] || fail PREVIOUS_SAFIR_CUTOVER_RECEIPT_MISSING
[ -d "$STAGING" ] && [ ! -L "$STAGING" ] || fail STAGING_BASE_INVALID
WORK="$(mktemp -d "$STAGING/safir-ui-v2-patched-XXXXXXXX")"
chmod 0700 "$WORK"
mkdir -p "$WORK/app" "$WORK/meta" "$WORK/tests"

# File | exact Git-blob hash of running Portal | implementation.
# The three divergent Production-managed files must be patched, not replaced.
cat > "$WORK/meta/pins.txt" <<'PINS'
app/Services/BaleSafirTemplateCatalogService.php|ed09578761a4c83e28d8da7807843d0fd16308f5|4e416a22578635af4c215882ecb1c6e8c4adcea1
app/Services/AdminNavigationRbacService.php|cc9c41a5bf790e5d0c27b050f74b7b909d4010ef|PATCH_RBAC
app/Services/DynamicAdminNavigationService.php|6de2bf3531c043436ce9153b98d3d82738288fb4|PATCH_MENU
resources/views/admin/communication-settings.php|7e4bfe3876e1cb8ec297b4f25b58caf21437eaec|PATCH_TABS
resources/views/admin/partials/bale-safir-campaign.php|0ead7d17db61752f8d53ed7664db7be9873b86d4|17583b885d0450a99f76e323bd24f6536b0ac6be
resources/views/admin/safir-management.php|NEW|a4f4a41c253ead960c0a024d9c508a7f4c5be104
routes/bale-safir-campaigns.php|7f6cac60063b7e13cb38d6e361a3368a3fdd4385|ed5b586a477895625d284b714b6591a54aabb175
routes/communication-center.php|b8a03cfc2ca35cb382c52a5f8b2e26e87a896a58|150caeb730ff0fc6c8798f34b3df1f88f4651a98
PINS
cut -d'|' -f1 "$WORK/meta/pins.txt" > "$WORK/meta/allowed.txt"

while IFS='|' read -r path old new; do
  if [ "$old" = NEW ]; then
    [ ! -e "$ACTIVE/$path" ] && [ ! -L "$ACTIVE/$path" ] ||
      fail "UNEXPECTED_EXISTING_NEW_FILE:$path"
  else
    [ -f "$ACTIVE/$path" ] && [ ! -L "$ACTIVE/$path" ] || fail "BASELINE_MISSING:$path"
    actual="$(git hash-object "$ACTIVE/$path")"
    [ "$actual" = "$old" ] || fail "BASELINE_UNEXPECTED_CHANGE:$path"
  fi
done < "$WORK/meta/pins.txt"
echo EIGHT_FILE_ACTUAL_PRODUCTION_BASELINE=PASS

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
  "$ACTIVE/" "$WORK/app/" || fail PORTAL_SNAPSHOT_FAILED
echo PORTAL_SOURCE_SNAPSHOT=PASS

while IFS='|' read -r path old new; do
  case "$new" in PATCH_*) continue;; esac
  tmp="$WORK/downloaded"
  curl -fLsS "$REMOTE/$path" -o "$tmp" || fail "DOWNLOAD_FAILED:$path"
  [ "$(git hash-object "$tmp")" = "$new" ] || fail "NEW_GIT_BLOB_MISMATCH:$path"
  install -D -m 0644 "$tmp" "$WORK/app/$path"
  php -l "$WORK/app/$path" >/dev/null || fail "NEW_PHP_SYNTAX_FAILED:$path"
done < "$WORK/meta/pins.txt"
rm -f "$WORK/downloaded"
echo REVIEWED_FIVE_FILE_REPLACEMENTS=PASS

# Fetch the reviewed *method only* from current dev source for the live nav patch.
NAV="$WORK/meta/dev-dynamic-nav.php"
curl -fLsS "$REMOTE/app/Services/DynamicAdminNavigationService.php" -o "$NAV" ||
  fail NAV_SOURCE_DOWNLOAD_FAILED
[ "$(git hash-object "$NAV")" = f5268c653f32df63b8514c829a603b34c538e43e ] ||
  fail NAV_SOURCE_BLOB_MISMATCH

python3 - "$WORK/app" "$NAV" <<'PY'
import sys
from pathlib import Path
root = Path(sys.argv[1])
nav_reference = Path(sys.argv[2]).read_text(encoding="utf-8")
def patch(relative, old, new, contract):
    path = root / relative
    original = path.read_text(encoding="utf-8")
    if original.count(old) != 1 or new in original:
        raise SystemExit("FAIL=PATCH_ANCHOR_UNEXPECTED:" + contract)
    path.write_text(original.replace(old, new, 1), encoding="utf-8")
    print("ADDITIVE_PATCH=PASS:" + contract)

rbac = 'app/Services/AdminNavigationRbacService.php'
path = root / rbac
source = path.read_text(encoding="utf-8")
anchor = "            '/admin/communications/settings/safir' => 'notifications.send.manage',"
insertion = "            '/admin/communications/safir' => 'notifications.send.manage',\n"
if source.count(anchor) != 1 or insertion in source:
    raise SystemExit("FAIL=RBAC_PATCH_PREIMAGE_UNEXPECTED")
path.write_text(source.replace(anchor, insertion + anchor, 1), encoding="utf-8")
print("ADDITIVE_PATCH=PASS:RBAC")

nav = 'app/Services/DynamicAdminNavigationService.php'
nav_file = root / nav
existing = nav_file.read_text(encoding="utf-8")
call_anchor = """            $items =
                $this->withTicketingPortalManagement(
                    $items,
                    $shellKey
                );"""
call_new = call_anchor + """

            // Virtual Core navigation, no shared DB writes.
            $items = $this->withSafirCampaignManagement($items, $shellKey);"""
method_start = nav_reference.find("    /**\n     * Portal-only Safir navigation.")
method_end = nav_reference.find("    private function withTicketingPortalManagement(", method_start)
if method_start < 0 or method_end < 0 or method_end <= method_start:
    raise SystemExit("FAIL=DEV_NAV_METHOD_MISSING")
method = nav_reference[method_start:method_end]
if 'private function withSafirCampaignManagement(' not in method or '/admin/communications/safir' not in method:
    raise SystemExit("FAIL=DEV_NAV_METHOD_UNEXPECTED")
if existing.count(call_anchor) != 1 or existing.count("    private function withTicketingPortalManagement(") != 1:
    raise SystemExit("FAIL=LIVE_NAV_PATCH_ANCHOR_UNEXPECTED")
if "withSafirCampaignManagement(" in existing:
    raise SystemExit("FAIL=LIVE_NAV_ALREADY_PATCHED")
modified = existing.replace(call_anchor, call_new, 1)
modified = modified.replace("    private function withTicketingPortalManagement(", method + "    private function withTicketingPortalManagement(", 1)
nav_file.write_text(modified, encoding="utf-8")
print("ADDITIVE_PATCH=PASS:DYNAMIC_NAV")

settings = 'resources/views/admin/communication-settings.php'
tab = "        <?php foreach ($sections as $key => $label): ?>"
patch(settings, tab, tab + "\n            <?php if ($key === 'safir') continue; // Dedicated Safir sidebar ?>", "LEGACY_TAB_HIDE")
PY

for path in app/Services/AdminNavigationRbacService.php app/Services/DynamicAdminNavigationService.php resources/views/admin/communication-settings.php; do
  php -l "$WORK/app/$path" >/dev/null || fail "PATCHED_PHP_LINT_FAILED:$path"
done
echo THREE_PRODUCTION_PRESERVING_PATCHES=PASS

# Strictly limit code differences to the eight approved paths.
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
  "$ACTIVE/" "$WORK/app/")" || fail SOURCE_DIFF_CHECK_FAILED
echo "$DIFF" | cut -d'|' -f2- | sort > "$WORK/meta/diff-paths.txt"
sort "$WORK/meta/allowed.txt" > "$WORK/meta/expected-paths.txt"
if ! diff -u "$WORK/meta/expected-paths.txt" "$WORK/meta/diff-paths.txt" >/dev/null; then
  echo "FAIL=EXACT_EIGHT_FILE_SCOPE_DIFFERENT" >&2
  exit 1
fi
echo EXACT_EIGHT_FILE_CHANGE_SCOPE=PASS

# Check the new template and UI contracts offline; no provider calls, no live env.
TEST="$WORK/tests/bale-safir-campaign-smoke.php"
curl -fLsS "https://raw.githubusercontent.com/ipkf-prime/troca/$SOURCE/tests/bale-safir-campaign-smoke.php" -o "$TEST" ||
  fail OFFLINE_TEST_DOWNLOAD_FAILED
[ "$(git hash-object "$TEST")" = eda817315d189e216042769b2ba02a657d1ac499 ] ||
  fail OFFLINE_TEST_BLOB_MISMATCH
ln -s "$WORK/app" "$WORK/public_html"
php "$TEST" > "$WORK/meta/offline-smoke.log" || fail OFFLINE_SMOKE_FAILED
grep -Fxq BALE_SAFIR_DYNAMIC_OFFLINE_SMOKE=PASS "$WORK/meta/offline-smoke.log" ||
  fail OFFLINE_SMOKE_RESULT_MISSING
echo OFFLINE_TEMPLATE_VARIABLE_TESTS=PASS

[ "$(readlink -e "$CURRENT")" = "$ACTIVE" ] || fail ACTIVE_SWITCHED_DURING_STAGE
cat > "$WORK/meta/receipt.txt" <<EOF
SAFIR_UI_V2_STAGE=PASS
SOURCE_COMMIT=$SOURCE
PORTAL_BASELINE=$ACTIVE
STAGE_PATH=$WORK/app
BASELINE_RBAC_BLOB=cc9c41a5bf790e5d0c27b050f74b7b909d4010ef
BASELINE_DYNAMIC_NAV_BLOB=6de2bf3531c043436ce9153b98d3d82738288fb4
BASELINE_SETTINGS_BLOB=7e4bfe3876e1cb8ec297b4f25b58caf21437eaec
CUTOVER=NO
DB_MUTATION=NO
LIVE_SEND=NO
EOF
echo SAFIR_UI_V2_STAGE=PASS
echo ACTIVE_PORTAL_UNTOUCHED=YES
echo OTHER_MODULES_UNTOUCHED=YES
echo DB_MUTATION=NO
echo LIVE_SEND=NO
echo CUTOVER=NO
echo "STAGE_PATH=$WORK/app"
echo NEXT=REVIEW_STAGE_RUNTIME_AND_BUILD_IMMUTABLE_RELEASE
