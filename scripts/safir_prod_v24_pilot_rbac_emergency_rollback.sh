#!/usr/bin/env bash
# Safir V2.4 manual Portal-only rollback to proven V2.3; no send.
set -Eeuo pipefail
umask 077
fail(){ echo "FAIL=$*" >&2; exit 1; }
[ "$(id -u)" = 0 ] || fail ROOT_REQUIRED
BASE=/srv/troca/portal-test
OLD="$BASE/releases/prod-safir-v23-testreport-20261102-af9b225e"
NEW="$BASE/releases/prod-safir-v24-rbac-preserved-20261102-13d45156"
CURRENT="$BASE/current"
exec 9>/run/lock/ipkf-safir-portal-cutover.lock
flock -n 9 || fail LOCK_BUSY
[ -L "$CURRENT" ] && [ "$(readlink -e "$CURRENT")" = "$NEW" ] || fail CURRENT_NOT_V24
[ -f "$NEW.safir-v24-cutover" ] && [ -f "$OLD.safir-v23-cutover" ] || fail RECEIPT_MISSING
grep -Fxq STATUS=SUCCESS "$NEW.safir-v24-cutover" || fail BAD_CUTOVER_RECEIPT
[ -d "$OLD" ] && [ ! -L "$OLD" ] || fail OLD_RELEASE_INVALID
nginx -t >/dev/null 2>&1 || fail NGINX_INVALID
TEMP="$(mktemp -d "$BASE/.safir-v24-rollback-XXXXXXXX")"
trap 'rm -rf -- "$TEMP"' EXIT
ln -s "$OLD" "$TEMP/previous"
mv -Tf "$TEMP/previous" "$CURRENT" || fail SWAP_FAILED
[ "$(readlink -e "$CURRENT")" = "$OLD" ] || fail LINK_FAILED
status="$(curl -sS --noproxy '*' --connect-timeout 3 --max-time 15 --resolve troca.corc.ir:443:127.0.0.1 -o "$TEMP/health.json" -w '%{http_code}' https://troca.corc.ir/health)" || fail HEALTH_UNREACHABLE
[ "$status" = 200 ] && php -r '$x=json_decode(file_get_contents($argv[1]),true);exit(($x["status"]??null)==="ok"?0:1);' "$TEMP/health.json" || fail HEALTH_INVALID
echo SAFIR_V24_MANUAL_ROLLBACK_TO_V23=PASS
echo "CURRENT_RELEASE=$OLD"
echo V24_RELEASE_AND_SHARED_AUDIT_PRESERVED=YES
echo BULK_SEND=DISABLED
echo TEST_SEND=UNCHANGED_ON_OLD_ENV
echo DB_MUTATION=NO
