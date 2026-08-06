#!/usr/bin/env bash
set -Eeuo pipefail

REPO="${1:-}"
EXPECTED_BRANCH="v0.6.1-notification-provider-management-dev"
EXPECTED_HEAD="030c3b1"

if [[ -z "$REPO" ]]; then
    echo "Usage: bash apply-access-control-catalog-descriptions-v061.sh /path/to/repo"
    exit 2
fi

cd "$REPO"

CURRENT_BRANCH="$(git branch --show-current)"
CURRENT_HEAD="$(git rev-parse --short HEAD)"

if [[ "$CURRENT_BRANCH" != "$EXPECTED_BRANCH" ]]; then
    echo "Unexpected branch: $CURRENT_BRANCH"
    echo "Expected branch: $EXPECTED_BRANCH"
    exit 3
fi

if [[ "$CURRENT_HEAD" != "$EXPECTED_HEAD" ]]; then
    echo "Unexpected HEAD: $CURRENT_HEAD"
    echo "Expected HEAD: $EXPECTED_HEAD"
    exit 4
fi

if [[ -n "$(git status --porcelain)" ]]; then
    echo "Working tree or index is not clean. Patch stopped."
    git status --short --branch
    exit 5
fi

MIGRATION_FILE="public_html/system/Database/Migrations/CompleteAccessControlCatalogDescriptions.php"
MIGRATE_FILE="public_html/public/migrate.php"
TEST_FILE="tests/AccessControlCatalogDescriptionsTest.php"
TOOL_FILE="tools/apply-access-control-catalog-descriptions-v061.sh"

cleanup() {
    local status=$?

    if [[ $status -ne 0 ]]; then
        echo
        echo "PATCH FAILED; RESTORING CLEAN TREE"

        git restore --staged --worktree -- \
            "$MIGRATE_FILE" \
            "$MIGRATION_FILE" \
            "$TEST_FILE" \
            "$TOOL_FILE" \
            2>/dev/null || true

        rm -f -- \
            "$MIGRATION_FILE" \
            "$TEST_FILE" \
            "$TOOL_FILE"

        git restore --staged -- \
            "$MIGRATION_FILE" \
            "$TEST_FILE" \
            "$TOOL_FILE" \
            2>/dev/null || true
    fi

    exit $status
}
trap cleanup EXIT

echo
echo "=== Create Access Control Description Migration ==="

cat > "$MIGRATION_FILE" <<'PHP'
<?php

namespace IPKF\Database\Migrations;

class CompleteAccessControlCatalogDescriptions extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('permissions')) {
            return;
        }

        $descriptions = [
            'automation.audit.view' =>
                'مشاهده سوابق ثبت، ارجاع و تغییرات مکاتبات اداری.',
            'notifications.routing.manage' =>
                'مدیریت قواعد انتخاب کانال، سرویس‌دهنده و مسیر ارسال اعلان‌ها.',
            'organizations.manage' =>
                'ایجاد، ویرایش و مدیریت سازمان‌های سامانه.',
            'positions.manage' =>
                'ایجاد، ویرایش و مدیریت پست‌ها و سمت‌های سازمانی.',
            'org_units.manage' =>
                'ایجاد، ویرایش و مدیریت ساختار و واحدهای سازمانی.',
            'work.item.view' =>
                'مشاهده فهرست و جزئیات تسک‌های مدیریت کار.',
            'work.item.create' =>
                'ایجاد تسک جدید در پروژه‌های مدیریت کار.',
            'work.item.update' =>
                'ویرایش مشخصات و تغییر وضعیت تسک‌ها.',
            'work.item.assign' =>
                'تعیین یا تغییر مسئول اجرای تسک‌ها.',
            'work.settings.view' =>
                'مشاهده تعاریف و تنظیمات ماژول مدیریت کار.',
        ];

        $statement = $this->db->prepare("
            UPDATE permissions
            SET description = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE code = ?
              AND (
                    description IS NULL
                    OR description = ''
              )
        ");

        foreach ($descriptions as $code => $description) {
            $statement->execute([
                $description,
                $code,
            ]);
        }
    }

    public function down(): void
    {
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
        ");
        $statement->execute([$table]);

        return (int) $statement->fetchColumn() > 0;
    }
}
PHP

echo "CREATED: $MIGRATION_FILE"

echo
echo "=== Register Migration ==="

cat > /tmp/ipkf-register-access-catalog-descriptions.pl <<'PERL'
use strict;
use warnings;

local $/;
my $file = shift @ARGV;

open my $in, '<:raw', $file
    or die "Cannot read $file: $!";

my $content = <$in>;
close $in;

my $needle =
    "        new \\IPKF\\Database\\Migrations\\"
    . "FinalizeAccessControlCatalogMetadata(),\n";

my $addition =
    "        new \\IPKF\\Database\\Migrations\\"
    . "CompleteAccessControlCatalogDescriptions(),\n";

index($content, $addition) < 0
    or die "Description migration is already registered.\n";

index($content, $needle) >= 0
    or die "Migration registration anchor was not found.\n";

$content =~ s/\Q$needle\E/$needle$addition/;

open my $out, '>:raw', $file
    or die "Cannot write $file: $!";

print {$out} $content;
close $out;
PERL

perl /tmp/ipkf-register-access-catalog-descriptions.pl "$MIGRATE_FILE"
rm -f /tmp/ipkf-register-access-catalog-descriptions.pl

echo "UPDATED: $MIGRATE_FILE"

echo
echo "=== Add Static Regression Test ==="

cat > "$TEST_FILE" <<'PHP'
<?php

$root = dirname(__DIR__);
$migrationPath = $root
    . '/public_html/system/Database/Migrations/'
    . 'CompleteAccessControlCatalogDescriptions.php';

$migration = file_get_contents($migrationPath);
$migrate = file_get_contents(
    $root . '/public_html/public/migrate.php'
);

if (!is_string($migration) || !is_string($migrate)) {
    fwrite(STDERR, "Access-control description sources are missing.\n");
    exit(1);
}

$expected = [
    'automation.audit.view' =>
        'مشاهده سوابق ثبت، ارجاع و تغییرات مکاتبات اداری.',
    'notifications.routing.manage' =>
        'مدیریت قواعد انتخاب کانال، سرویس‌دهنده و مسیر ارسال اعلان‌ها.',
    'organizations.manage' =>
        'ایجاد، ویرایش و مدیریت سازمان‌های سامانه.',
    'positions.manage' =>
        'ایجاد، ویرایش و مدیریت پست‌ها و سمت‌های سازمانی.',
    'org_units.manage' =>
        'ایجاد، ویرایش و مدیریت ساختار و واحدهای سازمانی.',
    'work.item.view' =>
        'مشاهده فهرست و جزئیات تسک‌های مدیریت کار.',
    'work.item.create' =>
        'ایجاد تسک جدید در پروژه‌های مدیریت کار.',
    'work.item.update' =>
        'ویرایش مشخصات و تغییر وضعیت تسک‌ها.',
    'work.item.assign' =>
        'تعیین یا تغییر مسئول اجرای تسک‌ها.',
    'work.settings.view' =>
        'مشاهده تعاریف و تنظیمات ماژول مدیریت کار.',
];

if (count($expected) !== 10) {
    fwrite(STDERR, "Expected description count is not 10.\n");
    exit(1);
}

foreach ($expected as $code => $description) {
    if (!str_contains($migration, "'{$code}'")) {
        fwrite(STDERR, "Missing permission code: {$code}\n");
        exit(1);
    }

    if (!str_contains($migration, "'{$description}'")) {
        fwrite(STDERR, "Missing description for: {$code}\n");
        exit(1);
    }
}

foreach ([
    'SET description = ?',
    'description IS NULL',
    "OR description = ''",
    'updated_at = CURRENT_TIMESTAMP',
] as $marker) {
    if (!str_contains($migration, $marker)) {
        fwrite(STDERR, "Missing migration marker: {$marker}\n");
        exit(1);
    }
}

if (
    !str_contains(
        $migrate,
        'new \\IPKF\\Database\\Migrations\\'
        . 'CompleteAccessControlCatalogDescriptions()'
    )
) {
    fwrite(STDERR, "Description migration is not registered.\n");
    exit(1);
}

echo "Access control catalog description checks passed.\n";
PHP

echo "CREATED: $TEST_FILE"

echo
echo "=== Copy Reproducible Tool ==="

mkdir -p tools
cp "$0" "$TOOL_FILE"
chmod +x "$TOOL_FILE"

echo "CREATED: $TOOL_FILE"

echo
echo "=== Normalize Final Newlines ==="

perl -0pi -e 's/\s*\z/\n/' \
    "$MIGRATION_FILE" \
    "$MIGRATE_FILE" \
    "$TEST_FILE" \
    "$TOOL_FILE"

echo
echo "=== Cached Validation ==="

if command -v php >/dev/null 2>&1; then
    php -l "$MIGRATION_FILE"
    php -l "$MIGRATE_FILE"
    php "$TEST_FILE"
else
    echo "PHP_NOT_AVAILABLE_ON_WINDOWS=SKIPPED"
fi

echo
echo "=== Description Completion Markers ==="

grep -Fn \
    "CompleteAccessControlCatalogDescriptions" \
    "$MIGRATION_FILE" \
    "$MIGRATE_FILE" \
    "$TEST_FILE"

grep -Fn \
    "automation.audit.view" \
    "$MIGRATION_FILE"

grep -Fn \
    "work.settings.view" \
    "$MIGRATION_FILE"

grep -Fn \
    "مدیریت قواعد انتخاب کانال" \
    "$MIGRATION_FILE"

echo
echo "=== Stage Patch ==="

git add -- \
    "$MIGRATION_FILE" \
    "$MIGRATE_FILE" \
    "$TEST_FILE" \
    "$TOOL_FILE"

git diff --cached --check

echo
echo "=== Scope Checks ==="

migration_scope_changed=0

if git diff --cached --name-only | grep -Eq \
    '^public_html/(public/migrate\.php|system/Database/Migrations/)'
then
    migration_scope_changed=1
fi

echo "MIGRATION_SCOPE_CHANGED=$migration_scope_changed"
echo "MIGRATION_REQUIRED=YES"

echo
echo "=== Unstaged Changes Check ==="

unstaged_count="$(git diff --name-only | wc -l | tr -d ' ')"
echo "UNSTAGED_CHANGES=$unstaged_count"

if [[ "$unstaged_count" != "0" ]]; then
    echo "Unexpected unstaged changes detected."
    git status --short
    exit 8
fi

echo
echo "=== Cached Summary ==="

git diff --cached --stat

echo
echo "=== Final Status ==="

git status --short --branch

echo
echo "ACCESS CONTROL CATALOG DESCRIPTIONS ADDED AND STAGED"
echo "No commit was created."

trap - EXIT
