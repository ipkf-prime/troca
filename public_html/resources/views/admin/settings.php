<?php $registry = $registry ?? ['available' => false, 'items' => []]; $status = (string) ($status ?? ''); ?>
<section class="admin-page-hero admin-page-hero--purple">
    <div><p class="admin-kicker">تنظیمات مرکزی</p><h2>رجیستری ماژول‌ها</h2><p>نام، نشانی اجرا، بازگشت SSO و اتصال دیتابیس ماژول‌ها را از پنل مرکزی مدیریت کنید.</p></div>
</section>

<?php if ($status === 'saved'): ?><div class="admin-alert admin-alert--success">تنظیمات ماژول ذخیره شد.</div><?php endif; ?>
<?php if ($status === 'invalid'): ?><div class="admin-alert admin-alert--danger"><?= admin_h((string) ($error ?? 'اطلاعات معتبر نیست.')) ?></div><?php endif; ?>
<?php if (!$registry['available']): ?><div class="admin-alert admin-alert--warning">جدول رجیستری موجود نیست؛ Migration را اجرا کنید.</div><?php endif; ?>

<section class="admin-card">
    <header><div><h3>تعریف یا ویرایش ماژول</h3><p>برای ویرایش، همان کلید ماژول را دوباره ذخیره کنید.</p></div></header>
    <form method="post" action="/admin/settings/modules" class="admin-form-grid">
        <label><span>نام نمایشی</span><input name="display_name" required placeholder="اتوماسیون اداری"></label>
        <label><span>کلید ماژول</span><input name="module_key" required dir="ltr" placeholder="automation"></label>
        <label><span>آدرس ماژول</span><input type="url" name="base_url" required dir="ltr" placeholder="https://oa-dev.troca.ir"></label>
        <label><span>آدرس Callback ورود یکپارچه</span><input type="url" name="sso_callback_url" dir="ltr" placeholder="https://oa-dev.troca.ir/auth/module-sso/callback"></label>
        <label><span>نام اتصال دیتابیس</span><input name="database_connection_name" dir="ltr" placeholder="automation"></label>
        <label><span>میزبان دیتابیس</span><input name="database_host" dir="ltr" placeholder="localhost"></label>
        <label><span>پورت دیتابیس</span><input type="number" name="database_port" value="3306" min="1" max="65535"></label>
        <label><span>نام دیتابیس</span><input name="database_name" dir="ltr" placeholder="troca_automation"></label>
        <label><span>Secret Reference</span><input name="secret_reference" dir="ltr" placeholder="AUTOMATION_DB_PASSWORD"></label>
        <label><span>ترتیب نمایش</span><input type="number" name="sort_order" value="10" min="0"></label>
        <label class="admin-checkbox"><input type="checkbox" name="is_active" value="1" checked><span>ماژول فعال است</span></label>
        <div><button class="admin-button admin-button--primary" type="submit" <?= !$registry['available'] ? 'disabled' : '' ?>>ذخیره ماژول</button></div>
    </form>
</section>

<section class="admin-card">
    <header><div><h3>ماژول‌های ثبت‌شده</h3><p>رمز دیتابیس در این بخش ذخیره یا نمایش داده نمی‌شود.</p></div></header>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>نام</th><th>کلید</th><th>آدرس</th><th>اتصال دیتابیس</th><th>وضعیت</th></tr></thead><tbody>
    <?php foreach ($registry['items'] as $item): ?><tr><td><?= admin_h($item['display_name']) ?></td><td dir="ltr"><?= admin_h($item['module_key']) ?></td><td dir="ltr"><?= admin_h($item['base_url']) ?></td><td dir="ltr"><?= admin_h(($item['database_connection_name'] ?? '') . ' · ' . ($item['database_host'] ?? '') . '/' . ($item['database_name'] ?? '')) ?></td><td><?= (int) $item['is_active'] === 1 ? 'فعال' : 'غیرفعال' ?></td></tr><?php endforeach; ?>
    <?php if ($registry['items'] === []): ?><tr><td colspan="5">هنوز ماژولی ثبت نشده است.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
