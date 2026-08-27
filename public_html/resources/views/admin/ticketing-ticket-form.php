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

$form =
    $form
    ?? [];

$options =
    $options
    ?? [];

$errors =
    $errors
    ?? [];

ob_start();
?>

<nav
    class="admin-breadcrumb"
    aria-label="breadcrumb"
>
    <a href="/admin/dashboard">
        داشبورد
    </a>
    <span>/</span>

    <a href="/admin/ticketing">
        پشتیبانی و تیکتینگ
    </a>
    <span>/</span>

    <a href="/admin/ticketing/tickets">
        تیکت‌های من
    </a>
    <span>/</span>

    <span>
        تیکت جدید
    </span>
</nav>


<div class="admin-page ticketing-page ticketing-form-page">

    <div class="admin-page-header ticketing-page-head">
        <div>
            <h1>
                ثبت تیکت جدید
            </h1>

            <p>
                موضوع و شرح درخواست را
                ثبت کنید.
            </p>
        </div>
    </div>


    <?php if ($errors !== []): ?>

        <section class="admin-section">
            <div
                class="admin-alert admin-alert--danger"
                role="alert"
            >
                <strong>
                    ثبت تیکت انجام نشد.
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


    <section class="admin-section ticketing-form-section">

        <form
            class="ticketing-create-form"
            method="post"
            action="/admin/ticketing/tickets"
         enctype="multipart/form-data">
            <input
                type="hidden"
                name="_token"
                value="<?= ticketing_h(
                    (
                        new \IPKF\Security\Csrf()
                    )->token()
                ) ?>"
            >


            <div class="admin-form-grid">

                <label>
                    <span>
                        پروژه پشتیبانی
                    </span>

                    <select
                        name="support_project_id"
                        id="ticket-support-project"
                        required
                    >
                        <?php foreach (
                            $options['projects']
                            ?? []
                            as $id => $project
                        ): ?>
                            <option
                                value="<?= ticketing_h($id) ?>"
                                <?= (string) (
                                    $form[
                                        'support_project_id'
                                    ]
                                    ?? ''
                                ) === (string) $id
                                    ? ' selected'
                                    : '' ?>
                            >
                                <?= ticketing_h(
                                    $project['title']
                                    ?? ''
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>


                <label>
                    <span>
                        سرویس
                    </span>

                    <select
                        name="support_service_id"
                        id="ticket-support-service"
                        required
                    >
                        <?php foreach (
                            $options['services']
                            ?? []
                            as $id => $service
                        ): ?>
                            <option
                                value="<?= ticketing_h($id) ?>"
                                data-project="<?= ticketing_h(
                                    $service['project_id']
                                    ?? ''
                                ) ?>"
                                <?= (string) (
                                    $form[
                                        'support_service_id'
                                    ]
                                    ?? ''
                                ) === (string) $id
                                    ? ' selected'
                                    : '' ?>
                            >
                                <?= ticketing_h(
                                    $service['title']
                                    ?? ''
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>


                <label class="admin-form-grid__wide">
                    <span>
                        عنوان تیکت
                    </span>

                    <input
                        type="text"
                        name="subject"
                        value="<?= ticketing_h(
                            $form['subject']
                            ?? ''
                        ) ?>"
                        maxlength="500"
                        required
                        autofocus
                        placeholder="خلاصه مشکل یا درخواست"
                    >
                </label>


                <label>
                    <span>
                        دسته‌بندی
                    </span>

                    <select
                        name="category_id"
                        required
                    >
                        <?php foreach (
                            $options['categories']
                            ?? []
                            as $id => $label
                        ): ?>

                            <option
                                value="<?= ticketing_h($id) ?>"
                                <?= (string) (
                                    $form['category_id']
                                    ?? ''
                                ) === (string) $id
                                    ? ' selected'
                                    : '' ?>
                            >
                                <?= ticketing_h($label) ?>
                            </option>

                        <?php endforeach; ?>
                    </select>
                </label>


                <label>
                    <span>
                        اولویت
                    </span>

                    <select
                        name="priority_code"
                        required
                    >
                        <?php foreach (
                            $options['priorities']
                            ?? []
                            as $code => $label
                        ): ?>

                            <option
                                value="<?= ticketing_h($code) ?>"
                                <?= (string) (
                                    $form[
                                        'priority_code'
                                    ]
                                    ?? 'normal'
                                ) === (string) $code
                                    ? ' selected'
                                    : '' ?>
                            >
                                <?= ticketing_h($label) ?>
                            </option>

                        <?php endforeach; ?>
                    </select>
                </label>


                <label class="admin-form-grid__wide">
                    <span>
                        شرح درخواست
                    </span>

                    <textarea
                        name="body"
                        rows="6"
                        maxlength="20000"
                        required
                        placeholder="شرح کامل مشکل، مراحل وقوع و اطلاعات مورد نیاز برای بررسی"
                    ><?= ticketing_h(
                        $form['body']
                        ?? ''
                    ) ?></textarea>
                </label>

            </div>



    <section class="ticketing-form-section">
        <header>
            <h3>پیوست‌ها</h3>
            <p>
                فایل‌های مرتبط با درخواست را می‌توانید
                همراه تیکت ارسال کنید.
            </p>
        </header>

        <label>
            <span>فایل‌های پیوست</span>

            <input
                type="file"
                name="attachments[]"
                multiple
                accept=".pdf,.jpg,.jpeg,.png,.webp,.txt,.log,.csv,.doc,.docx,.xls,.xlsx,.zip"
            >

            <small class="admin-muted">
                حداکثر ۵ فایل؛ هر فایل حداکثر ۱۰ مگابایت
                و مجموع فایل‌ها حداکثر ۲۵ مگابایت.
            </small>
        </label>
    </section>

<div class="admin-form-actions">

                <button
                    class="admin-button"
                    type="submit"
                >
                    ثبت تیکت
                </button>

                <a
                    class="admin-button admin-button--soft"
                    href="/admin/ticketing/tickets"
                >
                    انصراف
                </a>

            </div>

        </form>

    </section>

</div>

<script>
(function () {
    const project =
        document.getElementById(
            'ticket-support-project'
        );

    const service =
        document.getElementById(
            'ticket-support-service'
        );

    if (!project || !service) {
        return;
    }

    function syncServices() {
        const projectId =
            String(project.value);

        let firstVisible = null;
        let selectedVisible = false;

        Array.from(
            service.options
        ).forEach(function (option) {
            const visible =
                String(
                    option.dataset.project
                    || ''
                ) === projectId;

            option.hidden =
                !visible;

            option.disabled =
                !visible;

            if (visible && firstVisible === null) {
                firstVisible = option;
            }

            if (
                visible
                && option.selected
            ) {
                selectedVisible = true;
            }
        });

        if (
            !selectedVisible
            && firstVisible
        ) {
            firstVisible.selected = true;
        }
    }

    project.addEventListener(
        'change',
        syncServices
    );

    syncServices();
})();
</script>

<?php
$content =
    ob_get_clean()
    ?: '';

require __DIR__ . '/layout.php';
