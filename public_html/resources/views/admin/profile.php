<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
            false
        );
    }
}

$user = $context['user'] ?? [];
$userId = (int) ($context['user_id'] ?? 0);
$activeAssignment = $context['active_assignment'] ?? [];
$mfa = $context['mfa'] ?? [];
$status = trim((string) ($_GET['status'] ?? ''));
$avatarService = new \App\Services\ProfileAvatarService();
$avatarUrl = $avatarService->urlForUser($userId);
$avatarMessage = $avatarService->statusMessage($status);

$statusLabel = match ((string) ($user['status'] ?? '')) {
    'active' => 'فعال',
    'inactive' => 'غیرفعال',
    'suspended' => 'تعلیق‌شده',
    default => 'نامشخص',
};

ob_start();
?>
<style>
.profile-avatar-card {
    align-items: center;
    display: grid;
    gap: 1rem;
    grid-template-columns: auto minmax(0, 1fr);
}

.profile-avatar-preview {
    align-items: center;
    background: var(--admin-primary-soft);
    border: 1px solid var(--admin-border);
    border-radius: 50%;
    display: flex;
    height: 104px;
    justify-content: center;
    overflow: hidden;
    width: 104px;
}

.profile-avatar-preview img {
    height: 100%;
    object-fit: cover;
    width: 100%;
}

.profile-avatar-preview .admin-icon {
    height: 42px;
    width: 42px;
}

.profile-avatar-tools {
    display: grid;
    gap: .65rem;
}

.profile-avatar-upload {
    align-items: end;
    display: grid;
    gap: .55rem;
    grid-template-columns: minmax(220px, 1fr) auto;
    max-width: 720px;
}

.profile-avatar-upload label {
    display: grid;
    gap: .28rem;
}

.profile-avatar-upload label span {
    color: var(--admin-text-muted);
    font-size: .76rem;
    font-weight: 700;
}

.profile-avatar-upload input[type="file"] {
    border: 1px solid var(--admin-border);
    border-radius: 9px;
    min-height: 42px;
    padding: .42rem;
    width: 100%;
}

.profile-avatar-note {
    color: var(--admin-text-muted);
    font-size: .72rem;
    margin: 0;
}

@media (max-width: 680px) {
    .profile-avatar-card {
        grid-template-columns: 1fr;
    }

    .profile-avatar-preview {
        height: 88px;
        width: 88px;
    }

    .profile-avatar-upload {
        align-items: stretch;
        grid-template-columns: 1fr;
    }
}
</style>

<div class="account-shell">
    <?php require __DIR__ . '/partials/account-nav.php'; ?>

    <?php if (is_array($avatarMessage)): ?>
        <div class="account-notice account-notice--<?= admin_h(
            $avatarMessage['type'] ?? 'info'
        ) ?>">
            <?= admin_h($avatarMessage['text'] ?? '') ?>
        </div>
    <?php endif; ?>

    <section class="account-card profile-avatar-card">
        <div class="profile-avatar-preview" data-avatar-preview>
            <?php if ($avatarUrl !== ''): ?>
                <img
                    src="<?= admin_h($avatarUrl) ?>"
                    alt="تصویر پروفایل"
                    data-avatar-image
                >
            <?php else: ?>
                <?= \App\Support\AdminIcon::html('user') ?>
            <?php endif; ?>
        </div>

        <div class="profile-avatar-tools">
            <div class="account-card__head">
                <div>
                    <h2>تصویر پروفایل</h2>
                    <p>
                        تصویر در هدر سامانه و منوی کاربری نمایش داده می‌شود.
                    </p>
                </div>
            </div>

            <form
                class="profile-avatar-upload"
                method="post"
                action="/admin/profile/avatar"
                enctype="multipart/form-data"
            >
                <input
                    type="hidden"
                    name="_token"
                    value="<?= admin_h(
                        (new \IPKF\Security\Csrf())->token()
                    ) ?>"
                >
                <input
                    type="hidden"
                    name="MAX_FILE_SIZE"
                    value="2097152"
                >
                <label>
                    <span>انتخاب تصویر</span>
                    <input
                        type="file"
                        name="avatar"
                        accept="image/jpeg,image/png,image/webp"
                        required
                        data-avatar-input
                    >
                </label>
                <button class="admin-button" type="submit">
                    ذخیره تصویر
                </button>
            </form>

            <p class="profile-avatar-note">
                JPEG، PNG یا WebP؛ حداکثر ۲ مگابایت و حداقل ۶۴×۶۴ پیکسل.
            </p>

            <?php if ($avatarUrl !== ''): ?>
                <form
                    method="post"
                    action="/admin/profile/avatar/remove"
                    onsubmit="return confirm('تصویر پروفایل حذف شود؟')"
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= admin_h(
                            (new \IPKF\Security\Csrf())->token()
                        ) ?>"
                    >
                    <button
                        class="admin-button admin-button--soft"
                        type="submit"
                    >
                        حذف تصویر
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <section class="account-card">
        <div class="account-card__head">
            <div>
                <h2>نمای کلی حساب</h2>
                <p>
                    اطلاعات اصلی، نقش جاری و وضعیت امنیتی حساب شما
                </p>
            </div>
            <span class="account-badge account-badge--success">
                <?= admin_h($statusLabel) ?>
            </span>
        </div>

        <div class="account-summary">
            <article class="account-stat">
                <span>نام نمایشی</span>
                <strong><?= admin_h($user['name'] ?? '—') ?></strong>
            </article>

            <article class="account-stat">
                <span>نام کاربری</span>
                <strong dir="ltr">
                    <?= admin_h($user['username'] ?? '—') ?>
                </strong>
            </article>

            <article class="account-stat">
                <span>نقش فعال</span>
                <strong>
                    <?= admin_h($activeAssignment['role_title'] ?? '—') ?>
                </strong>
            </article>
        </div>
    </section>

    <section class="account-card">
        <div class="account-card__head">
            <div>
                <h3>وضعیت حساب</h3>
                <p>اطلاعات تماس و کنترل‌های مهم امنیتی</p>
            </div>
        </div>

        <div class="account-list">
            <div class="account-list__row">
                <span>ایمیل</span>
                <strong dir="ltr">
                    <?= admin_h($user['email'] ?? '—') ?>
                </strong>
                <span class="account-badge">شناسه ورود</span>
            </div>

            <div class="account-list__row">
                <span>شماره موبایل</span>
                <strong dir="ltr">
                    <?= admin_h($user['mobile'] ?? '—') ?>
                </strong>
                <span class="account-badge">تماس</span>
            </div>

            <div class="account-list__row">
                <span>تأیید دومرحله‌ای</span>
                <strong>
                    <?= ($mfa['enabled'] ?? false)
                        ? 'فعال'
                        : 'غیرفعال' ?>
                </strong>
                <span class="account-badge <?= ($mfa['enabled'] ?? false)
                    ? 'account-badge--success'
                    : 'account-badge--danger' ?>">
                    <?= ($mfa['enabled'] ?? false)
                        ? 'محافظت‌شده'
                        : 'نیازمند اقدام' ?>
                </span>
            </div>
        </div>

        <div class="account-actions" style="margin-top:.7rem">
            <a class="admin-button" href="/admin/profile/edit">
                ویرایش هویت و نشانی
            </a>
            <a class="admin-button admin-button--soft" href="/admin/security">
                تنظیمات امنیتی
            </a>
            <a class="admin-button admin-button--soft" href="/admin/profile/access">
                نقش‌ها و دسترسی‌ها
            </a>
            <a class="admin-button admin-button--soft" href="/admin/my-theme">
                ظاهر پنل
            </a>
        </div>
    </section>
</div>

<script>
(() => {
    const input = document.querySelector(
        '[data-avatar-input]'
    );
    const preview = document.querySelector(
        '[data-avatar-preview]'
    );

    if (!input || !preview) {
        return;
    }

    input.addEventListener('change', () => {
        const file = input.files?.[0];

        if (!file || !file.type.startsWith('image/')) {
            return;
        }

        const url = URL.createObjectURL(file);
        const image = document.createElement('img');
        image.src = url;
        image.alt = 'پیش‌نمایش تصویر پروفایل';
        image.onload = () => URL.revokeObjectURL(url);
        preview.replaceChildren(image);
    });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
