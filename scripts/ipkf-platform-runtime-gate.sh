#!/usr/bin/env bash
set -euo pipefail

MODE="${1:-verify}"

case "$MODE" in
    discover|verify|sync)
        ;;
    *)
        echo "Usage: $0 {discover|verify|sync}" >&2
        exit 64
        ;;
esac

ROOT="$(
    cd "$(dirname "${BASH_SOURCE[0]}")/.." &&
    pwd
)"

SOURCE="${IPKF_SOURCE_ROOT:-$ROOT/public_html}"

CORE_RUNTIME="${IPKF_CORE_RUNTIME:-/home/troca/dev.troca.ir}"
CORE_BASE_URL="${IPKF_CORE_BASE_URL:-https://dev.troca.ir}"
RUNTIME_PARENT="${IPKF_RUNTIME_PARENT:-/home/troca}"

MANIFEST="$ROOT/scripts/ipkf-platform-shared-runtime-files.txt"

test -d "$SOURCE"
test -f "$CORE_RUNTIME/bootstrap/app.php"
test -f "$MANIFEST"


TMP="$(
    mktemp -d
)"

trap 'rm -rf "$TMP"' EXIT

RAW="$TMP/runtime-registry.tsv"
TARGETS="$TMP/runtime-targets.tsv"


# ---------------------------------------------------------
# Registry is the source of truth.
# No module names or module runtime hosts are defined here.
# ---------------------------------------------------------
CORE_RUNTIME="$CORE_RUNTIME" php \
    -d display_errors=1 \
    -d log_errors=0 > "$RAW" <<'PHP'
<?php

declare(strict_types=1);

define(
    'BASE_PATH',
    getenv('CORE_RUNTIME')
);

require BASE_PATH . '/bootstrap/app.php';

restore_error_handler();
restore_exception_handler();

$db =
    (
        new \IPKF\Database\Connections\ConnectionResolver()
    )->resolve('core.primary');

$exists =
    (int) $db->query("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = 'application_modules'
    ")->fetchColumn();

if ($exists !== 1) {
    throw new RuntimeException(
        'application_modules registry is missing.'
    );
}

$rows =
    $db->query("
        SELECT
            module_key,
            display_name,
            base_url,
            route_path,
            runtime_mode
        FROM application_modules
        WHERE is_active = 1
        ORDER BY
            sort_order,
            module_key
    ")->fetchAll(PDO::FETCH_ASSOC)
    ?: [];

foreach ($rows as $row) {

    $moduleKey =
        strtolower(
            trim(
                (string) $row['module_key']
            )
        );

    if (
        preg_match(
            '/^[a-z0-9][a-z0-9_-]{1,99}$/',
            $moduleKey
        ) !== 1
    ) {
        throw new RuntimeException(
            'Invalid active module key: '
            . $moduleKey
        );
    }

    $baseUrl =
        rtrim(
            trim(
                (string) (
                    $row['base_url']
                    ?? ''
                )
            ),
            '/'
        );

    $route =
        trim(
            (string) (
                $row['route_path']
                ?? '/'
            )
        );

    if ($route === '') {
        $route = '/';
    }

    if (!str_starts_with($route, '/')) {
        $route = '/' . $route;
    }

    $runtimeMode =
        strtolower(
            trim(
                (string) (
                    $row['runtime_mode']
                    ?? ''
                )
            )
        );

    echo implode(
        "\x1F",
        [
            $moduleKey,
            str_replace(
                ["\x1F", "\r", "\n"],
                ' ',
                (string) $row['display_name']
            ),
            $baseUrl,
            $route,
            $runtimeMode,
        ]
    );

    echo PHP_EOL;
}
PHP


# ---------------------------------------------------------
# Core runtime is platform infrastructure, not a module.
# Active module runtimes are resolved dynamically from base_url.
# ---------------------------------------------------------
printf '%s\x1f%s\x1f%s\x1f%s\n' \
    "__core__" \
    "$CORE_RUNTIME" \
    "$CORE_BASE_URL/admin/dashboard" \
    "core" \
    > "$TARGETS"


while IFS=$'\x1f' read -r \
    module_key \
    display_name \
    base_url \
    route_path \
    runtime_mode
do
    [ -z "$module_key" ] && continue

    if [ -z "$base_url" ]; then

        # An embedded module belongs to Core.
        case "$runtime_mode" in
            embedded|core|'')
                root="$CORE_RUNTIME"
                smoke="$CORE_BASE_URL$route_path"
                ;;

            *)
                echo \
                    "ABORT: active module '$module_key' has no base_url" \
                    >&2
                exit 1
                ;;
        esac

    else

        host="$(
            BASE_URL="$base_url" php -r '
                $host = parse_url(
                    getenv("BASE_URL"),
                    PHP_URL_HOST
                );

                if (!is_string($host)) {
                    exit(2);
                }

                echo strtolower(trim($host));
            '
        )"

        if ! printf '%s' "$host" |
            grep -Eq '^[a-z0-9][a-z0-9.-]*[a-z0-9]$'
        then
            echo \
                "ABORT: unsafe runtime host for '$module_key': $host" \
                >&2
            exit 1
        fi

        root="$RUNTIME_PARENT/$host"
        smoke="$base_url$route_path"
    fi


    if [ ! -f "$root/bootstrap/app.php" ]; then
        echo \
            "ABORT: active module '$module_key' has no valid runtime: $root" \
            >&2
        exit 1
    fi


    printf '%s\x1f%s\x1f%s\x1f%s\n' \
        "$module_key" \
        "$root" \
        "$smoke" \
        "$runtime_mode" \
        >> "$TARGETS"

done < "$RAW"


# Keep one row per active module.
# Multiple modules may intentionally share a physical runtime;
# each module still receives an independent HTTP smoke check.


echo "============================================================"
echo "IPKF DYNAMIC RUNTIME DISCOVERY"
echo "============================================================"

while IFS=$'\x1f' read -r \
    module_key \
    runtime_root \
    smoke_url \
    runtime_mode
do
    echo \
        "TARGET=$module_key" \
        "|ROOT=$runtime_root" \
        "|SMOKE=$smoke_url" \
        "|MODE=$runtime_mode"
done < "$TARGETS"

TARGET_COUNT="$(
    wc -l < "$TARGETS" |
    tr -d ' '
)"

if [ "$TARGET_COUNT" -lt 1 ]; then
    echo "ABORT: no runtime target discovered" >&2
    exit 1
fi

echo "TARGET_COUNT=$TARGET_COUNT"


if [ "$MODE" = "discover" ]; then
    echo "DISCOVERY=PASS"
    exit 0
fi


# ---------------------------------------------------------
# Shared manifest validation
# ---------------------------------------------------------
FILES="$TMP/files.txt"
: > "$FILES"

while IFS= read -r line
do
    line="${line%%#*}"
    line="$(
        printf '%s' "$line" |
        xargs
    )"

    [ -z "$line" ] && continue

    case "$line" in
        /*|*'..'*)
            echo \
                "ABORT: unsafe manifest path: $line" \
                >&2
            exit 1
            ;;
    esac

    if [ ! -f "$SOURCE/$line" ]; then
        echo \
            "ABORT: source shared file missing: $line" \
            >&2
        exit 1
    fi

    printf '%s\n' "$line" \
        >> "$FILES"

done < "$MANIFEST"


FILE_COUNT="$(
    wc -l < "$FILES" |
    tr -d ' '
)"

if [ "$FILE_COUNT" -lt 1 ]; then
    echo "ABORT: empty shared manifest" >&2
    exit 1
fi

echo "SHARED_FILE_COUNT=$FILE_COUNT"


# ---------------------------------------------------------
# Optional dynamic sync.
# Every discovered runtime receives the same artifact.
# ---------------------------------------------------------
if [ "$MODE" = "sync" ]; then

    while IFS=$'\x1f' read -r \
        module_key \
        runtime_root \
        smoke_url \
        runtime_mode
    do
        echo "SYNC_RUNTIME=$module_key|$runtime_root"

        while IFS= read -r rel
        do
            mkdir -p \
                "$runtime_root/$(dirname "$rel")"

            cp -p \
                "$SOURCE/$rel" \
                "$runtime_root/$rel"

        done < "$FILES"

    done < "$TARGETS"
fi


# ---------------------------------------------------------
# Mandatory parity + PHP lint on all discovered runtimes.
# ---------------------------------------------------------
while IFS=$'\x1f' read -r \
    module_key \
    runtime_root \
    smoke_url \
    runtime_mode
do
    echo
    echo "VERIFY_RUNTIME=$module_key|$runtime_root"

    while IFS= read -r rel
    do
        source_file="$SOURCE/$rel"
        runtime_file="$runtime_root/$rel"

        if [ ! -f "$runtime_file" ]; then
            echo \
                "ABORT: runtime shared file missing: $runtime_file" \
                >&2
            exit 1
        fi

        if ! cmp -s \
            "$source_file" \
            "$runtime_file"
        then
            echo \
                "ABORT: runtime parity failure: $runtime_file" \
                >&2
            exit 1
        fi

        case "$rel" in
            *.php)
                php -l "$runtime_file" >/dev/null
                ;;
        esac

        echo "PARITY=PASS|$rel"

    done < "$FILES"


    # Runtime-level autoload contract.
    RUNTIME_ROOT="$runtime_root" php \
        -d display_errors=1 \
        -d log_errors=0 <<'PHP'
<?php

declare(strict_types=1);

define(
    'BASE_PATH',
    getenv('RUNTIME_ROOT')
);

require BASE_PATH . '/bootstrap/app.php';

restore_error_handler();
restore_exception_handler();

$class =
    \App\Services\DynamicModuleDashboardService::class;

if (!class_exists($class)) {
    throw new RuntimeException(
        'DynamicModuleDashboardService autoload failed.'
    );
}

$cards =
    (
        new \App\Services\DynamicModuleDashboardService()
    )->cards();

if (!is_array($cards)) {
    throw new RuntimeException(
        'Dynamic module cards contract failed.'
    );
}

echo
    'AUTOLOAD=PASS'
    . '|CARDS='
    . count($cards)
    . PHP_EOL;
PHP


    # Unauthenticated HTTP smoke:
    # 2xx/3xx/4xx are acceptable here; 5xx and unreachable are not.
    BODY="$TMP/http-body"

    status="$(
        curl \
            -k \
            -sS \
            --max-time 15 \
            -o "$BODY" \
            -w '%{http_code}' \
            "$smoke_url" \
        || true
    )"

    echo \
        "SMOKE=$smoke_url|HTTP=$status"

    if [ -z "$status" ] || [ "$status" = "000" ]; then
        echo \
            "ABORT: runtime unreachable: $smoke_url" \
            >&2
        exit 1
    fi

    if [ "$status" -ge 500 ]; then
        echo \
            "ABORT: runtime server error: $smoke_url" \
            >&2

        head -n 40 "$BODY" \
            >&2 \
            || true

        exit 1
    fi

done < "$TARGETS"


echo
echo "============================================================"
echo "IPKF PLATFORM DYNAMIC RUNTIME GATE PASS"
echo "MODE=$MODE"
echo "TARGET_COUNT=$TARGET_COUNT"
echo "RUNTIME SOURCE=application_modules"
echo "MODULE LIST HARDCODED=NO"
echo "PARITY=PASS"
echo "AUTOLOAD=PASS"
echo "HTTP_NON_500=PASS"
echo "============================================================"
