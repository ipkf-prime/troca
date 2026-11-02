#!/usr/bin/env bash
# IPKF Safir UI V2.2 + manual template test: strict 3-file Portal-only stage.
# Does NOT switch current, mutate DB, modify env, or send any provider message.
set -Eeuo pipefail
umask 077
fail(){ printf 'FAIL=%s\n' "$*" >&2; exit 1; }
[ "$(id -u)" = 0 ] || fail ROOT_REQUIRED
for cmd in git curl rsync php sha256sum readlink cmp; do
 command -v "$cmd" >/dev/null || fail "MISSING_TOOL:$cmd"
done
BASE=/srv/troca/portal-test
ACTIVE="$BASE/releases/prod-safir-ui-v21-20261101-4a53e8c0"
CURRENT="$BASE/current"
STAGING=/srv/troca/_staging/releases
SOURCE=3a56722861d673099d36a5b4e97d995ef8313152
REMOTE="https://raw.githubusercontent.com/ipkf-prime/troca/$SOURCE"
[ -L "$CURRENT" ] && [ "$(readlink -e "$CURRENT")" = "$ACTIVE" ] ||
 fail ACTIVE_PORTAL_RELEASE_CHANGED
[ -f "$ACTIVE.safir-ui-v21-cutover" ] || fail UI_V21_CUTOVER_RECEIPT_MISSING
[ -d "$STAGING" ] && [ ! -L "$STAGING" ] || fail STAGING_ROOT_MISSING

WORK="$(mktemp -d "$STAGING/safir-ui-v22-test-XXXXXXXX")"
chmod 0700 "$WORK"
mkdir -p "$WORK/app" "$WORK/meta" "$WORK/tests"
cat > "$WORK/meta/pins.txt" <<'PINS'
app/Services/BaleSafirCampaignService.php|f2b55479a702fbe09562aa3c182fba737a079e60|9767a4a061cd525be22b7f126b9e5b4d5dbd16b4
resources/views/admin/partials/bale-safir-campaign.php|e3329bbd06cbdfba53a802f8182eb08bad3da5b1|ff8c614b64dcaed25b91b8c39765976bdaf82cfa
routes/bale-safir-campaigns.php|574708a4b8a05d850c8a07373a6aa249dc5115fe|528fea17780163e298a8da8cf9c0402a490235d9
PINS

while IFS='|' read -r path original updated; do
  [ -f "$ACTIVE/$path" ] && [ ! -L "$ACTIVE/$path" ] ||
    fail "BASELINE_FILE_MISSING:$path"
  [ "$(git hash-object "$ACTIVE/$path")" = "$original" ] ||
    fail "PRODUCTION_BASELINE_DIVERGED:$path"
done < "$WORK/meta/pins.txt"
echo UI_V21_BASELINE_THREE_FILES=PASS

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

while IFS='|' read -r path original updated; do
  tempfile="$WORK/meta/download"
  curl -fLsS "$REMOTE/public_html/$path" -o "$tempfile" ||
    fail "REVIEWED_SOURCE_DOWNLOAD_FAILED:$path"
  [ "$(git hash-object "$tempfile")" = "$updated" ] ||
    fail "REVIEWED_SOURCE_BLOB_MISMATCH:$path"
  install -D -m 0644 "$tempfile" "$WORK/app/$path"
  php -l "$WORK/app/$path" >/dev/null ||
    fail "REVIEWED_PHP_LINT_FAILED:$path"
done < "$WORK/meta/pins.txt"
rm -f "$WORK/meta/download"
echo PINNED_THREE_FILE_PATCH_AND_PHP_LINT=PASS

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
printf '%s\n' "$DIFF" | sed -n 's/^[^|]*|//p' | sort > "$WORK/meta/actual.txt"
cut -d'|' -f1 "$WORK/meta/pins.txt" | sort > "$WORK/meta/allowed.txt"
cmp -s "$WORK/meta/actual.txt" "$WORK/meta/allowed.txt" ||
 fail EXTRA_OR_MISSING_CODE_CHANGE
echo EXACT_THREE_FILE_CHANGE_SCOPE=PASS

# Download the verified offline PHP smoke and run it against staged source.
curl -fLsS "$REMOTE/tests/bale-safir-campaign-smoke.php" \
 -o "$WORK/tests/bale-safir-campaign-smoke.php" ||
 fail OFFLINE_TEST_DOWNLOAD_FAILED
[ "$(git hash-object "$WORK/tests/bale-safir-campaign-smoke.php")" = \
  91a3fc5918ffb1372141cadc79d214066ec47f4c ] ||
 fail OFFLINE_TEST_CHANGED
ln -s "$WORK/app" "$WORK/public_html"
php "$WORK/tests/bale-safir-campaign-smoke.php" > "$WORK/meta/offline-smoke.log" ||
 fail OFFLINE_TEMPLATE_AND_MANUAL_TEST_FAILED
grep -Fxq BALE_SAFIR_DYNAMIC_OFFLINE_SMOKE=PASS \
 "$WORK/meta/offline-smoke.log" || fail OFFLINE_SMOKE_PASS_MISSING
grep -Fxq MANUAL_TEST_PRODUCTION_FAIL_CLOSED=PASS \
 "$WORK/meta/offline-smoke.log" || fail MANUAL_TEST_SEND_GATE_MISSING
grep -Fxq MANUAL_TEST_VARIABLE_VALIDATION=PASS \
 "$WORK/meta/offline-smoke.log" || fail MANUAL_TEST_VALIDATION_MISSING
echo OFFLINE_TEMPLATES_AND_TEST_GATES=PASS

# Confirm new UI always has a manual preview but real sending is separate.
VIEW="$WORK/app/resources/views/admin/partials/bale-safir-campaign.php"
grep -Fq 'data-safir-tab="variables"' "$VIEW" || fail TAB_MISSING
grep -Fq 'data-safir-manual-test' "$VIEW" || fail MANUAL_TEST_FORM_MISSING
grep -Fq 'data-safir-test-preview-button' "$VIEW" || fail PREVIEW_BUTTON_MISSING
grep -Fq 'manual_test_enabled' "$VIEW" || fail MANUAL_SEND_GATE_UI_MISSING
grep -Fq 'const dispatchForm' "$VIEW" || fail FIXED_JS_DISPATCH_FORM_MISSING
grep -Fq 'BALE_SAFIR_TEST_PRODUCTION_APPROVED' \
 "$WORK/app/app/Services/BaleSafirCampaignService.php" ||
 fail DEDICATED_TEST_APPROVAL_MISSING
echo TABS_PREVIEW_AND_SEND_GATES=PASS

[ "$(readlink -e "$CURRENT")" = "$ACTIVE" ] ||
 fail ACTIVE_PORTAL_CHANGED_DURING_STAGING
cat > "$WORK/meta/receipt.txt" <<EOF
SAFIR_UI_V22_TEST_STAGE=PASS
SOURCE_COMMIT=$SOURCE
ACTIVE_BEFORE=$ACTIVE
STAGE_PATH=$WORK/app
CHANGED_FILES=3
CUTOVER=NO
DB_MUTATION=NO
PRODUCTION_ENV_MUTATION=NO
LIVE_SEND=NO
EOF
echo '================================================'
echo SAFIR_UI_V22_TEST_STAGE=PASS
echo EXACT_THREE_FILE_CHANGE_SCOPE=PASS
echo ACTIVE_PORTAL_UNTOUCHED=YES
echo AUTOMATION_WORK_TICKETING_UNTOUCHED=YES
echo DB_MUTATION=NO
echo LIVE_SEND=NO
echo CUTOVER=NO
echo "STAGE_PATH=$WORK/app"
echo "STAGE_RECEIPT=$WORK/meta/receipt.txt"
echo NEXT=BUILD_IMMUTABLE_V22_PORTAL_RELEASE
echo '================================================'
