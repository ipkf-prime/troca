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
    padding: .8rem 1rem;
}

.work-project-timeline__header {
    align-items: center;
    display: flex;
    gap: 1rem;
    justify-content: space-between;
    margin-bottom: .25rem;
}

.work-project-timeline__header h3,
.work-project-timeline__header p {
    margin: 0;
}

.work-project-timeline__viewport {
    overflow-x: auto;
    overflow-y: hidden;
    padding: .8rem .25rem .55rem;
    scrollbar-color:
        color-mix(in srgb, var(--admin-primary) 32%, transparent)
        transparent;
    scrollbar-width: thin;
}

.work-project-timeline__list {
    align-items: flex-start;
    direction: rtl;
    display: flex;
    min-width: max-content;
    padding: 0 .75rem;
    position: relative;
}

.work-project-timeline__list::before {
    background: color-mix(
        in srgb,
        var(--admin-primary) 24%,
        var(--admin-border)
    );
    content: "";
    height: 2px;
    left: 4.6rem;
    position: absolute;
    right: 4.6rem;
    top: 1.15rem;
}

.work-project-timeline__entry {
    flex: 0 0 8.8rem;
    min-width: 0;
    position: relative;
    text-align: center;
}

.work-project-timeline__point {
    align-items: center;
    appearance: none;
    background: transparent;
    border: 0;
    color: inherit;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    font: inherit;
    gap: .3rem;
    min-height: 5.25rem;
    padding: 0 .35rem;
    position: relative;
    width: 100%;
}

.work-project-timeline__dot {
    background: var(--admin-primary);
    border: 4px solid var(--admin-surface);
    border-radius: 50%;
    box-shadow: 0 0 0 1px color-mix(
        in srgb,
        var(--admin-primary) 38%,
        transparent
    );
    flex: 0 0 auto;
    height: 1rem;
    margin-top: .65rem;
    position: relative;
    transition:
        box-shadow .16s ease,
        transform .16s ease;
    width: 1rem;
    z-index: 1;
}

.work-project-timeline__entry--item .work-project-timeline__dot {
    background: #2563eb;
}

.work-project-timeline__entry--interaction .work-project-timeline__dot {
    background: #7c3aed;
}

.work-project-timeline__point:hover .work-project-timeline__dot,
.work-project-timeline__point:focus-visible .work-project-timeline__dot,
.work-project-timeline__point[aria-expanded="true"] .work-project-timeline__dot {
    box-shadow:
        0 0 0 1px color-mix(
            in srgb,
            var(--admin-primary) 44%,
            transparent
        ),
        0 0 0 6px color-mix(
            in srgb,
            var(--admin-primary) 12%,
            transparent
        );
    transform: scale(1.12);
}

.work-project-timeline__point:focus-visible {
    outline: 0;
}

.work-project-timeline__label {
    display: block;
    font-size: .75rem;
    font-weight: 800;
    line-height: 1.55;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.work-project-timeline__date {
    color: var(--admin-text-muted);
    direction: rtl;
    display: block;
    font-size: .66rem;
    white-space: nowrap;
}

.work-project-timeline-tooltip {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: .8rem;
    box-shadow: 0 16px 38px rgb(15 23 42 / .18);
    direction: rtl;
    display: none;
    inset: auto auto 0 0;
    max-width: min(22rem, calc(100vw - 1.5rem));
    min-width: 16rem;
    padding: .75rem .85rem;
    position: fixed;
    text-align: right;
    z-index: 1000;
}

.work-project-timeline-tooltip.is-visible {
    display: block;
}

.work-project-timeline-tooltip__title {
    display: block;
    font-size: .86rem;
    margin-bottom: .3rem;
}

.work-project-timeline-tooltip__meta {
    color: var(--admin-text-muted);
    display: flex;
    flex-wrap: wrap;
    font-size: .72rem;
    gap: .25rem .55rem;
}

.work-project-timeline-tooltip__detail {
    border-top: 1px solid var(--admin-border);
    line-height: 1.8;
    margin: .55rem 0 0;
    padding-top: .5rem;
}

.work-project-timeline-tooltip__link {
    display: inline-flex;
    font-size: .75rem;
    font-weight: 800;
    margin-top: .55rem;
    text-decoration: none;
}

@media (max-width: 640px) {
    .work-project-timeline {
        padding-inline: .75rem;
    }

    .work-project-timeline__header {
        align-items: flex-start;
    }

    .work-project-timeline__header p {
        display: none;
    }

    .work-project-timeline__entry {
        flex-basis: 7.4rem;
    }

    .work-project-timeline-tooltip {
        min-width: 0;
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
                برای مشاهده جزئیات روی هر نقطه مکث کنید.
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
        <div
            class="work-project-timeline__viewport"
            aria-label="تایم‌لاین افقی پروژه"
        >
            <div class="work-project-timeline__list">
                <?php foreach ($timeline as $entry): ?>
                    <?php
                    $eventType = (string) (
                        $entry['event_type'] ?? ''
                    );
                    $itemReference = (string) (
                        $entry['item_reference'] ?? ''
                    );
                    $detail = $timelineDetail($entry);
                    $occurredAt = \App\Support\AdminFormat::jalaliDateTime(
                        (string) ($entry['occurred_at'] ?? '')
                    );
                    $occurredDate = trim(
                        explode(' ', $occurredAt, 2)[0] ?? $occurredAt
                    );
                    $itemUrl = $itemReference === ''
                        ? ''
                        : $projectUrl
                            . '/items/'
                            . rawurlencode($itemReference);

                    $eventGroup = str_starts_with(
                        $eventType,
                        'project_'
                    )
                        ? 'project'
                        : (
                            in_array(
                                $eventType,
                                [
                                    'work_comment_added',
                                    'work_checklist_added',
                                    'work_checklist_toggled',
                                    'work_attachment_uploaded',
                                ],
                                true
                            )
                                ? 'interaction'
                                : 'item'
                        );
                    ?>
                    <article
                        class="
                            work-project-timeline__entry
                            work-project-timeline__entry--<?= admin_h(
                                $eventGroup
                            ) ?>
                        "
                        data-timeline-entry
                        data-timeline-title="<?= admin_h(
                            $entry['event_title'] ?? ''
                        ) ?>"
                        data-timeline-actor="<?= admin_h(
                            $entry['actor_label'] ?? 'کاربر'
                        ) ?>"
                        data-timeline-time="<?= admin_h(
                            $occurredAt !== '' ? $occurredAt : '—'
                        ) ?>"
                        data-timeline-detail="<?= admin_h($detail) ?>"
                        data-timeline-url="<?= admin_h($itemUrl) ?>"
                    >
                        <button
                            class="work-project-timeline__point"
                            type="button"
                            aria-expanded="false"
                            aria-label="<?= admin_h(
                                ($entry['event_title'] ?? '')
                                . '، '
                                . ($occurredAt !== ''
                                    ? $occurredAt
                                    : 'بدون تاریخ')
                            ) ?>"
                        >
                            <span
                                class="work-project-timeline__dot"
                                aria-hidden="true"
                            ></span>
                            <span class="work-project-timeline__label">
                                <?= admin_h(
                                    $entry['event_title'] ?? ''
                                ) ?>
                            </span>
                            <time class="work-project-timeline__date">
                                <?= admin_h(
                                    $occurredDate !== ''
                                        ? $occurredDate
                                        : '—'
                                ) ?>
                            </time>
                        </button>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <div
            class="work-project-timeline-tooltip"
            data-timeline-tooltip
            role="tooltip"
            aria-hidden="true"
        >
            <strong
                class="work-project-timeline-tooltip__title"
                data-timeline-tooltip-title
            ></strong>

            <div class="work-project-timeline-tooltip__meta">
                <span data-timeline-tooltip-actor></span>
                <span data-timeline-tooltip-time></span>
            </div>

            <p
                class="work-project-timeline-tooltip__detail"
                data-timeline-tooltip-detail
            ></p>

            <a
                class="work-project-timeline-tooltip__link"
                data-timeline-tooltip-link
                href="#"
            >
                مشاهده آیتم مرتبط
            </a>
        </div>

        <script>
        (() => {
            const root = document.currentScript.closest(
                '.work-project-timeline'
            );
            if (!root) return;

            const tooltip = root.querySelector(
                '[data-timeline-tooltip]'
            );
            const title = tooltip.querySelector(
                '[data-timeline-tooltip-title]'
            );
            const actor = tooltip.querySelector(
                '[data-timeline-tooltip-actor]'
            );
            const time = tooltip.querySelector(
                '[data-timeline-tooltip-time]'
            );
            const detail = tooltip.querySelector(
                '[data-timeline-tooltip-detail]'
            );
            const link = tooltip.querySelector(
                '[data-timeline-tooltip-link]'
            );
            const entries = Array.from(
                root.querySelectorAll('[data-timeline-entry]')
            );

            let activeEntry = null;
            let hideTimer = null;

            const clearHide = () => {
                if (hideTimer !== null) {
                    window.clearTimeout(hideTimer);
                    hideTimer = null;
                }
            };

            const positionTooltip = entry => {
                const rect = entry.getBoundingClientRect();
                const tooltipRect = tooltip.getBoundingClientRect();
                const margin = 12;

                let left = rect.left
                    + (rect.width / 2)
                    - (tooltipRect.width / 2);

                left = Math.max(
                    margin,
                    Math.min(
                        left,
                        window.innerWidth
                            - tooltipRect.width
                            - margin
                    )
                );

                let top = rect.top
                    - tooltipRect.height
                    - margin;

                if (top < margin) {
                    top = rect.bottom + margin;
                }

                tooltip.style.left = `${left}px`;
                tooltip.style.top = `${top}px`;
            };

            const show = entry => {
                clearHide();

                if (activeEntry && activeEntry !== entry) {
                    activeEntry
                        .querySelector(
                            '.work-project-timeline__point'
                        )
                        ?.setAttribute('aria-expanded', 'false');
                }

                activeEntry = entry;
                const data = entry.dataset;

                title.textContent = data.timelineTitle || '';
                actor.textContent = data.timelineActor
                    ? `توسط ${data.timelineActor}`
                    : '';
                time.textContent = data.timelineTime || '';
                detail.textContent = data.timelineDetail || '';
                detail.hidden = !data.timelineDetail;

                if (data.timelineUrl) {
                    link.href = data.timelineUrl;
                    link.hidden = false;
                } else {
                    link.removeAttribute('href');
                    link.hidden = true;
                }

                entry
                    .querySelector(
                        '.work-project-timeline__point'
                    )
                    ?.setAttribute('aria-expanded', 'true');

                tooltip.classList.add('is-visible');
                tooltip.setAttribute('aria-hidden', 'false');

                window.requestAnimationFrame(
                    () => positionTooltip(entry)
                );
            };

            const hide = () => {
                clearHide();

                if (activeEntry) {
                    activeEntry
                        .querySelector(
                            '.work-project-timeline__point'
                        )
                        ?.setAttribute('aria-expanded', 'false');
                }

                activeEntry = null;
                tooltip.classList.remove('is-visible');
                tooltip.setAttribute('aria-hidden', 'true');
            };

            const scheduleHide = () => {
                clearHide();
                hideTimer = window.setTimeout(hide, 140);
            };

            entries.forEach(entry => {
                const point = entry.querySelector(
                    '.work-project-timeline__point'
                );

                entry.addEventListener(
                    'mouseenter',
                    () => show(entry)
                );
                entry.addEventListener(
                    'mouseleave',
                    scheduleHide
                );

                point?.addEventListener(
                    'focus',
                    () => show(entry)
                );
                point?.addEventListener(
                    'blur',
                    scheduleHide
                );
                point?.addEventListener(
                    'click',
                    event => {
                        event.stopPropagation();

                        if (activeEntry === entry) {
                            hide();
                        } else {
                            show(entry);
                        }
                    }
                );
            });

            tooltip.addEventListener('mouseenter', clearHide);
            tooltip.addEventListener('mouseleave', scheduleHide);

            document.addEventListener('click', event => {
                if (
                    activeEntry
                    && !activeEntry.contains(event.target)
                    && !tooltip.contains(event.target)
                ) {
                    hide();
                }
            });

            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') {
                    hide();
                }
            });

            window.addEventListener('resize', () => {
                if (activeEntry) {
                    positionTooltip(activeEntry);
                }
            }, {passive: true});

            root.querySelector(
                '.work-project-timeline__viewport'
            )?.addEventListener('scroll', () => {
                if (activeEntry) {
                    positionTooltip(activeEntry);
                }
            }, {passive: true});
        })();
        </script>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
