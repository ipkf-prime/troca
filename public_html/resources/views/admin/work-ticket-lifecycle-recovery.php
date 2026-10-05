<?php

declare(strict_types=1);

$ui = new \App\Services\Work\TicketWorkUiContentService();
$title = $ui->text('recovery.title');
$h = static fn (mixed $value): string => admin_h((string) $value);
$rows = is_array($recoveryRows ?? null) ? $recoveryRows : [];
$statusCode = trim((string) ($status ?? ''));
$actionOptions = $ui->options('options.ticket_action_code');

$lookup = static function (
    \App\Services\Work\TicketWorkUiContentService $ui,
    string $path,
    string $fallback = ''
): string {
    try {
        return $ui->text($path);
    } catch (\Throwable) {
        return $fallback;
    }
};

$statusText = $statusCode !== ''
    ? $lookup($ui, 'recovery.status.' . $statusCode, '')
    : '';

ob_start();
require __DIR__ . '/work-ui-styles.php';
?>
<div class="admin-page work-page" data-ticket-work-lifecycle-recovery>
    <nav class="admin-breadcrumb">
        <a href="/admin/work/settings"><?= $h($ui->text('page.back')) ?></a>
        <span>/</span>
        <a href="/admin/work/settings/ticket-work-policy?kind=lifecycle"><?= $h($ui->text('recovery.back_policy')) ?></a>
    </nav>

    <section class="admin-module-hub admin-module-hub--green work-ui-compact-hub">
        <div>
            <h2><?= $h($ui->text('recovery.title')) ?></h2>
            <p><?= $h($ui->text('recovery.subtitle')) ?></p>
        </div>
        <a class="admin-module-hub__back" href="/admin/work/settings/ticket-work-policy?kind=lifecycle"><?= $h($ui->text('recovery.back_policy')) ?></a>
    </section>

    <?php if ($statusText !== ''): ?>
        <div class="admin-alert admin-alert--info"><?= $h($statusText) ?></div>
    <?php endif; ?>

    <section class="admin-section">
        <p class="admin-muted"><?= $h($ui->text('recovery.note')) ?></p>

        <?php if ($rows === []): ?>
            <div class="admin-empty"><?= $h($ui->text('recovery.empty')) ?></div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr>
                        <th><?= $h($ui->text('recovery.columns.attempt')) ?></th>
                        <th><?= $h($ui->text('recovery.columns.ticket')) ?></th>
                        <th><?= $h($ui->text('recovery.columns.action')) ?></th>
                        <th><?= $h($ui->text('recovery.columns.result')) ?></th>
                        <th><?= $h($ui->text('recovery.columns.decision')) ?></th>
                        <th><?= $h($ui->text('recovery.columns.reason')) ?></th>
                        <th><?= $h($ui->text('recovery.columns.count')) ?></th>
                        <th><?= $h($ui->text('recovery.columns.updated')) ?></th>
                        <th><?= $h($ui->text('recovery.columns.actions')) ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $attempt = is_array($row['attempt'] ?? null) ? $row['attempt'] : [];
                        $attemptId = (int) ($row['attempt_id'] ?? 0);
                        $decision = trim((string) ($row['decision'] ?? ''));
                        $reason = trim((string) ($row['reason'] ?? ''));
                        $resultCode = trim((string) ($attempt['result_code'] ?? ''));
                        ?>
                        <tr>
                            <td><code><?= $h($row['attempt_reference'] ?? '') ?></code></td>
                            <td><code><?= $h($row['ticket_reference'] ?? '') ?></code></td>
                            <td><?= $h($actionOptions[(string) ($row['ticket_action_code'] ?? '')] ?? (string) ($row['ticket_action_code'] ?? '')) ?></td>
                            <td><?= $h($lookup($ui, 'recovery.result.' . $resultCode, $resultCode)) ?></td>
                            <td><?= $h($lookup($ui, 'recovery.decision.' . $decision, $decision)) ?></td>
                            <td><?= $h($lookup($ui, 'recovery.reason.' . $reason, $reason)) ?></td>
                            <td><?= $h($attempt['attempt_count'] ?? 0) ?></td>
                            <td><?= $h($attempt['last_attempted_at'] ?? $attempt['created_at'] ?? '') ?></td>
                            <td>
                                <?php if (($row['can_retry'] ?? false) === true && $attemptId > 0): ?>
                                    <form method="post" action="/admin/work/settings/ticket-work-lifecycle-recovery/<?= $h($attemptId) ?>/retry" style="display:inline-block">
                                        <input type="hidden" name="_token" value="<?= $h((new \IPKF\Security\Csrf())->token()) ?>">
                                        <button class="admin-button" type="submit"><?= $h($ui->text('recovery.action.retry')) ?></button>
                                    </form>
                                <?php endif; ?>

                                <?php if (($row['can_repair_audit'] ?? false) === true && $attemptId > 0): ?>
                                    <form method="post" action="/admin/work/settings/ticket-work-lifecycle-recovery/<?= $h($attemptId) ?>/repair-audit" style="display:inline-block">
                                        <input type="hidden" name="_token" value="<?= $h((new \IPKF\Security\Csrf())->token()) ?>">
                                        <button class="admin-button admin-button--soft" type="submit"><?= $h($ui->text('recovery.action.repair_audit')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php
$content = ob_get_clean() ?: '';
require __DIR__ . '/layout.php';
