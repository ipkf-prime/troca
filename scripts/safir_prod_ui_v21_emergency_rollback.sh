#!/usr/bin/env bash
# IPKF Safir UI V2.1 emergency manual rollback: Portal symlink only.
# Use after an otherwise successful Cutover if authenticated UI fails.
set -Eeuo pipefail
umask 077
fail(){ printf 'FAIL=%s\n' "$*" >&2; exit 1; }
[ "$(id -u)" -eq 0 ] || fail ROOT_REQUIRED
for bin in flock nginx php curl git ln mv readlink systemctl mktemp grep; do
  command -v "$bin" >/dev/null || fail "MISSING_TOOL:$bin"
done
BASE=/srv/troca/portal-test
CURRENT="$BASE/current"
NEW="$BASE/releases/prod-safir-ui-v21-20261101-4a53e8c0"
OLD="$BASE/releases/prod-safir-ui-v2-20261101-a9c8e01"
RECEIPT="$NEW.safir-ui-v21-cutover"
HOST=troca.corc.ir
exec 9>/run/lock/ipkf-safir-portal-cutover.lock
flock -n 9 || fail CUTOVER_LOCK_BUSY
[ -L "$CURRENT" ] || fail CURRENT_NOT_SYMLINK
[ "$(readlink -e "$CURRENT")" = "$NEW" ] || fail CURRENT_NOT_EXPECTED_V2
[ -f "$RECEIPT" ] || fail SUCCESSFUL_V2_CUTOVER_RECEIPT_MISSING
grep -Fxq 'STATUS=SUCCESS' "$RECEIPT" || fail INVALID_SUCCESS_RECEIPT
grep -Fxq "ACTIVE_BEFORE=$OLD" "$RECEIPT" || fail SUCCESS_RECEIPT_BASELINE_MISMATCH
grep -Fxq "CURRENT_RELEASE=$NEW" "$RECEIPT" || fail SUCCESS_RECEIPT_NEW_RELEASE_MISMATCH
[ -d "$OLD" ] && [ ! -L "$OLD" ] || fail PREVIOUS_RELEASE_INVALID
[ -f "$OLD.safir-ui-v2-cutover" ] || fail PREVIOUS_RELEASE_RECEIPT_MISSING
[ -f "$OLD/.env" ] || fail PREVIOUS_PRIVATE_ENV_MISSING
for relative in storage public/uploads; do
  [ -L "$OLD/$relative" ] || fail "PREVIOUS_LINK_MISSING:$relative"
  [ "$(readlink "$OLD/$relative")" = "$BASE/shared/$relative" ] ||
    fail "PREVIOUS_LINK_MISMATCH:$relative"
done
nginx -t >/dev/null 2>&1 || fail NGINX_INVALID
[ "$(systemctl is-active nginx)" = active ] || fail NGINX_INACTIVE
[ "$(systemctl is-active php8.4-fpm)" = active ] || fail PHP_FPM_INACTIVE
echo PREVIOUS_PORTAL_RELEASE_SAFETY=PASS

WORK="$(mktemp -d "$BASE/.safir-ui-v21-emergency-XXXXXXXX")"
chmod 0700 "$WORK"
trap 'rm -rf -- "$WORK"' EXIT
ln -s "$OLD" "$WORK/previous"
mv -Tf "$WORK/previous" "$CURRENT" || fail ROLLBACK_RENAME_FAILED
[ "$(readlink -e "$CURRENT")" = "$OLD" ] || fail ROLLBACK_LINK_VERIFY_FAILED
echo ATOMIC_PORTAL_ROLLBACK=PASS

status="$(curl --silent --show-error --noproxy '*' --connect-timeout 3 \
 --max-time 15 --resolve "$HOST:443:127.0.0.1" \
 -H 'Cache-Control: no-cache' -o "$WORK/health.json" \
 -w '%{http_code}' "https://$HOST/health")" || fail ROLLBACK_HEALTH_UNREACHABLE
[ "$status" = 200 ] || fail "ROLLBACK_HEALTH_STATUS:$status"
php -r '$j=json_decode(file_get_contents($argv[1]),true);
  exit(is_array($j)&&($j["status"]??null)==="ok"?0:1);' "$WORK/health.json" ||
  fail ROLLBACK_HEALTH_BAD_JSON
status="$(curl --silent --show-error --noproxy '*' --connect-timeout 3 \
 --max-time 15 --resolve "$HOST:443:127.0.0.1" \
 -o "$WORK/login.html" -w '%{http_code}' "https://$HOST/admin/login")" ||
 fail ROLLBACK_LOGIN_UNREACHABLE
[ "$status" = 200 ] || fail "ROLLBACK_LOGIN_STATUS:$status"
echo PREVIOUS_PORTAL_HTTP=PASS
echo SAFIR_UI_V21_MANUAL_ROLLBACK=PASS
echo "CURRENT_RELEASE=$OLD"
echo "V21_CANDIDATE_PRESERVED=$NEW"
echo OTHER_MODULES_UNTOUCHED=YES
echo NO_DB_MIGRATIONS=YES
echo SHARED_CATALOG_UNTOUCHED=YES
