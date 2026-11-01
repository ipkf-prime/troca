#!/usr/bin/env bash
# CI regression for Portal-only Rsync comparison.
# No Production access, credentials, deployment or HTTP calls.
set -Eeuo pipefail
command -v rsync >/dev/null || { echo MISSING_RSYNC >&2; exit 1; }
dir="$(mktemp -d)"
trap 'rm -rf "$dir"' EXIT
mkdir -p "$dir/baseline/app/Services" "$dir/stage/app/Services"
printf 'original\n' > "$dir/baseline/app/Services/CommunicationSettingsService.php"
printf 'patched\n' > "$dir/stage/app/Services/CommunicationSettingsService.php"
printf 'new template class\n' > "$dir/stage/app/Services/BaleSafirTemplateCatalogService.php"
diff="$(rsync -rnic --delete --out-format='%i|%n' "$dir/baseline/" "$dir/stage/")"
echo "$diff" | grep -Fq '|app/Services/CommunicationSettingsService.php'
echo "$diff" | grep -Fq '|app/Services/BaleSafirTemplateCatalogService.php'
count=0
while IFS= read -r line; do
    [ -n "$line" ] || continue
    case "$line" in *'|'*) ;; *) echo INVALID_RSYNC_RECORD >&2; exit 1;; esac
    relative="${line#*|}"
    case "$relative" in
      app/Services/CommunicationSettingsService.php|app/Services/BaleSafirTemplateCatalogService.php) ;;
      *) echo "UNEXPECTED_SOURCE_DIFF:$relative" >&2; exit 1 ;;
    esac
    count=$((count+1))
done <<< "$diff"
[ "$count" -eq 2 ] || { echo "BAD_DIFF_COUNT:$count" >&2; exit 1; }
echo RSYNC_ITEMIZED_PARSER=PASS
