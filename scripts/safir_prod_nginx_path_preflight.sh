#!/usr/bin/env bash
set -Eeuo pipefail
test "$(id -u)" -eq 0 || exit 1
BASE=/srv/troca/portal-test
OLD=/srv/troca/portal-test/releases/prod-affiliation-choice-r15-20261025T025142Z-294349-a13-parentfirst-test-a15-core-affiliation-a16-core-reviewer-renewal-a21-scoped-staff-nav-a25-provincial-test-a29-province-priority-a33-http-fail-closed-a41r3-scope-hardening
test "$(readlink -e "$BASE/current")" = "$OLD"
nginx -t
for site in /etc/nginx/sites-enabled/troca-portal-corc /etc/nginx/sites-enabled/troca-portal-test; do
  file="$(readlink -e "$site")"
  test -f "$file"
  grep -Fq 'root /srv/troca/portal-test/current/public;' "$file"
  grep -Fq 'fastcgi_pass unix:/run/php/php8.4-fpm-troca-portal-test.sock;' "$file"
  echo "PORTAL_FASTCGI_TARGET=$file"
  grep 'fastcgi_param SCRIPT_FILENAME' "$file"
done
echo SAFIR_PORTAL_NGINX_PREFLIGHT=PASS
