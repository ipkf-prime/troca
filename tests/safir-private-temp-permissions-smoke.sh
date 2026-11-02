#!/usr/bin/env bash
# Reproduce GNU chmod on both plain and setgid-inheriting releases trees.
set -Eeuo pipefail
ROOT="$(mktemp -d)"
trap 'rm -rf "$ROOT"' EXIT
for kind in plain setgid; do
  mkdir "$ROOT/$kind"
  if [ "$kind" = setgid ]; then chmod 2770 "$ROOT/$kind"; else chmod 0700 "$ROOT/$kind"; fi
  tmp="$(mktemp -d "$ROOT/$kind/.candidate-XXXXXX")"
  chgrp "$(id -gn)" "$tmp"
  chmod 0710 "$tmp"
  mode="$(stat -c %a "$tmp")"
  case "$mode" in 710|2710) :;; *) echo "FAIL=UNSAFE_TMP_MODE:$mode"; exit 1;; esac
  [ "$(stat -c %U "$tmp")" = "$(id -un)" ]
  [ "$(stat -c %G "$tmp")" = "$(id -gn)" ]
  if [ "$kind" = setgid ]; then [ "$mode" = 2710 ] || { echo "FAIL=SETGID_INHERITANCE"; exit 1; }; fi
  echo "SAFIR_PRIVATE_TMP_MODE_PASS=$kind:$mode"
done
