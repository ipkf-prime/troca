#!/usr/bin/env bash
set -Eeuo pipefail

REPO="${1:-}"
EXPECTED_BRANCH="v0.6.1-notification-provider-management-dev"
EXPECTED_HEAD="d254cdc"

if [[ -z "$REPO" ]]; then
    echo "Usage: bash apply-access-control-persian-localization-v061-r2.sh /path/to/repo"
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

MIGRATION_FILE="public_html/system/Database/Migrations/CompleteAccessControlPersianLocalization.php"
MIGRATE_FILE="public_html/public/migrate.php"
VIEW_FILE="public_html/resources/views/admin/access-control.php"
TEST_FILE="tests/AccessControlPersianLocalizationTest.php"
TOOL_FILE="tools/apply-access-control-persian-localization-v061-r2.sh"

cleanup() {
    local status=$?
    if [[ $status -ne 0 ]]; then
        echo
        echo "PATCH FAILED; RESTORING CLEAN TREE"
        git restore --staged --worktree --             "$MIGRATE_FILE"             "$VIEW_FILE"             "$MIGRATION_FILE"             "$TEST_FILE"             "$TOOL_FILE"             2>/dev/null || true
        rm -f -- "$MIGRATION_FILE" "$TEST_FILE" "$TOOL_FILE"
        git restore --staged --             "$MIGRATION_FILE"             "$TEST_FILE"             "$TOOL_FILE"             2>/dev/null || true
    fi
    exit $status
}
trap cleanup EXIT

echo
echo "=== Create Persian Permission Catalog Migration ==="

cat > "$MIGRATION_FILE" <<'PHP'
<?php

namespace IPKF\Database\Migrations;

class CompleteAccessControlPersianLocalization extends Migration
{
    public function up(): void
    {
        $this->localizePermissionCatalog();
        $this->completeDisplayGroups();
    }

    public function down(): void
    {
    }

    private function localizePermissionCatalog(): void
    {
        $items = [
            'account.profile.view' => [
                'مشاهده پروفایل حساب کاربری',
                'دسترسی به صفحه پروفایل و اطلاعات شخصی حساب.',
                'پروفایل',
            ],
            'account.security.view' => [
                'مشاهده امنیت حساب کاربری',
                'مشاهده تنظیمات امنیتی و نشست‌های حساب.',
                'امنیت حساب',
            ],
            'account.password.change' => [
                'تغییر گذرواژه حساب کاربری',
                'تغییر گذرواژه حساب کاربری جاری.',
                'امنیت حساب',
            ],
            'account.theme.manage' => [
                'مدیریت ظاهر شخصی پنل',
                'انتخاب و تنظیم پوسته و ظاهر شخصی پنل مدیریت.',
                'ظاهر پنل',
            ],
            'admin.theme.manage' => [
                'مدیریت ظاهر عمومی پنل',
                'مدیریت پوسته و تنظیمات ظاهری عمومی پنل مدیریت.',
                'ظاهر پنل',
            ],
            'admin.dashboard.view' => [
                'مشاهده داشبورد مدیریت',
                'دسترسی به داشبورد اصلی پنل مدیریت.',
                'داشبورد',
            ],
            'access.manage' => [
                'مدیریت نقش فعال و دسترسی‌ها',
                'مدیریت نقش فعال و دسترسی‌های مدیریتی حساب.',
                'دسترسی',
            ],
            'admin.settings.manage' => [
                'مدیریت تنظیمات سامانه',
                'تغییر تنظیمات مدیریتی سامانه.',
                'تنظیمات',
            ],
            'admin.pages.manage' => [
                'مدیریت صفحات پنل',
                'مدیریت صفحات و بخش‌های پنل مدیریت.',
                'صفحات پنل',
            ],
            'admin.reports.view' => [
                'مشاهده گزارش‌های مدیریتی',
                'دسترسی به گزارش‌های مدیریتی سامانه.',
                'گزارش‌ها',
            ],
            'admin.navigation.debug' => [
                'عیب‌یابی منوهای مدیریتی',
                'مشاهده اطلاعات فنی منوهای پویا برای عیب‌یابی.',
                'عیب‌یابی',
            ],
            'admin.route.debug' => [
                'عیب‌یابی مسیرهای مدیریتی',
                'مشاهده اطلاعات فنی مسیرها و قواعد دسترسی برای عیب‌یابی.',
                'عیب‌یابی',
            ],
            'roles.view' => [
                'مشاهده نقش‌ها',
                'مشاهده فهرست نقش‌های سامانه.',
                'نقش‌ها',
            ],
            'roles.create' => [
                'ایجاد نقش',
                'ایجاد نقش جدید در سامانه.',
                'نقش‌ها',
            ],
            'roles.update' => [
                'ویرایش نقش‌ها',
                'ویرایش مشخصات نقش‌های سامانه.',
                'نقش‌ها',
            ],
            'roles.delete' => [
                'حذف نقش‌ها',
                'حذف نقش‌های مجاز سامانه.',
                'نقش‌ها',
            ],
            'permissions.view' => [
                'مشاهده مجوزها',
                'مشاهده کاتالوگ مجوزهای سامانه.',
                'مجوزها',
            ],
            'permissions.assign' => [
                'تخصیص مجوزها',
                'تخصیص یا لغو مجوزهای نقش‌ها.',
                'مجوزها',
            ],
            'auth.login_token.issue' => [
                'صدور توکن ورود',
                'صدور توکن ورود یک‌بارمصرف یا موقت.',
                'ورود و نشست',
            ],
            'org_units.view' => [
                'مشاهده واحدهای سازمانی',
                'مشاهده ساختار و واحدهای سازمانی.',
                'واحدهای سازمانی',
            ],
            'positions.view' => [
                'مشاهده سمت‌های سازمانی',
                'مشاهده سمت‌ها و جایگاه‌های سازمانی.',
                'سمت‌ها',
            ],
            'user_org_assignments.manage' => [
                'مدیریت انتساب کاربران به سازمان',
                'مدیریت عضویت و انتساب کاربران به واحدهای سازمانی.',
                'انتساب کاربران',
            ],
            'organizations.view' => [
                'مشاهده سازمان‌ها',
                'مشاهده فهرست و اطلاعات سازمان‌ها.',
                'سازمان‌ها',
            ],
            'organizations.update' => [
                'ویرایش سازمان‌ها',
                'ویرایش مشخصات سازمان‌ها.',
                'سازمان‌ها',
            ],
            'support.view' => [
                'مشاهده بخش پشتیبانی',
                'دسترسی به امکانات و اطلاعات بخش پشتیبانی.',
                'پشتیبانی',
            ],
            'system.diagnostics.view' => [
                'مشاهده وضعیت تشخیصی سامانه',
                'مشاهده گزارش‌های سلامت و اطلاعات تشخیصی سامانه.',
                'پایش و عیب‌یابی',
            ],
            'system.installer.view' => [
                'مشاهده نصب‌کننده سامانه',
                'دسترسی به وضعیت و ابزار نصب سامانه.',
                'نصب و راه‌اندازی',
            ],
            'users.view' => [
                'مشاهده کاربران',
                'مشاهده فهرست و اطلاعات کاربران.',
                'کاربران',
            ],
            'users.create' => [
                'ایجاد کاربر',
                'ایجاد حساب کاربری جدید.',
                'کاربران',
            ],
            'users.update' => [
                'ویرایش کاربران',
                'ویرایش اطلاعات حساب‌های کاربری.',
                'کاربران',
            ],
            'users.delete' => [
                'حذف کاربران',
                'حذف یا غیرفعال‌سازی کاربران مجاز.',
                'کاربران',
            ],
            'users.manage' => [
                'مدیریت کاربران',
                'مدیریت کامل حساب‌های کاربری.',
                'کاربران',
            ],
            'work.project.view' => [
                'مشاهده پروژه‌ها و کارها',
                'مشاهده پروژه‌ها و کارهای ثبت‌شده.',
                'پروژه‌ها',
            ],
            'work.project.manage' => [
                'مدیریت پروژه‌ها و کارها',
                'ایجاد و ویرایش پروژه‌ها و کارهای زیرمجموعه.',
                'پروژه‌ها',
            ],
            'work.audit.view' => [
                'مشاهده تاریخچه تغییرات مدیریت کار',
                'مشاهده رویدادها و تغییرات ماژول مدیریت کار.',
                'ممیزی و تاریخچه',
            ],
            'work.settings.manage' => [
                'مدیریت تعاریف و تنظیمات مدیریت کار',
                'مدیریت تنظیمات و داده‌های پایه ماژول مدیریت کار.',
                'تنظیمات',
            ],
            'work.project.admin' => [
                'دسترسی مدیریتی سراسری به پروژه‌ها',
                'مدیریت همه پروژه‌ها و کارها بدون محدودیت مالکیت.',
                'پروژه‌ها',
            ],
        ];

        $statement = $this->db->prepare("
            UPDATE permissions
            SET title = ?,
                description = ?,
                display_group = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE code = ?
        ");

        foreach ($items as $code => $definition) {
            $statement->execute([
                $definition[0],
                $definition[1],
                $definition[2],
                $code,
            ]);
        }
    }

    private function completeDisplayGroups(): void
    {
        $groups = [
            'profile' => 'پروفایل',
            'security' => 'امنیت حساب',
            'password' => 'امنیت حساب',
            'theme' => 'ظاهر پنل',
            'dashboard' => 'داشبورد',
            'access' => 'دسترسی',
            'settings' => 'تنظیمات',
            'pages' => 'صفحات پنل',
            'reports' => 'گزارش‌ها',
            'navigation' => 'منوها',
            'routes' => 'مسیرها',
            'roles' => 'نقش‌ها',
            'permissions' => 'مجوزها',
            'login_tokens' => 'ورود و نشست',
            'org_units' => 'واحدهای سازمانی',
            'positions' => 'سمت‌ها',
            'user_org_assignments' => 'انتساب کاربران',
            'organizations' => 'سازمان‌ها',
            'support' => 'پشتیبانی',
            'diagnostics' => 'پایش و عیب‌یابی',
            'installer' => 'نصب و راه‌اندازی',
            'users' => 'کاربران',
            'audit' => 'ممیزی و تاریخچه',
            'item' => 'اقلام کار',
            'project' => 'پروژه‌ها',
            'providers' => 'سرویس‌دهندگان',
            'routing' => 'مسیریابی',
            'preferences' => 'ترجیحات',
            'send' => 'ارسال اعلان',
            'notification_send' => 'ارسال اعلان',
            'notification_recipients' => 'گیرندگان اعلان',
            'notification_manual_targets' => 'مقصدهای دستی',
            'notification_approval' => 'تأیید اعلان',
            'messages' => 'پیام‌ها',
            'general' => 'عمومی',
        ];

        $statement = $this->db->prepare("
            UPDATE permissions
            SET display_group = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE resource = ?
              AND (
                    display_group IS NULL
                    OR display_group = ''
                    OR display_group = resource
              )
        ");

        foreach ($groups as $resource => $title) {
            $statement->execute([$title, $resource]);
        }
    }
}
PHP

echo "CREATED: $MIGRATION_FILE"

echo
echo "=== Register Migration ==="

cat > /tmp/ipkf-register-persian-access-migration.pl <<'PERL'
use strict;
use warnings;

local $/;
my $file = shift @ARGV;
open my $in, '<:raw', $file or die "Cannot read $file: $!";
my $content = <$in>;
close $in;

my $needle =
    "        new \\IPKF\\Database\\Migrations\\RefineAccessControlExperience(),\n";
my $addition =
    "        new \\IPKF\\Database\\Migrations\\CompleteAccessControlPersianLocalization(),\n";

index($content, $addition) < 0
    or die "Persian localization migration is already registered.\n";
index($content, $needle) >= 0
    or die "Migration registration anchor was not found.\n";

$content =~ s/\Q$needle\E/$needle$addition/;

open my $out, '>:raw', $file or die "Cannot write $file: $!";
print {$out} $content;
close $out;
PERL

perl /tmp/ipkf-register-persian-access-migration.pl "$MIGRATE_FILE"
rm -f /tmp/ipkf-register-persian-access-migration.pl

echo "UPDATED: $MIGRATE_FILE"

echo
echo "=== Complete Persian UI Labels ==="

cat > /tmp/ipkf-localize-access-view.pl <<'PERL'
use strict;
use warnings;
use utf8;

local $/;
my $file = shift @ARGV;
open my $in, '<:encoding(UTF-8)', $file
    or die "Cannot read $file: $!";
my $content = <$in>;
close $in;

my $group_anchor = <<'ANCHOR';
    'general' => 'عمومی',
];
ANCHOR

my $group_replacement = <<'REPLACEMENT';
    'general' => 'عمومی',
    'profile' => 'پروفایل',
    'security' => 'امنیت حساب',
    'password' => 'امنیت حساب',
    'theme' => 'ظاهر پنل',
    'access' => 'دسترسی',
    'pages' => 'صفحات پنل',
    'navigation' => 'منوها',
    'routes' => 'مسیرها',
    'login_tokens' => 'ورود و نشست',
    'org_units' => 'واحدهای سازمانی',
    'positions' => 'سمت‌ها',
    'user_org_assignments' => 'انتساب کاربران',
    'organizations' => 'سازمان‌ها',
    'diagnostics' => 'پایش و عیب‌یابی',
    'installer' => 'نصب و راه‌اندازی',
    'notification_send' => 'ارسال اعلان',
    'notification_recipients' => 'گیرندگان اعلان',
    'notification_manual_targets' => 'مقصدهای دستی',
    'notification_approval' => 'تأیید اعلان',
];
REPLACEMENT

index($content, $group_anchor) >= 0
    or die "Group-title anchor was not found.\n";
$content =~ s/\Q$group_anchor\E/$group_replacement/;

my $label_anchor = <<'ANCHOR';
$groupLabel = static fn ($value): string =>
    $groupTitles[mb_strtolower(trim((string) $value), 'UTF-8')]
        ?? (string) $value;

$notices = [
ANCHOR

my $label_replacement = <<'REPLACEMENT';
$groupLabel = static fn ($value): string =>
    $groupTitles[mb_strtolower(trim((string) $value), 'UTF-8')]
        ?? (string) $value;

$scopeTitles = [
    'global' => 'سراسری',
    'organization' => 'سازمان',
    'org_unit' => 'واحد سازمانی',
    'province' => 'استان',
    'county' => 'شهرستان',
    'city' => 'شهر',
    'own' => 'فقط خود کاربر',
];

$scopeLabel = static fn ($value): string =>
    $scopeTitles[mb_strtolower(trim((string) $value), 'UTF-8')]
        ?? (string) $value;

$auditTargetTitles = [
    'role' => 'نقش',
    'user' => 'کاربر',
    'role_assignment' => 'انتساب نقش',
];

$auditTargetLabel = static fn ($value): string =>
    $auditTargetTitles[mb_strtolower(trim((string) $value), 'UTF-8')]
        ?? (string) $value;

$auditChangeTitles = [
    'role_permissions_updated' => 'تغییر مجوزهای نقش',
    'user_policy_updated' => 'تغییر سیاست دسترسی کاربر',
    'user_permission_overrides_updated' =>
        'تغییر استثناهای دسترسی کاربر',
];

$auditChangeLabel = static fn ($value): string =>
    $auditChangeTitles[mb_strtolower(trim((string) $value), 'UTF-8')]
        ?? (string) $value;

$notices = [
REPLACEMENT

index($content, $label_anchor) >= 0
    or die "Localization-label anchor was not found.\n";
$content =~ s/\Q$label_anchor\E/$label_replacement/;

$content =~ s/\$assignment\['scope_type'\]/\$scopeLabel(\$assignment['scope_type'])/g;
$content =~ s/\$row\['target_type'\]/\$auditTargetLabel(\$row['target_type'])/g;
$content =~ s/\$row\['change_type'\]/\$auditChangeLabel(\$row['change_type'])/g;

open my $out, '>:encoding(UTF-8)', $file
    or die "Cannot write $file: $!";
print {$out} $content;
close $out;
PERL

perl /tmp/ipkf-localize-access-view.pl "$VIEW_FILE"
rm -f /tmp/ipkf-localize-access-view.pl

echo "UPDATED: $VIEW_FILE"

echo
echo "=== Add Static Regression Test ==="

cat > "$TEST_FILE" <<'PHP'
<?php

$root = dirname(__DIR__);
$migration = file_get_contents(
    $root
    . '/public_html/system/Database/Migrations/'
    . 'CompleteAccessControlPersianLocalization.php'
);
$view = file_get_contents(
    $root . '/public_html/resources/views/admin/access-control.php'
);
$migrate = file_get_contents(
    $root . '/public_html/public/migrate.php'
);

if (!is_string($migration) || !is_string($view) || !is_string($migrate)) {
    fwrite(STDERR, "Access-control localization sources are missing.\n");
    exit(1);
}

$permissionCodes = [
    'account.profile.view',
    'account.security.view',
    'account.password.change',
    'account.theme.manage',
    'admin.theme.manage',
    'admin.dashboard.view',
    'access.manage',
    'admin.settings.manage',
    'admin.pages.manage',
    'admin.reports.view',
    'admin.navigation.debug',
    'admin.route.debug',
    'roles.view',
    'roles.create',
    'roles.update',
    'roles.delete',
    'permissions.view',
    'permissions.assign',
    'auth.login_token.issue',
    'org_units.view',
    'positions.view',
    'user_org_assignments.manage',
    'organizations.view',
    'organizations.update',
    'support.view',
    'system.diagnostics.view',
    'system.installer.view',
    'users.view',
    'users.create',
    'users.update',
    'users.delete',
    'users.manage',
    'work.project.view',
    'work.project.manage',
    'work.audit.view',
    'work.settings.manage',
    'work.project.admin',
];

foreach ($permissionCodes as $code) {
    if (!str_contains($migration, "'{$code}'")) {
        fwrite(STDERR, "Missing localized permission: {$code}\n");
        exit(1);
    }
}

$requiredPersianLabels = [
    'مشاهده سازمان‌ها',
    'ویرایش سازمان‌ها',
    'مشاهده تاریخچه تغییرات مدیریت کار',
    'مدیریت تعاریف و تنظیمات مدیریت کار',
    "'organizations' => 'سازمان‌ها'",
    "'org_units' => 'واحدهای سازمانی'",
    '$scopeLabel',
    '$auditTargetLabel',
    '$auditChangeLabel',
];

foreach ($requiredPersianLabels as $label) {
    if (
        !str_contains($migration, $label)
        && !str_contains($view, $label)
    ) {
        fwrite(STDERR, "Missing Persian localization marker: {$label}\n");
        exit(1);
    }
}

if (
    !str_contains(
        $migrate,
        'new \\IPKF\\Database\\Migrations\\'
        . 'CompleteAccessControlPersianLocalization()'
    )
) {
    fwrite(STDERR, "Persian localization migration is not registered.\n");
    exit(1);
}

foreach ([
    'View organizations',
    'Update organizations',
    'Workها',
    'تغییرات Work',
    'تنظیمات Work',
] as $forbidden) {
    if (str_contains($migration, $forbidden)) {
        fwrite(STDERR, "Legacy English label remains: {$forbidden}\n");
        exit(1);
    }
}

echo "Access control Persian localization checks passed.\n";
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
    "$VIEW_FILE" \
    "$TEST_FILE" \
    "$TOOL_FILE"

echo
echo "=== Cached Validation ==="

if command -v php >/dev/null 2>&1; then
    php -l "$MIGRATION_FILE"
    php -l "$MIGRATE_FILE"
    php -l "$VIEW_FILE"
    php "$TEST_FILE"
else
    echo "PHP_NOT_AVAILABLE_ON_WINDOWS=SKIPPED"
fi

echo
echo "=== Persian Localization Markers ==="

grep -Fn \
    "CompleteAccessControlPersianLocalization" \
    "$MIGRATION_FILE" \
    "$MIGRATE_FILE" \
    "$TEST_FILE"

grep -Fn "مشاهده سازمان‌ها" "$MIGRATION_FILE"
grep -Fn "مشاهده تاریخچه تغییرات مدیریت کار" "$MIGRATION_FILE"
grep -Fn '$scopeLabel' "$VIEW_FILE"
grep -Fn '$auditChangeLabel' "$VIEW_FILE"

echo
echo "=== Stage Patch ==="

git add -- \
    "$MIGRATION_FILE" \
    "$MIGRATE_FILE" \
    "$VIEW_FILE" \
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
echo "ACCESS CONTROL PERSIAN LOCALIZATION ADDED AND STAGED"
echo "No commit was created."

trap - EXIT
