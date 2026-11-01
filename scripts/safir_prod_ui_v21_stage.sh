#!/usr/bin/env bash
# IPKF Safir UI 2.1 - three-file UI/label-only Stage against LIVE Portal UI V2.
# Never switches current, changes DB, reads/prints private env, or calls Safir.
set -Eeuo pipefail
umask 077
fail(){ printf 'FAIL=%s\n' "$*" >&2; exit 1; }
[ "$(id -u)" -eq 0 ] || fail ROOT_REQUIRED
for cmd in git curl rsync php python3 cmp sha256sum; do
  command -v "$cmd" >/dev/null || fail "TOOL_MISSING:$cmd"
done

BASE=/srv/troca/portal-test
CURRENT="$BASE/current"
ACTIVE="$BASE/releases/prod-safir-ui-v2-20261101-a9c8e01"
OLD_STAGE=/srv/troca/_staging/releases/safir-ui-v2-patched-QriAUWxk/app
STAGING=/srv/troca/_staging/releases
SOURCE=4a53e8c034063b9e9d1cc8673a19a458a7bb31e7
REMOTE="https://raw.githubusercontent.com/ipkf-prime/troca/$SOURCE"

[ -L "$CURRENT" ] && [ "$(readlink -e "$CURRENT")" = "$ACTIVE" ] ||
 fail ACTIVE_PORTAL_RELEASE_CHANGED
[ -f "$ACTIVE.safir-ui-v2-cutover" ] || fail UI_V2_SUCCESS_RECEIPT_MISSING
[ -d "$OLD_STAGE" ] && [ ! -L "$OLD_STAGE" ] || fail UI_V2_STAGE_MISSING
[ -d "$STAGING" ] && [ ! -L "$STAGING" ] || fail STAGING_BASE_INVALID

# Source provenance: current UI V2 must equal the reviewed V2 stage on
# these three files. Never assume the Git branch equals LIVE patched files.
for p in \
 app/Services/DynamicAdminNavigationService.php \
 routes/bale-safir-campaigns.php \
 resources/views/admin/partials/bale-safir-campaign.php; do
  [ -f "$ACTIVE/$p" ] && [ -f "$OLD_STAGE/$p" ] ||
    fail "BASELINE_FILE_MISSING:$p"
  cmp -s "$OLD_STAGE/$p" "$ACTIVE/$p" ||
    fail "ACTIVE_SOURCE_DIFFERS_FROM_REVIEWED_V2_STAGE:$p"
done
[ "$(git hash-object "$ACTIVE/routes/bale-safir-campaigns.php")" = \
  ed5b586a477895625d284b714b6591a54aabb175 ] ||
  fail ROUTE_V2_BLOB_MISMATCH
[ "$(git hash-object "$ACTIVE/resources/views/admin/partials/bale-safir-campaign.php")" = \
  17583b885d0450a99f76e323bd24f6536b0ac6be ] ||
  fail VIEW_V2_BLOB_MISMATCH
echo REVIEWED_UI_V2_BASELINE=PASS

WORK="$(mktemp -d "$STAGING/safir-ui-v21-XXXXXXXX")"
chmod 0700 "$WORK"
mkdir -p "$WORK/app" "$WORK/meta" "$WORK/tests"
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
echo PORTAL_ONLY_SOURCE_SNAPSHOT=PASS

VIEW="$WORK/app/resources/views/admin/partials/bale-safir-campaign.php"
curl -fLsS \
 "$REMOTE/public_html/resources/views/admin/partials/bale-safir-campaign.php" \
 -o "$WORK/meta/reviewed-view.php" || fail UI_VIEW_DOWNLOAD_FAILED
[ "$(git hash-object "$WORK/meta/reviewed-view.php")" = \
  e3329bbd06cbdfba53a802f8182eb08bad3da5b1 ] ||
 fail REVIEWED_UI_VIEW_BLOB_INVALID
install -m 0644 "$WORK/meta/reviewed-view.php" "$VIEW"
echo REVIEWED_MINIMAL_UI_PATCH=PASS

# Apply exactly one Persian title replacement to each LIVE-derived file.
# Do not replace the entire shared Navigation class from Git.
python3 - "$WORK/app" <<'PY'
from pathlib import Path
import sys
root = Path(sys.argv[1])
patches = [
 ('app/Services/DynamicAdminNavigationService.php',
  "'title' => 'کمپین‌های سفیر بله',",
  "'title' => 'قالب‌های سفیر بله',"),
 ('routes/bale-safir-campaigns.php',
  "'title' => 'کمپین‌های سفیر بله',",
  "'title' => 'مدیریت قالب‌های سفیر بله',"),
]
for relative, before, after in patches:
    file = root / relative
    content = file.read_text('utf-8')
    if content.count(before) != 1 or after in content:
        raise SystemExit('FAIL=LABEL_PATCH_PREIMAGE_UNEXPECTED:' + relative)
    file.write_text(content.replace(before, after, 1), encoding='utf-8')
    print('SINGLE_LABEL_PATCH=PASS:' + relative)
PY

for relative in \
 app/Services/DynamicAdminNavigationService.php \
 routes/bale-safir-campaigns.php \
 resources/views/admin/partials/bale-safir-campaign.php; do
 php -l "$WORK/app/$relative" >/dev/null ||
   fail "PHP_LINT_FAILED:$relative"
done
echo THREE_UI_FILES_SYNTAX=PASS

# Ensure nothing else changed; hidden files, other modules, and send logic
# are not permitted in this UI-only Stage.
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
 --out-format='%i|%n' "$ACTIVE/" "$WORK/app/")" ||
 fail SOURCE_DIFF_FAILED
printf '%s\n' "$DIFF" | sed -n 's/^[^|]*|//p' | sort > "$WORK/meta/actual-diff.txt"
printf '%s\n' \
 app/Services/DynamicAdminNavigationService.php \
 resources/views/admin/partials/bale-safir-campaign.php \
 routes/bale-safir-campaigns.php | sort > "$WORK/meta/allowed-diff.txt"
cmp -s "$WORK/meta/actual-diff.txt" "$WORK/meta/allowed-diff.txt" ||
  fail NOT_EXACT_THREE_FILE_UI_PATCH
echo EXACT_THREE_FILE_UI_ONLY_SCOPE=PASS

# The contract test does not bootstrap application, DB, session or provider.
TEST="$WORK/tests/safir-ui-v21-contract-smoke.php"
curl -fLsS "$REMOTE/tests/safir-ui-v21-contract-smoke.php" \
  -o "$TEST" || fail CONTRACT_TEST_DOWNLOAD_FAILED
[ "$(git hash-object "$TEST")" = acfce6418dbe902ca4327e72d58413da6fd579d6 ] ||
  fail CONTRACT_TEST_HASH_CHANGED
ln -s "$WORK/app" "$WORK/public_html"
php "$TEST" > "$WORK/meta/offline-ui-smoke.log" ||
  fail OFFLINE_UI_CONTRACT_TEST_FAILED
grep -Fxq SAFIR_UI_V21_STATIC_SMOKE=PASS "$WORK/meta/offline-ui-smoke.log" ||
  fail OFFLINE_UI_CONTRACT_PASS_MISSING
echo OFFLINE_UI_V21_CONTRACT=PASS

# Immutable list of exact before/after content IDs for next release builder.
{
 for relative in \
 app/Services/DynamicAdminNavigationService.php \
 resources/views/admin/partials/bale-safir-campaign.php \
 routes/bale-safir-campaigns.php; do
   printf '%s|%s|%s\n' "$relative" \
     "$(git hash-object "$ACTIVE/$relative")" \
     "$(git hash-object "$WORK/app/$relative")"
 done
} > "$WORK/meta/three-file-pins.txt"

[ "$(readlink -e "$CURRENT")" = "$ACTIVE" ] ||
  fail ACTIVE_RELEASE_CHANGED_DURING_STAGE
cat > "$WORK/meta/receipt.txt" <<EOF
SAFIR_UI_V21_STAGE=PASS
SOURCE_COMMIT=$SOURCE
ACTIVE_BEFORE=$ACTIVE
STAGE_PATH=$WORK/app
CHANGED_FILES=3
CUTOVER=NO
DB_MUTATION=NO
LIVE_SEND=NO
EOF
echo '=========================================='
echo SAFIR_UI_V21_STAGE=PASS
echo REVIEWED_UI_V2_BASELINE=PASS
echo EXACT_THREE_FILE_UI_ONLY_SCOPE=PASS
echo OFFLINE_UI_V21_CONTRACT=PASS
echo ACTIVE_PORTAL_UNTOUCHED=YES
echo AUTOMATION_WORK_TICKETING_UNTOUCHED=YES
echo DB_MUTATION=NO
echo LIVE_SEND=NO
echo CUTOVER=NO
echo "STAGE_PATH=$WORK/app"
echo "STAGE_RECEIPT=$WORK/meta/receipt.txt"
echo NEXT=PREPARE_UI_V21_IMMUTABLE_RELEASE
echo '=========================================='
