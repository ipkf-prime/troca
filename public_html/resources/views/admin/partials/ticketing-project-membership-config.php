<?php

declare(strict_types=1);

$membershipForm = [];

try {
    $membershipForm =
        (
            new \App\Services\Ticketing\SupportProjectMembershipConfigurationService()
        )->form(
            $reference
        );

} catch (\Throwable) {

    $membershipForm =
        [];
}

if (!is_array($membershipForm)) {
    $membershipForm = [];
}

$status =
    strtolower(
        trim(
            (string) (
                $_GET[
                    'membership_status'
                ]
                ?? ''
            )
        )
    );

$fields =
    is_array(
        $membershipForm[
            'membership_fields'
        ]
        ?? null
    )
        ? array_values(
            $membershipForm[
                'membership_fields'
            ]
        )
        : [];

$fieldTypes = [
    'text' =>
        'متن کوتاه',

    'textarea' =>
        'متن چندخطی',

    'number' =>
        'عدد',

    'date' =>
        'تاریخ',

    'select' =>
        'انتخاب تکی',

    'multiselect' =>
        'انتخاب چندگانه',

    'boolean' =>
        'بله / خیر',

    'file' =>
        'فایل',

    'lookup' =>
        'منبع داده',
];
?>


<header class="ticketing-membership-header">
    <div>
        <h2>
            عضویت کاربران
        </h2>

        <p class="admin-muted">
            سیاست ورود کاربران و اطلاعات موردنیاز
            برای عضویت در این پروژه را مشخص کنید.
        </p>
    </div>

    <!-- TICKETING_REQUESTER_MANAGER_MEMBERS_LINK -->
    <!-- TICKETING_PROJECT_MEMBER_ACCESS_CENTER_LINK -->
    <a
        class="admin-button admin-button--soft"
        href="/admin/ticketing/projects/<?= rawurlencode($reference) ?>/members"
    >
        اعضا و دسترسی‌ها
    </a>
</header>


<?php if ($status === 'saved'): ?>
    <div
        class="admin-alert admin-alert--success"
        role="status"
    >
        تنظیمات عضویت ذخیره شد.
    </div>
<?php elseif ($status === 'invalid'): ?>
    <div
        class="admin-alert admin-alert--danger"
        role="alert"
    >
        اطلاعات تنظیمات عضویت معتبر نیست.
    </div>
<?php endif; ?>


<form
    method="post"
    action="/admin/ticketing/projects/<?= ticketing_h(
        rawurlencode(
            $reference
        )
    ) ?>/membership"
    data-membership-builder
>
    <input
        type="hidden"
        name="_token"
        value="<?= ticketing_h(
            (
                new \IPKF\Security\Csrf()
            )->token()
        ) ?>"
    >


    <div class="ticketing-membership-policy">

        <label>
            <span>
                نوع عضویت
            </span>

            <select
                name="membership_mode"
            >
                <option
                    value="public"
                    <?= (
                        $membershipForm[
                            'membership_mode'
                        ]
                        ?? 'public'
                    ) === 'public'
                        ? 'selected'
                        : '' ?>
                >
                    عمومی
                </option>

                <option
                    value="private"
                    <?= (
                        $membershipForm[
                            'membership_mode'
                        ]
                        ?? ''
                    ) === 'private'
                        ? 'selected'
                        : '' ?>
                >
                    اختصاصی
                </option>
            </select>

            <small>
                پروژه عمومی در فهرست درخواست عضویت
                قابل مشاهده است.
            </small>
        </label>


        <label>
            <span>
                تأیید عضویت
            </span>

            <select
                name="approval_mode"
            >
                <option
                    value="manager"
                    <?= (
                        $membershipForm[
                            'approval_mode'
                        ]
                        ?? 'manager'
                    ) === 'manager'
                        ? 'selected'
                        : '' ?>
                >
                    تأیید مدیر پروژه
                </option>

                <option
                    value="auto"
                    <?= (
                        $membershipForm[
                            'approval_mode'
                        ]
                        ?? ''
                    ) === 'auto'
                        ? 'selected'
                        : '' ?>
                >
                    تأیید خودکار
                </option>
            </select>

            <small>
                در حالت مدیر، درخواست پیش از عضویت
                بررسی می‌شود.
            </small>
        </label>


        <label>
            <span>
                کد دعوت
            </span>

            <span class="ticketing-switch">
                <input
                    type="checkbox"
                    name="invite_enabled"
                    value="1"
                    <?= !empty(
                        $membershipForm[
                            'invite_enabled'
                        ]
                    )
                        ? 'checked'
                        : '' ?>
                >

                <span
                    class="ticketing-switch__track"
                    aria-hidden="true"
                ></span>

                <span class="ticketing-switch__label">
                    فعال باشد
                </span>
            </span>
        </label>


        <label>
            <span>
                فرم تخصصی عضویت
            </span>

            <span class="ticketing-switch">
                <input
                    id="ticketing-membership-form-enabled"
                    type="checkbox"
                    name="form_enabled"
                    value="1"
                    <?= !empty(
                        $membershipForm[
                            'form_enabled'
                        ]
                    )
                        ? 'checked'
                        : '' ?>
                >

                <span
                    class="ticketing-switch__track"
                    aria-hidden="true"
                ></span>

                <span class="ticketing-switch__label">
                    فرم تکمیلی نمایش داده شود
                </span>
            </span>
        </label>

    </div>


    <section
        id="ticketing-membership-fields-wrap"
        class="ticketing-membership-fields"
        <?= !empty(
            $membershipForm[
                'form_enabled'
            ]
        )
            ? ''
            : 'hidden' ?>
    >
        <header class="ticketing-membership-fields__header">
            <div>
                <h3>
                    فیلدهای فرم عضویت
                </h3>

                <p class="admin-muted">
                    فیلدهای موردنیاز کاربران را
                    به‌صورت داینامیک تعریف کنید.
                </p>
            </div>

            <button
                id="ticketing-membership-add-field"
                type="button"
                class="admin-button admin-button--soft"
            >
                افزودن فیلد
            </button>
        </header>


        <div class="ticketing-membership-table-scroll">
            <div class="ticketing-membership-table">

                <div
                    class="ticketing-membership-row ticketing-membership-row--header"
                    aria-hidden="true"
                >
                    <span>عنوان</span>
                    <span>نوع</span>
                    <span>داده / منبع داده</span>
                    <span>وابسته به فیلد</span>
                    <span>ترتیب</span>
                    <span>اجباری</span>
                    <span>عملیات</span>
                </div>


                <div
                    id="ticketing-membership-field-list"
                    class="ticketing-membership-field-list"
                >
                    <?php foreach (
                        $fields
                        as $index => $field
                    ): ?>

                        <?php
                        $key =
                            trim(
                                (string) (
                                    $field[
                                        'field_key'
                                    ]
                                    ?? ''
                                )
                            );

                        $type =
                            trim(
                                (string) (
                                    $field[
                                        'field_type'
                                    ]
                                    ?? 'text'
                                )
                            );

                        $dependency =
                            trim(
                                (string) (
                                    $field[
                                        'dependency_field_key'
                                    ]
                                    ?? ''
                                )
                            );

                        $options =
                            is_array(
                                $field[
                                    'options'
                                ]
                                ?? null
                            )
                                ? implode(
                                    PHP_EOL,
                                    array_map(
                                        'strval',
                                        $field[
                                            'options'
                                        ]
                                    )
                                )
                                : '';
                        ?>

                        <div
                            class="ticketing-membership-row"
                            data-membership-field
                        >

                            <input
                                type="hidden"
                                name="membership_fields[<?= (int) $index ?>][field_key]"
                                value="<?= ticketing_h(
                                    $key
                                ) ?>"
                                data-field-key-input
                            >


                            <input
                                type="text"
                                name="membership_fields[<?= (int) $index ?>][title]"
                                value="<?= ticketing_h(
                                    $field[
                                        'title'
                                    ]
                                    ?? ''
                                ) ?>"
                                maxlength="255"
                                placeholder="عنوان فیلد"
                                aria-label="عنوان"
                                data-field-title
                            >


                            <select
                                name="membership_fields[<?= (int) $index ?>][field_type]"
                                aria-label="نوع"
                                data-field-type
                            >
                                <?php foreach (
                                    $fieldTypes
                                    as $code => $title
                                ): ?>

                                    <option
                                        value="<?= ticketing_h(
                                            $code
                                        ) ?>"
                                        <?= $type === $code
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= ticketing_h(
                                            $title
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>
                            </select>


                            <div class="ticketing-field-data">

                                <input
                                    type="text"
                                    name="membership_fields[<?= (int) $index ?>][data_source_key]"
                                    value="<?= ticketing_h(
                                        $field[
                                            'data_source_key'
                                        ]
                                        ?? ''
                                    ) ?>"
                                    dir="ltr"
                                    maxlength="190"
                                    placeholder="data.source"
                                    aria-label="منبع داده"
                                    data-field-source
                                >


                                <textarea
                                    name="membership_fields[<?= (int) $index ?>][options_text]"
                                    rows="1"
                                    placeholder="هر گزینه در یک سطر"
                                    aria-label="گزینه‌های ثابت"
                                    data-field-options
                                ><?= ticketing_h(
                                    $options
                                ) ?></textarea>


                                <span
                                    class="ticketing-field-data__empty"
                                    data-field-data-empty
                                >
                                    —
                                </span>

                            </div>


                            <select
                                name="membership_fields[<?= (int) $index ?>][dependency_field_key]"
                                aria-label="وابسته به فیلد"
                                data-field-dependency
                                data-current-dependency="<?= ticketing_h(
                                    $dependency
                                ) ?>"
                            >
                                <option value="">
                                    بدون وابستگی
                                </option>

                                <?php foreach (
                                    $fields
                                    as $candidate
                                ): ?>

                                    <?php
                                    $candidateKey =
                                        trim(
                                            (string) (
                                                $candidate[
                                                    'field_key'
                                                ]
                                                ?? ''
                                            )
                                        );

                                    if (
                                        $candidateKey === ''
                                        ||
                                        $candidateKey === $key
                                    ) {
                                        continue;
                                    }
                                    ?>

                                    <option
                                        value="<?= ticketing_h(
                                            $candidateKey
                                        ) ?>"
                                        <?= $candidateKey === $dependency
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= ticketing_h(
                                            $candidate[
                                                'title'
                                            ]
                                            ?? $candidateKey
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>
                            </select>


                            <input
                                type="number"
                                name="membership_fields[<?= (int) $index ?>][sort_order]"
                                min="0"
                                max="100000"
                                value="<?= ticketing_h(
                                    $field[
                                        'sort_order'
                                    ]
                                    ?? (
                                        ($index + 1)
                                        * 10
                                    )
                                ) ?>"
                                aria-label="ترتیب"
                            >


                            <label
                                class="ticketing-switch ticketing-switch--compact"
                                title="اجباری"
                            >
                                <input
                                    type="checkbox"
                                    name="membership_fields[<?= (int) $index ?>][is_required]"
                                    value="1"
                                    <?= !empty(
                                        $field[
                                            'is_required'
                                        ]
                                    )
                                        ? 'checked'
                                        : '' ?>
                                >

                                <span
                                    class="ticketing-switch__track"
                                    aria-hidden="true"
                                ></span>
                            </label>


                            <button
                                type="button"
                                class="ticketing-field-remove"
                                data-membership-remove
                                title="حذف فیلد"
                                aria-label="حذف فیلد"
                            >
                                ×
                            </button>

                        </div>

                    <?php endforeach; ?>
                </div>


                <template
                    id="ticketing-membership-field-template"
                >
                    <div
                        class="ticketing-membership-row"
                        data-membership-field
                    >
                        <input
                            type="hidden"
                            name="membership_fields[__INDEX__][field_key]"
                            value=""
                            data-field-key-input
                        >

                        <input
                            type="text"
                            name="membership_fields[__INDEX__][title]"
                            maxlength="255"
                            placeholder="عنوان فیلد"
                            aria-label="عنوان"
                            data-field-title
                        >

                        <select
                            name="membership_fields[__INDEX__][field_type]"
                            aria-label="نوع"
                            data-field-type
                        >
                            <option value="text">
                                متن کوتاه
                            </option>

                            <option value="textarea">
                                متن چندخطی
                            </option>

                            <option value="number">
                                عدد
                            </option>

                            <option value="date">
                                تاریخ
                            </option>

                            <option value="select">
                                انتخاب تکی
                            </option>

                            <option value="multiselect">
                                انتخاب چندگانه
                            </option>

                            <option value="boolean">
                                بله / خیر
                            </option>

                            <option value="file">
                                فایل
                            </option>

                            <option value="lookup">
                                منبع داده
                            </option>
                        </select>

                        <div class="ticketing-field-data">
                            <input
                                type="text"
                                name="membership_fields[__INDEX__][data_source_key]"
                                dir="ltr"
                                maxlength="190"
                                placeholder="data.source"
                                aria-label="منبع داده"
                                data-field-source
                            >

                            <textarea
                                name="membership_fields[__INDEX__][options_text]"
                                rows="1"
                                placeholder="هر گزینه در یک سطر"
                                aria-label="گزینه‌های ثابت"
                                data-field-options
                            ></textarea>

                            <span
                                class="ticketing-field-data__empty"
                                data-field-data-empty
                            >
                                —
                            </span>
                        </div>

                        <select
                            name="membership_fields[__INDEX__][dependency_field_key]"
                            aria-label="وابسته به فیلد"
                            data-field-dependency
                            data-current-dependency=""
                        >
                            <option value="">
                                بدون وابستگی
                            </option>
                        </select>

                        <input
                            type="number"
                            name="membership_fields[__INDEX__][sort_order]"
                            min="0"
                            max="100000"
                            value="10"
                            aria-label="ترتیب"
                        >

                        <label
                            class="ticketing-switch ticketing-switch--compact"
                            title="اجباری"
                        >
                            <input
                                type="checkbox"
                                name="membership_fields[__INDEX__][is_required]"
                                value="1"
                            >

                            <span
                                class="ticketing-switch__track"
                                aria-hidden="true"
                            ></span>
                        </label>

                        <button
                            type="button"
                            class="ticketing-field-remove"
                            data-membership-remove
                            title="حذف فیلد"
                            aria-label="حذف فیلد"
                        >
                            ×
                        </button>
                    </div>
                </template>

            </div>
        </div>

    </section>


    <div class="admin-form-actions">
        <button
            type="submit"
            class="admin-button"
        >
            ذخیره تنظیمات عضویت
        </button>
    </div>
</form>


<style>
.ticketing-membership-header {
    margin-bottom: .85rem;
}

.ticketing-membership-header h2,
.ticketing-membership-header p {
    margin: 0;
}

.ticketing-membership-header h2 {
    margin-bottom: .18rem;
    font-size: .95rem;
}

.ticketing-membership-policy {
    display: grid;
    grid-template-columns:
        repeat(4, minmax(0, 1fr));
    gap: .7rem;
}

.ticketing-membership-policy > label {
    display: grid;
    align-content: start;
    gap: .3rem;
    min-width: 0;
    margin: 0;
}

.ticketing-membership-policy
> label
> span:first-child {
    font-size: .74rem;
    font-weight: 700;
}

.ticketing-membership-policy small {
    color: #7b8981;
    font-size: .64rem;
    line-height: 1.65;
}

.ticketing-membership-fields {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #dfe7e2;
}

.ticketing-membership-fields[hidden] {
    display: none !important;
}

.ticketing-membership-fields__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .7rem;
    margin-bottom: .65rem;
}

.ticketing-membership-fields__header h3,
.ticketing-membership-fields__header p {
    margin: 0;
}

.ticketing-membership-fields__header h3 {
    margin-bottom: .12rem;
    font-size: .85rem;
}

.ticketing-membership-table-scroll {
    width: 100%;
    overflow-x: auto;
}

.ticketing-membership-table {
    min-width: 930px;
}

.ticketing-membership-field-list {
    display: grid;
    gap: .4rem;
}

.ticketing-membership-row {
    display: grid;
    grid-template-columns:
        minmax(145px, 1.3fr)
        minmax(120px, .9fr)
        minmax(190px, 1.55fr)
        minmax(150px, 1.1fr)
        72px
        58px
        40px;
    gap: .4rem;
    align-items: center;
    padding: .4rem;
    border: 1px solid #dfe7e2;
    border-radius: 9px;
    background: #fbfdfc;
}

.ticketing-membership-row--header {
    padding-top: 0;
    padding-bottom: .3rem;
    border: 0;
    background: transparent;
    color: #64748b;
    font-size: .66rem;
    font-weight: 700;
}

.ticketing-membership-row input,
.ticketing-membership-row select,
.ticketing-membership-row textarea {
    width: 100%;
    min-width: 0;
    margin: 0;
}

.ticketing-membership-row input,
.ticketing-membership-row select {
    height: 36px;
}

.ticketing-membership-row textarea {
    min-height: 36px;
    height: 36px;
    padding-block: .4rem;
    resize: vertical;
}

.ticketing-field-data {
    min-width: 0;
}

.ticketing-field-data > [hidden] {
    display: none !important;
}

.ticketing-field-data__empty {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 36px;
    color: #94a3b8;
}

.ticketing-switch--compact {
    justify-content: center;
}

.ticketing-switch--compact
.ticketing-switch__track {
    width: 34px;
    height: 20px;
}

.ticketing-switch--compact
.ticketing-switch__track::after {
    width: 14px;
    height: 14px;
}

.ticketing-switch--compact
> input:checked
+ .ticketing-switch__track::after {
    transform: translateX(-14px);
}

.ticketing-field-remove {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    padding: 0;
    border: 1px solid #efc8c8;
    border-radius: 8px;
    background: #fff7f7;
    color: #b42318;
    font-size: 1rem;
    cursor: pointer;
}

.ticketing-field-remove:hover {
    background: #feecec;
}

@media (max-width: 1100px) {
    .ticketing-membership-policy {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .ticketing-membership-policy {
        grid-template-columns: 1fr;
    }

    .ticketing-membership-fields__header {
        align-items: stretch;
        flex-direction: column;
    }
}
</style>


<script>
(() => {
    const builder =
        document.querySelector(
            '[data-membership-builder]'
        );

    if (!builder) {
        return;
    }

    const enabled =
        document.getElementById(
            'ticketing-membership-form-enabled'
        );

    const wrap =
        document.getElementById(
            'ticketing-membership-fields-wrap'
        );

    const list =
        document.getElementById(
            'ticketing-membership-field-list'
        );

    const add =
        document.getElementById(
            'ticketing-membership-add-field'
        );

    const template =
        document.getElementById(
            'ticketing-membership-field-template'
        );

    if (
        !enabled
        ||
        !wrap
        ||
        !list
        ||
        !add
        ||
        !template
    ) {
        return;
    }


    const rows =
        () => Array.from(
            list.querySelectorAll(
                '[data-membership-field]'
            )
        );


    const makeKey =
        () => (
            'field_'
            + Date.now().toString(36)
            + '_'
            + Math.random()
                .toString(36)
                .slice(2, 8)
        );


    const nextIndex =
        () => {
            let max = -1;

            list
                .querySelectorAll(
                    '[name^="membership_fields["]'
                )
                .forEach(
                    (element) => {
                        const match =
                            element.name.match(
                                /^membership_fields\[(\d+)\]/
                            );

                        if (match) {
                            max =
                                Math.max(
                                    max,
                                    Number(
                                        match[1]
                                    )
                                );
                        }
                    }
                );

            return max + 1;
        };


    const syncData =
        (row) => {
            const type =
                row.querySelector(
                    '[data-field-type]'
                );

            const source =
                row.querySelector(
                    '[data-field-source]'
                );

            const options =
                row.querySelector(
                    '[data-field-options]'
                );

            const empty =
                row.querySelector(
                    '[data-field-data-empty]'
                );

            if (
                !type
                ||
                !source
                ||
                !options
                ||
                !empty
            ) {
                return;
            }

            const usesOptions =
                type.value === 'select'
                ||
                type.value === 'multiselect';

            const usesSource =
                type.value === 'lookup';

            options.hidden =
                !usesOptions;

            source.hidden =
                !usesSource;

            empty.hidden =
                usesOptions
                ||
                usesSource;
        };


    const ensureKeys =
        () => {
            rows().forEach(
                (row) => {
                    const input =
                        row.querySelector(
                            '[data-field-key-input]'
                        );

                    if (
                        input
                        &&
                        input.value.trim() === ''
                    ) {
                        input.value =
                            makeKey();
                    }
                }
            );
        };


    const rebuildDependencies =
        () => {
            ensureKeys();

            const values =
                rows().map(
                    (row) => ({
                        row,
                        key:
                            row.querySelector(
                                '[data-field-key-input]'
                            )?.value.trim()
                            || '',
                        title:
                            row.querySelector(
                                '[data-field-title]'
                            )?.value.trim()
                            || 'فیلد بدون عنوان',
                    })
                );

            values.forEach(
                (current) => {
                    const select =
                        current.row.querySelector(
                            '[data-field-dependency]'
                        );

                    if (!select) {
                        return;
                    }

                    const selected =
                        select.value
                        ||
                        select.dataset.currentDependency
                        ||
                        '';

                    select.innerHTML = '';

                    const blank =
                        document.createElement(
                            'option'
                        );

                    blank.value = '';
                    blank.textContent =
                        'بدون وابستگی';

                    select.appendChild(
                        blank
                    );

                    values.forEach(
                        (candidate) => {
                            if (
                                candidate.key === ''
                                ||
                                candidate.key === current.key
                            ) {
                                return;
                            }

                            const option =
                                document.createElement(
                                    'option'
                                );

                            option.value =
                                candidate.key;

                            option.textContent =
                                candidate.title;

                            if (
                                candidate.key === selected
                            ) {
                                option.selected =
                                    true;
                            }

                            select.appendChild(
                                option
                            );
                        }
                    );

                    select.dataset.currentDependency =
                        select.value;
                }
            );
        };


    const initialize =
        (row) => {
            syncData(
                row
            );

            row.querySelector(
                '[data-field-type]'
            )?.addEventListener(
                'change',
                () => {
                    syncData(
                        row
                    );
                }
            );

            row.querySelector(
                '[data-field-title]'
            )?.addEventListener(
                'input',
                rebuildDependencies
            );

            const dependency =
                row.querySelector(
                    '[data-field-dependency]'
                );

            dependency?.addEventListener(
                'change',
                () => {
                    dependency.dataset.currentDependency =
                        dependency.value;
                }
            );
        };


    rows().forEach(
        initialize
    );


    enabled.addEventListener(
        'change',
        () => {
            wrap.hidden =
                !enabled.checked;
        }
    );


    add.addEventListener(
        'click',
        () => {
            const index =
                nextIndex();

            const html =
                template.innerHTML
                    .replaceAll(
                        '__INDEX__',
                        String(index)
                    );

            list.insertAdjacentHTML(
                'beforeend',
                html
            );

            const currentRows =
                rows();

            const row =
                currentRows[
                    currentRows.length - 1
                ];

            if (!row) {
                return;
            }

            const key =
                row.querySelector(
                    '[data-field-key-input]'
                );

            if (key) {
                key.value =
                    makeKey();
            }

            const sort =
                row.querySelector(
                    '[name$="[sort_order]"]'
                );

            if (sort) {
                sort.value =
                    String(
                        currentRows.length
                        * 10
                    );
            }

            initialize(
                row
            );

            rebuildDependencies();

            row.querySelector(
                '[data-field-title]'
            )?.focus();
        }
    );


    list.addEventListener(
        'click',
        (event) => {
            const button =
                event.target.closest(
                    '[data-membership-remove]'
                );

            if (!button) {
                return;
            }

            button.closest(
                '[data-membership-field]'
            )?.remove();

            rebuildDependencies();
        }
    );


    wrap.hidden =
        !enabled.checked;

    ensureKeys();

    rows().forEach(
        syncData
    );

    rebuildDependencies();
})();
</script>
