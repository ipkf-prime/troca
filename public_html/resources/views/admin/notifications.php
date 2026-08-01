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

$page = $page ?? [];
$items = $page['items'] ?? [];
$pagination = $page['pagination'] ?? [];
$filter = (string) ($page['filter'] ?? 'all');
$unreadCount = (int) ($page['unread_count'] ?? 0);
$status = (string) ($status ?? '');

$pageUrl = static function (
    int $targetPage
) use ($filter): string {
    return '/admin/notifications?'
        . http_build_query([
            'filter' => $filter,
            'page' => $targetPage,
        ]);
};

ob_start();
?>
<style>
.notification-page {
    display: grid;
    gap: .85rem;
}

.notification-head,
.notification-card,
.notification-empty {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: .95rem;
}

.notification-head {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: .75rem;
    justify-content: space-between;
    padding: .85rem .95rem;
}

.notification-head h2 {
    font-size: 1.05rem;
    margin: 0;
}

.notification-head p {
    color: var(--admin-text-muted);
    font-size: .72rem;
    line-height: 1.75;
    margin: .08rem 0 0;
}

.notification-tools,
.notification-filters {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: .45rem;
}

.notification-filter {
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 999px;
    color: var(--admin-text);
    font-size: .72rem;
    font-weight: 800;
    padding: .4rem .7rem;
    text-decoration: none;
}

.notification-filter.is-active {
    background: var(--admin-primary-soft);
    color: var(--admin-primary);
}

.notification-list {
    display: grid;
    gap: .65rem;
}

.notification-card {
    display: grid;
    gap: .6rem;
    padding: .8rem .9rem;
}

.notification-card.is-unread {
    border-color: color-mix(
        in srgb,
        var(--admin-primary) 32%,
        var(--admin-border)
    );
    box-shadow: inset -3px 0 0 var(--admin-primary);
}

.notification-card__head {
    align-items: flex-start;
    display: flex;
    gap: .7rem;
    justify-content: space-between;
}

.notification-card__head strong {
    font-size: .84rem;
    line-height: 1.7;
}

.notification-card__head time {
    color: var(--admin-text-muted);
    direction: ltr;
    font-size: .66rem;
    white-space: nowrap;
}

.notification-card p {
    color: var(--admin-text-muted);
    font-size: .74rem;
    line-height: 1.9;
    margin: 0;
    white-space: pre-line;
}

.notification-card__footer {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: .45rem;
    justify-content: space-between;
}

.notification-meta {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
}

.notification-badge {
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 999px;
    color: var(--admin-text-muted);
    font-size: .64rem;
    font-weight: 800;
    padding: .22rem .48rem;
}

.notification-badge--urgent,
.notification-badge--high {
    background: #fff1f2;
    color: #be123c;
}

.notification-empty {
    color: var(--admin-text-muted);
    padding: 1.5rem;
    text-align: center;
}

@media (max-width: 640px) {
    .notification-head,
    .notification-card__head,
    .notification-card__footer {
        align-items: stretch;
        display: grid;
    }

    .notification-tools .admin-button,
    .notification-card__footer .admin-button,
    .notification-card__footer form,
    .notification-card__footer form button {
        width: 100%;
    }
}
</style>

<div class="notification-page">
    <nav class="admin-breadcrumb" aria-label="breadcrumb">
        <a href="/admin/dashboard">داشبورد</a>
        <span aria-hidden="true">/</span>
        <span>اعلان‌های من</span>
    </nav>

    <?php if ($status === 'read'): ?>
        <div class="admin-alert admin-alert--success">
            اعلان خوانده شد.
        </div>
    <?php elseif ($status === 'all_read'): ?>
        <div class="admin-alert admin-alert--success">
            همه اعلان‌ها خوانده شدند.
        </div>
    <?php endif; ?>

    <section class="notification-head">
        <div>
            <h2>صندوق اعلان‌ها</h2>
            <p>
                اعلان‌های سامانه، Work و سایر ماژول‌ها در این بخش
                یکپارچه می‌شوند.
            </p>
        </div>

        <div class="notification-tools">
            <div class="notification-filters">
                <a
                    class="notification-filter<?= $filter === 'all'
                        ? ' is-active'
                        : '' ?>"
                    href="/admin/notifications?filter=all"
                >
                    همه
                </a>
                <a
                    class="notification-filter<?= $filter === 'unread'
                        ? ' is-active'
                        : '' ?>"
                    href="/admin/notifications?filter=unread"
                >
                    خوانده‌نشده
                    <?= admin_h(
                        \App\Support\AdminFormat::digits(
                            $unreadCount
                        )
                    ) ?>
                </a>
            </div>

            <?php if ($unreadCount > 0): ?>
                <form
                    method="post"
                    action="/admin/notifications/read-all"
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
                        خواندن همه
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <?php if (($page['ok'] ?? false) !== true): ?>
        <div class="admin-alert">
            زیرساخت اعلان در حال حاضر در دسترس نیست.
        </div>
    <?php elseif ($items === []): ?>
        <div class="notification-empty">
            <?= $filter === 'unread'
                ? 'اعلان خوانده‌نشده‌ای وجود ندارد.'
                : 'هنوز اعلانی برای شما ثبت نشده است.' ?>
        </div>
    <?php else: ?>
        <div class="notification-list">
            <?php foreach ($items as $item): ?>
                <article class="notification-card<?= empty(
                    $item['is_read']
                ) ? ' is-unread' : '' ?>">
                    <header class="notification-card__head">
                        <strong>
                            <?= admin_h($item['title']) ?>
                        </strong>
                        <time>
                            <?= admin_h(
                                \App\Support\AdminFormat::digits(
                                    $item['created_at']
                                )
                            ) ?>
                        </time>
                    </header>

                    <p><?= admin_h($item['body']) ?></p>

                    <footer class="notification-card__footer">
                        <div class="notification-meta">
                            <span class="notification-badge notification-badge--<?= admin_h(
                                $item['priority_code']
                            ) ?>">
                                <?= admin_h(
                                    match ($item['priority_code']) {
                                        'urgent' => 'فوری',
                                        'high' => 'مهم',
                                        'low' => 'کم‌اهمیت',
                                        default => 'عادی',
                                    }
                                ) ?>
                            </span>
                            <span class="notification-badge">
                                <?= admin_h(
                                    $item['category_code']
                                ) ?>
                            </span>
                            <?php if (!empty($item['is_read'])): ?>
                                <span class="notification-badge">
                                    خوانده‌شده
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="notification-tools">
                            <?php if (
                                ($item['action_url'] ?? '') !== ''
                            ): ?>
                                <a
                                    class="admin-button admin-button--soft"
                                    href="<?= admin_h(
                                        $item['action_url']
                                    ) ?>"
                                >
                                    مشاهده
                                </a>
                            <?php endif; ?>

                            <?php if (empty($item['is_read'])): ?>
                                <form
                                    method="post"
                                    action="<?= admin_h(
                                        '/admin/notifications/'
                                        . rawurlencode(
                                            $item['reference']
                                        )
                                        . '/read'
                                    ) ?>"
                                >
                                    <input
                                        type="hidden"
                                        name="_token"
                                        value="<?= admin_h(
                                            (new \IPKF\Security\Csrf())
                                                ->token()
                                        ) ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="return_to"
                                        value="<?= admin_h(
                                            $pageUrl(
                                                (int) (
                                                    $pagination['page']
                                                    ?? 1
                                                )
                                            )
                                        ) ?>"
                                    >
                                    <button
                                        class="admin-button"
                                        type="submit"
                                    >
                                        خواندم
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (
        ($page['ok'] ?? false) === true
        && (int) ($pagination['total'] ?? 0) > 0
    ): ?>
        <div class="admin-pagination">
            <span>
                صفحه
                <?= admin_h(
                    \App\Support\AdminFormat::digits(
                        $pagination['page'] ?? 1
                    )
                ) ?>
                از
                <?= admin_h(
                    \App\Support\AdminFormat::digits(
                        $pagination['last_page'] ?? 1
                    )
                ) ?>
            </span>
            <div>
                <?php if (!empty(
                    $pagination['has_previous']
                )): ?>
                    <a
                        class="admin-button admin-button--soft"
                        href="<?= admin_h(
                            $pageUrl(
                                (int) $pagination['previous_page']
                            )
                        ) ?>"
                    >
                        قبلی
                    </a>
                <?php endif; ?>

                <?php if (!empty(
                    $pagination['has_next']
                )): ?>
                    <a
                        class="admin-button"
                        href="<?= admin_h(
                            $pageUrl(
                                (int) $pagination['next_page']
                            )
                        ) ?>"
                    >
                        بعدی
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
