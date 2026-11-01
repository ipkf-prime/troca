#!/usr/bin/env bash
# Safir emergency rollback: only the pinned Portal current link.
set -Eeuo pipefail
umask 077
fail(){ printf 'FAIL=%s\n' "$*" >&2; exit 1; }
[ "$(id -u)" -eq 0 ] || fail ROOT_REQUIRED
BASE=/srv/troca/portal-test
CURRENT="$BASE/current"
CAND="$BASE/releases/prod-safir-portal-20261101T130944Z-92749b72"
OLD="$BASE/releases/prod-affiliation-choice-r15-20261025T025142Z-294349-a13-parentfirst-test-a15-core-affiliation-a16-core-reviewer-renewal-a21-scoped-staff-nav-a25-provincial-test-a29-province-priority-a33-http-fail-closed-a41r3-scope-hardening"
HOST=troca.corc.ir
exec 9>/run/lock/ipkf-safir-portal-cutover.lock
flock -n 9 || fail CUTOVER_LOCK_BUSY
[ -L "$CURRENT" ] || fail CURRENT_NOT_SYMLINK
[ "$(readlink -e "$CURRENT")" = "$CAND" ] || fail CURRENT_NOT_EXPECTED_SAFIR_RELEASE
[ -d "$OLD" ] || fail OLD_RELEASE_MISSING
[ -f "$OLD/.env" ] || fail OLD_ENV_MISSING
[ -L "$OLD/storage" ] || fail OLD_STORAGE_LINK_MISSING
[ "$(readlink "$OLD/storage")" = "$BASE/shared/storage" ] || fail OLD_STORAGE_TARGET_INVALID
[ -L "$OLD/public/uploads" ] || fail OLD_UPLOAD_LINK_MISSING
[ "$(readlink "$OLD/public/uploads")" = "$BASE/shared/public/uploads" ] || fail OLD_UPLOAD_TARGET_INVALID
nginx -t >/dev/null 2>&1 || fail NGINX_INVALID
(
 cd "$OLD"
 sha256sum -c - >/dev/null <<'SHA'
ded5d77f129fb9493e2c4a69e935246ac7c97f143a1b92c0cee32cfe853e4135  public/index.php
f9824aaab334b4492f53d4ee6cc1cf0b14052182fa5792e70b8f8e98f2f31cdf  routes/web.php
76423289b6b47c87727c492cb0279712c3d46c3c8409cbb1bcce04b2e7d9ffee  routes/communication-center.php
bfc28fa0242a668b588122249db58ac01474736f2a7f9d8720f3b1a49841fd0a  system/Routing/RouteLoader.php
255289bb6e1619751a19c5eccc6bb0cab17d033d215698daefe7dc8918814855  app/Services/CommunicationSettingsService.php
c64c1672b4d4ef2f58903fbe7cc28b14ff75a2761bb803ff6d052c458a3660d0  app/Services/AdminNavigationRbacService.php
876966a04dac826324f39a0e5a87833c971ee086964eac7cc00e4460ec98444f  resources/views/admin/communication-settings.php
SHA
) || fail ORIGINAL_BASELINE_CHANGED
echo PREVIOUS_RELEASE_SAFETY=PASS

WORK="$(mktemp -d "$BASE/.safir-rollback-XXXXXXXX")"
trap 'rm -rf -- "$WORK"' EXIT
ln -s "$OLD" "$WORK/previous"
mv -Tf "$WORK/previous" "$CURRENT" || fail ATOMIC_ROLLBACK_RENAME_FAILED
[ "$(readlink -e "$CURRENT")" = "$OLD" ] || fail ROLLBACK_CURRENT_MISMATCH
echo PORTAL_CURRENT_ROLLBACK=PASS

CODE="$(curl --silent --show-error --noproxy '*' --connect-timeout 3 --max-time 15 \
 --resolve "$HOST:443:127.0.0.1" \
 -o "$WORK/health.json" -w '%{http_code}' "https://$HOST/health")" ||
 fail ROLLBACK_HEALTH_UNAVAILABLE
[ "$CODE" = 200 ] || fail "ROLLBACK_HEALTH_BAD_STATUS:$CODE"
php -r '$j=json_decode(file_get_contents($argv[1]),true);exit(is_array($j)&&($j["status"]??"")==="ok"?0:1);' "$WORK/health.json" ||
 fail ROLLBACK_HEALTH_INVALID
echo 'SAFIR_MANUAL_PORTAL_ROLLBACK=PASS'
echo 'PREVIOUS_PORTAL_RESTORED=YES'
echo 'AUTOMATION_WORK_TICKETING_UNTOUCHED=YES'
echo 'DATABASE_MUTATION=NO'
echo 'SAFIR_SHARED_CATALOG_PRESERVED=YES'
