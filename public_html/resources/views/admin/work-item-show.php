<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$page = $page ?? [];
$item = $page['item'] ?? [];
$children = $page['children'] ?? [];
$checklist = $page['checklist'] ?? [];
$comments = $page['comments'] ?? [];
$attachments = $page['attachments'] ?? [];
$activities = $page['activities'] ?? [];
$projectReference = (string) ($item['project_reference'] ?? '');
$itemReference = (string) ($item['public_reference'] ?? '');
$baseUrl = '/admin/work/projects/' . rawurlencode($projectReference) . '/items';
$itemUrl = $baseUrl . '/' . rawurlencode($itemReference);
$isLocked = !empty($item['is_locked']);
$csrf = (new \IPKF\Security\Csrf())->token();

$startDate = \App\Support\AdminFormat::jalaliDate(substr((string) ($item['start_at'] ?? ''), 0, 10));
$dueDate = \App\Support\AdminFormat::jalaliDate(substr((string) ($item['due_at'] ?? ''), 0, 10));
$createdAt = \App\Support\AdminFormat::jalaliDateTime($item['created_at'] ?? '');
$updatedAt = \App\Support\AdminFormat::jalaliDateTime($item['updated_at'] ?? '');

$formatBytes = static function (int $bytes): string {
    if ($bytes < 1024) {
        return \App\Support\AdminFormat::digits($bytes) . ' بایت';
    }
    if ($bytes < 1048576) {
        return \App\Support\AdminFormat::digits(number_format($bytes / 1024, 1)) . ' کیلوبایت';
    }

    return \App\Support\AdminFormat::digits(number_format($bytes / 1048576, 1)) . ' مگابایت';
};

$eventDetail = static function (array $activity): string {
    $payload = $activity['payload'] ?? [];
    $parts = [];

    foreach (['title', 'original_name'] as $field) {
        if (!empty($payload[$field])) {
            $parts[] = (string) $payload[$field];
        }
    }

    if (array_key_exists('completed', $payload)) {
        $parts[] = !empty($payload['completed']) ? 'انجام شد' : 'باز شد';
    }

    return implode(' — ', $parts);
};

ob_start();
require __DIR__ . '/work-ui-styles.php';
?>
<style>
.work-detail-grid{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(280px,.8fr);gap:16px}
.work-detail-stack{display:grid;gap:16px}
.work-detail-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.work-detail-meta div,.work-detail-child,.work-comment,.work-activity{background:var(--admin-surface-muted);border:1px solid var(--admin-border);border-radius:12px;padding:11px 12px}
.work-detail-meta span,.work-comment small,.work-activity small{display:block;color:var(--admin-text-muted);font-size:var(--admin-font-size-sm)}
.work-detail-description{white-space:pre-wrap;line-height:2}
.work-checklist-row{align-items:center;border-bottom:1px solid var(--admin-border);display:flex;gap:10px;padding:9px 0}
.work-checklist-row:last-child{border-bottom:0}
.work-checklist-row form{margin:0}
.work-checklist-row.is-complete strong{text-decoration:line-through;color:var(--admin-text-muted)}
.work-detail-list{display:grid;gap:10px}
.work-comment p{margin:7px 0 0;white-space:pre-wrap}
.work-activity strong{display:block}
.work-detail-inline-form{display:flex;gap:8px;align-items:end;flex-wrap:wrap}
.work-detail-inline-form label{flex:1 1 260px}
.work-detail-inline-form .admin-button{flex:0 0 auto}
.work-file-row{align-items:center;display:flex;gap:10px;justify-content:space-between;border-bottom:1px solid var(--admin-border);padding:10px 0}
.work-file-row:last-child{border-bottom:0}
@media(max-width:900px){.work-detail-grid{grid-template-columns:1fr}.work-detail-meta{grid-template-columns:1fr}}
</style>

<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a><span>/</span>
    <a href="/admin/work">مدیریت کار</a><span>/</span>
    <a href="/admin/work/projects">پروژه‌ها</a><span>/</span>
    <a href="<?= admin_h('/admin/work/projects/' . rawurlencode($projectReference)) ?>">
        <?= admin_h($item['project_title'] ?? '') ?>
    </a><span>/</span>
    <a href="<?= admin_h($baseUrl) ?>">کارها و تسک‌ها</a><span>/</span>
    <span><?= admin_h($item['title'] ?? '') ?></span>
</nav>

<section class="admin-module-hub admin-module-hub--green work-ui-compact-hub">
    <div class="admin-module-hub__icon"><?= \App\Support\AdminIcon::html('circle-check') ?></div>
    <div>
        <h2><?= admin_h($item['title'] ?? '') ?></h2>
        <p><?= admin_h($item['type_title'] ?? '') ?> · <?= admin_h($item['project_title'] ?? '') ?></p>
    </div>
    <a class="admin-module-hub__back" href="<?= admin_h($baseUrl) ?>">بازگشت به فهرست</a>
</section>

<?php if (isset($_GET['saved'])): ?>
    <section class="admin-section"><div class="admin-alert admin-alert--success">عملیات با موفقیت انجام شد.</div></section>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <section class="admin-section"><div class="admin-alert admin-alert--danger"><?= admin_h((string) $_GET['error']) ?></div></section>
<?php endif; ?>

<div class="work-detail-grid">
    <div class="work-detail-stack">
        <section class="admin-section">
            <div class="admin-section__header">
                <div>
                    <h2>شرح و اطلاعات کار</h2>
                    <p class="admin-muted">وضعیت جاری و جزئیات اجرایی</p>
                </div>
                <?php if (!$isLocked): ?>
                    <a class="admin-button" href="<?= admin_h($itemUrl . '/edit') ?>">ویرایش</a>
                <?php endif; ?>
            </div>

            <div class="work-detail-meta">
                <div><span>وضعیت</span><strong><?= admin_h($item['status_title'] ?? '') ?></strong></div>
                <div><span>اولویت</span><strong><?= admin_h($item['priority_title'] ?? '') ?></strong></div>
                <div><span>مسئول</span><strong><?= admin_h(($item['assignee_name'] ?? '') ?: 'بدون مسئول') ?></strong></div>
                <div><span>پیشرفت</span><strong><?= admin_h(\App\Support\AdminFormat::digits((int) ($item['progress_percent'] ?? 0))) ?>٪</strong></div>
                <div><span>شروع</span><strong><?= admin_h($startDate !== '' ? $startDate : '—') ?></strong></div>
                <div><span>سررسید</span><strong><?= admin_h($dueDate !== '' ? $dueDate : '—') ?></strong></div>
                <div><span>برآورد زمان</span><strong><?= !empty($item['estimate_minutes']) ? admin_h(\App\Support\AdminFormat::digits((int) $item['estimate_minutes'])) . ' دقیقه' : '—' ?></strong></div>
                <div><span>آخرین تغییر</span><strong><?= admin_h($updatedAt !== '' ? $updatedAt : '—') ?></strong></div>
            </div>

            <?php if (!empty($item['parent_reference'])): ?>
                <p>
                    <strong>والد:</strong>
                    <a href="<?= admin_h($baseUrl . '/' . rawurlencode((string) $item['parent_reference'])) ?>">
                        <?= admin_h($item['parent_title'] ?? '') ?>
                    </a>
                </p>
            <?php endif; ?>

            <hr>
            <div class="work-detail-description"><?= admin_h(($item['description'] ?? '') ?: 'شرحی برای این آیتم ثبت نشده است.') ?></div>
        </section>

        <section class="admin-section">
            <div class="admin-section__header">
                <div>
                    <h2>چک‌لیست</h2>
                    <p class="admin-muted"><?= admin_h(\App\Support\AdminFormat::digits(count($checklist))) ?> مورد</p>
                </div>
            </div>

            <?php if (!$isLocked): ?>
                <form method="post" action="<?= admin_h($itemUrl . '/checklist') ?>" class="work-detail-inline-form">
                    <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
                    <label>
                        <span>مورد جدید</span>
                        <input name="title" maxlength="500" required placeholder="عنوان مورد چک‌لیست">
                    </label>
                    <button class="admin-button" type="submit">افزودن</button>
                </form>
            <?php endif; ?>

            <?php if ($checklist === []): ?>
                <p class="admin-empty-state">چک‌لیستی ثبت نشده است.</p>
            <?php else: ?>
                <?php foreach ($checklist as $row): ?>
                    <div class="work-checklist-row<?= !empty($row['is_completed']) ? ' is-complete' : '' ?>">
                        <?php if (!$isLocked): ?>
                            <form method="post" action="<?= admin_h($itemUrl . '/checklist/' . (int) $row['id'] . '/toggle') ?>">
                                <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
                                <input type="hidden" name="completed" value="<?= !empty($row['is_completed']) ? '0' : '1' ?>">
                                <button class="admin-button admin-button--soft admin-button--compact" type="submit">
                                    <?= !empty($row['is_completed']) ? 'بازکردن' : 'انجام شد' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                        <strong><?= admin_h($row['title'] ?? '') ?></strong>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="admin-section">
            <div class="admin-section__header">
                <div>
                    <h2>دیدگاه‌ها</h2>
                    <p class="admin-muted"><?= admin_h(\App\Support\AdminFormat::digits(count($comments))) ?> دیدگاه</p>
                </div>
            </div>

            <?php if (!$isLocked): ?>
                <form method="post" action="<?= admin_h($itemUrl . '/comments') ?>">
                    <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
                    <label>
                        <span>دیدگاه جدید</span>
                        <textarea name="body" rows="4" maxlength="10000" required placeholder="گزارش پیشرفت، توضیح یا نتیجه بررسی"></textarea>
                    </label>
                    <div class="admin-form-actions">
                        <button class="admin-button" type="submit">ثبت دیدگاه</button>
                    </div>
                </form>
            <?php endif; ?>

            <div class="work-detail-list">
                <?php if ($comments === []): ?>
                    <p class="admin-empty-state">هنوز دیدگاهی ثبت نشده است.</p>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                        <article class="work-comment">
                            <strong><?= admin_h($comment['author_display_name_snapshot'] ?? '') ?></strong>
                            <small><?= admin_h(\App\Support\AdminFormat::jalaliDateTime($comment['created_at'] ?? '')) ?></small>
                            <p><?= admin_h($comment['body'] ?? '') ?></p>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="admin-section">
            <div class="admin-section__header">
                <div>
                    <h2>زیرمجموعه‌ها</h2>
                    <p class="admin-muted"><?= admin_h(\App\Support\AdminFormat::digits(count($children))) ?> آیتم</p>
                </div>
                <?php if (!$isLocked): ?>
                    <a class="admin-button" href="<?= admin_h($baseUrl . '/create') ?>">ایجاد آیتم</a>
                <?php endif; ?>
            </div>

            <div class="work-detail-list">
                <?php if ($children === []): ?>
                    <p class="admin-empty-state">این آیتم زیرمجموعه‌ای ندارد.</p>
                <?php else: ?>
                    <?php foreach ($children as $child): ?>
                        <a class="work-detail-child" href="<?= admin_h($baseUrl . '/' . rawurlencode((string) $child['public_reference'])) ?>">
                            <strong><?= admin_h($child['title'] ?? '') ?></strong>
                            <small class="admin-muted">
                                <?= admin_h($child['type_title'] ?? '') ?>
                                · <?= admin_h($child['status_title'] ?? '') ?>
                                · <?= admin_h(\App\Support\AdminFormat::digits((int) ($child['progress_percent'] ?? 0))) ?>٪
                            </small>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <aside class="work-detail-stack">
        <section class="admin-section">
            <div class="admin-section__header">
                <div>
                    <h2>پیوست‌ها</h2>
                    <p class="admin-muted"><?= admin_h(\App\Support\AdminFormat::digits(count($attachments))) ?> فایل</p>
                </div>
            </div>

            <?php if (!$isLocked): ?>
                <form method="post" action="<?= admin_h($itemUrl . '/attachments') ?>" enctype="multipart/form-data">
                    <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
                    <label>
                        <span>انتخاب فایل</span>
                        <input type="file" name="attachment" required accept=".pdf,.jpg,.jpeg,.png,.txt,.docx,.xlsx">
                    </label>
                    <p class="admin-muted">حداکثر ۱۵ مگابایت</p>
                    <button class="admin-button" type="submit">بارگذاری</button>
                </form>
            <?php endif; ?>

            <?php if ($attachments === []): ?>
                <p class="admin-empty-state">فایلی پیوست نشده است.</p>
            <?php else: ?>
                <?php foreach ($attachments as $attachment): ?>
                    <div class="work-file-row">
                        <div>
                            <strong><?= admin_h($attachment['original_name'] ?? '') ?></strong>
                            <small class="admin-muted">
                                <?= admin_h($formatBytes((int) ($attachment['size_bytes'] ?? 0))) ?>
                                · <?= admin_h(\App\Support\AdminFormat::jalaliDateTime($attachment['created_at'] ?? '')) ?>
                            </small>
                        </div>
                        <a
                            class="admin-button admin-button--soft admin-button--compact"
                            href="<?= admin_h($itemUrl . '/attachments/' . rawurlencode((string) $attachment['public_reference'])) ?>"
                        >دریافت</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="admin-section">
            <h2>اطلاعات ثبت</h2>
            <div class="work-detail-meta">
                <div><span>شماره</span><strong><?= admin_h(\App\Support\AdminFormat::digits((int) ($item['sequence_number'] ?? 0))) ?></strong></div>
                <div><span>ایجاد</span><strong><?= admin_h($createdAt !== '' ? $createdAt : '—') ?></strong></div>
            </div>
        </section>

        <section class="admin-section">
            <div class="admin-section__header">
                <div>
                    <h2>تاریخچه فعالیت</h2>
                    <p class="admin-muted"><?= admin_h(\App\Support\AdminFormat::digits(count($activities))) ?> رویداد</p>
                </div>
            </div>

            <div class="work-detail-list">
                <?php if ($activities === []): ?>
                    <p class="admin-empty-state">رویدادی ثبت نشده است.</p>
                <?php else: ?>
                    <?php foreach ($activities as $activity): ?>
                        <?php $detail = $eventDetail($activity); ?>
                        <article class="work-activity">
                            <strong><?= admin_h($activity['event_title'] ?? '') ?></strong>
                            <?php if ($detail !== ''): ?><span><?= admin_h($detail) ?></span><?php endif; ?>
                            <small>
                                <?= admin_h($activity['actor_display_name_snapshot'] ?? '') ?>
                                · <?= admin_h(\App\Support\AdminFormat::jalaliDateTime($activity['occurred_at'] ?? '')) ?>
                            </small>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </aside>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
