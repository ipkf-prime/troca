#!/usr/bin/env bash
set -Eeuo pipefail

REPO="${1:-}"
EXPECTED_BRANCH="v0.6.1-notification-provider-management-dev"
EXPECTED_HEAD="3ada975"

if [[ -z "$REPO" ]]; then
    echo "Usage: bash apply-access-control-catalog-metadata-v061.sh /path/to/repo"
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

MIGRATION_FILE="public_html/system/Database/Migrations/FinalizeAccessControlCatalogMetadata.php"
MIGRATE_FILE="public_html/public/migrate.php"
TEST_FILE="tests/AccessControlCatalogMetadataTest.php"
TOOL_FILE="tools/apply-access-control-catalog-metadata-v061.sh"

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
echo "=== Create Access Control Catalog Metadata Migration ==="

cat > "$MIGRATION_FILE" <<'PHP'
<?php

namespace IPKF\Database\Migrations;

class FinalizeAccessControlCatalogMetadata extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('permissions')) {
            return;
        }

        $items = [
            'automation.correspondence.cartable.view' => [
                'مکاتبات اداری',
                'مشاهده کارتابل و مکاتبات ارجاع‌شده به کاربر.',
            ],
            'automation.correspondence.close' => [
                'مکاتبات اداری',
                'بستن گردش مکاتبه پس از پایان فرایند.',
            ],
            'automation.correspondence.create' => [
                'مکاتبات اداری',
                'ایجاد پیش‌نویس مکاتبه جدید.',
            ],
            'automation.correspondence.edit_draft' => [
                'مکاتبات اداری',
                'ویرایش پیش‌نویس مکاتبه پیش از ثبت رسمی.',
            ],
            'automation.correspondence.register' => [
                'مکاتبات اداری',
                'ثبت رسمی مکاتبه در دفتر مربوط.',
            ],
            'automation.correspondence.route' => [
                'مکاتبات اداری',
                'ارجاع مکاتبه به کاربر یا واحد سازمانی.',
            ],
            'automation.correspondence.view' => [
                'مکاتبات اداری',
                'مشاهده فهرست و جزئیات مکاتبات اداری.',
            ],
            'automation.registry.manage' => [
                'دفاتر ثبت مکاتبات',
                'مدیریت دفترهای ثبت، شماره‌گذاری و قواعد دبیرخانه.',
            ],
            'messages.admin.manage' => [
                'پیام‌رسان داخلی',
                'مدیریت کامل پیام‌های داخلی در سطح سامانه.',
            ],
            'messages.admin.view' => [
                'پیام‌رسان داخلی',
                'مشاهده مدیریتی پیام‌های داخلی کاربران.',
            ],
            'messages.reply' => [
                'پیام‌رسان داخلی',
                'پاسخ به پیام‌های دریافت‌شده در کارتابل داخلی.',
            ],
            'messages.send' => [
                'پیام‌رسان داخلی',
                'ارسال پیام داخلی به کاربران سامانه.',
            ],
            'messages.view' => [
                'پیام‌رسان داخلی',
                'مشاهده کارتابل پیام‌های داخلی.',
            ],
            'notifications.send.manage' => [
                'ارسال اعلان',
                'ارسال مستقیم اعلان از کانال‌های فعال سامانه.',
            ],
            'notifications.preferences.self' => [
                'ترجیحات اعلان',
                'مدیریت کانال‌ها و ترجیحات دریافت اعلان شخصی.',
            ],
            'notifications.providers.manage' => [
                'سرویس‌دهندگان اعلان',
                'مدیریت سرویس‌دهندگان ایمیل، پیام کوتاه و پیام‌رسان.',
            ],
            'notifications.reports.view' => [
                'گزارش‌های اعلان',
                'مشاهده گزارش ارسال و تحویل اعلان‌ها.',
            ],
            'appointments.assign' => [
                'انتصاب‌ها',
                'انتصاب اشخاص به پست‌ها و جایگاه‌های سازمانی.',
            ],
            'appointments.manage' => [
                'انتصاب‌ها',
                'مدیریت انتصاب‌ها و سوابق جایگاه‌های سازمانی.',
            ],
            'organizational_context.switch' => [
                'جایگاه سازمانی فعال',
                'تغییر جایگاه سازمانی فعال کاربر در نشست جاری.',
            ],
            'signature_authorizations.manage' => [
                'مجوزهای امضا',
                'مدیریت حدود اختیار و مجوزهای امضای سازمانی.',
            ],
            'signatures.manage' => [
                'امضاها',
                'ثبت، ویرایش و مدیریت امضاهای سازمانی.',
            ],
            'signatures.view' => [
                'امضاها',
                'مشاهده امضاهای ثبت‌شده و وضعیت آن‌ها.',
            ],
            'notifications.deliveries.view' => [
                'گزارش تحویل اعلان',
                'مشاهده وضعیت و جزئیات تحویل اعلان‌ها.',
            ],
            'notifications.manage' => [
                'اعلان‌ها',
                'مدیریت زیرساخت و تنظیمات عمومی اعلان‌ها.',
            ],
            'notifications.view' => [
                'اعلان‌ها',
                'مشاهده اعلان‌های شخصی کاربر.',
            ],
            'notifications.templates.manage' => [
                'قالب‌های اعلان',
                'مدیریت قالب‌های محتوایی اعلان‌ها.',
            ],
        ];

        $statement = $this->db->prepare("
            UPDATE permissions
            SET display_group = ?,
                description = CASE
                    WHEN description IS NULL OR description = ''
                        THEN ?
                    ELSE description
                END,
                updated_at = CURRENT_TIMESTAMP
            WHERE code = ?
        ");

        foreach ($items as $code => $definition) {
            $statement->execute([
                $definition[0],
                $definition[1],
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

cat > /tmp/ipkf-register-access-catalog-metadata.pl <<'PERL'
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
    . "CompleteAccessControlPersianLocalization(),\n";

my $addition =
    "        new \\IPKF\\Database\\Migrations\\"
    . "FinalizeAccessControlCatalogMetadata(),\n";

index($content, $addition) < 0
    or die "Catalog metadata migration is already registered.\n";

index($content, $needle) >= 0
    or die "Migration registration anchor was not found.\n";

$content =~ s/\Q$needle\E/$needle$addition/;

open my $out, '>:raw', $file
    or die "Cannot write $file: $!";

print {$out} $content;
close $out;
PERL

perl /tmp/ipkf-register-access-catalog-metadata.pl "$MIGRATE_FILE"
rm -f /tmp/ipkf-register-access-catalog-metadata.pl

echo "UPDATED: $MIGRATE_FILE"

echo
echo "=== Add Static Regression Test ==="

cat > "$TEST_FILE" <<'PHP'
<?php

$root = dirname(__DIR__);
$migrationPath = $root
    . '/public_html/system/Database/Migrations/'
    . 'FinalizeAccessControlCatalogMetadata.php';

$migration = file_get_contents($migrationPath);
$migrate = file_get_contents(
    $root . '/public_html/public/migrate.php'
);

if (!is_string($migration) || !is_string($migrate)) {
    fwrite(STDERR, "Access-control catalog metadata sources are missing.\n");
    exit(1);
}

$expected = [
    'automation.correspondence.cartable.view' =>
        'مکاتبات اداری',
    'automation.correspondence.close' =>
        'مکاتبات اداری',
    'automation.correspondence.create' =>
        'مکاتبات اداری',
    'automation.correspondence.edit_draft' =>
        'مکاتبات اداری',
    'automation.correspondence.register' =>
        'مکاتبات اداری',
    'automation.correspondence.route' =>
        'مکاتبات اداری',
    'automation.correspondence.view' =>
        'مکاتبات اداری',
    'automation.registry.manage' =>
        'دفاتر ثبت مکاتبات',
    'messages.admin.manage' =>
        'پیام‌رسان داخلی',
    'messages.admin.view' =>
        'پیام‌رسان داخلی',
    'messages.reply' =>
        'پیام‌رسان داخلی',
    'messages.send' =>
        'پیام‌رسان داخلی',
    'messages.view' =>
        'پیام‌رسان داخلی',
    'notifications.send.manage' =>
        'ارسال اعلان',
    'notifications.preferences.self' =>
        'ترجیحات اعلان',
    'notifications.providers.manage' =>
        'سرویس‌دهندگان اعلان',
    'notifications.reports.view' =>
        'گزارش‌های اعلان',
    'appointments.assign' =>
        'انتصاب‌ها',
    'appointments.manage' =>
        'انتصاب‌ها',
    'organizational_context.switch' =>
        'جایگاه سازمانی فعال',
    'signature_authorizations.manage' =>
        'مجوزهای امضا',
    'signatures.manage' =>
        'امضاها',
    'signatures.view' =>
        'امضاها',
    'notifications.deliveries.view' =>
        'گزارش تحویل اعلان',
    'notifications.manage' =>
        'اعلان‌ها',
    'notifications.view' =>
        'اعلان‌ها',
    'notifications.templates.manage' =>
        'قالب‌های اعلان',
];

if (count($expected) !== 27) {
    fwrite(STDERR, "Expected permission count is not 27.\n");
    exit(1);
}

foreach ($expected as $code => $group) {
    if (!str_contains($migration, "'{$code}'")) {
        fwrite(STDERR, "Missing permission code: {$code}\n");
        exit(1);
    }

    if (!str_contains($migration, "'{$group}'")) {
        fwrite(STDERR, "Missing display group: {$group}\n");
        exit(1);
    }
}

foreach ([
    'SET display_group = ?',
    "WHEN description IS NULL OR description = ''",
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
        . 'FinalizeAccessControlCatalogMetadata()'
    )
) {
    fwrite(STDERR, "Catalog metadata migration is not registered.\n");
    exit(1);
}

echo "Access control catalog metadata checks passed.\n";
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
echo "=== Catalog Metadata Markers ==="

grep -Fn \
    "FinalizeAccessControlCatalogMetadata" \
    "$MIGRATION_FILE" \
    "$MIGRATE_FILE" \
    "$TEST_FILE"

grep -Fn \
    "automation.correspondence.cartable.view" \
    "$MIGRATION_FILE"

grep -Fn \
    "notifications.templates.manage" \
    "$MIGRATION_FILE"

grep -Fn \
    "جایگاه سازمانی فعال" \
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
echo "ACCESS CONTROL CATALOG METADATA ADDED AND STAGED"
echo "No commit was created."

trap - EXIT
