#!/usr/bin/env bash
# IPKF Safir read-only diagnostic after HTTP 404 and successful automatic rollback.
set -Eeuo pipefail
umask 077
fail(){ printf 'FAIL=%s\n' "$*" >&2; exit 1; }
[ "$(id -u)" -eq 0 ] || fail ROOT_REQUIRED
for cmd in php curl nginx runuser readlink stat grep awk mktemp; do
  command -v "$cmd" >/dev/null || fail "MISSING_TOOL:$cmd"
done
BASE=/srv/troca/portal-test
CURRENT="$BASE/current"
OLD="$BASE/releases/prod-affiliation-choice-r15-20261025T025142Z-294349-a13-parentfirst-test-a15-core-affiliation-a16-core-reviewer-renewal-a21-scoped-staff-nav-a25-provincial-test-a29-province-priority-a33-http-fail-closed-a41r3-scope-hardening"
NEW="$BASE/releases/prod-safir-portal-20261101T130944Z-92749b72"
HOST=troca.corc.ir
[ -L "$CURRENT" ] || fail CURRENT_NOT_SYMLINK
[ "$(readlink -e "$CURRENT")" = "$OLD" ] || fail ROLLBACK_NOT_ACTIVE
[ -f "$NEW.safir-receipt" ] || fail CANDIDATE_RECEIPT_MISSING
[ -d "$NEW" ] || fail CANDIDATE_MISSING
grep -Fxq "ACTIVE_BEFORE=$OLD" "$NEW.safir-receipt" || fail BASELINE_RECEIPT_MISMATCH
grep -Fxq "RELEASE_PATH=$NEW" "$NEW.safir-receipt" || fail CANDIDATE_RECEIPT_MISMATCH
echo ROLLBACK_ACTIVE=PASS

# Bootstrap only the route graph as the Portal runtime account. No dispatch.
route_graph(){
  local label="$1" path="$2"
  echo "=== ROUTE_GRAPH_$label ==="
  runuser -u troca-portal-test -- php -d display_errors=stderr -r '
    define("BASE_PATH",$argv[1]);
    $app=require BASE_PATH."/bootstrap/app.php";
    $router=$app->router();
    $property=new ReflectionProperty($router,"routes");
    $routes=$property->getValue($router);
    $paths=[
      ["GET","/admin/communications/settings/safir/export","SAFIR_EXPORT"],
      ["POST","/admin/communications/settings/safir","SAFIR_POST"],
      ["GET","/admin/login","LOGIN"],
      ["GET","/health","HEALTH"]
    ];
    foreach($paths as $item){
      echo "ROUTE_".$item[2]."=".(isset($routes[$item[0]][$item[1]])?"PRESENT":"ABSENT")."\n";
    }
    echo "ROUTE_COUNT=".$router->count()."\n";
  ' "$path" || fail "BOOTSTRAP_ROUTE_GRAPH_FAILED:$label"
}
route_graph OLD "$OLD"
route_graph CANDIDATE "$NEW"

TMP="$(mktemp -d)"
trap 'rm -rf -- "$TMP"' EXIT
nginx -t > "$TMP/test" 2>&1 || fail NGINX_CONFIGURATION_INVALID
nginx -T > "$TMP/full" 2>&1 || fail NGINX_EFFECTIVE_CONFIGURATION_UNREADABLE
echo NGINX_CONFIG=PASS
if grep -Fq "$BASE/current/public" "$TMP/full"; then echo NGINX_CURRENT_ROOT=YES; else echo NGINX_CURRENT_ROOT=NO; fi
echo NGINX_ROUTE_DIRECTIVES_BEGIN
grep -E '^[[:space:]]*(location|try_files|root|alias|fastcgi_param[[:space:]]+SCRIPT_FILENAME)[[:space:]]' "$TMP/full" | head -n 65 || true
echo NGINX_ROUTE_DIRECTIVES_END

for path in /health /admin/login /admin/communications/settings/safir/export; do
  label="$(printf '%s' "$path" | tr / _)"
  http="$(curl --silent --show-error --noproxy '*' --connect-timeout 3 --max-time 12 \
    --resolve "$HOST:443:127.0.0.1" -D "$TMP/headers" -o "$TMP/body" \
    --write-out '%{http_code}' "https://$HOST$path")" ||
      fail "LOOPBACK_HTTP_FAILED:$label"
  printf 'CURRENT_HTTP_%s=%s\n' "$label" "$http"
  if grep -qi '^X-Request-ID:' "$TMP/headers"; then
    printf 'APPLICATION_REQUEST_ID_%s=YES\n' "$label"
  else
    printf 'APPLICATION_REQUEST_ID_%s=NO\n' "$label"
  fi
  php -r '
    $body=file_get_contents($argv[1]);
    $name="OTHER";
    if(str_contains($body,"404 - Route not found"))$name="ROUTER_DEFAULT_404";
    elseif(str_contains($body,"صفحه موردنظر یافت نشد"))$name="APPLICATION_404";
    elseif(stripos($body,"<title>404 Not Found</title>")!==false)$name="SERVER_404";
    echo "HTTP_BODY_KIND=".$name."\n";
  ' "$TMP/body"
done
[ "$(readlink -e "$CURRENT")" = "$OLD" ] || fail CURRENT_CHANGED_DURING_INSPECTION
echo SAFIR_ROUTE_DIAGNOSTIC=PASS
echo CURRENT_UNCHANGED=YES
echo DATABASE_MUTATION=NO
echo CUTOVER=NO
echo LIVE_SEND=NO
