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

$status = $status ?? '';
$errors = $errors ?? [];

ob_start();
?>
<div class="account-shell">
    <?php require __DIR__ . '/partials/account-nav.php'; ?>

    <?php if ($status === 'saved'): ?>
        <div class="account-notice account-notice--success">
            ظاهر شخصی پنل ذخیره شد.
        </div>
    <?php elseif ($status === 'reset'): ?>
        <div class="account-notice account-notice--success">
            تنظیمات شخصی حذف شد و پوسته عمومی سامانه فعال است.
        </div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="account-notice account-notice--danger">
            تنظیمات انتخاب‌شده معتبر نیست.
        </div>
    <?php endif; ?>

    <form
        method="post"
        action="/admin/my-theme"
        class="account-card"
    >
        <input
            type="hidden"
            name="_token"
            value="<?= admin_h(
                (new \IPKF\Security\Csrf())->token()
            ) ?>"
        >

        <div class="account-card__head">
            <div>
                <h2>ظاهر پنل</h2>
                <p>
                    انتخاب شما فقط برای همین حساب ذخیره می‌شود.
                </p>
            </div>
            <span class="account-badge account-badge--success">
                <?= admin_h($theme['preset_title'] ?? 'پوسته سامانه') ?>
            </span>
        </div>

        <div class="theme-compact-grid">
            <?php foreach ($presets as $key => $preset): ?>
                <?php
                $tokens = $preset['tokens'];
                $active = ($theme['active_preset'] ?? '') === $key;
                ?>
                <label class="theme-choice<?= $active
                    ? ' is-active'
                    : '' ?>">
                    <input
                        type="radio"
                        name="active_preset"
                        value="<?= admin_h($key) ?>"
                        <?= $active ? ' checked' : '' ?>
                    >
                    <span class="theme-choice__preview">
                        <i style="background:linear-gradient(
                            160deg,
                            <?= admin_h($tokens['sidebar_bg']) ?>,
                            <?= admin_h($tokens['sidebar_bg_2']) ?>
                        )"></i>
                        <b style="background:<?= admin_h(
                            $tokens['surface']
                        ) ?>"></b>
                    </span>
                    <strong><?= admin_h($preset['title']) ?></strong>
                    <small>
                        <?= admin_h($preset['description']) ?>
                    </small>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="account-actions" style="margin-top:.75rem">
            <button type="submit">
                ذخیره ظاهر من
            </button>
        </div>
    </form>

    <form
        method="post"
        action="/admin/theme/reset"
        class="account-card"
    >
        <input
            type="hidden"
            name="_token"
            value="<?= admin_h(
                (new \IPKF\Security\Csrf())->token()
            ) ?>"
        >
        <input type="hidden" name="scope" value="user">

        <div class="account-card__head">
            <div>
                <h3>بازگشت به ظاهر عمومی سامانه</h3>
                <p>
                    تنظیمات شخصی حذف می‌شود و ظاهر تعیین‌شده توسط مدیر
                    سامانه استفاده خواهد شد.
                </p>
            </div>
            <button
                type="submit"
                class="admin-button admin-button--soft"
            >
                بازنشانی
            </button>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
