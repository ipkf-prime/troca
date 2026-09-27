<?php
$baleAccountText = static fn(string $key): string =>
    \App\Services\UiContent\UiContentInlineGuide::bodyText(
        $key,
        'core',
        'bale-account-link'
    );


$page = is_array($page ?? null)
    ? $page
    : [];

$items = is_array(
    $page['items'] ?? null
)
    ? $page['items']
    : [];

$available = (
    $page['available'] ?? false
) === true;

$escapeBaleAccount = static function (
    mixed $value
): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
};

/*
 * BALE_ACCOUNT_MANAGED_GUIDE_V1
 *
 * Stored instants remain UTC. Convert only for display,
 * using the configured IPKF display timezone.
 */
$baleDisplayDate = static function (mixed $value): string {
    $local = \IPKF\Support\Clock::formatDateTime($value);

    if (!is_string($local) || $local === '') {
        return '—';
    }

    $jalali = \IPKF\Support\PersianDate::fromGregorianDate(
        substr($local, 0, 10)
    );

    $time = substr($local, 11, 8);

    return strtr(
        $jalali . ' - ' . $time,
        [
            '0' => '۰', '1' => '۱', '2' => '۲',
            '3' => '۳', '4' => '۴', '5' => '۵',
            '6' => '۶', '7' => '۷', '8' => '۸',
            '9' => '۹',
        ]
    );
};

ob_start();
?>

<style>
.bale-connect-form {
    margin-block: .8rem;
}
.bale-activation-notice {
    background: var(--admin-primary-soft);
    border: 1px solid var(--admin-border);
    border-radius: .8rem;
    display: grid;
    gap: .7rem;
    margin-block: .8rem;
    padding: .9rem;
}
.bale-activation-notice p {
    margin: 0;
    line-height: 1.8;
}
.bale-activation-notice .admin-button {
    justify-self: start;
    text-decoration: none;
}
@media (max-width: 640px) {
    .bale-connect-form .admin-button,
    .bale-activation-notice .admin-button {
        box-sizing: border-box;
        justify-content: center;
        text-align: center;
        width: 100%;
    }
}
</style>

<style>
.bale-account-managed-guide {
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: .7rem;
    font-size: .8rem;
    line-height: 1.9;
    margin-block-start: .85rem;
    padding: .75rem;
    white-space: pre-line;
}
@media (max-width: 640px) {
    .bale-account-managed-guide {
        overflow-wrap: anywhere;
        padding: .65rem;
    }
}
</style>

<div class="account-shell" dir="rtl">

    <?php
    require __DIR__
        . '/partials/account-nav.php';
    ?>

    <section class="account-card">

        <div class="account-card__head">
            <div>
                <h2><?= admin_h($baleAccountText('core.bale-account-link.ui.page_title')) ?></h2>
                <p>
                    <?= admin_h($baleAccountText('core.bale-account-link.ui.status_intro')) ?>
                </p>
            </div>
        </div>


        <?php
        $baleStatus = (string) (
            $page['status'] ?? ''
        );

        $baleStatusMessages = [
            'csrf' => $baleAccountText('core.bale-account-link.error.csrf'),
            'invalid' => $baleAccountText('core.bale-account-link.error.invalid'),
            'rate_limited' => $baleAccountText('core.bale-account-link.error.rate_limited'),
            'connected' => $baleAccountText('core.bale-account-link.error.connected'),
            'failed' => $baleAccountText('core.bale-account-link.error.failed'),
        ];
        ?>

        <?php if (
            isset($baleStatusMessages[$baleStatus])
        ): ?>
            <div class="admin-alert">
                <?= $escapeBaleAccount(
                    $baleStatusMessages[$baleStatus]
                ) ?>
            </div>
        <?php endif; ?>

        <?php if (
            is_string(
                $page['activation_link'] ?? null
            )
        ): ?>
            <div class="bale-activation-notice">
                <strong>
                    <?= admin_h($baleAccountText('core.bale-account-link.notice.request_created')) ?>
                </strong>

                <p>
                    <?= admin_h($baleAccountText('core.bale-account-link.ui.confirm_instruction')) ?>
                    <?= admin_h($baleAccountText('core.bale-account-link.ui.link_expiry')) ?>
                </p>

                <a
                    class="admin-button"
                    href="<?= $escapeBaleAccount(
                        $page['activation_link']
                    ) ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    referrerpolicy="no-referrer"
                >
                    <?= admin_h($baleAccountText('core.bale-account-link.ui.confirm_button')) ?>
                </a>

                <p>
                    <?= admin_h($baleAccountText('core.bale-account-link.ui.pending_notice')) ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if (!$available): ?>

            <div class="admin-alert">
                <?= admin_h($baleAccountText('core.bale-account-link.error.connection_unavailable')) ?>
            </div>

        <?php elseif ($items === []): ?>

            <div class="admin-alert">
                <?= admin_h($baleAccountText('core.bale-account-link.error.no_active_bot')) ?>
            </div>

        <?php else: ?>

            <?php foreach ($items as $item): ?>

                <article
                    class="account-card"
                    style="margin-block: .75rem"
                >

                    <h3>
                        <?= $escapeBaleAccount(
                            $item['title'] !== ''
                                ? $item['title']
                                : $baleAccountText('core.bale-account-link.ui.default_bot_name')
                        ) ?>
                    </h3>

                    <?php if (
                        $item['bot_username'] !== ''
                    ): ?>

                        <p dir="ltr">
                            @<?= $escapeBaleAccount(
                                $item['bot_username']
                            ) ?>
                        </p>

                    <?php endif; ?>

                    <p>
                        <?= admin_h($baleAccountText('core.bale-account-link.ui.status_label')) ?>
                        <strong>
                            <?= $item['connected']
                                ? $baleAccountText('core.bale-account-link.ui.connected')
                                : $baleAccountText('core.bale-account-link.ui.not_connected') ?>
                        </strong>
                    </p>

                    <?php if (
                        $item['connected'] &&
                        $item['verified_at'] !== ''
                    ): ?>

                        <p>
                            <?= admin_h($baleAccountText('core.bale-account-link.ui.verified_at')) ?>
                            <?= $escapeBaleAccount(
                                $baleDisplayDate(
                                    $item['verified_at']
                                )
                            ) ?>
                        </p>

                    <?php endif; ?>

                    <?php if (
                        !$item['connected']
                    ): ?>

                        <?php if (
                            ($item['purpose_code'] ?? '')
                                === 'service_access'
                        ): ?>
                            <form
                                method="post"
                                action="/admin/account/bale/start"
                                class="bale-connect-form"
                            >
                                <input
                                    type="hidden"
                                    name="_token"
                                    value="<?= $escapeBaleAccount(
                                        (new \IPKF\Security\Csrf())->token()
                                    ) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="provider_reference"
                                    value="<?= $escapeBaleAccount(
                                        $item['provider_reference']
                                    ) ?>"
                                >

                                <button
                                    class="admin-button"
                                    type="submit"
                                >
                                    <?= admin_h($baleAccountText('core.bale-account-link.ui.connect_button')) ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <p>
                                <?= admin_h($baleAccountText('core.bale-account-link.ui.managed_notice')) ?>
                            </p>
                        <?php endif; ?>

                    <?php endif; ?>

                    <?php if (
                        ($item['purpose_code'] ?? '')
                            === 'service_access'
                    ): ?>
                        <?php
                        $serviceGuide = \App\Services\UiContent\UiContentInlineGuide::bodyHtml(
                            'core.bale-account.guide.01',
                            'core',
                            'bale-account'
                        );
                        ?>

                        <?php if (
                            trim(strip_tags($serviceGuide)) !== ''
                        ): ?>
                            <div class="bale-account-managed-guide">
                                <?= $serviceGuide ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>



    </section>

</div>

<?php
$content = ob_get_clean();

require __DIR__ . '/layout.php';
