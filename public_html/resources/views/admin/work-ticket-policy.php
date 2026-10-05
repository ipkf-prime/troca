<?php

declare(strict_types=1);

/*
 * TICKET_WORK_POLICY_ADMIN_UI_V1
 *
 * All user-visible copy is loaded from JSON through TicketWorkUiContentService.
 */

$ui =
    new \App\Services\Work\TicketWorkUiContentService();

$title =
    $ui->text('page.title');

$h =
    static fn (mixed $value): string =>
        admin_h((string) $value);

$pageData =
    is_array($policyPage ?? null)
        ? $policyPage
        : [];

$currentKind =
    in_array(
        (string) ($kind ?? ''),
        ['destination', 'access', 'lifecycle'],
        true
    )
        ? (string) $kind
        : 'destination';

$current =
    is_array($pageData[$currentKind] ?? null)
        ? $pageData[$currentKind]
        : [
            'schema' => [],
            'editable_columns' => [],
            'rows' => [],
        ];

$statusCode =
    trim((string) ($status ?? ''));

$statusText = '';

if ($statusCode !== '') {
    try {
        $statusText =
            $ui->text('status.' . $statusCode);
    } catch (\Throwable) {
        $statusText = '';
    }
}

$fieldLabel =
    static function (
        \App\Services\Work\TicketWorkUiContentService $ui,
        string $column
    ): string {
        try {
            return $ui->text('fields.' . $column);
        } catch (\Throwable) {
            return $column;
        }
    };

$dynamicFieldOptions =
    is_array(
        $current['field_options']
        ?? null
    )
        ? $current['field_options']
        : [];

$fieldOptions =
    static function (
        \App\Services\Work\TicketWorkUiContentService $ui,
        string $column
    ) use (
        $dynamicFieldOptions
    ): array {
        if (
            array_key_exists(
                $column,
                $dynamicFieldOptions
            )
            && is_array(
                $dynamicFieldOptions[$column]
            )
        ) {
            return
                $dynamicFieldOptions[$column];
        }

        return
            $ui->options(
                'options.' . $column
            );
    };

$ruleActive =
    static function (array $row): bool {
        if (array_key_exists('is_active', $row)) {
            return (int) $row['is_active'] === 1;
        }

        if (array_key_exists('enabled', $row)) {
            return (int) $row['enabled'] === 1;
        }

        if (array_key_exists('archived_at', $row)) {
            return empty($row['archived_at']);
        }

        foreach (['status', 'status_code'] as $field) {
            if (array_key_exists($field, $row)) {
                return
                    strtolower(
                        trim((string) $row[$field])
                    ) === 'active';
            }
        }

        return true;
    };

ob_start();

require __DIR__ . '/work-ui-styles.php';
?>

<div class="admin-page work-page" data-ticket-work-policy-admin>
    <nav class="admin-breadcrumb">
        <a href="/admin/work"><?= $h($ui->text('page.back')) ?></a>
        <span>/</span>
        <a href="/admin/work/settings"><?= $h($ui->text('page.back')) ?></a>
    </nav>

    <section class="admin-module-hub admin-module-hub--green work-ui-compact-hub">
        <div>
            <h2><?= $h($ui->text('page.title')) ?></h2>
            <p><?= $h($ui->text('page.subtitle')) ?></p>
        </div>

        <a
            class="admin-module-hub__back"
            href="/admin/work/settings"
        ><?= $h($ui->text('page.back')) ?></a>
    </section>

    <?php if ($statusText !== ''): ?>
        <div class="admin-alert admin-alert--info">
            <?= $h($statusText) ?>
        </div>
    <?php endif; ?>

    <div class="admin-tabs" role="tablist">
        <a
            class="admin-tab<?= $currentKind === 'destination' ? ' is-active' : '' ?>"
            href="/admin/work/settings/ticket-work-policy?kind=destination"
        ><?= $h($ui->text('page.destination_tab')) ?></a>

        <a
            class="admin-tab<?= $currentKind === 'access' ? ' is-active' : '' ?>"
            href="/admin/work/settings/ticket-work-policy?kind=access"
        ><?= $h($ui->text('page.access_tab')) ?></a>

        <a
            class="admin-tab<?= $currentKind === 'lifecycle' ? ' is-active' : '' ?>"
            href="/admin/work/settings/ticket-work-policy?kind=lifecycle"
        ><?= $h($ui->text('page.lifecycle_tab')) ?></a>

        <a
            class="admin-tab"
            href="/admin/work/settings/ticket-work-lifecycle-recovery"
        ><?= $h($ui->text('page.recovery_tab')) ?></a>
    </div>

    <section class="admin-section">
        <p class="admin-muted">
            <?= $h($ui->text('page.schema_note')) ?>
        </p>

        <details>
            <summary><?= $h($ui->text('page.create')) ?></summary>

            <form
                method="post"
                action="/admin/work/settings/ticket-work-policy/<?= $h($currentKind) ?>"
                class="admin-form"
            >
                <input
                    type="hidden"
                    name="_token"
                    value="<?= $h((new \IPKF\Security\Csrf())->token()) ?>"
                >

                <div class="admin-form-grid">
                    <?php foreach (($current['editable_columns'] ?? []) as $column): ?>
                        <?php
                        $name = (string) ($column['name'] ?? '');
                        $options = $fieldOptions($ui, $name);
                        ?>
                        <label>
                            <span><?= $h($fieldLabel($ui, $name)) ?></span>

                            <?php if ($options !== []): ?>
                                <select name="fields[<?= $h($name) ?>]">
                                    <option value=""></option>
                                    <?php foreach ($options as $value => $label): ?>
                                        <option value="<?= $h($value) ?>">
                                            <?= $h($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input
                                    name="fields[<?= $h($name) ?>]"
                                    value=""
                                    autocomplete="off"
                                >
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="admin-form-actions">
                    <button class="admin-button" type="submit">
                        <?= $h($ui->text('page.create')) ?>
                    </button>
                </div>
            </form>
        </details>
    </section>

    <section class="admin-section">
        <?php if (($current['rows'] ?? []) === []): ?>
            <div class="admin-empty">
                <?= $h($ui->text('page.empty')) ?>
            </div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th><?= $h($ui->text('page.rule_id')) ?></th>
                        <?php foreach (($current['editable_columns'] ?? []) as $column): ?>
                            <th>
                                <?= $h($fieldLabel($ui, (string) ($column['name'] ?? ''))) ?>
                            </th>
                        <?php endforeach; ?>
                        <th><?= $h($ui->text('page.actions')) ?></th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach (($current['rows'] ?? []) as $row): ?>
                        <?php
                        $rowId = (int) ($row['id'] ?? 0);
                        $active = $ruleActive($row);
                        ?>
                        <tr>
                            <td><?= $h($rowId) ?></td>

                            <?php foreach (($current['editable_columns'] ?? []) as $column): ?>
                                <?php $name = (string) ($column['name'] ?? ''); ?>
                                <td><?= $h($row[$name] ?? '') ?></td>
                            <?php endforeach; ?>

                            <td>
                                <details>
                                    <summary><?= $h($ui->text('page.save')) ?></summary>

                                    <form
                                        method="post"
                                        action="/admin/work/settings/ticket-work-policy/<?= $h($currentKind) ?>/<?= $h($rowId) ?>"
                                        class="admin-form"
                                    >
                                        <input
                                            type="hidden"
                                            name="_token"
                                            value="<?= $h((new \IPKF\Security\Csrf())->token()) ?>"
                                        >

                                        <div class="admin-form-grid">
                                            <?php foreach (($current['editable_columns'] ?? []) as $column): ?>
                                                <?php
                                                $name = (string) ($column['name'] ?? '');
                                                $options = $fieldOptions($ui, $name);
                                                $value = (string) ($row[$name] ?? '');
                                                ?>
                                                <label>
                                                    <span><?= $h($fieldLabel($ui, $name)) ?></span>

                                                    <?php if ($options !== []): ?>
                                                        <select name="fields[<?= $h($name) ?>]">
                                                            <option value=""></option>
                                                            <?php foreach ($options as $optionValue => $label): ?>
                                                                <option
                                                                    value="<?= $h($optionValue) ?>"
                                                                    <?= $value === (string) $optionValue ? ' selected' : '' ?>
                                                                ><?= $h($label) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    <?php else: ?>
                                                        <input
                                                            name="fields[<?= $h($name) ?>]"
                                                            value="<?= $h($value) ?>"
                                                            autocomplete="off"
                                                        >
                                                    <?php endif; ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="admin-form-actions">
                                            <button class="admin-button" type="submit">
                                                <?= $h($ui->text('page.save')) ?>
                                            </button>
                                        </div>
                                    </form>
                                </details>

                                <form
                                    method="post"
                                    action="/admin/work/settings/ticket-work-policy/<?= $h($currentKind) ?>/<?= $h($rowId) ?>/<?= $active ? 'deactivate' : 'restore' ?>"
                                    style="display:inline-block"
                                >
                                    <input
                                        type="hidden"
                                        name="_token"
                                        value="<?= $h((new \IPKF\Security\Csrf())->token()) ?>"
                                    >

                                    <button
                                        class="admin-button admin-button--soft"
                                        type="submit"
                                    ><?= $h($ui->text($active ? 'page.deactivate' : 'page.restore')) ?></button>
                                </form>
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
$content =
    ob_get_clean()
    ?: '';

require __DIR__ . '/layout.php';
