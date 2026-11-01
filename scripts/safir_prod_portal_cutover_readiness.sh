#!/usr/bin/env bash
# IPKF Safir: read-only Portal-only cutover readiness, no changes to service.
set -Eeuo pipefail
umask 077
fail() { printf 'FAIL=%s\n' "$*" >&2; exit 1; }
[ "$(id -u)" -eq 0 ] || fail ROOT_REQUIRED
BASE=/srv/troca/portal-test
ACTIVE="$(readlink -e "$BASE/current")" || fail CURRENT_UNRESOLVED
CAND="$BASE/releases/prod-safir-portal-20261101T130944Z-92749b72"
[ -L "$BASE/current" ] || fail CURRENT_LINK_MISSING
case "$ACTIVE" in "$BASE"/releases/*) : ;; *) fail CURRENT_OUTSIDE_RELEASES ;; esac
[ -d "$CAND" ] && [ ! -L "$CAND" ] || fail CANDIDATE_INVALID
[ -f "$CAND.safir-receipt" ] || fail RECEIPT_MISSING
grep -Fxq "ACTIVE_BEFORE=$ACTIVE" "$CAND.safir-receipt" || fail BASELINE_RECEIPT_MISMATCH
grep -Fxq "RELEASE_PATH=$CAND" "$CAND.safir-receipt" || fail TARGET_RECEIPT_MISMATCH
grep -Fxq CUTOVER=NO "$CAND.safir-receipt" || fail RECEIPT_NOT_PRECUTOVER
[ -f "$CAND/.env" ] || fail CANDIDATE_ENV_MISSING
[ "$(stat -c %a "$CAND/.env")" = 600 ] || fail ENV_MODE_INVALID
[ "$(stat -c %U "$CAND/.env")" = troca-portal-test ] || fail ENV_OWNER_INVALID
for d in storage public/uploads; do
  [ -L "$CAND/$d" ] || fail "SHARED_LINK_MISSING:$d"
  [ "$(readlink "$CAND/$d")" = "$BASE/shared/$d" ] || fail "SHARED_LINK_WRONG:$d"
done
echo CANDIDATE_RELEASE_AND_ENV=PASS

# Print URL host and scheme only, never sensitive environment settings.
ENDPOINT="$(php -r '
define("BASE_PATH",$argv[1]);require BASE_PATH."/system/Support/Env.php";
\IPKF\Support\Env::loadLayered(BASE_PATH."/.env");
$g=static fn($k)=>(string)\IPKF\Support\Env::get($k,"");
foreach(["BALE_SAFIR_SEND_ENABLED","BALE_SAFIR_PRODUCTION_APPROVED"] as $k){
 if(filter_var($g($k),FILTER_VALIDATE_BOOLEAN)){fwrite(STDERR,"FAIL=SEND_ENABLED\n");exit(2);}
}
$p=parse_url($g("APP_URL"));
$s=strtolower((string)($p["scheme"]??""));
$h=(string)($p["host"]??"");
$n=(string)($p["port"]??"");
if(!in_array($s,["http","https"],true)||!preg_match("/^[A-Za-z0-9.-]{1,253}$/D",$h)){fwrite(STDERR,"FAIL=APP_URL_INVALID\n");exit(3);}
echo $s."|".$h."|".$n."\n";
' "$CAND")" || fail RUNTIME_ENV_INVALID
IFS='|' read -r SCHEME HOST PORT <<< "$ENDPOINT"
printf 'PORTAL_HOST=%s\nPORTAL_SCHEME=%s\nPORTAL_PORT=%s\n' "$HOST" "$SCHEME" "$PORT"
echo SAFIR_SEND_FLAGS_DISABLED=PASS

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
nginx -t > "$TMP/nginx-test" 2>&1 || fail NGINX_TEST_FAILED
nginx -T > "$TMP/nginx-full" 2>&1 || fail NGINX_CONFIG_READ_FAILED
echo NGINX_CONFIG=PASS
if grep -Fq "$BASE/current/" "$TMP/nginx-full"; then
  echo NGINX_REFERENCES_CURRENT=YES
else echo NGINX_REFERENCES_CURRENT=NO; fi
if grep -Fq "$ACTIVE/" "$TMP/nginx-full"; then
  echo NGINX_REFERENCES_ACTIVE_RELEASE=YES
else echo NGINX_REFERENCES_ACTIVE_RELEASE=NO; fi
echo NGINX_PORTAL_REFERENCES_BEGIN
grep -Fn "$BASE/" "$TMP/nginx-full" | grep -E 'root|alias|SCRIPT_FILENAME|fastcgi_param|include' | head -n 12 || true
echo NGINX_PORTAL_REFERENCES_END
printf 'NGINX_SERVICE=%s\n' "$(systemctl is-active nginx || true)"
printf 'PHP_FPM_SERVICE=%s\n' "$(systemctl is-active php8.4-fpm || true)"

# Local-only loopback probes. Never follow redirects or access remote DNS.
for proto in http https; do
  if [ "$proto" = http ]; then p=80; else p=443; fi
  if [ -n "$PORT" ]; then
    [ "$proto" = "$SCHEME" ] || continue
    p="$PORT"
  fi
  url="$proto://$HOST"
  if [ -n "$PORT" ]; then url="$url:$PORT"; fi
  if status="$(curl --silent --show-error --connect-timeout 3 --max-time 8 \
    --resolve "$HOST:$p:127.0.0.1" -o "$TMP/health-$proto" \
    -w '%{http_code}' "$url/health" 2>"$TMP/curl.err")"; then
    printf 'LOCAL_HEALTH_%s_HTTP=%s\n' "$proto" "$status"
    if [ "$status" = 200 ]; then
      if php -r '$a=json_decode(file_get_contents($argv[1]),true);exit(is_array($a)&&($a["status"]??null)==="ok"?0:1);' "$TMP/health-$proto"; then
        printf 'LOCAL_HEALTH_%s_JSON=PASS\n' "$proto"
      else printf 'LOCAL_HEALTH_%s_JSON=FAIL\n' "$proto"; fi
    fi
  else printf 'LOCAL_HEALTH_%s=UNREACHABLE\n' "$proto"; fi
done
echo SAFIR_PORTAL_CUTOVER_READINESS=COMPLETE
echo PRODUCTION_CHANGED=NO
echo CUTOVER=NO
echo LIVE_SEND=NO
