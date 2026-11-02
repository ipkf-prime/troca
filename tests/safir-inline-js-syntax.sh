#!/usr/bin/env bash
# Extract the rendered inline Safir JavaScript (the page HTML is PHP) and
# check its real JS parser syntax. Catches duplicate top-level const declarations.
set -Eeuo pipefail
VIEW="public_html/resources/views/admin/partials/bale-safir-campaign.php"
TMP="$(mktemp --suffix=.js)"
trap 'rm -f "$TMP"' EXIT
php -r '
 $v = file_get_contents($argv[1]);
 if (!preg_match_all("~<script>(.*?)</script>~s", $v, $matches) || count($matches[1]) !== 1) {
   fwrite(STDERR, "FAIL=SAFIR_INLINE_JS_BLOCK_COUNT\n");exit(2);
 }
 if (str_contains($matches[1][0], "<?php") || str_contains($matches[1][0], "<?=")) {
   fwrite(STDERR, "FAIL=UNRENDERED_PHP_IN_JS\n");exit(3);
 }
 file_put_contents($argv[2], $matches[1][0]);
' "$VIEW" "$TMP"
node --check "$TMP"
echo SAFIR_INLINE_JAVASCRIPT_PARSER=PASS
