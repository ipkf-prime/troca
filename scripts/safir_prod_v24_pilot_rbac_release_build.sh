#!/usr/bin/env bash
# Safir V2.4: guarded CSV permission and bounded pilot, ZERO send during build.
set -Eeuo pipefail
umask 077
fail(){ printf 'FAIL=%s\n' "$*" >&2; exit 1; }
[ "$(id -u)" = 0 ] || fail ROOT_REQUIRED
for x in curl git rsync php runuser stat readlink cmp nginx flock; do command -v "$x" >/dev/null || fail "MISSING:$x"; done
BASE=/srv/troca/portal-test
OLD="$BASE/releases/prod-safir-v23-testreport-20261102-af9b225e"
NEW="$BASE/releases/prod-safir-v24-pilot-rbac-20261102-6ae83579"
CURRENT="$BASE/current"
SOURCE=6ae83579e7e7458a8ba46dffbde71eaa4180b8f9
CATALOG="$BASE/shared/storage/private/bale-safir/templates.json"
RECEIPT="$NEW.safir-v24-receipt"
exec 9>/run/lock/ipkf-safir-portal-cutover.lock
flock -n 9 || fail LOCK_BUSY
[ -L "$CURRENT" ] && [ "$(readlink -e "$CURRENT")" = "$OLD" ] || fail CURRENT_CHANGED
[ -f "$OLD.safir-v23-cutover" ] || fail OLD_RECEIPT_MISSING
source /etc/ipkf-release/contract.env
[ "$PORTAL_BASE" = "$BASE" ] && [ "$PORTAL_USER" = troca-portal-test ] || fail CONTRACT_INVALID
[ ! -e "$NEW" ] && [ ! -L "$NEW" ] && [ ! -e "$RECEIPT" ] || fail CANDIDATE_EXISTS
[ -f "$OLD/.env" ] && [ ! -L "$OLD/.env" ] || fail ENV_MISSING
[ "$(stat -c %a "$OLD/.env")" = 600 ] && [ "$(stat -c %U "$OLD/.env")" = troca-portal-test ] || fail ENV_PERMISSIONS
[ -f "$CATALOG" ] && [ ! -L "$CATALOG" ] || fail CATALOG_MISSING
WORK="$(mktemp -d "$BASE/releases/.safir-v24-stage-XXXXXXXX")"
chmod 0700 "$WORK"
READY=NO
cleanup(){ rc=$?; trap - EXIT; if [ "$READY" = NO ] && [ -d "$WORK" ]; then rm -rf -- "$WORK"; fi; exit "$rc"; }
trap cleanup EXIT
cat > "$WORK/pins" <<'PINS'
app/Services/AdminNavigationRbacService.php|9db72cc23d292e9979fbcf0f7bbca348f3d48ce0|aafc313b2d6b21ffb73dfe655f85e3e7e2cb824b
app/Services/BaleSafirCampaignService.php|25d7810d5a4219f77f2d6939ae3667cc838c5e4c|96b6d8e4d4d0d9c393ae85a813354d1789c23d16
resources/views/admin/partials/bale-safir-campaign.php|6a713200176b9215ad72bdf5fc199ed0f0d2a1ce|bf15d0b990bc232dfa14f14ed9282d764ba4fc2c
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
echo VERIFIED_THREE_FILE_OVERLAY=PASS
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
echo EXACT_THREE_FILE_SCOPE=PASS
curl -fLsS "https://raw.githubusercontent.com/ipkf-prime/troca/$SOURCE/tests/safir-v24-pilot-rbac-smoke.php" \
 -o "$WORK/tests/safir-v24-pilot-rbac-smoke.php" || fail TEST_DOWNLOAD_FAILED
[ "$(git hash-object "$WORK/tests/safir-v24-pilot-rbac-smoke.php")" = bdeffa29ade77e2e4c5bc44e08df9150a7a377f1 ] || fail TEST_PIN_FAILED
ln -s "$WORK/app" "$WORK/public_html"
php "$WORK/tests/safir-v24-pilot-rbac-smoke.php" > "$WORK/test-output" || fail OFFLINE_TEST_FAILED
grep -Fxq SAFIR_CSV_RBAC_MATCHES_MAIN_PAGE=PASS "$WORK/test-output" || fail CSV_RBAC_TEST_FAILED
grep -Fxq PILOT_CAMPAIGN_ALLOWLIST_AND_ALL_ROWS_GATE=PASS "$WORK/test-output" || fail PILOT_GUARD_TEST_FAILED
grep -Fxq NO_HTTP_NO_DB_NO_SEND=PASS "$WORK/test-output" || fail ZERO_SEND_TEST_FAILED
echo OFFLINE_PILOT_AND_CSV_RBAC=PASS
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
 $rbac=new \\App\\Services\\AdminNavigationRbacService();
 if(($rbac->permissionForPath("/admin/communications/safir/test-report.csv")??"")!=="notifications.send.manage")exit(15);
 \IPKF\Support\Env::loadLayered(BASE_PATH."/.env");
 foreach(["BALE_SAFIR_SEND_ENABLED","BALE_SAFIR_PRODUCTION_APPROVED",
 "BALE_SAFIR_PILOT_ENABLED","BALE_SAFIR_PILOT_PRODUCTION_APPROVED"] as $flag){
 if(filter_var(\IPKF\Support\Env::get($flag,false),FILTER_VALIDATE_BOOLEAN))exit(12);
 }
 if((string)\IPKF\Support\Env::get("BALE_SAFIR_CATALOG_PATH","")!==$argv[2])exit(13);
 echo "RUNTIME_CSV_RBAC_AND_PILOT_LOCKS=PASS\n";
' "$WORK/app" "$CATALOG" || fail CANDIDATE_RUNTIME_FAILED
nginx -t >/dev/null 2>&1 || fail NGINX_INVALID
[ "$(readlink -e "$CURRENT")" = "$OLD" ] || fail CURRENT_MOVED
mv -T "$WORK/app" "$NEW" || fail RELEASE_COPY_FAILED
READY=YES
rmdir "$WORK"
{
 echo SAFIR_V24_CANDIDATE=PASS
 echo "SOURCE_COMMIT=$SOURCE"
 echo "PREVIOUS_RELEASE=$OLD"
 echo "CANDIDATE_RELEASE=$NEW"
 echo CHANGED_FILES=3
 echo CUTOVER=NO
 echo DB_MUTATION=NO
 echo BULK_SEND=NO
 echo TEST_SEND=NO
} > "$RECEIPT"
chmod 0600 "$RECEIPT"
echo SAFIR_V24_IMMUTABLE_CANDIDATE=PASS
echo "CANDIDATE_RELEASE=$NEW"
echo "CURRENT_RELEASE=$OLD"
echo BULK_AND_PILOT_SEND_LOCKS_DISABLED=YES
echo OTHER_MODULES_UNTOUCHED=YES
echo NEXT=GUARDED_PORTAL_CUTOVER_V24
