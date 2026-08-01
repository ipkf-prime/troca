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
$form = $page['form'] ?? [];
$errors = $errors ?? [];
$status = (string) ($status ?? '');
$provinces = $page['provinces'] ?? [];
$counties = $page['counties'] ?? [];
$cities = $page['cities'] ?? [];
$addressTypes = $page['address_types'] ?? [];

ob_start();
?>
<style>
.self-profile-form {
    display: grid;
    gap: .75rem;
}

.self-profile-grid {
    display: grid;
    gap: .7rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.self-profile-field {
    display: grid;
    gap: .25rem;
}

.self-profile-field span {
    font-size: .72rem;
    font-weight: 800;
}

.self-profile-field input,
.self-profile-field select,
.self-profile-field textarea {
    min-height: 2.55rem;
    width: 100%;
}

.self-profile-field textarea {
    min-height: 6.5rem;
    resize: vertical;
}

.self-profile-wide {
    grid-column: 1 / -1;
}

@media (max-width: 950px) {
    .self-profile-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .self-profile-grid {
        grid-template-columns: 1fr;
    }

    .self-profile-wide {
        grid-column: auto;
    }
}
</style>

<div class="account-shell">
    <?php require __DIR__ . '/partials/account-nav.php'; ?>

    <?php if ($status === 'saved'): ?>
        <div class="admin-alert admin-alert--success">
            اطلاعات هویتی و نشانی ذخیره شد.
        </div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="admin-alert admin-alert--danger">
            <strong>ذخیره انجام نشد.</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= admin_h($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form
        class="self-profile-form"
        method="post"
        action="/admin/profile/edit"
    >
        <input
            type="hidden"
            name="_token"
            value="<?= admin_h(
                (new \IPKF\Security\Csrf())->token()
            ) ?>"
        >

        <section class="account-card">
            <div class="account-card__head">
                <div>
                    <h2>اطلاعات هویتی من</h2>
                    <p>
                        هر کاربر می‌تواند پرونده هویتی خودش را مشاهده و اصلاح کند.
                    </p>
                </div>
            </div>

            <div class="self-profile-grid">
                <label class="self-profile-field">
                    <span>نام</span>
                    <input
                        name="first_name"
                        value="<?= admin_h(
                            $form['first_name'] ?? ''
                        ) ?>"
                        maxlength="100"
                        required
                    >
                </label>

                <label class="self-profile-field">
                    <span>نام خانوادگی</span>
                    <input
                        name="last_name"
                        value="<?= admin_h(
                            $form['last_name'] ?? ''
                        ) ?>"
                        maxlength="100"
                        required
                    >
                </label>

                <label class="self-profile-field">
                    <span>نوع شخص</span>
                    <input
                        value="<?= admin_h(
                            ($form['person_type'] ?? 'individual')
                                === 'legal'
                                ? 'شخص حقوقی'
                                : 'شخص حقیقی'
                        ) ?>"
                        readonly
                    >
                </label>

                <label class="self-profile-field">
                    <span>کد ملی</span>
                    <input
                        name="national_code"
                        value="<?= admin_h(
                            $form['national_code'] ?? ''
                        ) ?>"
                        maxlength="10"
                        inputmode="numeric"
                        dir="ltr"
                    >
                </label>

                <label class="self-profile-field">
                    <span>نام پدر</span>
                    <input
                        name="father_name"
                        value="<?= admin_h(
                            $form['father_name'] ?? ''
                        ) ?>"
                        maxlength="100"
                    >
                </label>

                <label class="self-profile-field">
                    <span>تاریخ تولد شمسی</span>
                    <input
                        name="birth_date_jalali"
                        value="<?= admin_h(
                            $form['birth_date_jalali'] ?? ''
                        ) ?>"
                        placeholder="۱۴۰۰/۰۱/۰۱"
                        inputmode="numeric"
                        dir="ltr"
                    >
                </label>

                <label class="self-profile-field">
                    <span>محل تولد</span>
                    <input
                        name="birth_place"
                        value="<?= admin_h(
                            $form['birth_place'] ?? ''
                        ) ?>"
                        maxlength="150"
                    >
                </label>

                <label class="self-profile-field">
                    <span>شماره شناسنامه</span>
                    <input
                        name="identity_number"
                        value="<?= admin_h(
                            $form['identity_number'] ?? ''
                        ) ?>"
                        maxlength="50"
                        dir="ltr"
                    >
                </label>

                <label class="self-profile-field">
                    <span>سریال شناسنامه</span>
                    <input
                        name="identity_serial"
                        value="<?= admin_h(
                            $form['identity_serial'] ?? ''
                        ) ?>"
                        maxlength="50"
                        dir="ltr"
                    >
                </label>
            </div>
        </section>

        <section class="account-card">
            <div class="account-card__head">
                <div>
                    <h3>نشانی اصلی من</h3>
                    <p>
                        اطلاعات جغرافیایی و نشانی در پرونده شخص ذخیره می‌شود.
                    </p>
                </div>
            </div>

            <?php if ($provinces === []): ?>
                <div class="admin-alert admin-alert--danger">
                    داده جغرافیایی فعال پیدا نشد. Migration و داده‌های مرجع جغرافیا را بررسی کنید.
                </div>
            <?php endif; ?>

            <div class="self-profile-grid">
                <label class="self-profile-field">
                    <span>نوع نشانی</span>
                    <select name="address_type_id">
                        <option value="0">انتخاب نشده</option>
                        <?php foreach ($addressTypes as $option): ?>
                            <option
                                value="<?= (int) (
                                    $option['id'] ?? 0
                                ) ?>"
                                <?= (int) (
                                    $form['address_type_id'] ?? 0
                                ) === (int) (
                                    $option['id'] ?? 0
                                ) ? 'selected' : '' ?>
                            >
                                <?= admin_h(
                                    $option['title'] ?? ''
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="self-profile-field">
                    <span>استان</span>
                    <select name="province_location_id" data-province>
                        <option value="0">انتخاب نشده</option>
                        <?php foreach ($provinces as $option): ?>
                            <option
                                value="<?= (int) (
                                    $option['id'] ?? 0
                                ) ?>"
                                <?= (int) (
                                    $form['province_location_id'] ?? 0
                                ) === (int) (
                                    $option['id'] ?? 0
                                ) ? 'selected' : '' ?>
                            >
                                <?= admin_h(
                                    $option['title'] ?? ''
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="self-profile-field">
                    <span>شهرستان</span>
                    <select name="county_location_id" data-county>
                        <option value="0">انتخاب نشده</option>
                        <?php foreach ($counties as $option): ?>
                            <option
                                value="<?= (int) (
                                    $option['id'] ?? 0
                                ) ?>"
                                data-province-id="<?= (int) (
                                    $option['province_location_id'] ?? 0
                                ) ?>"
                                <?= (int) (
                                    $form['county_location_id'] ?? 0
                                ) === (int) (
                                    $option['id'] ?? 0
                                ) ? 'selected' : '' ?>
                            >
                                <?= admin_h(
                                    $option['title'] ?? ''
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="self-profile-field">
                    <span>شهر</span>
                    <select name="city_location_id" data-city>
                        <option value="0">انتخاب نشده</option>
                        <?php foreach ($cities as $option): ?>
                            <option
                                value="<?= (int) (
                                    $option['id'] ?? 0
                                ) ?>"
                                data-province-id="<?= (int) (
                                    $option['province_location_id'] ?? 0
                                ) ?>"
                                data-county-id="<?= (int) (
                                    $option['county_location_id'] ?? 0
                                ) ?>"
                                <?= (int) (
                                    $form['city_location_id'] ?? 0
                                ) === (int) (
                                    $option['id'] ?? 0
                                ) ? 'selected' : '' ?>
                            >
                                <?= admin_h(
                                    $option['title'] ?? ''
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="self-profile-field">
                    <span>ناحیه یا محله</span>
                    <input
                        name="district"
                        value="<?= admin_h(
                            $form['district'] ?? ''
                        ) ?>"
                        maxlength="150"
                    >
                </label>

                <label class="self-profile-field">
                    <span>کد پستی</span>
                    <input
                        name="postal_code"
                        value="<?= admin_h(
                            $form['postal_code'] ?? ''
                        ) ?>"
                        maxlength="10"
                        inputmode="numeric"
                        dir="ltr"
                    >
                </label>

                <label class="self-profile-field self-profile-wide">
                    <span>نشانی کامل</span>
                    <textarea
                        name="address_line"
                        maxlength="500"
                    ><?= admin_h(
                        $form['address_line'] ?? ''
                    ) ?></textarea>
                </label>
            </div>
        </section>

        <div class="account-actions">
            <button class="admin-button" type="submit">
                ذخیره اطلاعات
            </button>
            <a
                class="admin-button admin-button--soft"
                href="/admin/profile"
            >
                انصراف
            </a>
        </div>
    </form>
</div>

<script>
(() => {
    const scope = document.querySelector(
        '.account-shell'
    );
    if (!scope) {
        return;
    }
    const province = scope.querySelector(
        '[data-province]'
    );
    const county = scope.querySelector(
        '[data-county]'
    );
    const city = scope.querySelector(
        '[data-city]'
    );

    const buildLocationCascade = (
        province,
        county,
        city
    ) => {
        if (!province || !county || !city) {
            return;
        }

        const countyPlaceholder =
            county.options[0]?.cloneNode(true)
            ?? new Option('انتخاب نشده', '0');
        const cityPlaceholder =
            city.options[0]?.cloneNode(true)
            ?? new Option('انتخاب نشده', '0');

        const countyOptions = [
            ...county.options,
        ]
            .filter(option => option.value !== '0')
            .map(option => ({
                value: option.value,
                label: option.textContent ?? '',
                provinceId:
                    option.dataset.provinceId ?? '0',
            }));

        const cityOptions = [
            ...city.options,
        ]
            .filter(option => option.value !== '0')
            .map(option => ({
                value: option.value,
                label: option.textContent ?? '',
                provinceId:
                    option.dataset.provinceId ?? '0',
                countyId:
                    option.dataset.countyId ?? '0',
            }));

        const replaceOptions = (
            select,
            placeholder,
            items,
            selectedValue
        ) => {
            select.replaceChildren(
                placeholder.cloneNode(true)
            );

            items.forEach(item => {
                const option = new Option(
                    item.label,
                    item.value,
                    false,
                    item.value === selectedValue
                );
                option.dataset.provinceId =
                    item.provinceId ?? '0';
                option.dataset.countyId =
                    item.countyId ?? '0';
                select.append(option);
            });

            if (
                selectedValue !== '0'
                && !items.some(
                    item => item.value === selectedValue
                )
            ) {
                select.value = '0';
            }
        };

        const refreshCounties = (
            preserveSelection = true
        ) => {
            const provinceId =
                province.value || '0';
            const selectedCounty = preserveSelection
                ? county.value || '0'
                : '0';

            const filtered = provinceId === '0'
                ? []
                : countyOptions.filter(
                    item =>
                        item.provinceId === provinceId
                );

            countyPlaceholder.textContent =
                provinceId === '0'
                    ? 'ابتدا استان را انتخاب کنید'
                    : (
                        filtered.length > 0
                            ? 'انتخاب نشده'
                            : 'شهرستانی ثبت نشده است'
                    );

            replaceOptions(
                county,
                countyPlaceholder,
                filtered,
                selectedCounty
            );

            county.disabled =
                provinceId === '0'
                || filtered.length === 0;
        };

        const refreshCities = (
            preserveSelection = true
        ) => {
            const provinceId =
                province.value || '0';
            const countyId =
                county.value || '0';
            const selectedCity = preserveSelection
                ? city.value || '0'
                : '0';

            const filtered =
                provinceId === '0'
                || countyId === '0'
                    ? []
                    : cityOptions.filter(
                        item =>
                            item.provinceId
                                === provinceId
                            && item.countyId
                                === countyId
                    );

            cityPlaceholder.textContent =
                countyId === '0'
                    ? 'ابتدا شهرستان را انتخاب کنید'
                    : (
                        filtered.length > 0
                            ? 'انتخاب نشده'
                            : 'شهری ثبت نشده است'
                    );

            replaceOptions(
                city,
                cityPlaceholder,
                filtered,
                selectedCity
            );

            city.disabled =
                countyId === '0'
                || filtered.length === 0;
        };

        province.addEventListener(
            'change',
            () => {
                refreshCounties(false);
                refreshCities(false);
            }
        );

        county.addEventListener(
            'change',
            () => refreshCities(false)
        );

        refreshCounties(true);
        refreshCities(true);
    };

    buildLocationCascade(
        province,
        county,
        city
    );
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
