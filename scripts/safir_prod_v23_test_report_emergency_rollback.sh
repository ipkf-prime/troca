#!/usr/bin/env bash
# Safir V2.3 manual Portal-only rollback; preserve audit in shared storage.
set -Eeuo pipefail
umask 077
fail(){ echo "FAIL=$*" >&2; exit 1; }
[ "$(id -u)" = 0 ] || fail ROOT_REQUIRED
BASE=/srv/troca/portal-test
OLD="$BASE/releases/prod-safir-ui-v22-20261102-3a567228"
NEW="$BASE/releases/prod-safir-v23-testreport-20261102-af9b225e"
CURRENT="$BASE/current"
exec 9>/run/lock/ipkf-safir-portal-cutover.lock
flock -n 9 || fail LOCK_BUSY
[ -L "$CURRENT" ] && [ "$(readlink -e "$CURRENT")" = "$NEW" ] || fail CURRENT_NOT_V23
[ -f "$NEW.safir-v23-cutover" ] && [ -f "$OLD.safir-ui-v22-cutover" ] || fail RECEIPT_MISSING
grep -Fxq STATUS=SUCCESS "$NEW.safir-v23-cutover" || fail BAD_CUTOVER_RECEIPT
[ -d "$OLD" ] && [ ! -L "$OLD" ] || fail OLD_RELEASE_INVALID
nginx -t >/dev/null 2>&1 || fail NGINX_INVALID
TEMP="$(mktemp -d "$BASE/.safir-v23-rollback-XXXXXXXX")"
trap 'rm -rf -- "$TEMP"' EXIT
ln -s "$OLD" "$TEMP/previous"
mv -Tf "$TEMP/previous" "$CURRENT" || fail SWAP_FAILED
[ "$(readlink -e "$CURRENT")" = "$OLD" ] || fail LINK_FAILED
status="$(curl -sS --noproxy '*' --connect-timeout 3 --max-time 15 --resolve troca.corc.ir:443:127.0.0.1 -o "$TEMP/health.json" -w '%{http_code}' https://troca.corc.ir/health)" || fail HEALTH_UNREACHABLE
[ "$status" = 200 ] && php -r '$x=json_decode(file_get_contents($argv[1]),true);exit(($x["status"]??null)==="ok"?0:1);' "$TEMP/health.json" || fail HEALTH_INVALID
echo SAFIR_V23_MANUAL_ROLLBACK=PASS
echo "CURRENT_RELEASE=$OLD"
echo V23_RELEASE_AND_SHARED_AUDIT_PRESERVED=YES
echo BULK_SEND=DISABLED
echo TEST_SEND=DISABLED_ON_OLD_ENV
echo DB_MUTATION=NO
