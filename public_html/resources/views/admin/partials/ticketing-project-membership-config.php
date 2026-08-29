<?php

declare(strict_types=1);

try {
    $membershipForm =
        (
            new \App\Services\Ticketing\SupportProjectMembershipConfigurationService()
        )->form(
            $reference
        );

} catch (\Throwable) {

    $membershipForm =
        null;
}


if (!is_array($membershipForm)) {
    return;
}

$membershipStatus =
    trim(
        (string) (
            $_GET[
                'membership_status'
            ]
            ?? ''
        )
    );
?>

<section
    class="admin-section ticketing-membership-builder"
>

    <div class="admin-section__header">
        <div>
            <h2>
                عضویت کاربران
            </h2>

            <p class="admin-muted">
                نوع عضویت و اطلاعات موردنیاز
                این پروژه را تعریف کنید.
            </p>
        </div>
    </div>


    <?php if (
        $membershipStatus === 'saved'
    ): ?>
        <div class="admin-alert admin-alert--success">
            تنظیمات عضویت ذخیره شد.
        </div>
    <?php elseif (
        $membershipStatus === 'invalid'
    ): ?>
        <div class="admin-alert admin-alert--danger">
            تنظیمات فرم معتبر نیست.
        </div>
    <?php endif; ?>


    <form
        method="post"
        action="/admin/ticketing/projects/<?= ticketing_h(
            rawurlencode(
                $reference
            )
        ) ?>/membership"
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


        <div class="admin-form-grid">

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
                            ?? ''
                        ) === 'public'
                            ? ' selected'
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
                            ? ' selected'
                            : '' ?>
                    >
                        اختصاصی
                    </option>
                </select>

                <small class="admin-muted">
                    عمومی در فهرست پروژه‌های قابل
                    درخواست دیده می‌شود؛ اختصاصی فقط
                    از مسیر دعوت.
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
                            ?? ''
                        ) === 'manager'
                            ? ' selected'
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
                            ? ' selected'
                            : '' ?>
                    >
                        تأیید خودکار
                    </option>
                </select>
            </label>


            <label>
                <span>
                    دعوت
                </span>

                <span>
                    <input
                        type="checkbox"
                        name="invite_enabled"
                        value="1"
                        <?= !empty(
                            $membershipForm[
                                'invite_enabled'
                            ]
                        )
                            ? ' checked'
                            : '' ?>
                    >

                    کد دعوت فعال باشد
                </span>
            </label>


            <label>
                <span>
                    فرم تخصصی عضویت
                </span>

                <span>
                    <input
                        type="checkbox"
                        id="ticketing-membership-form-enabled"
                        name="form_enabled"
                        value="1"
                        <?= !empty(
                            $membershipForm[
                                'form_enabled'
                            ]
                        )
                            ? ' checked'
                            : '' ?>
                    >

                    اطلاعات تکمیلی دریافت شود
                </span>
            </label>

        </div>


        <div
            id="ticketing-membership-fields-wrap"
            <?= empty(
                $membershipForm[
                    'form_enabled'
                ]
            )
                ? ' hidden'
                : '' ?>
        >

            <div class="ticketing-membership-fields-head">

                <div>
                    <strong>
                        فیلدهای فرم عضویت
                    </strong>

                    <small class="admin-muted">
                        هیچ فیلد تخصصی به پروژه
                        خاصی در کد وابسته نیست.
                    </small>
                </div>


                <button
                    type="button"
                    id="ticketing-membership-add-field"
                    class="admin-button admin-button--soft"
                >
                    افزودن فیلد
                </button>

            </div>


            <div
                id="ticketing-membership-fields"
                class="ticketing-membership-fields"
            >

                <?php foreach (
                    (
                        $membershipForm[
                            'membership_fields'
                        ]
                        ?? []
                    )
                    as $index => $field
                ): ?>

                    <div
                        class="ticketing-membership-field"
                        data-membership-field
                    >

                        <div class="admin-form-grid">

                            <label>
                                <span>عنوان</span>

                                <input
                                    type="text"
                                    maxlength="255"
                                    name="membership_fields[<?= (int) $index ?>][title]"
                                    value="<?= ticketing_h(
                                        $field[
                                            'title'
                                        ]
                                        ?? ''
                                    ) ?>"
                                >
                            </label>


                            <label>
                                <span>کلید</span>

                                <input
                                    type="text"
                                    dir="ltr"
                                    maxlength="100"
                                    name="membership_fields[<?= (int) $index ?>][field_key]"
                                    value="<?= ticketing_h(
                                        $field[
                                            'field_key'
                                        ]
                                        ?? ''
                                    ) ?>"
                                    placeholder="field_key"
                                >
                            </label>


                            <label>
                                <span>نوع</span>

                                <select
                                    name="membership_fields[<?= (int) $index ?>][field_type]"
                                >
                                    <?php foreach ([
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
                                            'داده مرجع / Lookup',

                                    ] as $code => $title): ?>

                                        <option
                                            value="<?= ticketing_h(
                                                $code
                                            ) ?>"
                                            <?= (
                                                $field[
                                                    'field_type'
                                                ]
                                                ?? 'text'
                                            ) === $code
                                                ? ' selected'
                                                : '' ?>
                                        >
                                            <?= ticketing_h(
                                                $title
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>
                                </select>
                            </label>


                            <label>
                                <span>
                                    داده / منبع داده
                                </span>

                                <input
                                    type="text"
                                    dir="ltr"
                                    maxlength="190"
                                    name="membership_fields[<?= (int) $index ?>][data_source_key]"
                                    value="<?= ticketing_h(
                                        $field[
                                            'data_source_key'
                                        ]
                                        ?? ''
                                    ) ?>"
                                    placeholder="data.source.key"
                                >
                            </label>


                            <label class="admin-form-grid__wide">
                                <span>
                                    گزینه‌های ثابت
                                </span>

                                <textarea
                                    rows="3"
                                    name="membership_fields[<?= (int) $index ?>][options_text]"
                                    placeholder="هر گزینه در یک خط"
                                ><?= ticketing_h(
                                    implode(
                                        PHP_EOL,
                                        is_array(
                                            $field[
                                                'options'
                                            ]
                                            ?? null
                                        )
                                            ? $field[
                                                'options'
                                            ]
                                            : []
                                    )
                                ) ?></textarea>
                            </label>


                            <label>
                                <span>
                                    وابسته به فیلد
                                </span>

                                <input
                                    type="text"
                                    dir="ltr"
                                    maxlength="100"
                                    name="membership_fields[<?= (int) $index ?>][dependency_field_key]"
                                    value="<?= ticketing_h(
                                        $field[
                                            'dependency_field_key'
                                        ]
                                        ?? ''
                                    ) ?>"
                                >
                            </label>


                            <label>
                                <span>
                                    ترتیب
                                </span>

                                <input
                                    type="number"
                                    min="0"
                                    max="100000"
                                    name="membership_fields[<?= (int) $index ?>][sort_order]"
                                    value="<?= ticketing_h(
                                        $field[
                                            'sort_order'
                                        ]
                                        ?? 10
                                    ) ?>"
                                >
                            </label>


                            <label>
                                <span>
                                    الزام
                                </span>

                                <span>
                                    <input
                                        type="checkbox"
                                        name="membership_fields[<?= (int) $index ?>][is_required]"
                                        value="1"
                                        <?= !empty(
                                            $field[
                                                'is_required'
                                            ]
                                        )
                                            ? ' checked'
                                            : '' ?>
                                    >

                                    اجباری
                                </span>
                            </label>

                        </div>


                        <div class="admin-form-actions">
                            <button
                                type="button"
                                class="admin-button admin-button--soft"
                                data-membership-remove
                            >
                                حذف فیلد
                            </button>
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>


            <template
                id="ticketing-membership-field-template"
            >
                <div
                    class="ticketing-membership-field"
                    data-membership-field
                >
                    <div class="admin-form-grid">

                        <label>
                            <span>عنوان</span>
                            <input
                                type="text"
                                maxlength="255"
                                name="membership_fields[__INDEX__][title]"
                            >
                        </label>

                        <label>
                            <span>کلید</span>
                            <input
                                type="text"
                                dir="ltr"
                                maxlength="100"
                                name="membership_fields[__INDEX__][field_key]"
                                placeholder="field_key"
                            >
                        </label>

                        <label>
                            <span>نوع</span>
                            <select
                                name="membership_fields[__INDEX__][field_type]"
                            >
                                <option value="text">متن کوتاه</option>
                                <option value="textarea">متن چندخطی</option>
                                <option value="number">عدد</option>
                                <option value="date">تاریخ</option>
                                <option value="select">انتخاب تکی</option>
                                <option value="multiselect">انتخاب چندگانه</option>
                                <option value="boolean">بله / خیر</option>
                                <option value="file">فایل</option>
                                <option value="lookup">داده مرجع / Lookup</option>
                            </select>
                        </label>

                        <label>
                            <span>
                                داده / منبع داده
                            </span>
                            <input
                                type="text"
                                dir="ltr"
                                maxlength="190"
                                name="membership_fields[__INDEX__][data_source_key]"
                                placeholder="data.source.key"
                            >
                        </label>

                        <label class="admin-form-grid__wide">
                            <span>
                                گزینه‌های ثابت
                            </span>
                            <textarea
                                rows="3"
                                name="membership_fields[__INDEX__][options_text]"
                                placeholder="هر گزینه در یک خط"
                            ></textarea>
                        </label>

                        <label>
                            <span>
                                وابسته به فیلد
                            </span>
                            <input
                                type="text"
                                dir="ltr"
                                maxlength="100"
                                name="membership_fields[__INDEX__][dependency_field_key]"
                            >
                        </label>

                        <label>
                            <span>ترتیب</span>
                            <input
                                type="number"
                                min="0"
                                max="100000"
                                value="10"
                                name="membership_fields[__INDEX__][sort_order]"
                            >
                        </label>

                        <label>
                            <span>الزام</span>
                            <span>
                                <input
                                    type="checkbox"
                                    value="1"
                                    name="membership_fields[__INDEX__][is_required]"
                                >
                                اجباری
                            </span>
                        </label>

                    </div>

                    <div class="admin-form-actions">
                        <button
                            type="button"
                            class="admin-button admin-button--soft"
                            data-membership-remove
                        >
                            حذف فیلد
                        </button>
                    </div>
                </div>
            </template>

        </div>


        <div class="admin-form-actions">
            <button
                type="submit"
                class="admin-button"
            >
                ذخیره تنظیمات عضویت
            </button>
        </div>

    </form>

</section>


<style>
.ticketing-membership-builder {
    margin-top: 1rem;
}

.ticketing-membership-fields-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .65rem;
    margin: .85rem 0 .55rem;
}

.ticketing-membership-fields-head > div {
    display: grid;
    gap: .12rem;
}

.ticketing-membership-fields {
    display: grid;
    gap: .6rem;
}

.ticketing-membership-field {
    padding: .75rem;
    border:
        1px solid
        var(--admin-border, #dfe7e2);
    border-radius: 12px;
    background: #fff;
}
</style>


<script>
(() => {
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
            'ticketing-membership-fields'
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
        || !wrap
        || !list
        || !add
        || !template
    ) {
        return;
    }

    const sync =
        () => {
            wrap.hidden =
                !enabled.checked;
        };

    const nextIndex =
        () => {
            let max = -1;

            list
                .querySelectorAll(
                    '[name^="membership_fields["]'
                )
                .forEach(
                    (input) => {
                        const match =
                            input.name.match(
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

    enabled.addEventListener(
        'change',
        sync
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

            const row =
                button.closest(
                    '[data-membership-field]'
                );

            if (row) {
                row.remove();
            }
        }
    );

    sync();
})();
</script>
