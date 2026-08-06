#!/usr/bin/env bash
set -euo pipefail

repo_root="${1:-/d/Documents/GitHub/troca}"
expected_branch="v0.6.1-notification-provider-management-dev"
expected_head="4058f24"

cd "$repo_root"

current_branch="$(git branch --show-current)"
current_head="$(git rev-parse --short HEAD)"

if [[ "$current_branch" != "$expected_branch" ]]; then
    printf 'Expected branch %s; current branch is %s.\n' \
        "$expected_branch" "$current_branch" >&2
    exit 1
fi

if [[ "$current_head" != "$expected_head" ]]; then
    printf 'Expected HEAD %s; current HEAD is %s.\n' \
        "$expected_head" "$current_head" >&2
    exit 1
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "Working tree or index is not clean. Patch stopped." >&2
    git status --short --branch >&2
    exit 1
fi

view_file="public_html/resources/views/admin/communication-settings.php"
style_file="public_html/resources/views/admin/partials/communication-style.php"
test_file="tests/NotificationSendWizardRuntimeTest.php"
tool_file="tools/apply-notification-send-wizard-runtime-hotfix-v061.sh"

cleanup_on_error() {
    status=$?

    if [[ "$status" -ne 0 ]]; then
        echo
        echo "PATCH FAILED; RESTORING CLEAN TREE" >&2

        git restore --staged --worktree -- \
          "$view_file" \
          "$style_file" \
          >/dev/null 2>&1 || true

        rm -f -- "$test_file" "$tool_file"
    fi

    exit "$status"
}

trap cleanup_on_error EXIT

echo
echo "=== Fix Notification Send Wizard Runtime ==="

VIEW_FILE="$view_file" perl <<'PERL'
use strict;
use warnings;
use utf8;
use open qw(:std :encoding(UTF-8));

my $path = $ENV{VIEW_FILE};

open my $fh, '<:encoding(UTF-8)', $path
    or die "Could not read $path: $!\n";

local $/;
my $text = <$fh>;
close $fh;

$text =~ s/\r\n?/\n/g;

my $old = <<'OLD';
                    const contentStatus =
                        form.querySelector(
                            '[data-send-content-status]'
                        );
                    const formatBytes = (bytes) => {
OLD

my $new = <<'NEW';
                    const contentStatus =
                        form.querySelector(
                            '[data-send-content-status]'
                        );
                    const subject =
                        form.querySelector(
                            '[data-send-subject]'
                        );
                    const digits =
                        new Intl.NumberFormat('fa-IR');
                    const formatBytes = (bytes) => {
NEW

my $count = () = $text =~ /\Q$old\E/g;

die "Expected one wizard runtime anchor; found $count.\n"
    if $count != 1;

my $position = index($text, $old);

substr(
    $text,
    $position,
    length($old),
    $new
);

$text =~ s/\n*\z/\n/;

open my $out, '>:encoding(UTF-8)', $path
    or die "Could not write $path: $!\n";

print {$out} $text;
close $out;

print "UPDATED: local subject reference\n";
print "UPDATED: local Persian number formatter\n";
PERL

if ! grep -Fq \
  "notification-send-wizard-runtime-hotfix-v061" \
  "$style_file"
then
    cat >> "$style_file" <<'CSS'

<style>
/* notification-send-wizard-runtime-hotfix-v061 */
.notification-send-form
    > .notification-send-section[hidden],
.notification-send-form
    > .notification-send-review[hidden] {
    display: none !important;
}
</style>
CSS
fi

echo "UPDATED: wizard hidden panel hardening"

cat > "$test_file" <<'PHP'
<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$viewPath = $root
    . '/public_html/resources/views/admin/'
    . 'communication-settings.php';

$stylePath = $root
    . '/public_html/resources/views/admin/partials/'
    . 'communication-style.php';

$view = file_get_contents($viewPath);
$style = file_get_contents($stylePath);

if (!is_string($view) || !is_string($style)) {
    fwrite(STDERR, "FAIL: runtime source unreadable.\n");
    exit(1);
}

$subjectDeclaration = <<<'JS'
                    const subject =
                        form.querySelector(
                            '[data-send-subject]'
                        );
JS;

$digitsDeclaration = <<<'JS'
                    const digits =
                        new Intl.NumberFormat('fa-IR');
JS;

$checks = [
    str_contains($view, $subjectDeclaration),
    str_contains($view, $digitsDeclaration),
    str_contains(
        $view,
        'renderFiles();'
    ),
    str_contains(
        $view,
        'showStep(1);'
    ),
    str_contains(
        $style,
        'notification-send-wizard-runtime-hotfix-v061'
    ),
    str_contains(
        $style,
        '.notification-send-review[hidden]'
    ),
];

foreach ($checks as $index => $passed) {
    if (!$passed) {
        fwrite(
            STDERR,
            'FAIL: wizard runtime check '
            . ($index + 1)
            . " failed.\n"
        );
        exit(1);
    }
}

echo "Notification send wizard runtime checks passed.\n";
PHP

echo "ADDED: NotificationSendWizardRuntimeTest.php"

mkdir -p tools
cp -- "$0" "$tool_file"

git add -- \
  "$view_file" \
  "$style_file" \
  "$test_file" \
  "$tool_file"

echo
echo "=== Cached Validation ==="

git diff --cached --check

if command -v php >/dev/null 2>&1; then
    echo
    echo "=== PHP Validation ==="

    php -l "$view_file"
    php -l "$style_file"
    php -l "$test_file"
    php "$test_file"
else
    echo
    echo "PHP_NOT_AVAILABLE_ON_WINDOWS=SKIPPED"
fi

echo
echo "=== Runtime Markers ==="

git grep -n -E \
  "const subject =|const digits =|notification-send-wizard-runtime-hotfix-v061|Notification send wizard runtime checks passed" \
  -- \
  "$view_file" \
  "$style_file" \
  "$test_file"

echo
echo "=== Backend Scope Check ==="

backend_changed="$(
  git diff --cached --name-only \
    | grep -E '^public_html/(app|routes|system)/' \
    || true
)"

if [[ -n "$backend_changed" ]]; then
    echo "BACKEND_SCOPE_CHANGED=1" >&2
    printf '%s\n' "$backend_changed" >&2
    exit 1
fi

echo "BACKEND_SCOPE_CHANGED=0"
echo "MIGRATION_REQUIRED=NO"

echo
echo "=== Unstaged Changes Check ==="

if git diff --quiet; then
    echo "UNSTAGED_CHANGES=0"
else
    echo "UNSTAGED_CHANGES=1"
    git status --short
    exit 1
fi

echo
echo "=== Cached Summary ==="

git diff --cached --stat

echo
echo "=== Final Status ==="

git status --short --branch

echo
echo "NOTIFICATION SEND WIZARD RUNTIME HOTFIX ADDED AND STAGED"
echo "No commit was created."

trap - EXIT
