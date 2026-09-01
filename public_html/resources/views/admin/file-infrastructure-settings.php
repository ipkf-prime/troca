<?php

if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars(
            (string) (
                $value
                ?? ''
            ),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
            false
        );
    }
}

$fileInfrastructure =
    is_array(
        $fileInfrastructure
        ?? null
    )
        ? $fileInfrastructure
        : [];

$result =
    is_array(
        $result
        ?? null
    )
        ? $result
        : [];

$posted =
    is_array(
        $result['input']
        ?? null
    )
        ? $result['input']
        : [];

$storageRoot =
    array_key_exists(
        'storage_root',
        $posted
    )
        ? (string) $posted[
            'storage_root'
        ]
        : (string) (
            $fileInfrastructure[
                'storage_root_configured'
            ]
            ?? ''
        );

$scannerBinary =
    array_key_exists(
        'clamav_binary_path',
        $posted
    )
        ? (string) $posted[
            'clamav_binary_path'
        ]
        : (string) (
            $fileInfrastructure[
                'scanner_binary_configured'
            ]
            ?? ''
        );

$scanTimeout =
    array_key_exists(
        'scan_timeout_seconds',
        $posted
    )
        ? (int) $posted[
            'scan_timeout_seconds'
        ]
        : (int) (
            $fileInfrastructure[
                'scan_timeout_seconds'
            ]
            ?? 45
        );

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

    <a href="/admin/settings">
        تنظیمات
    </a>

    <span>/</span>

    <span>
        زیرساخت فایل و آنتی‌ویروس
    </span>
</nav>


<?php if ($result !== []): ?>

    <div
        class="admin-alert admin-alert--<?= ($result['ok'] ?? false) ? 'success' : 'danger' ?>"
    >
        <?= admin_h(
            (string) (
                $result['message']
                ?? 'نتیجه عملیات مشخص نیست.'
            )
        ) ?>
    </div>

<?php endif; ?>


<section class="admin-section">

    <div class="admin-panel-heading">

        <div>

            <h2>
                زیرساخت مشترک فایل و آنتی‌ویروس
            </h2>

            <p>
                تنظیم مشترک فضای فایل خصوصی و موتور بررسی بدافزار
                برای ماژول‌های سامانه.
            </p>

        </div>

    </div>


    <div class="admin-record-table-wrap">

        <table class="admin-table admin-record-table">

            <thead>
            <tr>
                <th>مولفه</th>
                <th>درایور</th>
                <th>مقدار مؤثر فعلی</th>
                <th>منبع</th>
            </tr>
            </thead>

            <tbody>

            <tr>

                <td>
                    فضای ذخیره‌سازی خصوصی
                </td>

                <td dir="ltr">
                    <?= admin_h(
                        $fileInfrastructure[
                            'storage_driver'
                        ]
                        ?? 'filesystem'
                    ) ?>
                </td>

                <td>
                    <code dir="ltr">
                        <?= admin_h(
                            $fileInfrastructure[
                                'storage_root_effective'
                            ]
                            ?? 'پیش‌فرض قدیمی هر ماژول'
                        ) ?>
                    </code>
                </td>

                <td dir="ltr">
                    <?= admin_h(
                        $fileInfrastructure[
                            'storage_root_source'
                        ]
                        ?? 'unknown'
                    ) ?>
                </td>

            </tr>


            <tr>

                <td>
                    موتور بررسی بدافزار
                </td>

                <td dir="ltr">
                    <?= admin_h(
                        $fileInfrastructure[
                            'scanner_driver'
                        ]
                        ?? 'clamav_process'
                    ) ?>
                </td>

                <td>
                    <code dir="ltr">
                        <?= admin_h(
                            $fileInfrastructure[
                                'scanner_binary_effective'
                            ]
                            ?? 'پیدا نشد'
                        ) ?>
                    </code>
                </td>

                <td dir="ltr">
                    <?= admin_h(
                        $fileInfrastructure[
                            'scanner_binary_source'
                        ]
                        ?? 'unknown'
                    ) ?>
                </td>

            </tr>


            <tr>

                <td>
                    مهلت اسکن
                </td>

                <td dir="ltr">
                    seconds
                </td>

                <td>
                    <?= admin_h(
                        \App\Support\AdminFormat::digits(
                            (string) (
                                $fileInfrastructure[
                                    'scan_timeout_seconds'
                                ]
                                ?? 45
                            )
                        )
                    ) ?>
                </td>

                <td dir="ltr">
                    <?= admin_h(
                        $fileInfrastructure[
                            'scan_timeout_source'
                        ]
                        ?? 'unknown'
                    ) ?>
                </td>

            </tr>

            </tbody>

        </table>

    </div>

</section>


<section class="admin-section">

    <div class="admin-panel-heading">

        <div>

            <h3>
                تنظیمات اجرایی
            </h3>

            <p>
                برای استفاده از NAS، NFS یا File Server دیگر،
                فضای مقصد را روی سیستم Mount کرده و مسیر Mount را ثبت کنید.
            </p>

        </div>

    </div>


    <form
        method="post"
        action="/admin/settings/file-infrastructure"
    >

        <input
            type="hidden"
            name="_token"
            value="<?= admin_h(
                (new \IPKF\Security\Csrf())->token()
            ) ?>"
        >


        <div class="admin-form-grid">

            <label>

                <span>
                    درایور ذخیره‌سازی
                </span>

                <input
                    type="text"
                    value="filesystem / mounted filesystem"
                    readonly
                    dir="ltr"
                >

                <small class="admin-field-help">
                    فضای محلی یا فضای Mount شده قابل استفاده است.
                </small>

            </label>


            <label>

                <span>
                    مسیر پایه فضای خصوصی
                </span>

                <input
                    type="text"
                    name="storage_root"
                    value="<?= admin_h(
                        $storageRoot
                    ) ?>"
                    dir="ltr"
                    autocomplete="off"
                    placeholder="/home/troca/ipkf-private-files"
                >

                <small class="admin-field-help">
                    خالی بودن یعنی استفاده از ENV یا مسیر سازگار قدیمی ماژول.
                    مسیر نباید داخل Document Root باشد.
                </small>

            </label>


            <label>

                <span>
                    درایور آنتی‌ویروس
                </span>

                <input
                    type="text"
                    value="clamav_process"
                    readonly
                    dir="ltr"
                >

            </label>


            <label>

                <span>
                    مسیر clamscan
                </span>

                <input
                    type="text"
                    name="clamav_binary_path"
                    value="<?= admin_h(
                        $scannerBinary
                    ) ?>"
                    dir="ltr"
                    autocomplete="off"
                    placeholder="خالی = تشخیص خودکار"
                >

                <small class="admin-field-help">
                    ENV مشترک، تنظیم قدیمی Automation، PATH و
                    مسیرهای استاندارد به‌ترتیب بررسی می‌شوند.
                </small>

            </label>


            <label class="admin-field--compact">

                <span>
                    مهلت اسکن
                </span>

                <input
                    type="number"
                    name="scan_timeout_seconds"
                    value="<?= admin_h(
                        (string) $scanTimeout
                    ) ?>"
                    min="5"
                    max="300"
                    dir="ltr"
                >

                <small class="admin-field-help">
                    بین ۵ تا ۳۰۰ ثانیه
                </small>

            </label>

        </div>


        <div class="admin-form-actions">

            <button
                class="admin-button"
                type="submit"
                name="action"
                value="save"
            >
                ذخیره تنظیمات
            </button>

            <button
                class="admin-button admin-button--soft"
                type="submit"
                name="action"
                value="test_storage"
            >
                آزمون فضای ذخیره‌سازی
            </button>

            <button
                class="admin-button admin-button--soft"
                type="submit"
                name="action"
                value="test_scanner"
            >
                آزمون آنتی‌ویروس
            </button>

            <a
                class="admin-button admin-button--soft"
                href="/admin/settings"
            >
                بازگشت
            </a>

        </div>

    </form>

</section>


<section class="admin-section">

    <div class="admin-alert admin-alert--warning">

        سیاست امنیتی Fail-Closed از پنل قابل غیرفعال‌کردن نیست.
        اتصال Automation، Ticketing و Work به این زیرساخت
        در مرحله بعد انجام می‌شود.

    </div>

</section>

<?php

$content =
    ob_get_clean();

include __DIR__
    . '/layout.php';
