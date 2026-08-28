#!/usr/bin/env bash

set -u

BASE="$(
    cd "$(dirname "${BASH_SOURCE[0]}")/.." &&
    pwd
)"

WORKER="$BASE/scripts/process-ticketing-sla.php"

PHP_BIN="${PHP_BIN:-$(command -v php || true)}"

LOG_FILE="${TICKETING_SLA_LOG:-/home/troca/logs/ticketing-sla-worker.log}"

MAX_LOG_BYTES="${TICKETING_SLA_LOG_MAX_BYTES:-20971520}"

timestamp_utc()
{
    date -u '+%Y-%m-%dT%H:%M:%SZ'
}


log_line()
{
    printf '%s\n' "$1" >> "$LOG_FILE"
}


mkdir -p "$(dirname "$LOG_FILE")"


if [ -f "$LOG_FILE" ]
then

    size="$(
        wc -c < "$LOG_FILE" |
        tr -d '[:space:]'
    )"

    case "$size" in
        ''|*[!0-9]*)
            size=0
            ;;
    esac


    if [ "$size" -ge "$MAX_LOG_BYTES" ]
    then
        rm -f "${LOG_FILE}.1"
        mv "$LOG_FILE" "${LOG_FILE}.1"
    fi
fi


STARTED_AT="$(timestamp_utc)"


log_line ""
log_line "===================================================================================================="
log_line "TICKETING_SLA_SCHEDULED_START|UTC=$STARTED_AT|PID=$$|BASE=$BASE"


if [ -z "$PHP_BIN" ] || [ ! -x "$PHP_BIN" ]
then
    log_line "TICKETING_SLA_SCHEDULED_ERROR|UTC=$(timestamp_utc)|REASON=php_not_found"
    log_line "TICKETING_SLA_SCHEDULED_END|UTC=$(timestamp_utc)|PID=$$|RC=127"
    exit 127
fi


if [ ! -f "$WORKER" ]
then
    log_line "TICKETING_SLA_SCHEDULED_ERROR|UTC=$(timestamp_utc)|REASON=worker_missing"
    log_line "TICKETING_SLA_SCHEDULED_END|UTC=$(timestamp_utc)|PID=$$|RC=127"
    exit 127
fi


set +e

"$PHP_BIN" \
    "$WORKER" \
    --apply \
    --limit=200 \
    >> "$LOG_FILE" \
    2>&1

RC=$?

set -e


FINISHED_AT="$(timestamp_utc)"


log_line "TICKETING_SLA_SCHEDULED_END|UTC=$FINISHED_AT|PID=$$|RC=$RC"
log_line "===================================================================================================="


exit "$RC"
