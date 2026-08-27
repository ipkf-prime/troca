<?php

declare(strict_types=1);

if (!function_exists('ticketing_h')) {
    function ticketing_h($value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
            false
        );
    }
}

$page =
    $page
    ?? [];

$project =
    $page['project']
    ?? [];

$layers =
    $page['layers']
    ?? [];

$nodes =
    $page['nodes']
    ?? [];

$relations =
    $page['relations']
    ?? [];

$teams =
    $page['teams']
    ?? [];

$queues =
    $page['queues']
    ?? [];

$teamNodes =
    $page['team_nodes']
    ?? [];

$teamQueues =
    $page['team_queues']
    ?? [];

$teamMembers =
    $page['team_members']
    ?? [];

$staffCandidates =
    $page['staff_candidates']
    ?? [];

$errors =
    $errors
    ?? [];

$status =
    $status
    ?? '';

$reference =
    (string) (
        $project['public_reference']
        ?? ''
    );

$action =
    '/admin/ticketing/projects/'
    . rawurlencode($reference)
    . '/topology';

$csrf =
    (
        new \IPKF\Security\Csrf()
    )->token();

$statusMessages = [
    'layer-created' =>
        'لایه پشتیبانی ثبت شد.',

    'node-created' =>
        'گره پشتیبانی ثبت شد.',

    'relation-created' =>
        'ارتباط ساختاری ثبت شد.',

    'team-created' =>
        'تیم پشتیبانی ثبت شد.',

    'queue-created' =>
        'صف پشتیبانی ثبت شد.',

    'team-node-bound' =>
        'تیم به گره متصل شد.',

    'team-queue-bound' =>
        'تیم به صف متصل شد.',

    'team-member-added' =>
        'کارشناس به تیم افزوده شد.',
];

ob_start();
?>

<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a>
    <span>/</span>
    <a href="/admin/ticketing">پشتیبانی و تیکتینگ</a>
    <span>/</span>
    <a href="/admin/ticketing/projects">پروژه‌ها</a>
    <span>/</span>
    <span>ساختار پشتیبانی</span>
</nav>


<div class="admin-page ticketing-page">

    <div class="admin-page-header">
        <div>
            <h1>
                ساختار پشتیبانی:
                <?= ticketing_h(
                    $project['title']
                    ?? ''
                ) ?>
            </h1>

            <p>
                مدیریت مستقل لایه‌ها، گره‌ها،
                ارتباطات، تیم‌ها، صف‌ها و کارشناسان
            </p>
        </div>

        <a
            class="admin-button admin-button--soft"
            href="/admin/ticketing/projects"
        >
            بازگشت به پروژه‌ها
        </a>
    </div>


    <?php if (
        $status !== ''
        && isset(
            $statusMessages[$status]
        )
    ): ?>

        <section class="admin-section">
            <div class="admin-alert admin-alert--success">
                <?= ticketing_h(
                    $statusMessages[$status]
                ) ?>
            </div>
        </section>

    <?php endif; ?>


    <?php if ($errors !== []): ?>

        <section class="admin-section">
            <div
                class="admin-alert admin-alert--danger"
                role="alert"
            >
                <strong>
                    عملیات انجام نشد.
                </strong>

                <ul>
                    <?php foreach (
                        $errors
                        as $error
                    ): ?>
                        <li>
                            <?= ticketing_h($error) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

    <?php endif; ?>


    <section class="admin-section">
        <h2>۱. لایه‌های پشتیبانی</h2>

        <form method="post" action="<?= ticketing_h($action) ?>">
            <input type="hidden" name="_token" value="<?= ticketing_h($csrf) ?>">
            <input type="hidden" name="action" value="layer.create">

            <div class="admin-form-grid">
                <label>
                    <span>عنوان لایه</span>
                    <input
                        type="text"
                        name="title"
                        maxlength="255"
                        required
                        placeholder="مثلاً سطح پشتیبانی اول"
                    >
                </label>

                <label>
                    <span>کد</span>
                    <input
                        type="text"
                        name="code"
                        maxlength="100"
                        required
                        dir="ltr"
                        placeholder="level-1"
                    >
                </label>

                <label>
                    <span>رتبه</span>
                    <input
                        type="number"
                        name="rank_order"
                        min="1"
                        value="<?= ticketing_h(
                            (count($layers) + 1) * 10
                        ) ?>"
                        required
                    >
                </label>
            </div>

            <div class="ticketing-topology-checks">
                <label>
                    <input type="checkbox" name="is_entry_layer" value="1">
                    لایه ورودی
                </label>

                <label>
                    <input type="checkbox" name="is_terminal_layer" value="1">
                    لایه نهایی
                </label>

                <label>
                    <input type="checkbox" name="can_observe_descendants" value="1">
                    مشاهده زیرلایه‌ها
                </label>

                <label>
                    <input type="checkbox" name="can_assist_descendants" value="1">
                    همکاری در زیرلایه‌ها
                </label>

                <label>
                    <input type="checkbox" name="can_takeover_descendants" value="1">
                    Take Over زیرلایه‌ها
                </label>

                <label>
                    <input type="checkbox" name="can_transfer_downward" value="1">
                    انتقال به لایه پایین
                </label>
            </div>

            <button class="admin-button" type="submit">
                افزودن لایه
            </button>
        </form>

        <?php if ($layers !== []): ?>
            <div class="admin-table-wrap ticketing-topology-table">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>رتبه</th>
                        <th>عنوان</th>
                        <th>کد</th>
                        <th>Observe</th>
                        <th>Assist</th>
                        <th>Take Over</th>
                        <th>Down</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($layers as $layer): ?>
                        <tr>
                            <td><?= ticketing_h($layer['rank_order']) ?></td>
                            <td><?= ticketing_h($layer['title']) ?></td>
                            <td dir="ltr"><?= ticketing_h($layer['code']) ?></td>
                            <td><?= (int) $layer['can_observe_descendants'] ? 'بله' : '—' ?></td>
                            <td><?= (int) $layer['can_assist_descendants'] ? 'بله' : '—' ?></td>
                            <td><?= (int) $layer['can_takeover_descendants'] ? 'بله' : '—' ?></td>
                            <td><?= (int) $layer['can_transfer_downward'] ? 'بله' : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>


    <section class="admin-section">
        <h2>۲. گره‌های پشتیبانی</h2>

        <?php if ($layers === []): ?>
            <div class="admin-muted">
                ابتدا حداقل یک لایه تعریف کنید.
            </div>
        <?php else: ?>

            <form method="post" action="<?= ticketing_h($action) ?>">
                <input type="hidden" name="_token" value="<?= ticketing_h($csrf) ?>">
                <input type="hidden" name="action" value="node.create">

                <div class="admin-form-grid">
                    <label>
                        <span>لایه</span>
                        <select name="layer_id" required>
                            <?php foreach ($layers as $layer): ?>
                                <option value="<?= ticketing_h($layer['id']) ?>">
                                    <?= ticketing_h(
                                        $layer['title']
                                        . ' - '
                                        . $layer['rank_order']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>عنوان گره</span>
                        <input
                            type="text"
                            name="title"
                            maxlength="255"
                            required
                        >
                    </label>

                    <label>
                        <span>کد</span>
                        <input
                            type="text"
                            name="code"
                            maxlength="100"
                            dir="ltr"
                            required
                        >
                    </label>

                    <label>
                        <span>نوع Scope</span>
                        <input
                            type="text"
                            name="scope_type_code"
                            maxlength="50"
                            dir="ltr"
                            placeholder="organization / geography / all"
                        >
                    </label>

                    <label>
                        <span>شناسه Scope</span>
                        <input
                            type="text"
                            name="scope_reference"
                            maxlength="190"
                            dir="ltr"
                        >
                    </label>

                    <label>
                        <span>Core Organization Reference</span>
                        <input
                            type="text"
                            name="core_organization_reference"
                            maxlength="100"
                            dir="ltr"
                        >
                    </label>
                </div>

                <label>
                    <input type="checkbox" name="is_intake_node" value="1">
                    گره Intake
                </label>

                <br><br>

                <button class="admin-button" type="submit">
                    افزودن گره
                </button>
            </form>

        <?php endif; ?>

        <?php if ($nodes !== []): ?>
            <div class="admin-table-wrap ticketing-topology-table">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>لایه</th>
                        <th>عنوان</th>
                        <th>کد</th>
                        <th>Scope</th>
                        <th>Intake</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($nodes as $node): ?>
                        <tr>
                            <td>
                                <?= ticketing_h(
                                    $node['layer_title']
                                    . ' / '
                                    . $node['rank_order']
                                ) ?>
                            </td>
                            <td><?= ticketing_h($node['title']) ?></td>
                            <td dir="ltr"><?= ticketing_h($node['code']) ?></td>
                            <td dir="ltr">
                                <?= ticketing_h(
                                    trim(
                                        (string) ($node['scope_type_code'] ?? '')
                                        . ':'
                                        . (string) ($node['scope_reference'] ?? ''),
                                        ':'
                                    )
                                    ?: '—'
                                ) ?>
                            </td>
                            <td><?= (int) $node['is_intake_node'] ? 'بله' : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>


    <section class="admin-section">
        <h2>۳. ارتباط بین گره‌ها</h2>

        <?php if (count($nodes) < 2): ?>
            <div class="admin-muted">
                برای تعریف ارتباط حداقل دو گره لازم است.
            </div>
        <?php else: ?>

            <form method="post" action="<?= ticketing_h($action) ?>">
                <input type="hidden" name="_token" value="<?= ticketing_h($csrf) ?>">
                <input type="hidden" name="action" value="relation.create">

                <div class="admin-form-grid">
                    <label>
                        <span>گره والد / سطح بالاتر</span>
                        <select name="parent_node_id" required>
                            <?php foreach ($nodes as $node): ?>
                                <option value="<?= ticketing_h($node['id']) ?>">
                                    <?= ticketing_h(
                                        $node['title']
                                        . ' - '
                                        . $node['layer_title']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>گره فرزند / سطح پایین‌تر</span>
                        <select name="child_node_id" required>
                            <?php foreach ($nodes as $node): ?>
                                <option value="<?= ticketing_h($node['id']) ?>">
                                    <?= ticketing_h(
                                        $node['title']
                                        . ' - '
                                        . $node['layer_title']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="ticketing-topology-checks">
                    <label>
                        <input
                            type="checkbox"
                            name="is_primary_path"
                            value="1"
                            checked
                        >
                        مسیر اصلی
                    </label>

                    <label>
                        <input
                            type="checkbox"
                            name="allow_escalation"
                            value="1"
                            checked
                        >
                        Escalation مجاز
                    </label>

                    <label>
                        <input
                            type="checkbox"
                            name="allow_downward_transfer"
                            value="1"
                            checked
                        >
                        انتقال رو به پایین مجاز
                    </label>
                </div>

                <button class="admin-button" type="submit">
                    ثبت ارتباط
                </button>
            </form>

        <?php endif; ?>

        <?php if ($relations !== []): ?>
            <div class="admin-table-wrap ticketing-topology-table">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>سطح بالاتر</th>
                        <th>سطح پایین‌تر</th>
                        <th>Escalation</th>
                        <th>Downward</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($relations as $relation): ?>
                        <tr>
                            <td>
                                <?= ticketing_h(
                                    $relation['parent_title']
                                    . ' / '
                                    . $relation['parent_rank']
                                ) ?>
                            </td>
                            <td>
                                <?= ticketing_h(
                                    $relation['child_title']
                                    . ' / '
                                    . $relation['child_rank']
                                ) ?>
                            </td>
                            <td><?= (int) $relation['allow_escalation'] ? 'بله' : 'خیر' ?></td>
                            <td><?= (int) $relation['allow_downward_transfer'] ? 'بله' : 'خیر' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>


    <section class="admin-section">
        <h2>۴. تیم‌های پشتیبانی</h2>

        <form method="post" action="<?= ticketing_h($action) ?>">
            <input type="hidden" name="_token" value="<?= ticketing_h($csrf) ?>">
            <input type="hidden" name="action" value="team.create">

            <div class="admin-form-grid">
                <label>
                    <span>عنوان تیم</span>
                    <input type="text" name="title" maxlength="255" required>
                </label>

                <label>
                    <span>کد تیم</span>
                    <input type="text" name="code" maxlength="100" dir="ltr" required>
                </label>
            </div>

            <button class="admin-button" type="submit">
                افزودن تیم
            </button>
        </form>

        <?php if ($teams !== []): ?>
            <div class="admin-table-wrap ticketing-topology-table">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>عنوان</th>
                        <th>کد</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($teams as $team): ?>
                        <tr>
                            <td><?= ticketing_h($team['title']) ?></td>
                            <td dir="ltr"><?= ticketing_h($team['code']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>


    <section class="admin-section">
        <h2>۵. اتصال تیم به گره</h2>

        <?php if ($teams !== [] && $nodes !== []): ?>
            <form method="post" action="<?= ticketing_h($action) ?>">
                <input type="hidden" name="_token" value="<?= ticketing_h($csrf) ?>">
                <input type="hidden" name="action" value="team_node.bind">

                <div class="admin-form-grid">
                    <label>
                        <span>تیم</span>
                        <select name="team_id" required>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?= ticketing_h($team['id']) ?>">
                                    <?= ticketing_h($team['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>گره</span>
                        <select name="node_id" required>
                            <?php foreach ($nodes as $node): ?>
                                <option value="<?= ticketing_h($node['id']) ?>">
                                    <?= ticketing_h(
                                        $node['title']
                                        . ' - '
                                        . $node['layer_title']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <button class="admin-button" type="submit">
                    اتصال تیم به گره
                </button>
            </form>
        <?php endif; ?>

        <?php if ($teamNodes !== []): ?>
            <div class="admin-table-wrap ticketing-topology-table">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>تیم</th>
                        <th>گره</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($teamNodes as $row): ?>
                        <tr>
                            <td><?= ticketing_h($row['team_title']) ?></td>
                            <td><?= ticketing_h($row['node_title']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>


    <section class="admin-section">
        <h2>۶. صف‌های پشتیبانی</h2>

        <?php if ($nodes !== []): ?>
            <form method="post" action="<?= ticketing_h($action) ?>">
                <input type="hidden" name="_token" value="<?= ticketing_h($csrf) ?>">
                <input type="hidden" name="action" value="queue.create">

                <div class="admin-form-grid">
                    <label>
                        <span>گره</span>
                        <select name="node_id" required>
                            <?php foreach ($nodes as $node): ?>
                                <option value="<?= ticketing_h($node['id']) ?>">
                                    <?= ticketing_h(
                                        $node['title']
                                        . ' - '
                                        . $node['layer_title']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>عنوان صف</span>
                        <input type="text" name="title" maxlength="255" required>
                    </label>

                    <label>
                        <span>کد</span>
                        <input type="text" name="code" maxlength="100" dir="ltr" required>
                    </label>

                    <label>
                        <span>روش Assignment</span>
                        <select name="assignment_mode_code">
                            <option value="manual">دستی</option>
                            <option value="least_loaded">کم‌بارترین کارشناس</option>
                            <option value="round_robin">Round Robin</option>
                            <option value="rule_based">Rule Based</option>
                        </select>
                    </label>

                    <label>
                        <span>حداکثر تیکت باز برای هر کارشناس</span>
                        <input
                            type="number"
                            name="max_open_per_agent"
                            min="1"
                        >
                    </label>
                </div>

                <label>
                    <input type="checkbox" name="is_default" value="1">
                    صف پیش‌فرض گره
                </label>

                <br><br>

                <button class="admin-button" type="submit">
                    افزودن صف
                </button>
            </form>
        <?php endif; ?>

        <?php if ($queues !== []): ?>
            <div class="admin-table-wrap ticketing-topology-table">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>صف</th>
                        <th>گره</th>
                        <th>لایه</th>
                        <th>روش تخصیص</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($queues as $queue): ?>
                        <tr>
                            <td><?= ticketing_h($queue['title']) ?></td>
                            <td><?= ticketing_h($queue['node_title']) ?></td>
                            <td><?= ticketing_h($queue['layer_title']) ?></td>
                            <td dir="ltr"><?= ticketing_h($queue['assignment_mode_code']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>


    <section class="admin-section">
        <h2>۷. اتصال تیم به صف</h2>

        <?php if ($teams !== [] && $queues !== []): ?>
            <form method="post" action="<?= ticketing_h($action) ?>">
                <input type="hidden" name="_token" value="<?= ticketing_h($csrf) ?>">
                <input type="hidden" name="action" value="team_queue.bind">

                <div class="admin-form-grid">
                    <label>
                        <span>تیم</span>
                        <select name="team_id" required>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?= ticketing_h($team['id']) ?>">
                                    <?= ticketing_h($team['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>صف</span>
                        <select name="queue_id" required>
                            <?php foreach ($queues as $queue): ?>
                                <option value="<?= ticketing_h($queue['id']) ?>">
                                    <?= ticketing_h(
                                        $queue['title']
                                        . ' - '
                                        . $queue['node_title']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <button class="admin-button" type="submit">
                    اتصال تیم به صف
                </button>
            </form>
        <?php endif; ?>

        <?php if ($teamQueues !== []): ?>
            <div class="admin-table-wrap ticketing-topology-table">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>تیم</th>
                        <th>صف</th>
                        <th>گره</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($teamQueues as $row): ?>
                        <tr>
                            <td><?= ticketing_h($row['team_title']) ?></td>
                            <td><?= ticketing_h($row['queue_title']) ?></td>
                            <td><?= ticketing_h($row['node_title']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>


    <section class="admin-section">
        <h2>۸. کارشناسان تیم</h2>

        <?php if (
            $teams !== []
            && $staffCandidates !== []
        ): ?>
            <form method="post" action="<?= ticketing_h($action) ?>">
                <input type="hidden" name="_token" value="<?= ticketing_h($csrf) ?>">
                <input type="hidden" name="action" value="team_member.add">

                <div class="admin-form-grid">
                    <label>
                        <span>تیم</span>
                        <select name="team_id" required>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?= ticketing_h($team['id']) ?>">
                                    <?= ticketing_h($team['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>کارشناس</span>
                        <select name="project_member_id" required>
                            <?php foreach ($staffCandidates as $member): ?>
                                <option value="<?= ticketing_h($member['id']) ?>">
                                    <?= ticketing_h(
                                        $member['display_name_snapshot']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>نقش</span>
                        <select name="staff_role_code">
                            <option value="agent">Agent</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="manager">Manager</option>
                            <option value="observer">Observer</option>
                        </select>
                    </label>

                    <label>
                        <span>وزن بار کاری</span>
                        <input
                            type="number"
                            name="workload_weight"
                            min="0.1"
                            step="0.1"
                            value="1"
                        >
                    </label>
                </div>

                <button class="admin-button" type="submit">
                    افزودن کارشناس
                </button>
            </form>
        <?php else: ?>
            <div class="admin-muted">
                برای افزودن کارشناس، عضو پروژه باید به حساب Core متصل باشد.
            </div>
        <?php endif; ?>

        <?php if ($teamMembers !== []): ?>
            <div class="admin-table-wrap ticketing-topology-table">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>کارشناس</th>
                        <th>تیم</th>
                        <th>نقش</th>
                        <th>Assign</th>
                        <th>Assist</th>
                        <th>Take Over</th>
                        <th>Transfer</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($teamMembers as $member): ?>
                        <tr>
                            <td><?= ticketing_h($member['member_name']) ?></td>
                            <td><?= ticketing_h($member['team_title']) ?></td>
                            <td dir="ltr"><?= ticketing_h($member['staff_role_code']) ?></td>
                            <td><?= (int) $member['can_assign'] ? 'بله' : '—' ?></td>
                            <td><?= (int) $member['can_assist'] ? 'بله' : '—' ?></td>
                            <td><?= (int) $member['can_takeover'] ? 'بله' : '—' ?></td>
                            <td><?= (int) $member['can_transfer'] ? 'بله' : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

</div>


<style>
.ticketing-topology-checks {
    display: flex;
    flex-wrap: wrap;
    gap: 14px 24px;
    margin: 16px 0;
}

.ticketing-topology-checks label {
    display: inline-flex;
    align-items: center;
    gap: 7px;
}

.ticketing-topology-table {
    margin-top: 20px;
}

.ticketing-topology-table table {
    min-width: 720px;
}
</style>

<?php

$content =
    ob_get_clean()
    ?: '';

require __DIR__ . '/layout.php';
