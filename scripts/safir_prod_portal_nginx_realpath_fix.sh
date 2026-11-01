#!/usr/bin/env bash
# IPKF Safir - scoped Nginx FastCGI realpath fix, with backup/restore.
# Does not switch Portal, modify other sites, or restart PHP-FPM.
set -Eeuo pipefail
umask 077
fail(){ printf 'FAIL=%s\n' "$*" >&2; exit 1; }
[ "$(id -u)" -eq 0 ] || fail ROOT_REQUIRED
BASE=/srv/troca/portal-test
OLD="$BASE/releases/prod-affiliation-choice-r15-20261025T025142Z-294349-a13-parentfirst-test-a15-core-affiliation-a16-core-reviewer-renewal-a21-scoped-staff-nav-a25-provincial-test-a29-province-priority-a33-http-fail-closed-a41r3-scope-hardening"
CAND="$BASE/releases/prod-safir-portal-20261101T130944Z-92749b72"
[ "$(readlink -e "$BASE/current")" = "$OLD" ] || fail BASELINE_NOT_ACTIVE
[ -d "$CAND" ] && [ -f "$CAND.safir-receipt" ] || fail CANDIDATE_INVALID
nginx -t >/dev/null 2>&1 || fail NGINX_PRECHECK_FAILED
[ "$(systemctl is-active nginx)" = active ] || fail NGINX_INACTIVE
[ "$(systemctl is-active php8.4-fpm)" = active ] || fail PHP_FPM_INACTIVE
exec 9>/run/lock/ipkf-safir-portal-nginx.lock
flock -n 9 || fail NGINX_CHANGE_LOCK_BUSY

SITE_A=/etc/nginx/sites-enabled/troca-portal-corc
SITE_B=/etc/nginx/sites-enabled/troca-portal-test
COUNT=0
for site in "$SITE_A" "$SITE_B"; do
  [ -e "$site" ] || fail SITE_NOT_FOUND
  file="$(readlink -e "$site")" || fail SITE_UNRESOLVED
  case "$file" in /etc/nginx/sites-available/*|/etc/nginx/sites-enabled/*) :;; *) fail BAD_SITE_PATH;; esac
  [ -f "$file" ] && [ ! -L "$file" ] || fail BAD_SITE_TYPE
  [ "$(stat -c %U "$file")" = root ] || fail BAD_SITE_OWNER
  grep -Fq 'root /srv/troca/portal-test/current/public;' "$file" || fail BAD_SITE_ROOT
  grep -Fq 'fastcgi_pass unix:/run/php/php8.4-fpm-troca-portal-test.sock;' "$file" || fail BAD_FPM_SOCKET
  if grep -E '^[[:space:]]*fastcgi_pass[[:space:]]+' "$file" |
     grep -Fv 'unix:/run/php/php8.4-fpm-troca-portal-test.sock;' >/dev/null; then
    fail NON_PORTAL_POOL_IN_SITE
  fi
  n="$(grep -Ec '^[[:space:]]*fastcgi_param[[:space:]]+SCRIPT_FILENAME[[:space:]]+\$document_root\$fastcgi_script_name[[:space:]]*;' "$file" || true)"
  [ "$n" -gt 0 ] || fail FASTCGI_DIRECTIVE_ABSENT
  COUNT=$((COUNT+n))
done
[ "$COUNT" -eq 3 ] || fail UNEXPECTED_DIRECTIVE_COUNT
[ "$(readlink -e "$SITE_A")" != "$(readlink -e "$SITE_B")" ] || fail DUPLICATE_SITE_TARGET
echo PORTAL_ONLY_THREE_FASTCGI_DIRECTIVES=PASS

install -d -m 700 /var/backups/ipkf-safir
BACKUP="$(mktemp -d /var/backups/ipkf-safir/nginx-portal.XXXXXXXX)"
for site in "$SITE_A" "$SITE_B"; do
  file="$(readlink -e "$site")"
  cp -p -- "$file" "$BACKUP/$(basename "$site")"
done
echo NGINX_ORIGINAL_CONFIG_BACKUP=PASS

CHANGED=0
DONE=0
rollback(){
  rc=$?
  trap - EXIT
  if [ "$CHANGED" = 1 ] && [ "$DONE" = 0 ]; then
    status=PASS
    for site in "$SITE_A" "$SITE_B"; do
      file="$(readlink -e "$site")"
      temp="$(mktemp "$(dirname "$file")/.safir-restore.XXXXXXXX")" || { status=FAIL; break; }
      cp -p "$BACKUP/$(basename "$site")" "$temp" || status=FAIL
      mv -Tf "$temp" "$file" || status=FAIL
    done
    if [ "$status" = PASS ] && nginx -t >/dev/null 2>&1 && systemctl reload nginx; then
      echo NGINX_AUTO_RESTORE=PASS
    else
      echo NGINX_AUTO_RESTORE=FAILED >&2
      rc=97
    fi
  fi
  exit "$rc"
}
trap rollback EXIT
CHANGED=1

for site in "$SITE_A" "$SITE_B"; do
  file="$(readlink -e "$site")"
  temp="$(mktemp "$(dirname "$file")/.safir-realpath.XXXXXXXX")"
  python3 - "$file" "$temp" <<'PY'
import sys
from pathlib import Path
source=Path(sys.argv[1]).read_bytes()
old=b'fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;'
new=b'fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;'
if source.count(old)<1: raise SystemExit("MISSING_EXPECTED_DIRECTIVE")
Path(sys.argv[2]).write_bytes(source.replace(old,new))
PY
  chmod "$(stat -c %a "$file")" "$temp"
  chown --reference="$file" "$temp"
  mv -Tf "$temp" "$file"
done

nginx -t >/dev/null 2>&1 || fail NEW_NGINX_CONFIG_INVALID
for site in "$SITE_A" "$SITE_B"; do
  file="$(readlink -e "$site")"
  grep -Fq 'fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;' "$file" || fail REALPATH_NOT_APPLIED
  if grep -Fq 'fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;' "$file"; then fail OLD_DIRECTIVE_REMAINS; fi
done
echo PORTAL_FASTCGI_REALPATH=PASS
systemctl reload nginx || fail NGINX_RELOAD_FAILED
[ "$(systemctl is-active nginx)" = active ] || fail NGINX_AFTER_RELOAD_INACTIVE
[ "$(systemctl is-active php8.4-fpm)" = active ] || fail PHP_FPM_AFTER_RELOAD_INACTIVE
echo NGINX_GRACEFUL_RELOAD=PASS

WORK="$(mktemp -d)"
for uri in /health /admin/login /admin/communications/settings/safir/export; do
  code="$(curl -sS --noproxy '*' --connect-timeout 3 --max-time 12 \
   --resolve troca.corc.ir:443:127.0.0.1 -o "$WORK/body" -w '%{http_code}' \
   "https://troca.corc.ir$uri")" || fail LOCAL_HTTP_FAILED
  case "$uri" in
    /health)
      [ "$code" = 200 ] || fail HEALTH_HTTP_INVALID
      php -r '$v=json_decode(file_get_contents($argv[1]),true);exit(is_array($v)&&($v["status"]??null)==="ok"?0:1);' "$WORK/body" || fail HEALTH_JSON_INVALID
      echo BASELINE_HEALTH=PASS ;;
    /admin/login) [ "$code" = 200 ] || fail LOGIN_HTTP_INVALID; echo BASELINE_LOGIN=PASS ;;
    /admin/communications/settings/safir/export)
      [ "$code" = 404 ] || fail OLD_ROUTE_HTTP_INVALID; echo BASELINE_SAFIR_ROUTE_ABSENT=PASS ;;
  esac
done
[ "$(readlink -e "$BASE/current")" = "$OLD" ] || fail CURRENT_CHANGED
DONE=1
echo SAFIR_PORTAL_NGINX_REALPATH_FIX=PASS
echo PORTAL_CUTOVER=NO
echo PHP_FPM_RESTART=NO
echo SEND=NO
echo "BACKUP_DIR=$BACKUP"
