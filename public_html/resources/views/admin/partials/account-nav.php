<?php
$accountPath = parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
) ?: '/';

$accountLinks = [
    [
        'href' => '/admin/profile',
        'label' => 'نمای کلی',
        'paths' => ['/admin/profile'],
    ],
    [
        'href' => '/admin/account',
        'label' => 'اطلاعات حساب',
        'paths' => ['/admin/account'],
    ],
    [
        'href' => '/admin/security',
        'label' => 'امنیت و ورود',
        'paths' => [
            '/admin/security',
            '/admin/password',
        ],
    ],
    [
        'href' => '/admin/profile/access',
        'label' => 'نقش‌ها و دسترسی‌ها',
        'paths' => ['/admin/profile/access'],
    ],
    [
        'href' => '/admin/my-theme',
        'label' => 'ظاهر پنل',
        'paths' => ['/admin/my-theme'],
    ],
];
?>
<style>
.account-shell {
    display: grid;
    gap: .8rem;
}

.account-tabs {
    display: flex;
    gap: .35rem;
    margin: 0;
    overflow-x: auto;
    padding: .15rem 0 .3rem;
    scrollbar-width: thin;
    white-space: nowrap;
}

.account-tabs a {
    align-items: center;
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 999px;
    color: var(--admin-text-muted);
    display: inline-flex;
    flex: 0 0 auto;
    font-size: .78rem;
    font-weight: 800;
    min-height: 2.35rem;
    padding: .4rem .82rem;
    text-decoration: none;
}

.account-tabs a:hover {
    color: var(--admin-text);
}

.account-tabs a.is-active {
    background: var(--admin-primary-soft);
    border-color: color-mix(
        in srgb,
        var(--admin-primary) 25%,
        var(--admin-border)
    );
    color: var(--admin-primary);
}

.account-card {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: .9rem;
    padding: .9rem;
}

.account-card + .account-card {
    margin-top: .75rem;
}

.account-card__head {
    align-items: flex-start;
    display: flex;
    gap: .8rem;
    justify-content: space-between;
    margin-bottom: .7rem;
}

.account-card__head h2,
.account-card__head h3 {
    line-height: 1.55;
    margin: 0;
}

.account-card__head h2 {
    font-size: 1rem;
}

.account-card__head h3 {
    font-size: .86rem;
}

.account-card__head p {
    color: var(--admin-text-muted);
    font-size: .7rem;
    line-height: 1.75;
    margin: .08rem 0 0;
}

.account-summary {
    display: grid;
    gap: .6rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.account-stat {
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: .75rem;
    min-width: 0;
    padding: .7rem;
}

.account-stat span,
.account-stat small {
    color: var(--admin-text-muted);
    display: block;
    font-size: .68rem;
    line-height: 1.65;
}

.account-stat strong {
    display: block;
    font-size: .88rem;
    line-height: 1.65;
    margin-top: .18rem;
    overflow-wrap: anywhere;
}

.account-list {
    border: 1px solid var(--admin-border);
    border-radius: .75rem;
    overflow: hidden;
}

.account-list__row {
    align-items: center;
    border-top: 1px solid var(--admin-border);
    display: grid;
    gap: .7rem;
    grid-template-columns: minmax(8rem, .65fr) minmax(0, 1.35fr) auto;
    min-height: 3.35rem;
    padding: .55rem .7rem;
}

.account-list__row:first-child {
    border-top: 0;
}

.account-list__row > span {
    color: var(--admin-text-muted);
    font-size: .7rem;
}

.account-list__row > strong {
    font-size: .78rem;
    overflow-wrap: anywhere;
}

.account-badge {
    align-items: center;
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 999px;
    display: inline-flex;
    font-size: .65rem;
    font-weight: 800;
    min-height: 1.75rem;
    padding: .2rem .5rem;
}

.account-badge--success {
    background: var(--admin-primary-soft);
    color: var(--admin-primary);
}

.account-badge--danger {
    background: #fff1f2;
    color: #be123c;
}

.account-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .45rem;
}

.security-grid {
    display: grid;
    gap: .65rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.security-method {
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: .8rem;
    padding: .75rem;
}

.security-method__head {
    align-items: center;
    display: flex;
    gap: .6rem;
    justify-content: space-between;
}

.security-method__head strong {
    font-size: .82rem;
}

.security-method p {
    color: var(--admin-text-muted);
    font-size: .68rem;
    line-height: 1.75;
    margin: .35rem 0 0;
}

.security-form {
    display: grid;
    gap: .6rem;
    margin-top: .7rem;
}

.security-form--2 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.security-form label > span {
    display: block;
    font-size: .7rem;
    font-weight: 800;
    margin-bottom: .25rem;
}

.security-form input {
    min-height: 2.45rem;
}

.setup-box {
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: .78rem;
    display: grid;
    gap: .7rem;
    margin-top: .7rem;
    padding: .75rem;
}

.setup-secret {
    align-items: center;
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: .65rem;
    display: flex;
    gap: .55rem;
    justify-content: space-between;
    padding: .55rem .65rem;
}

.setup-secret code {
    direction: ltr;
    font-size: .76rem;
    overflow-wrap: anywhere;
    text-align: left;
}

.recovery-codes {
    display: grid;
    gap: .45rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    margin-top: .7rem;
}

.recovery-code {
    background: var(--admin-surface-muted);
    border: 1px dashed var(--admin-border);
    border-radius: .55rem;
    direction: ltr;
    font-family: ui-monospace, monospace;
    font-size: .75rem;
    padding: .5rem;
    text-align: center;
}

.password-layout {
    display: grid;
    gap: .75rem;
    grid-template-columns: minmax(0, 1.1fr) minmax(15rem, .65fr);
}

.password-panel {
    display: grid;
    gap: .65rem;
}

.password-field {
    position: relative;
}

.password-field > span {
    display: block;
    font-size: .72rem;
    font-weight: 800;
    margin-bottom: .25rem;
}

.password-field input {
    min-height: 2.55rem;
    padding-left: 4.2rem;
}

.password-toggle {
    background: transparent;
    border: 0;
    color: var(--admin-primary);
    cursor: pointer;
    font-size: .68rem;
    left: .55rem;
    padding: .3rem;
    position: absolute;
    top: 2rem;
}

.password-meter {
    background: var(--admin-border);
    border-radius: 999px;
    height: .35rem;
    overflow: hidden;
}

.password-meter span {
    background: var(--admin-primary);
    display: block;
    height: 100%;
    transition: width .15s ease;
    width: 0;
}

.password-rules {
    display: grid;
    gap: .45rem;
    margin: 0;
    padding: 0;
}

.password-rules li {
    color: var(--admin-text-muted);
    font-size: .69rem;
    line-height: 1.7;
    list-style: none;
    padding-right: 1rem;
    position: relative;
}

.password-rules li::before {
    content: "•";
    position: absolute;
    right: 0;
}

.password-rules li.is-valid {
    color: var(--admin-primary);
}

.role-cards {
    display: grid;
    gap: .65rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.role-card {
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: .78rem;
    display: grid;
    gap: .55rem;
    padding: .7rem;
}

.role-card.is-active {
    background: var(--admin-primary-soft);
    border-color: color-mix(
        in srgb,
        var(--admin-primary) 30%,
        var(--admin-border)
    );
}

.role-card__head {
    align-items: flex-start;
    display: flex;
    gap: .5rem;
    justify-content: space-between;
}

.role-card__head strong,
.role-card__head small {
    display: block;
}

.role-card__head strong {
    font-size: .8rem;
}

.role-card__head small {
    color: var(--admin-text-muted);
    direction: ltr;
    font-size: .63rem;
    margin-top: .1rem;
}

.role-card__meta {
    color: var(--admin-text-muted);
    display: flex;
    flex-wrap: wrap;
    font-size: .67rem;
    gap: .4rem .75rem;
}

.theme-compact-grid {
    display: grid;
    gap: .65rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.theme-choice {
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: .78rem;
    cursor: pointer;
    display: grid;
    gap: .45rem;
    padding: .65rem;
    position: relative;
}

.theme-choice.is-active {
    border-color: var(--admin-primary);
    box-shadow: 0 0 0 2px var(--admin-primary-soft);
}

.theme-choice input {
    left: .55rem;
    position: absolute;
    top: .55rem;
}

.theme-choice__preview {
    border: 1px solid var(--admin-border);
    border-radius: .55rem;
    display: grid;
    grid-template-columns: 28% 1fr;
    height: 5.2rem;
    overflow: hidden;
}

.theme-choice__preview i,
.theme-choice__preview b {
    display: block;
}

.theme-choice__preview b {
    margin: .55rem;
    border-radius: .4rem;
}

.theme-choice strong {
    font-size: .75rem;
}

.theme-choice small {
    color: var(--admin-text-muted);
    font-size: .63rem;
    line-height: 1.65;
}

.account-notice {
    border: 1px solid var(--admin-border);
    border-radius: .72rem;
    font-size: .72rem;
    line-height: 1.8;
    padding: .6rem .7rem;
}

.account-notice--success {
    background: var(--admin-primary-soft);
    color: var(--admin-primary);
}

.account-notice--danger {
    background: #fff1f2;
    color: #be123c;
}

.account-notice--info {
    background: var(--admin-surface-muted);
    color: var(--admin-text);
}

@media (max-width: 900px) {
    .account-summary,
    .theme-compact-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .password-layout {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .account-tabs {
        margin-inline: -.2rem;
        padding-inline: .2rem;
    }

    .account-card {
        padding: .75rem;
    }

    .account-summary,
    .security-grid,
    .security-form--2,
    .role-cards,
    .theme-compact-grid,
    .recovery-codes {
        grid-template-columns: 1fr;
    }

    .account-list__row {
        align-items: flex-start;
        grid-template-columns: 1fr auto;
    }

    .account-list__row > strong {
        grid-column: 1 / -1;
        grid-row: 2;
    }

    .account-card__head {
        display: grid;
    }

    .setup-secret {
        align-items: stretch;
        display: grid;
    }

    .account-actions .admin-button,
    .account-actions button {
        flex: 1 1 auto;
    }
}
</style>

<nav class="account-tabs" aria-label="بخش‌های حساب کاربری">
    <?php foreach ($accountLinks as $link): ?>
        <?php
        $active = in_array(
            $accountPath,
            $link['paths'],
            true
        );
        ?>
        <a
            class="<?= $active ? 'is-active' : '' ?>"
            href="<?= admin_h($link['href']) ?>"
            <?= $active ? 'aria-current="page"' : '' ?>
        >
            <?= admin_h($link['label']) ?>
        </a>
    <?php endforeach; ?>
</nav>
