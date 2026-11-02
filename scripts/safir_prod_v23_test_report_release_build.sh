#!/usr/bin/env bash
# Safir V2.3: guarded Portal-only immutable candidate; ZERO send, ZERO DB.
set -Eeuo pipefail
umask 077
fail(){ printf 'FAIL=%s\n' "$*" >&2; exit 1; }
[ "$(id -u)" = 0 ] || fail ROOT_REQUIRED
for x in curl git rsync php runuser stat readlink cmp nginx flock; do command -v "$x" >/dev/null || fail "MISSING:$x"; done
BASE=/srv/troca/portal-test
OLD="$BASE/releases/prod-safir-ui-v22-20261102-3a567228"
NEW="$BASE/releases/prod-safir-v23-testreport-20261102-af9b225e"
CURRENT="$BASE/current"
SOURCE=af9b225e6392c72a7f7f686d8862bdc7820123de
CATALOG="$BASE/shared/storage/private/bale-safir/templates.json"
RECEIPT="$NEW.safir-v23-receipt"
exec 9>/run/lock/ipkf-safir-portal-cutover.lock
flock -n 9 || fail LOCK_BUSY
[ -L "$CURRENT" ] && [ "$(readlink -e "$CURRENT")" = "$OLD" ] || fail CURRENT_CHANGED
[ -f "$OLD.safir-ui-v22-cutover" ] || fail OLD_RECEIPT_MISSING
source /etc/ipkf-release/contract.env
[ "$PORTAL_BASE" = "$BASE" ] && [ "$PORTAL_USER" = troca-portal-test ] || fail CONTRACT_INVALID
[ ! -e "$NEW" ] && [ ! -L "$NEW" ] && [ ! -e "$RECEIPT" ] || fail CANDIDATE_EXISTS
[ -f "$OLD/.env" ] && [ ! -L "$OLD/.env" ] || fail ENV_MISSING
[ "$(stat -c %a "$OLD/.env")" = 600 ] && [ "$(stat -c %U "$OLD/.env")" = troca-portal-test ] || fail ENV_PERMISSIONS
[ -f "$CATALOG" ] && [ ! -L "$CATALOG" ] || fail CATALOG_MISSING
WORK="$(mktemp -d "$BASE/releases/.safir-v23-stage-XXXXXXXX")"
chmod 0700 "$WORK"
READY=NO
cleanup(){ rc=$?; trap - EXIT; if [ "$READY" = NO ] && [ -d "$WORK" ]; then rm -rf -- "$WORK"; fi; exit "$rc"; }
trap cleanup EXIT
cat > "$WORK/pins" <<'PINS'
app/Services/BaleSafirCampaignService.php|9767a4a061cd525be22b7f126b9e5b4d5dbd16b4|25d7810d5a4219f77f2d6939ae3667cc838c5e4c
app/Services/BaleSafirTestReportService.php|NEW|07cc8a3fe10dc8aa293aa84ba5c3a85d4a58c3a6
resources/views/admin/partials/bale-safir-campaign.php|ff8c614b64dcaed25b91b8c39765976bdaf82cfa|6a713200176b9215ad72bdf5fc199ed0f0d2a1ce
routes/bale-safir-campaigns.php|528fea17780163e298a8da8cf9c0402a490235d9|52c38ee3b582b75c291a664c256f89a7c02049e3
PINS
while IFS='|' read -r path old new; do
 if [ "$old" = NEW ]; then
  [ ! -e "$OLD/$path" ] && [ ! -L "$OLD/$path" ] || fail "ALREADY_PRESENT:$path"
 else
  [ -f "$OLD/$path" ] && [ ! -L "$OLD/$path" ] || fail "MISSING_BASELINE:$path"
  [ "$(git hash-object "$OLD/$path")" = "$old" ] || fail "BASELINE_DIFFERENT:$path"
 fi
done < "$WORK/pins"
echo BASELINE_PINS=PASS
mkdir -p "$WORK/app" "$WORK/tests"
rsync -a \
 --exclude='/.env*' --exclude='**/.env*' \
 --exclude='/storage' --exclude='/public/uploads' \
 --exclude='/.git' --exclude='**/.git' \
 --exclude='**/backups' --exclude='**/backup' --exclude='**/_admin' \
 --exclude='**/*.pem' --exclude='**/*.key' --exclude='**/*.p12' --exclude='**/*.pfx' \
 --exclude='**/*.sql' --exclude='**/*.sql.gz' --exclude='**/*.dump' \
 --exclude='**/*.bak' --exclude='**/*.sqlite' \
 "$OLD/" "$WORK/app/" || fail SNAPSHOT_FAILED
while IFS='|' read -r path old new; do
 curl -fLsS "https://raw.githubusercontent.com/ipkf-prime/troca/$SOURCE/public_html/$path" \
  -o "$WORK/downloaded" || fail "DOWNLOAD_FAILED:$path"
 [ "$(git hash-object "$WORK/downloaded")" = "$new" ] || fail "SOURCE_HASH_FAILED:$path"
 install -D -m 0644 "$WORK/downloaded" "$WORK/app/$path"
 php -l "$WORK/app/$path" >/dev/null || fail "PHP_INVALID:$path"
done < "$WORK/pins"
rm -f "$WORK/downloaded"
echo VERIFIED_FOUR_FILE_OVERLAY=PASS
rsync -rnic --delete \
 --exclude='/.env*' --exclude='**/.env*' \
 --exclude='/storage' --exclude='/public/uploads' \
 --exclude='/.git' --exclude='**/.git' \
 --exclude='**/backups' --exclude='**/backup' --exclude='**/_admin' \
 --exclude='**/*.pem' --exclude='**/*.key' --exclude='**/*.p12' --exclude='**/*.pfx' \
 --exclude='**/*.sql' --exclude='**/*.sql.gz' --exclude='**/*.dump' \
 --exclude='**/*.bak' --exclude='**/*.sqlite' \
 --out-format='%i|%n' "$OLD/" "$WORK/app/" |
 sed -n 's/^[^|]*|//p' | sort > "$WORK/actual-diff"
cut -d'|' -f1 "$WORK/pins" | sort > "$WORK/expected-diff"
cmp -s "$WORK/actual-diff" "$WORK/expected-diff" || fail UNEXPECTED_CODE_DIFF
echo EXACT_FOUR_FILE_SCOPE=PASS
curl -fLsS "https://raw.githubusercontent.com/ipkf-prime/troca/$SOURCE/tests/bale-safir-campaign-smoke.php" \
 -o "$WORK/tests/bale-safir-campaign-smoke.php" || fail TEST_DOWNLOAD_FAILED
[ "$(git hash-object "$WORK/tests/bale-safir-campaign-smoke.php")" = 981bf43bbaefdb812bdd0dee502e08ac6b699512 ] || fail TEST_PIN_FAILED
ln -s "$WORK/app" "$WORK/public_html"
php "$WORK/tests/bale-safir-campaign-smoke.php" > "$WORK/test-output" || fail OFFLINE_TEST_FAILED
grep -Fxq BALE_SAFIR_DYNAMIC_OFFLINE_SMOKE=PASS "$WORK/test-output" || fail TEST_RESULT_FAILED
grep -Fxq SAFIR_PERSISTENT_REPORT_CSV=PASS "$WORK/test-output" || fail REPORT_TEST_FAILED
echo OFFLINE_IDEMPOTENCY_AND_REPORT=PASS
rm -rf -- "$WORK/tests" "$WORK/public_html" "$WORK/test-output" "$WORK/actual-diff" "$WORK/expected-diff" "$WORK/pins"
[ ! -e "$WORK/app/.env" ] && [ ! -e "$WORK/app/storage" ] || fail PRIVATE_DATA_IN_STAGE
for path in storage public/uploads; do
 [ -L "$OLD/$path" ] && [ "$(readlink "$OLD/$path")" = "$BASE/shared/$path" ] || fail "OLD_LINK_INVALID:$path"
done
install -o troca-portal-test -g troca-portal-test -m 0600 "$OLD/.env" "$WORK/app/.env"
ln -s "$BASE/shared/storage" "$WORK/app/storage"
ln -s "$BASE/shared/public/uploads" "$WORK/app/public/uploads"
cmp -s "$OLD/.env" "$WORK/app/.env" || fail ENV_CHANGED
chown "$(stat -c %U:%G "$OLD")" "$WORK/app"
chmod "$(stat -c %a "$OLD")" "$WORK/app"
# The work parent is root-owned and created mode 0700. Only the
# Portal runtime account's primary group may traverse it for the CLI
# route graph check; no listing/read access to the temporary parent.
chgrp "$(id -gn "$PORTAL_USER")" "$WORK"
chmod 0710 "$WORK"
# When the releases directory has setgid, mktemp inherits it. GNU chmod
# preserves that bit on directories: 0710 may legitimately read as 2710.
# Both modes forbid access by "other"; actual Portal-user traversal is
# checked immediately below.
TEMP_MODE="$(stat -c %a "$WORK")"
case "$TEMP_MODE" in
  710|2710) : ;;
  *) fail "TEMP_ROOT_PERMISSION_CHANGED:$TEMP_MODE" ;;
esac
[ "$(stat -c %G "$WORK")" = "$(id -gn "$PORTAL_USER")" ] ||
 fail TEMP_ROOT_GROUP_CHANGED
[ "$(stat -c %U "$WORK")" = root ] || fail TEMP_ROOT_OWNER_CHANGED
runuser -u "$PORTAL_USER" -- test -r "$WORK/app/bootstrap/app.php" ||
 fail PORTAL_CANNOT_READ_STAGED_BOOTSTRAP
runuser -u "$PORTAL_USER" -- test -r "$WORK/app/.env" ||
 fail PORTAL_CANNOT_READ_STAGED_ENV
echo PORTAL_STAGED_RUNTIME_TRAVERSAL=PASS
runuser -u troca-portal-test -- php -r '
 define("BASE_PATH",$argv[1]);$app=require BASE_PATH."/bootstrap/app.php";
 $r=(new ReflectionProperty($app->router(),"routes"))->getValue($app->router());
 foreach([["GET","/admin/communications/safir"],["GET","/admin/communications/safir/test-report.csv"],
 ["POST","/admin/communications/settings/safir"]] as [$v,$p])if(!isset($r[$v][$p]))exit(11);
 \IPKF\Support\Env::loadLayered(BASE_PATH."/.env");
 foreach(["BALE_SAFIR_SEND_ENABLED","BALE_SAFIR_PRODUCTION_APPROVED",
 "BALE_SAFIR_TEST_SEND_ENABLED","BALE_SAFIR_TEST_PRODUCTION_APPROVED"] as $flag){
 if(filter_var(\IPKF\Support\Env::get($flag,false),FILTER_VALIDATE_BOOLEAN))exit(12);
 }
 if((string)\IPKF\Support\Env::get("BALE_SAFIR_CATALOG_PATH","")!==$argv[2])exit(13);
 echo "RUNTIME_ROUTES_AND_FOUR_SEND_LOCKS=PASS\n";
' "$WORK/app" "$CATALOG" || fail CANDIDATE_RUNTIME_FAILED
nginx -t >/dev/null 2>&1 || fail NGINX_INVALID
[ "$(readlink -e "$CURRENT")" = "$OLD" ] || fail CURRENT_MOVED
mv -T "$WORK/app" "$NEW" || fail RELEASE_COPY_FAILED
READY=YES
rmdir "$WORK"
{
 echo SAFIR_V23_CANDIDATE=PASS
 echo "SOURCE_COMMIT=$SOURCE"
 echo "PREVIOUS_RELEASE=$OLD"
 echo "CANDIDATE_RELEASE=$NEW"
 echo CHANGED_FILES=4
 echo CUTOVER=NO
 echo DB_MUTATION=NO
 echo BULK_SEND=NO
 echo TEST_SEND=NO
} > "$RECEIPT"
chmod 0600 "$RECEIPT"
echo SAFIR_V23_IMMUTABLE_CANDIDATE=PASS
echo "CANDIDATE_RELEASE=$NEW"
echo "CURRENT_RELEASE=$OLD"
echo ALL_SEND_LOCKS_DISABLED=YES
echo OTHER_MODULES_UNTOUCHED=YES
echo NEXT=GUARDED_PORTAL_CUTOVER_V23
