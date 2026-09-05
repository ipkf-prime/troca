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

$errors =
    is_array($errors ?? null)
        ? $errors
        : [];

$old =
    is_array($old ?? null)
        ? $old
        : [];

$createdInvitation =
    is_array($createdInvitation ?? null)
        ? $createdInvitation
        : null;

ob_start();
?>

<style>
.user-invite-page {
    display:grid;
    gap:.85rem;
}

.user-invite-card {
    background:var(--admin-surface);
    border:1px solid var(--admin-border);
    border-radius:.9rem;
    padding:1rem;
}

.user-invite-card__head {
    margin-bottom:.9rem;
}

.user-invite-card__head h2 {
    font-size:1rem;
    margin:0;
}

.user-invite-card__head p {
    color:var(--admin-text-muted);
    font-size:.72rem;
    line-height:1.8;
    margin:.2rem 0 0;
}

.user-invite-grid {
    display:grid;
    gap:.7rem;
    grid-template-columns:
        repeat(2,minmax(0,1fr));
}

.user-invite-field {
    display:grid;
    gap:.3rem;
}

.user-invite-field--wide {
    grid-column:1/-1;
}

.user-invite-field span {
    font-size:.72rem;
    font-weight:700;
}

.user-invite-field small {
    color:var(--admin-text-muted);
    font-size:.63rem;
}

.user-invite-field input,
.user-invite-field select {
    width:100%;
}

.user-invite-result {
    background:var(--admin-surface-muted);
    border:1px solid var(--admin-primary);
    border-radius:.8rem;
    display:grid;
    gap:.65rem;
    padding:.8rem;
}

.user-invite-link {
    align-items:center;
    display:flex;
    gap:.45rem;
}

.user-invite-link input {
    direction:ltr;
    flex:1;
    font-family:
        ui-monospace,
        SFMono-Regular,
        Consolas,
        monospace;
}

.user-invite-actions {
    display:flex;
    flex-wrap:wrap;
    gap:.45rem;
    justify-content:flex-end;
    margin-top:.8rem;
}

@media(max-width:760px) {
    .user-invite-grid {
        grid-template-columns:1fr;
    }

    .user-invite-link {
        align-items:stretch;
        display:grid;
    }
}
</style>

<div class="user-invite-page">

    <nav class="admin-breadcrumb">
        <a href="/admin/dashboard">
            داشبورد
        </a>
        <span>/</span>
        <a href="/admin/users">
            کاربران
        </a>
        <span>/</span>
        <span>
            دعوت کاربر
        </span>
    </nav>

    <section class="admin-module-hub admin-module-hub--blue">
        <div class="admin-module-hub__icon">
            <?= \App\Support\AdminIcon::html('users') ?>
        </div>

        <div>
            <h2>دعوت کاربر</h2>
            <p>
                ایجاد لینک اختصاصی و یک‌بارمصرف برای ثبت‌نام کاربر
            </p>
        </div>

        <a
            class="admin-module-hub__back"
            href="/admin/users"
        >
            بازگشت به کاربران
        </a>
    </section>

    <?php if (isset($errors['general'])): ?>
        <div class="admin-alert">
            <?= admin_h($errors['general']) ?>
        </div>
    <?php endif; ?>

    <?php if ($createdInvitation !== null): ?>
        <section class="user-invite-result">
            <div>
                <strong>
                    لینک دعوت با موفقیت ساخته شد
                </strong>

                <p class="admin-muted">
                    این لینک را فقط برای شخص موردنظر ارسال کنید.
                    لینک تا زمان انقضا یا استفاده معتبر است.
                </p>
            </div>

            <div class="user-invite-link">
                <input
                    id="created-invitation-url"
                    value="<?= admin_h(
                        $createdInvitation['url']
                        ?? ''
                    ) ?>"
                    readonly
                >

                <button
                    class="admin-button admin-button--soft"
                    type="button"
                    data-copy-invitation
                >
                    کپی لینک
                </button>
            </div>

            <small class="admin-muted">
                انقضا:
                <?= admin_h(
                    $createdInvitation[
                        'expires_at'
                    ] ?? ''
                ) ?>
            </small>
        </section>
    <?php endif; ?>

    <section class="user-invite-card">

        <header class="user-invite-card__head">
            <h2>مشخصات دعوت</h2>
            <p>
                موبایل اجباری است.
                نقش کاربر از این فرم قابل انتخاب نیست و حساب پس از ثبت‌نام
                فقط نقش پایه «کاربر» خواهد داشت.
            </p>
        </header>

        <form
            method="post"
            action="/admin/users/invite"
        >
            <input
                type="hidden"
                name="_token"
                value="<?= admin_h(
                    (
                        new \IPKF\Security\Csrf()
                    )->token()
                ) ?>"
            >

            <div class="user-invite-grid">

                <label class="user-invite-field">
                    <span>
                        نام و نام خانوادگی
                    </span>

                    <input
                        name="full_name"
                        maxlength="150"
                        value="<?= admin_h(
                            $old['full_name']
                            ?? ''
                        ) ?>"
                    >

                    <small>
                        اختیاری؛ در صفحه ثبت‌نام قابل تکمیل است.
                    </small>

                    <?php if (isset(
                        $errors['full_name']
                    )): ?>
                        <small class="admin-error">
                            <?= admin_h(
                                $errors['full_name']
                            ) ?>
                        </small>
                    <?php endif; ?>
                </label>

                <label class="user-invite-field">
                    <span>
                        شماره موبایل
                    </span>

                    <input
                        name="mobile"
                        maxlength="15"
                        inputmode="tel"
                        dir="ltr"
                        placeholder="09123456789"
                        value="<?= admin_h(
                            $old['mobile']
                            ?? ''
                        ) ?>"
                        required
                    >

                    <?php if (isset(
                        $errors['mobile']
                    )): ?>
                        <small class="admin-error">
                            <?= admin_h(
                                $errors['mobile']
                            ) ?>
                        </small>
                    <?php endif; ?>
                </label>

                <label class="user-invite-field">
                    <span>
                        ایمیل
                    </span>

                    <input
                        type="email"
                        name="email"
                        maxlength="150"
                        dir="ltr"
                        value="<?= admin_h(
                            $old['email']
                            ?? ''
                        ) ?>"
                    >

                    <small>
                        اختیاری
                    </small>

                    <?php if (isset(
                        $errors['email']
                    )): ?>
                        <small class="admin-error">
                            <?= admin_h(
                                $errors['email']
                            ) ?>
                        </small>
                    <?php endif; ?>
                </label>

                <label class="user-invite-field">
                    <span>
                        اعتبار لینک
                    </span>

                    <select name="expires_days">
                        <?php foreach (
                            [
                                1 => '۱ روز',
                                3 => '۳ روز',
                                7 => '۷ روز',
                                14 => '۱۴ روز',
                                30 => '۳۰ روز',
                            ]
                            as $days => $label
                        ): ?>
                            <option
                                value="<?= $days ?>"
                                <?= (int) (
                                    $old[
                                        'expires_days'
                                    ] ?? 7
                                ) === $days
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= admin_h($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

            </div>

            <div class="user-invite-actions">
                <a
                    class="admin-button admin-button--soft"
                    href="/admin/users"
                >
                    انصراف
                </a>

                <button
                    class="admin-button"
                    type="submit"
                >
                    ساخت لینک دعوت
                </button>
            </div>
        </form>
    </section>
</div>

<script>
(() => {
    const button =
        document.querySelector(
            '[data-copy-invitation]'
        );

    const field =
        document.getElementById(
            'created-invitation-url'
        );

    if (!button || !field) {
        return;
    }

    button.addEventListener(
        'click',
        async () => {
            const value =
                field.value || '';

            if (!value) {
                return;
            }

            try {
                await navigator.clipboard
                    .writeText(value);

                button.textContent =
                    'کپی شد';
            } catch (_) {
                field.select();
                document.execCommand(
                    'copy'
                );

                button.textContent =
                    'کپی شد';
            }
        }
    );
})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
