#!/usr/bin/env bash

set -u

APPLICATION="${1:-}"

if [ -z "$APPLICATION" ]; then
    exit 2
fi

BASE="$(
    cd "$(dirname "$0")/.."
    pwd
)"

WORKER="$BASE/scripts/run-ipkf-scheduler.php"

PHP_BIN="$(
    command -v php 2>/dev/null ||
    true
)"

LOG_DIR="/home/troca/logs"
LOG_FILE="$LOG_DIR/ipkf-scheduler-${APPLICATION}.log"
LOCK_FILE="/tmp/ipkf-scheduler-${APPLICATION}.lock"

mkdir -p "$LOG_DIR"

timestamp_utc()
{
    date -u '+%Y-%m-%dT%H:%M:%SZ'
}

log_line()
{
    printf '%s\n' "$1" >> "$LOG_FILE"
}

if [ -z "$PHP_BIN" ]; then
    log_line \
        "IPKF_SCHEDULER_ERROR|UTC=$(timestamp_utc)|APPLICATION=$APPLICATION|REASON=php_not_found"

    exit 127
fi

if [ ! -f "$WORKER" ]; then
    log_line \
        "IPKF_SCHEDULER_ERROR|UTC=$(timestamp_utc)|APPLICATION=$APPLICATION|REASON=worker_missing"

    exit 127
fi

exec 9>"$LOCK_FILE"

if ! flock -n 9
then
    log_line \
        "IPKF_SCHEDULER_SKIP|UTC=$(timestamp_utc)|APPLICATION=$APPLICATION|REASON=already_running"

    exit 0
fi

log_line \
    "IPKF_SCHEDULER_START|UTC=$(timestamp_utc)|APPLICATION=$APPLICATION|PID=$$"

"$PHP_BIN" \
    "$WORKER" \
    --application="$APPLICATION" \
    --limit=20 \
    >> "$LOG_FILE" \
    2>&1

RC=$?

log_line \
    "IPKF_SCHEDULER_END|UTC=$(timestamp_utc)|APPLICATION=$APPLICATION|PID=$$|RC=$RC"

exit "$RC"
