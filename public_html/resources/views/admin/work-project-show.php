<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$project = $project ?? [];
$access = $access ?? [];
$projectReference = (string) ($project['public_reference'] ?? '');
$projectUrl = '/admin/work/projects/' . rawurlencode($projectReference);
$isArchived = !empty($project['archived_at']);
$saved = isset($_GET['saved']);
$startDateFa = \App\Support\PersianDate::fromGregorianDate((string) ($project['start_date'] ?? ''));
$targetDateFa = \App\Support\PersianDate::fromGregorianDate((string) ($project['target_date'] ?? ''));
$identityLabels = new \App\Services\UserIdentityLabelService();
$timeline = is_array($project['timeline'] ?? null)
    ? $project['timeline']
    : [];
$canViewTimeline = !empty($access['can_view_audit']);

$timelineDetail = static function (array $entry): string {
    $payload = is_array($entry['payload'] ?? null)
        ? $entry['payload']
        : [];
    $parts = [];

    if (!empty($entry['item_title'])) {
        $parts[] = (string) $entry['item_title'];
    }

    foreach (['title', 'original_name', 'display_name'] as $field) {
        $value = trim((string) ($payload[$field] ?? ''));

        if ($value !== '' && !in_array($value, $parts, true)) {
            $parts[] = $value;
        }
    }

    if (!empty($payload['role_code'])) {
        $parts[] = match ((string) $payload['role_code']) {
            'manager' => 'مدیر پروژه',
            'member' => 'عضو پروژه',
            'observer' => 'ناظر',
            default => (string) $payload['role_code'],
        };
    }

    if (array_key_exists('completed', $payload)) {
        $parts[] = !empty($payload['completed'])
            ? 'انجام شد'
            : 'باز شد';
    }

    return implode(' — ', array_values(array_unique($parts)));
};
$project['owner_display_name'] = $identityLabels->labelForReference(
    (string) ($project['owner_user_reference'] ?? ''),
    (string) ($project['owner_display_name'] ?? '')
);

ob_start();
require __DIR__ . '/work-ui-styles.php';
?>
<style>
.work-project-timeline {
    margin-top: 1rem;
    padding: 1rem 1.1rem;
}

.work-project-timeline__header {
    align-items: center;
    display: flex;
    gap: 1rem;
    justify-content: space-between;
    margin-bottom: .85rem;
}

.work-project-timeline__header h3,
.work-project-timeline__header p {
    margin: 0;
}

.work-project-timeline__list {
    display: grid;
    position: relative;
}

.work-project-timeline__list::before {
    background: color-mix(
        in srgb,
        var(--admin-primary) 20%,
        var(--admin-border)
    );
    bottom: .8rem;
    content: "";
    position: absolute;
    right: .55rem;
    top: .8rem;
    width: 2px;
}

.work-project-timeline__entry {
    display: grid;
    gap: .25rem;
    grid-template-columns: 1.2rem minmax(0, 1fr);
    padding: .55rem 0;
    position: relative;
}

.work-project-timeline__dot {
    background: var(--admin-primary);
    border: 3px solid var(--admin-surface);
    border-radius: 50%;
    box-shadow: 0 0 0 1px color-mix(
        in srgb,
        var(--admin-primary) 28%,
        transparent
    );
    height: .8rem;
    margin-top: .3rem;
    position: relative;
    width: .8rem;
    z-index: 1;
}

.work-project-timeline__body {
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: .75rem;
    padding: .65rem .75rem;
}

.work-project-timeline__top {
    align-items: start;
    display: flex;
    gap: .75rem;
    justify-content: space-between;
}

.work-project-timeline__top strong {
    display: block;
}

.work-project-timeline__time {
    color: var(--admin-text-muted);
    direction: rtl;
    flex: 0 0 auto;
    font-size: .72rem;
    white-space: nowrap;
}

.work-project-timeline__meta {
    color: var(--admin-text-muted);
    font-size: .76rem;
    margin-top: .25rem;
}

.work-project-timeline__detail {
    margin: .35rem 0 0;
}

@media (max-width: 640px) {
    .work-project-timeline__top {
        display: block;
    }

    .work-project-timeline__time {
        display: block;
        margin-top: .2rem;
    }
}
</style>
<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a><span>/</span>
    <a href="/admin/work">مدیریت کار</a><span>/</span>
    <a href="/admin/work/projects">پروژه‌ها</a><span>/</span>
    <span><?= admin_h($project['title'] ?? '') ?></span>
</nav>

<section class="admin-module-hub admin-module-hub--green work-ui-compact-hub">
    <div class="admin-module-hub__icon"><?= \App\Support\AdminIcon::html('organization') ?></div>
    <div>
        <h2><?= admin_h($project['title'] ?? '') ?></h2>
        <p dir="ltr"><?= admin_h($project['code'] ?? '') ?></p>
    </div>
    <a class="admin-module-hub__back" href="/admin/work/projects">بازگشت به پروژه‌ها</a>
</section>

<?php if ($saved): ?>
    <section class="admin-section"><div class="admin-alert admin-alert--success">اطلاعات پروژه با موفقیت ذخیره شد.</div></section>
<?php endif; ?>

<section class="admin-section work-compact-section">
    <div class="admin-users-toolbar">
        <div>
            <span class="admin-pill"><?= admin_h($project['status_title'] ?? '') ?></span>
            <span class="admin-pill"><?= admin_h($project['visibility_title'] ?? '') ?></span>
            <?php if (!empty($access['role_title'])): ?><span class="admin-pill"><?= admin_h($access['role_title']) ?></span><?php endif; ?>
            <?php if ($isArchived): ?><span class="admin-status-badge">بایگانی‌شده</span><?php endif; ?>
        </div>
        <div class="admin-form-actions">
            <?php if (!$isArchived && !empty($access['can_manage_project'])): ?>
                <a class="admin-button" href="<?= admin_h($projectUrl . '/edit') ?>">ویرایش پروژه</a>
                <form method="post" action="<?= admin_h($projectUrl . '/archive') ?>" onsubmit="return confirm('پروژه بایگانی شود؟');">
                    <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
                    <button class="admin-button work-button--danger" type="submit">بایگانی</button>
                </form>
            <?php elseif ($isArchived && !empty($access['can_manage_project'])): ?>
                <form method="post" action="<?= admin_h($projectUrl . '/restore') ?>">
                    <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
                    <button class="admin-button" type="submit">بازیابی پروژه</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-action-grid work-project-actions">
        <?php if (!empty($access['can_manage_members'])): ?>
            <a class="admin-action-card" href="<?= admin_h($projectUrl . '/members') ?>">
                <div class="admin-action-card__icon"><?= \App\Support\AdminIcon::html('users') ?></div>
                <div><h4>اعضای پروژه</h4><p>افزودن مدیر، عضو و ناظر پروژه</p></div>
                <span class="work-action-button work-action-button--navigate">مدیریت اعضا</span>
            </a>
        <?php endif; ?>
        <a class="admin-action-card" href="<?= admin_h($projectUrl . '/items') ?>">
            <div class="admin-action-card__icon"><?= \App\Support\AdminIcon::html('status') ?></div>
            <div><h4>کارها و تسک‌ها</h4><p>ساخت کار، نقطه عطف، تسک و زیرتسک</p></div>
            <span class="work-action-button work-action-button--navigate">مشاهده کارها</span>
        </a>
    </div>

    <dl class="work-project-summary">
        <div><span>شناسه عمومی</span><strong dir="ltr"><?= admin_h($projectReference) ?></strong></div>
        <div><span>مالک</span><strong><?= admin_h(($project['owner_display_name'] ?? '') ?: '—') ?></strong></div>
        <div><span>سازمان</span><strong><?= admin_h(($project['organization_snapshot'] ?? '') ?: '—') ?></strong></div>
        <div><span>تاریخ شروع</span><strong><?= admin_h($startDateFa !== '' ? $startDateFa : '—') ?></strong></div>
        <div><span>تاریخ هدف</span><strong><?= admin_h($targetDateFa !== '' ? $targetDateFa : '—') ?></strong></div>
        <div><span>اعضا</span><strong><?= admin_h(\App\Support\AdminFormat::digits((int) ($project['member_count'] ?? 0))) ?></strong></div>
        <div><span>کل آیتم‌ها</span><strong><?= admin_h(\App\Support\AdminFormat::digits((int) ($project['item_count'] ?? 0))) ?></strong></div>
        <div><span>آیتم‌های باز</span><strong><?= admin_h(\App\Support\AdminFormat::digits((int) ($project['open_item_count'] ?? 0))) ?></strong></div>
    </dl>

    <?php if (trim((string) ($project['description'] ?? '')) !== ''): ?>
        <div class="work-project-description">
            <h3>شرح پروژه</h3>
            <p style="white-space:pre-wrap"><?= admin_h($project['description']) ?></p>
        </div>
    <?php endif; ?>
</section>

<?php if ($canViewTimeline): ?>
<section class="admin-section work-project-timeline">
    <div class="work-project-timeline__header">
        <div>
            <h3>تایم‌لاین پروژه</h3>
            <p class="admin-muted">
                تاریخچه رویدادها و تغییرات اجرایی پروژه
            </p>
        </div>
        <span class="admin-pill">
            <?= admin_h(
                \App\Support\AdminFormat::digits(count($timeline))
            ) ?> رویداد
        </span>
    </div>

    <?php if ($timeline === []): ?>
        <div class="admin-empty-state">
            هنوز رویدادی برای این پروژه ثبت نشده است.
        </div>
    <?php else: ?>
        <div class="work-project-timeline__list">
            <?php foreach ($timeline as $entry): ?>
                <?php
                $itemReference = (string) (
                    $entry['item_reference'] ?? ''
                );
                $detail = $timelineDetail($entry);
                $occurredAt = \App\Support\AdminFormat::jalaliDateTime(
                    (string) ($entry['occurred_at'] ?? '')
                );
                ?>
                <article class="work-project-timeline__entry">
                    <span
                        class="work-project-timeline__dot"
                        aria-hidden="true"
                    ></span>

                    <div class="work-project-timeline__body">
                        <div class="work-project-timeline__top">
                            <div>
                                <strong>
                                    <?= admin_h(
                                        $entry['event_title'] ?? ''
                                    ) ?>
                                </strong>
                                <div class="work-project-timeline__meta">
                                    توسط
                                    <?= admin_h(
                                        $entry['actor_label'] ?? 'کاربر'
                                    ) ?>
                                </div>
                            </div>

                            <time class="work-project-timeline__time">
                                <?= admin_h(
                                    $occurredAt !== ''
                                        ? $occurredAt
                                        : '—'
                                ) ?>
                            </time>
                        </div>

                        <?php if ($detail !== ''): ?>
                            <p class="work-project-timeline__detail">
                                <?php if ($itemReference !== ''): ?>
                                    <a href="<?= admin_h(
                                        $projectUrl
                                        . '/items/'
                                        . rawurlencode($itemReference)
                                    ) ?>">
                                        <?= admin_h($detail) ?>
                                    </a>
                                <?php else: ?>
                                    <?= admin_h($detail) ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
