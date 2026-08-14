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

$page =
    is_array($page ?? null)
        ? $page
        : [];

$organizations =
    is_array($page['organizations'] ?? null)
        ? $page['organizations']
        : [];

$selectedOrganization =
    is_array(
        $page['selected_organization']
        ?? null
    )
        ? $page['selected_organization']
        : null;

$contactPoints =
    is_array($page['contact_points'] ?? null)
        ? $page['contact_points']
        : [];

$contactTypes =
    is_array($page['contact_types'] ?? null)
        ? $page['contact_types']
        : [];


/*
 * structured-phone-contact-types-v1
 *
 * "extension" is legacy-only.
 * Internal extension is entered with the phone number.
 */
$contactTypes =
    array_values(
        array_filter(
            $contactTypes,
            static fn (array $type): bool =>
                (string) (
                    $type['code']
                    ?? ''
                ) !== 'extension'
        )
    );


$addressTypes =
    is_array($page['address_types'] ?? null)
        ? $page['address_types']
        : [];

$query =
    trim(
        (string) ($query ?? '')
    );

$status =
    trim(
        (string) ($status ?? '')
    );

$csrf =
    (new \IPKF\Security\Csrf())
        ->token();

$digits =
    static fn (mixed $value): string =>
        \App\Support\AdminFormat::digits(
            $value
        );

$checked =
    static fn (mixed $value): string =>
        (int) $value === 1
            ? ' checked'
            : '';

$selected =
    static fn (
        mixed $current,
        mixed $expected
    ): string =>
        (string) $current ===
        (string) $expected
            ? ' selected'
            : '';

$statusLabel =
    static fn (mixed $status): string =>
        (string) $status === 'active'
            ? 'فعال'
            : 'غیرفعال';

$dispatchLabels = [
    'postal' =>
        'پست',

    'courier' =>
        'پیک',

    'hand_delivery' =>
        'تحویل دستی',

    'fax' =>
        'فاکس',

    'email' =>
        'ایمیل',

    'system' =>
        'سامانه',
];

$pointKindLabels = [
    'secretariat' =>
        'دبیرخانه',

    'office' =>
        'دفتر',

    'department' =>
        'اداره / واحد',

    'branch' =>
        'شعبه',
];

$statusMessages = [
    'organization_saved' =>
        'سازمان بیرونی با موفقیت ثبت شد.',

    'organization_updated' =>
        'اطلاعات سازمان بیرونی با موفقیت ویرایش شد.',

    'organization_deactivated' =>
        'سازمان بیرونی غیرفعال شد.',

    'organization_failed' =>
        'ثبت یا ویرایش سازمان انجام نشد.',

    'point_saved' =>
        'دبیرخانه یا نقطه مکاتباتی ثبت شد.',

    'point_updated' =>
        'نقطه مکاتباتی ویرایش شد.',

    'point_deactivated' =>
        'نقطه مکاتباتی غیرفعال شد.',

    'point_failed' =>
        'ثبت یا ویرایش نقطه مکاتباتی انجام نشد.',

    'method_saved' =>
        'راه ارتباطی ثبت شد.',

    'method_updated' =>
        'راه ارتباطی ویرایش شد.',

    'method_deactivated' =>
        'راه ارتباطی غیرفعال شد.',

    'method_failed' =>
        'ثبت یا ویرایش راه ارتباطی انجام نشد.',

    'address_saved' =>
        'نشانی مکاتباتی ثبت شد.',

    'address_updated' =>
        'نشانی مکاتباتی ویرایش شد.',

    'address_deactivated' =>
        'نشانی مکاتباتی غیرفعال شد.',

    'address_failed' =>
        'ثبت یا ویرایش نشانی انجام نشد.',

    'invalid_csrf' =>
        'اعتبار فرم منقضی شده است. صفحه را دوباره بارگذاری کنید.',
];

$isFailureStatus =
    $status === 'invalid_csrf'
    || str_contains(
        $status,
        'failed'
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

    <a href="/admin/automation">
        اتوماسیون اداری
    </a>

    <span>/</span>

    <span>
        سازمان‌های بیرونی
    </span>
</nav>



<style id="external-directory-compact-ui">
    details.external-directory-collapse,
    details.external-directory-inline-details {
        margin-top: 12px;
    }

    details.external-directory-collapse > summary,
    details.external-directory-inline-details > summary {
        cursor: pointer;
        font-weight: 700;
        list-style: none;
        user-select: none;
    }

    details.external-directory-collapse > summary::-webkit-details-marker,
    details.external-directory-inline-details > summary::-webkit-details-marker {
        display: none;
    }

    details.external-directory-collapse > summary {
        padding: 4px 0;
    }

    details.external-directory-inline-details {
        border: 1px solid rgba(13, 118, 106, 0.16);
        border-radius: 10px;
        padding: 12px 14px;
    }

    details.external-directory-inline-details > summary {
        padding: 3px 0;
    }

    details.external-directory-collapse[open] > summary,
    details.external-directory-inline-details[open] > summary {
        margin-bottom: 14px;
    }

    form[action^="/admin/automation/external-organizations"]
    .admin-form-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        margin-top: 16px;
    }

    form[action^="/admin/automation/external-organizations"]
    + form[action^="/admin/automation/external-organizations"] {
        margin-top: 12px;
    }

    .external-directory-selected-summary {
        display: flex;
        align-items: center;
        gap: 10px 18px;
        flex-wrap: wrap;
    }

    .external-directory-selected-summary small {
        white-space: nowrap;
    }

    .external-directory-create-destination {
        margin-top: 8px;
    }

    /* external-directory-grid-tabs-v2 */

    .external-directory-table-wrap {
        margin-top: 14px;
        overflow-x: auto;
        border: 1px solid rgba(13, 118, 106, .14);
        border-radius: 12px;
        background: #fff;
    }

    .external-directory-table {
        width: 100%;
        min-width: 820px;
        border-collapse: collapse;
    }

    .external-directory-table th,
    .external-directory-table td {
        padding: 10px 12px;
        text-align: right;
        vertical-align: middle;
        border-bottom: 1px solid rgba(13, 118, 106, .10);
    }

    .external-directory-table th {
        white-space: nowrap;
        font-size: 12px;
        background: rgba(13, 118, 106, .045);
    }

    .external-directory-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .external-directory-table tbody tr:hover {
        background: rgba(13, 118, 106, .03);
    }

    .external-directory-table tbody tr.is-selected {
        background: rgba(13, 118, 106, .075);
    }

    .external-directory-table__title {
        display: flex;
        flex-direction: column;
        gap: 3px;
        min-width: 180px;
    }

    .external-directory-table__title small {
        opacity: .68;
    }

    .external-directory-row-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .external-directory-workspace {
        padding-bottom: 8px;
    }

    .external-directory-workspace__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .external-directory-tabs {
        display: flex;
        align-items: center;
        gap: 7px;
        flex-wrap: wrap;
        margin-top: 16px;
        padding: 6px;
        border: 1px solid rgba(13, 118, 106, .14);
        border-radius: 12px;
        background: rgba(13, 118, 106, .025);
    }

    .external-directory-tab {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 9px 14px;
        border-radius: 9px;
        text-decoration: none;
        color: inherit;
        font-weight: 700;
    }

    .external-directory-tab:hover {
        background: rgba(13, 118, 106, .07);
    }

    .external-directory-tab.is-active {
        background: #0d766a;
        color: #fff;
    }

    .external-directory-tab__count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 21px;
        height: 21px;
        padding: 0 6px;
        border-radius: 999px;
        background: rgba(255, 255, 255, .18);
        font-size: 11px;
    }

    .external-directory-destination-selector {
        display: flex;
        align-items: end;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 14px;
    }

    .external-directory-destination-selector .admin-field {
        min-width: 280px;
        flex: 1;
    }

    .external-directory-tab-empty {
        margin-top: 12px;
    }


    /* external-directory-action-driven-v1 */

    .external-directory-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 14px;
    }

    /* external-directory-linear-details-v1 */

    .external-directory-readonly-grid {
        display: block;
        margin-top: 14px;
        border-top: 1px solid rgba(13, 118, 106, .13);
        border-bottom: 1px solid rgba(13, 118, 106, .13);
    }

    .external-directory-readonly-item {
        display: grid;
        grid-template-columns: 180px minmax(0, 1fr);
        align-items: center;
        gap: 18px;
        min-height: 46px;
        padding: 9px 12px;
        border: 0;
        border-bottom: 1px solid rgba(13, 118, 106, .10);
        border-radius: 0;
        background: transparent;
    }

    .external-directory-readonly-item:last-child {
        border-bottom: 0;
    }

    .external-directory-readonly-item:hover {
        background: rgba(13, 118, 106, .025);
    }

    .external-directory-readonly-item small {
        display: block;
        margin: 0;
        opacity: .68;
        font-size: 12px;
        font-weight: 600;
    }

    .external-directory-readonly-item strong {
        display: block;
        overflow-wrap: anywhere;
        font-weight: 700;
    }

    @media (max-width: 720px) {
        .external-directory-readonly-item {
            grid-template-columns: 1fr;
            gap: 4px;
            padding: 10px 8px;
        }
    }

    .external-directory-action-form {
        margin-top: 14px;
    }

    .external-directory-action-form > summary {
        display: none;
    }

    .external-directory-point-summary {
        margin-bottom: 14px;
    }


    /* external-directory-real-action-buttons-v1 */

    .external-directory-header-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .external-directory-actions > a.admin-btn,
    .external-directory-row-actions > a.admin-btn,
    .external-directory-header-actions > a.admin-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 36px;
        padding: 8px 14px;
        border: 1px solid #0d766a;
        border-radius: 9px;
        background: #fff;
        color: #0d766a !important;
        text-decoration: none !important;
        font-weight: 700;
        line-height: 1.2;
        cursor: pointer;
        white-space: nowrap;
        transition:
            background-color .15s ease,
            color .15s ease,
            border-color .15s ease;
    }

    .external-directory-row-actions > a.admin-btn {
        min-height: 32px;
        padding: 6px 11px;
        font-size: 12px;
    }

    .external-directory-actions > a.admin-btn:hover,
    .external-directory-row-actions > a.admin-btn:hover,
    .external-directory-header-actions > a.admin-btn:hover {
        background: rgba(13, 118, 106, .08);
    }

    .external-directory-actions > a.admin-btn.admin-btn--primary,
    .external-directory-header-actions > a.admin-btn.admin-btn--primary {
        background: #0d766a;
        border-color: #0d766a;
        color: #fff !important;
    }

    .external-directory-actions > a.admin-btn.admin-btn--primary:hover,
    .external-directory-header-actions > a.admin-btn.admin-btn--primary:hover {
        background: #0a655b;
        border-color: #0a655b;
    }


    /* external-contact-method-compact-ui-v4 */

    .external-contact-method-form {
        margin-top: 12px;
        padding: 14px 16px;
        border: 1px solid #dce9e5;
        border-radius: 14px;
        background: #fbfdfc;
    }

    .external-contact-method-form
    .admin-form-grid {
        display: grid;
        grid-template-columns:
            minmax(165px, .8fr)
            96px
            minmax(240px, 1.7fr)
            96px;
        gap: 10px 12px;
        align-items: start;
    }

    /* ردیف اول */

    .external-contact-method-form
    .admin-field:has([name="contact_type_code"]) {
        grid-column: 1;
        min-width: 0;
    }

    .external-contact-method-form
    .admin-field:has([name="area_code"]) {
        grid-column: 2;
        min-width: 0;
    }

    .external-contact-method-form
    .admin-field:has([name="value"]) {
        grid-column: 3;
        min-width: 0;
    }

    .external-contact-method-form
    .admin-field:has([name="extension"]) {
        grid-column: 4;
        min-width: 0;
    }

    .external-contact-method-form:not(.is-phone)
    .admin-field:has([name="value"]) {
        grid-column: 2 / 5;
    }

    /* ردیف دوم */

    .external-contact-method-form
    .admin-field:has([name="label"]) {
        grid-column: 1 / 4;
        min-width: 0;
    }

    .external-contact-method-form
    .admin-field:has([name="sort_order"]) {
        grid-column: 4;
        min-width: 0;
    }

    .external-contact-method-form
    .admin-field:has([name="sort_order"])
    input {
        width: 100%;
        max-width: 96px;
    }

    /* توضیحات کوچک تلفن */

    .external-contact-method-form
    [data-phone-contact-field]
    .admin-muted {
        display: block;
        margin-top: 4px;
        font-size: 10px;
        line-height: 1.35;
        white-space: nowrap;
    }

    /* گزینه‌ها */

    .external-contact-method-form
    .admin-field:has(input[type="checkbox"]) {
        width: max-content;
        min-width: 0;
        margin: 0;
        padding: 0;
        align-self: center;
    }

    .external-contact-method-form
    .admin-field:has(input[type="checkbox"])
    > span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 30px;
        margin: 0;
        padding: 5px 10px;
        border: 1px solid #dbe8e4;
        border-radius: 999px;
        background: #fff;
        font-size: 11px;
        line-height: 1.25;
        white-space: nowrap;
        cursor: pointer;
    }

    .external-contact-method-form
    input[type="checkbox"] {
        width: 14px;
        height: 14px;
        margin: 0;
        flex: 0 0 auto;
    }

    .external-contact-method-form
    .admin-field:has([name="is_primary"]) {
        grid-column: 1;
    }

    .external-contact-method-form
    .admin-field:has([name="is_verified"]) {
        grid-column: 2;
    }

    .external-contact-method-form
    .admin-field:has([name="supports_dispatch"]) {
        grid-column: 3;
    }

    .external-contact-method-form
    .admin-field:has([name="supports_followup"]) {
        grid-column: 4;
    }

    .external-contact-method-form
    .admin-form-actions {
        margin-top: 10px;
        padding-top: 0;
        border-top: 0;
    }

    .external-contact-method-form
    .admin-form-actions .admin-btn {
        min-height: 34px;
        padding: 7px 14px;
    }

    @media (max-width: 680px) {
        .external-contact-method-form {
            padding: 12px;
        }

        .external-contact-method-form
        .admin-form-grid {
            grid-template-columns:
                82px
                minmax(0, 1fr)
                82px;
        }

        .external-contact-method-form
        .admin-field:has([name="contact_type_code"]) {
            grid-column: 1 / 4;
        }

        .external-contact-method-form
        .admin-field:has([name="area_code"]) {
            grid-column: 1;
        }

        .external-contact-method-form
        .admin-field:has([name="value"]) {
            grid-column: 2;
        }

        .external-contact-method-form
        .admin-field:has([name="extension"]) {
            grid-column: 3;
        }

        .external-contact-method-form:not(.is-phone)
        .admin-field:has([name="value"]) {
            grid-column: 1 / 4;
        }

        .external-contact-method-form
        .admin-field:has([name="label"]) {
            grid-column: 1 / 3;
        }

        .external-contact-method-form
        .admin-field:has([name="sort_order"]) {
            grid-column: 3;
        }

        .external-contact-method-form
        [data-phone-contact-field]
        .admin-muted {
            white-space: normal;
        }
    }


    /* external-contact-phone-group-v6 */

    /*
     * Main form:
     * right = contact type
     * remaining space = one real contact-value group
     */

    .external-contact-method-form
    .admin-form-grid {
        grid-template-columns:
            190px
            minmax(0, 1fr)
            110px;
        gap: 11px 14px;
        align-items: start;
    }

    .external-contact-method-form
    .admin-field:has([name="contact_type_code"]) {
        grid-column: 1;
    }

    .external-contact-phone-group {
        grid-column: 2 / 4;

        display: grid;
        grid-template-columns: minmax(0, 1fr);

        gap: 12px;
        min-width: 0;

        /*
         * Phone values themselves should read:
         *
         * LEFT -> RIGHT
         * area code | number | extension
         */
        direction: ltr;
    }

    .external-contact-method-form.is-phone
    .external-contact-phone-group {
        grid-template-columns:
            110px
            minmax(260px, 1fr)
            110px;
    }

    /*
     * Each Persian field remains RTL internally.
     */

    .external-contact-phone-group
    .admin-field {
        grid-column: auto;
        min-width: 0;
        direction: rtl;
    }

    /*
     * Neutralize V4 column rules inside the real
     * phone group. The wrapper owns positioning now.
     */

    .external-contact-method-form
    .external-contact-phone-group
    .admin-field:has([name="area_code"]),
    .external-contact-method-form
    .external-contact-phone-group
    .admin-field:has([name="value"]),
    .external-contact-method-form
    .external-contact-phone-group
    .admin-field:has([name="extension"]) {
        grid-column: auto;
    }

    /*
     * Exactly equal control height.
     */

    .external-contact-method-form
    input:not([type="checkbox"]),
    .external-contact-method-form
    select {
        width: 100%;
        min-width: 0;
        height: 38px;
        min-height: 38px;
        box-sizing: border-box;
    }

    /*
     * Numeric phone values themselves are LTR.
     */

    .external-contact-phone-group
    input[name="area_code"],
    .external-contact-phone-group
    input[name="value"],
    .external-contact-phone-group
    input[name="extension"] {
        text-align: left;
    }

    /*
     * Same label baseline.
     */

    .external-contact-phone-group
    .admin-field > span:first-child,
    .external-contact-method-form
    .admin-field:has([name="contact_type_code"])
    > span:first-child {
        display: flex;
        align-items: center;
        min-height: 19px;
        margin-bottom: 5px;
        line-height: 1.3;
    }

    /*
     * Helper notes remain visually secondary.
     */

    .external-contact-phone-group
    .admin-muted {
        display: block;
        margin-top: 4px;
        font-size: 10px;
        line-height: 1.35;
        white-space: nowrap;
    }

    /*
     * Second row.
     */

    .external-contact-method-form
    .admin-field:has([name="label"]) {
        grid-column: 1 / 3;
        min-width: 0;
    }

    .external-contact-method-form
    .admin-field:has([name="sort_order"]) {
        grid-column: 3;
        min-width: 0;
    }

    .external-contact-method-form
    .admin-field:has([name="sort_order"])
    input {
        width: 100%;
        max-width: none;
    }

    /*
     * Options pack naturally without oversized gaps.
     */

    .external-contact-method-form
    .admin-field:has([name="is_primary"]),
    .external-contact-method-form
    .admin-field:has([name="is_verified"]),
    .external-contact-method-form
    .admin-field:has([name="supports_dispatch"]),
    .external-contact-method-form
    .admin-field:has([name="supports_followup"]) {
        grid-column: auto;
        width: max-content;
        justify-self: start;
    }

    /*
     * Mobile:
     * contact type first,
     * phone group on its own row.
     */

    @media (max-width: 680px) {
        .external-contact-method-form
        .admin-form-grid {
            grid-template-columns:
                minmax(0, 1fr)
                92px;
        }

        .external-contact-method-form
        .admin-field:has([name="contact_type_code"]) {
            grid-column: 1 / 3;
        }

        .external-contact-phone-group {
            grid-column: 1 / 3;
        }

        .external-contact-method-form.is-phone
        .external-contact-phone-group {
            grid-template-columns:
                78px
                minmax(0, 1fr)
                78px;
        }

        .external-contact-method-form
        .admin-field:has([name="label"]) {
            grid-column: 1;
        }

        .external-contact-method-form
        .admin-field:has([name="sort_order"]) {
            grid-column: 2;
        }

        .external-contact-phone-group
        .admin-muted {
            white-space: normal;
        }
    }


    /* external-contact-phone-alignment-v7 */

    /*
     * Lock all three telephone fields to exactly
     * the same internal vertical rhythm:
     *
     * label 20px
     * input 38px
     * helper 16px
     */

    .external-contact-phone-group {
        align-items: start;
    }

    .external-contact-phone-group
    .admin-field {
        display: grid;
        grid-template-rows:
            20px
            38px
            16px;
        row-gap: 4px;
        align-content: start;
        align-items: start;
        margin: 0;
        padding: 0;
    }

    .external-contact-phone-group
    .admin-field > span:first-child {
        grid-row: 1;
        display: flex;
        align-items: center;
        height: 20px;
        min-height: 20px;
        margin: 0;
        padding: 0;
        line-height: 20px;
    }

    .external-contact-phone-group
    .admin-field > input {
        grid-row: 2;
        display: block;
        width: 100%;
        height: 38px;
        min-height: 38px;
        margin: 0;
        box-sizing: border-box;
    }

    .external-contact-phone-group
    .admin-field > .admin-muted {
        grid-row: 3;
        display: block;
        height: 16px;
        min-height: 16px;
        margin: 0;
        padding: 0;
        overflow: visible;
        font-size: 10px;
        line-height: 16px;
        white-space: nowrap;
    }

    /*
     * The number field has no helper text, but its
     * first two rows remain exactly identical to
     * area code and extension.
     */

    .external-contact-phone-group
    .admin-field:has([name="value"]) {
        grid-template-rows:
            20px
            38px
            16px;
    }

    @media (max-width: 680px) {
        .external-contact-phone-group
        .admin-field > .admin-muted {
            height: auto;
            min-height: 16px;
            line-height: 1.35;
            white-space: normal;
        }
    }


    /* external-contact-options-group-v8 */

    /*
     * Contact type must use exactly the same
     * vertical rhythm as the three phone fields.
     */

    .external-contact-method-form
    .admin-field:has([name="contact_type_code"]) {
        display: grid;
        grid-template-rows:
            20px
            38px
            16px;
        row-gap: 4px;
        align-content: start;
        align-items: start;
        margin: 0;
        padding: 0;
    }

    .external-contact-method-form
    .admin-field:has([name="contact_type_code"])
    > span:first-child {
        grid-row: 1;
        display: flex;
        align-items: center;
        height: 20px;
        min-height: 20px;
        margin: 0;
        padding: 0;
        line-height: 20px;
    }

    .external-contact-method-form
    .admin-field:has([name="contact_type_code"])
    > select {
        grid-row: 2;
        display: block;
        width: 100%;
        height: 38px;
        min-height: 38px;
        margin: 0;
        box-sizing: border-box;
    }

    /*
     * First-row controls now share one exact
     * input/select baseline.
     */

    .external-contact-method-form
    .admin-field:has([name="contact_type_code"]),
    .external-contact-phone-group {
        align-self: start;
    }

    /*
     * Real options wrapper.
     * The main form grid no longer positions
     * individual checkboxes independently.
     */

    .external-contact-options-group {
        grid-column: 1 / -1;

        display: grid;
        grid-template-columns:
            repeat(4, minmax(0, 1fr));

        gap: 8px;
        align-items: stretch;

        width: 100%;
        min-width: 0;

        margin-top: 1px;
        padding: 0;
    }

    /*
     * Neutralize old per-checkbox grid rules.
     */

    .external-contact-method-form
    .external-contact-options-group
    .admin-field:has(input[type="checkbox"]) {
        grid-column: auto;
        justify-self: stretch;

        display: block;

        width: 100%;
        min-width: 0;

        margin: 0;
        padding: 0;
    }

    /*
     * Hidden semantic options must really disappear.
     * This is especially important for telephone,
     * which is not a dispatch channel.
     */

    .external-contact-method-form
    .external-contact-options-group
    .admin-field[hidden] {
        display: none !important;
    }

    /*
     * Uniform option cards.
     */

    .external-contact-method-form
    .external-contact-options-group
    .admin-field > span {
        display: flex;
        align-items: center;
        justify-content: flex-start;

        gap: 7px;

        width: 100%;
        min-height: 34px;

        margin: 0;
        padding: 6px 10px;

        border: 1px solid #dbe8e4;
        border-radius: 9px;

        background: #fff;

        box-sizing: border-box;

        font-size: 11px;
        line-height: 1.35;

        white-space: nowrap;
        cursor: pointer;
    }

    .external-contact-method-form
    .external-contact-options-group
    input[type="checkbox"] {
        width: 14px;
        height: 14px;

        min-width: 14px;
        min-height: 14px;

        margin: 0;

        flex: 0 0 14px;
    }

    /*
     * When one semantic option is hidden,
     * remaining cards pack without the strange
     * scattered positions seen previously.
     */

    .external-contact-options-group
    .admin-field:not([hidden]) {
        min-width: 0;
    }

    /*
     * Tablet.
     */

    @media (max-width: 900px) {
        .external-contact-options-group {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }
    }

    /*
     * Mobile.
     */

    @media (max-width: 520px) {
        .external-contact-options-group {
            grid-template-columns: 1fr;
        }

        .external-contact-method-form
        .external-contact-options-group
        .admin-field > span {
            white-space: normal;
        }
    }


    /* external-contact-action-alignment-v9 */

    /*
     * Keep the contact-method primary action
     * physically aligned to the right edge.
     * Explicit LTR flex direction avoids inherited
     * RTL/LTR ambiguity; button text stays RTL.
     */

    .external-contact-method-form
    .admin-form-actions {
        display: flex;
        width: 100%;
        direction: ltr;
        justify-content: flex-end;
        align-items: center;
        margin-top: 12px;
    }

    .external-contact-method-form
    .admin-form-actions
    .admin-btn {
        direction: rtl;
        margin: 0;
    }


    /* contact-method-persian-numeric-v10 */

    .external-contact-options-group
    .admin-field.is-dispatch-unavailable
    > span {
        opacity: .58;
        background: #f5f7f6;
        cursor: not-allowed;
    }

    .external-contact-options-group
    .admin-field.is-dispatch-unavailable
    input[type="checkbox"] {
        cursor: not-allowed;
    }

    .external-contact-method-form
    input[name="area_code"],
    .external-contact-method-form
    input[name="extension"],
    .external-contact-method-form
    input[name="sort_order"] {
        font-variant-numeric: normal;
    }

</style>


<?php
$directoryMode =
    strtolower(
        trim(
            (string) (
                $_GET['mode']
                ?? ''
            )
        )
    );

if (!in_array(
    $directoryMode,
    [
        '',
        'create-organization',
        'edit-organization',
        'create-destination',
        'edit-destination',
        'manage-contacts',
        'manage-addresses',
    ],
    true
)) {
    $directoryMode = '';
}
?>

<section
    class="admin-module-hub
           admin-module-hub--teal
           admin-users-heading"
>
    <div class="admin-module-hub__icon">
        <?= \App\Support\AdminIcon::html(
            'organization'
        ) ?>
    </div>

    <div>
        <h2>
            سازمان‌های بیرونی و دبیرخانه‌های مقصد
        </h2>

        <p>
            مدیریت سازمان مقصد، دبیرخانه،
            راه‌های ارتباطی و نشانی‌های مورد استفاده
            در ارسال و پیگیری مکاتبات
        </p>
    </div>

    <a
        class="admin-module-hub__back"
        href="/admin/automation"
    >
        بازگشت به اتوماسیون
    </a>
</section>


<?php if (isset($statusMessages[$status])): ?>
<section class="admin-section">
    <div
        class="admin-alert<?= $isFailureStatus
            ? ' admin-alert--danger'
            : '' ?>"
        role="status"
    >
        <?= admin_h(
            $statusMessages[$status]
        ) ?>
    </div>
</section>
<?php endif; ?>


<section class="admin-section admin-users-panel">
    <div class="admin-section__header">
        <div>
            <h2>
                فهرست سازمان‌های بیرونی
            </h2>

            <p class="admin-muted">
                جستجو بر اساس عنوان، شناسه ملی
                یا شماره ثبت
            </p>
        </div>

        <div class="external-directory-header-actions">

            <a
                class="admin-btn admin-btn--primary"
                href="/admin/automation/external-organizations?mode=create-organization"
            >
                + سازمان جدید
            </a>
        </div>
    </div>

    <form
        method="get"
        action="/admin/automation/external-organizations"
        class="admin-form"
    >
        <div class="admin-form-grid">
            <label class="admin-field admin-field--wide">
                <span>
                    جستجو
                </span>

                <input
                    type="search"
                    name="q"
                    value="<?= admin_h($query) ?>"
                    placeholder="نام سازمان، عنوان کوتاه، شناسه ملی یا شماره ثبت"
                >
            </label>
        </div>

        <div class="admin-form-actions">
            <button
                type="submit"
                class="admin-btn admin-btn--primary"
            >
                جستجو
            </button>

            <?php if ($query !== ''): ?>
                <a
                    class="admin-btn"
                    href="/admin/automation/external-organizations"
                >
                    نمایش همه
                </a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($organizations === []): ?>
        <div class="admin-alert">
            سازمان بیرونی مطابق جستجو یافت نشد.
        </div>
    <?php else: ?>
        <div
            class="external-directory-table-wrap"
            id="external-directory-organization-grid"
        >
            <table class="external-directory-table">
                <thead>
                    <tr>
                        <th>عنوان سازمان</th>
                        <th>عنوان کوتاه</th>
                        <th>شناسه ملی</th>
                        <th>شماره ثبت</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach (
                    $organizations
                    as $organization
                ): ?>
                    <?php
                    $reference =
                        (string) (
                            $organization[
                                'public_reference'
                            ]
                            ?? ''
                        );

                    $isSelectedOrganization =
                        is_array(
                            $selectedOrganization
                        )
                        && (
                            (
                                $selectedOrganization[
                                    'public_reference'
                                ]
                                ?? ''
                            )
                            === $reference
                        );
                    ?>

                    <tr
                        <?= $isSelectedOrganization
                            ? 'class="is-selected"'
                            : '' ?>
                    >
                        <td>
                            <div
                                class="external-directory-table__title"
                            >
                                <strong>
                                    <?= admin_h(
                                        $organization[
                                            'title_fa'
                                        ]
                                        ?? ''
                                    ) ?>
                                </strong>

                                <?php if (
                                    (
                                        $organization[
                                            'title_en'
                                        ]
                                        ?? ''
                                    ) !== ''
                                ): ?>
                                    <small dir="ltr">
                                        <?= admin_h(
                                            $organization[
                                                'title_en'
                                            ]
                                        ) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </td>

                        <td>
                            <?= admin_h(
                                $organization[
                                    'short_title'
                                ]
                                ?: '—'
                            ) ?>
                        </td>

                        <td dir="ltr">
                            <?= admin_h(
                                $organization[
                                    'national_id'
                                ]
                                ?: '—'
                            ) ?>
                        </td>

                        <td dir="ltr">
                            <?= admin_h(
                                $organization[
                                    'registration_number'
                                ]
                                ?: '—'
                            ) ?>
                        </td>

                        <td>
                            <?= admin_h(
                                $statusLabel(
                                    $organization[
                                        'status'
                                    ]
                                    ?? ''
                                )
                            ) ?>
                        </td>

                        <td>
                            <div
                                class="external-directory-row-actions"
                            >
                                <a
                                    class="admin-btn"
                                    href="/admin/automation/external-organizations?<?= admin_h(
                                        http_build_query([
                                            'organization' =>
                                                $reference,
                                            'tab' =>
                                                'destinations',
                                        ])
                                    ) ?>"
                                >
                                    مشاهده
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>





<?php if (
    $directoryMode
        === 'create-organization'
): ?>
<details class="admin-section external-directory-collapse external-directory-action-form" open>
    <summary>
        ثبت سازمان جدید
    </summary>

    <div class="admin-muted">
        اطلاعات پایه سازمان مقصد
    </div>

<form
        method="post"
        action="/admin/automation/external-organizations/save"
        class="admin-form"
    >
        <input
            type="hidden"
            name="_token"
            value="<?= admin_h($csrf) ?>"
        >

        <div class="admin-form-grid">
            <label class="admin-field">
                <span>
                    عنوان فارسی *
                </span>

                <input
                    type="text"
                    name="title_fa"
                    required
                >
            </label>

            <label class="admin-field">
                <span>
                    عنوان انگلیسی
                </span>

                <input
                    type="text"
                    name="title_en"
                    dir="ltr"
                >
            </label>

            <label class="admin-field">
                <span>
                    عنوان کوتاه
                </span>

                <input
                    type="text"
                    name="short_title"
                >
            </label>

            <label class="admin-field">
                <span>
                    شناسه ملی
                </span>

                <input
                    type="text"
                    name="national_id"
                    dir="ltr"
                >
            </label>

            <label class="admin-field">
                <span>
                    شماره ثبت
                </span>

                <input
                    type="text"
                    name="registration_number"
                    dir="ltr"
                >
            </label>

            <label class="admin-field">
                <span>
                    وب‌سایت
                </span>

                <input
                    type="url"
                    name="website_url"
                    dir="ltr"
                >
            </label>

            <label class="admin-field admin-field--wide">
                <span>
                    توضیحات
                </span>

                <textarea
                    name="notes"
                    rows="3"
                ></textarea>
            </label>
        </div>

        <div class="admin-form-actions">
            <button
                type="submit"
                class="admin-btn admin-btn--primary"
            >
                ثبت سازمان
            </button>
        </div>
    </form>
</details>
<?php endif; ?>



<?php if (is_array($selectedOrganization)): ?>
<?php
$organizationReference =
    (string) (
        $selectedOrganization[
            'public_reference'
        ]
        ?? ''
    );
?>

<?php
$activeDirectoryTab =
    strtolower(
        trim(
            (string) (
                $_GET['tab']
                ?? 'destinations'
            )
        )
    );

if (!in_array(
    $activeDirectoryTab,
    [
        'profile',
        'destinations',
        'contacts',
        'addresses',
    ],
    true
)) {
    $activeDirectoryTab =
        'destinations';
}

$requestedPointReference =
    trim(
        (string) (
            $_GET['point']
            ?? ''
        )
    );

$selectedDirectoryPointReference =
    null;

$activeContactMethodCount = 0;
$activeAddressCount = 0;

foreach ($contactPoints as $tabPoint) {
    $tabPointReference =
        (string) (
            $tabPoint[
                'public_reference'
            ]
            ?? ''
        );

    if (
        $requestedPointReference !== ''
        && $tabPointReference
            === $requestedPointReference
    ) {
        $selectedDirectoryPointReference =
            $tabPointReference;
    }

    foreach (
        (
            is_array(
                $tabPoint['methods']
                ?? null
            )
                ? $tabPoint['methods']
                : []
        )
        as $tabMethod
    ) {
        if (
            (
                $tabMethod['status']
                ?? ''
            ) === 'active'
        ) {
            ++$activeContactMethodCount;
        }
    }

    foreach (
        (
            is_array(
                $tabPoint['addresses']
                ?? null
            )
                ? $tabPoint['addresses']
                : []
        )
        as $tabAddress
    ) {
        if (
            (
                $tabAddress['status']
                ?? ''
            ) === 'active'
        ) {
            ++$activeAddressCount;
        }
    }
}

if (
    $selectedDirectoryPointReference
        === null
    && $contactPoints !== []
) {
    foreach (
        $contactPoints
        as $tabPoint
    ) {
        if (
            (
                $tabPoint['status']
                ?? ''
            ) === 'active'
            && (int) (
                $tabPoint[
                    'is_primary'
                ]
                ?? 0
            ) === 1
        ) {
            $selectedDirectoryPointReference =
                (string) (
                    $tabPoint[
                        'public_reference'
                    ]
                    ?? ''
                );

            break;
        }
    }

    if (
        $selectedDirectoryPointReference
            === null
    ) {
        $selectedDirectoryPointReference =
            (string) (
                $contactPoints[0][
                    'public_reference'
                ]
                ?? ''
            );
    }
}

$directoryTabUrl =
    static function (
        string $tab,
        ?string $pointReference = null
    ) use (
        $organizationReference
    ): string {
        $parameters = [
            'organization' =>
                $organizationReference,

            'tab' =>
                $tab,
        ];

        if (
            $pointReference !== null
            && $pointReference !== ''
        ) {
            $parameters['point'] =
                $pointReference;
        }

        return
            '/admin/automation/'
            . 'external-organizations?'
            . http_build_query(
                $parameters
            );
    };
?>

<section
    class="admin-section external-directory-workspace"
>
    <div
        class="external-directory-workspace__header"
    >
        <div>
            <h2>
                <?= admin_h(
                    $selectedOrganization[
                        'title_fa'
                    ]
                    ?? ''
                ) ?>
            </h2>

            <p class="admin-muted">
                مدیریت اطلاعات و مسیرهای مکاتباتی سازمان
            </p>
        </div>

        <span class="admin-badge">
            <?= admin_h(
                $statusLabel(
                    $selectedOrganization[
                        'status'
                    ]
                    ?? ''
                )
            ) ?>
        </span>
    </div>

    <nav
        class="external-directory-tabs"
        aria-label="بخش‌های سازمان بیرونی"
    >
        <a
            class="external-directory-tab<?= $activeDirectoryTab === 'profile'
                ? ' is-active'
                : '' ?>"
            href="<?= admin_h(
                $directoryTabUrl(
                    'profile'
                )
            ) ?>"
        >
            اطلاعات سازمان
        </a>

        <a
            class="external-directory-tab<?= $activeDirectoryTab === 'destinations'
                ? ' is-active'
                : '' ?>"
            href="<?= admin_h(
                $directoryTabUrl(
                    'destinations'
                )
            ) ?>"
        >
            مقصدهای مکاتباتی

            <span
                class="external-directory-tab__count"
            >
                <?= admin_h(
                    $digits(
                        count($contactPoints)
                    )
                ) ?>
            </span>
        </a>

        <a
            class="external-directory-tab<?= $activeDirectoryTab === 'contacts'
                ? ' is-active'
                : '' ?>"
            href="<?= admin_h(
                $directoryTabUrl(
                    'contacts',
                    $selectedDirectoryPointReference
                )
            ) ?>"
        >
            راه‌های ارتباطی

            <span
                class="external-directory-tab__count"
            >
                <?= admin_h(
                    $digits(
                        $activeContactMethodCount
                    )
                ) ?>
            </span>
        </a>

        <a
            class="external-directory-tab<?= $activeDirectoryTab === 'addresses'
                ? ' is-active'
                : '' ?>"
            href="<?= admin_h(
                $directoryTabUrl(
                    'addresses',
                    $selectedDirectoryPointReference
                )
            ) ?>"
        >
            نشانی‌ها

            <span
                class="external-directory-tab__count"
            >
                <?= admin_h(
                    $digits(
                        $activeAddressCount
                    )
                ) ?>
            </span>
        </a>
    </nav>
</section>



<?php if ($activeDirectoryTab === 'profile'): ?>
<section class="admin-section admin-users-panel">
    <div class="admin-section__header">
        <div>
            <h2>
                <?= admin_h(
                    $selectedOrganization[
                        'title_fa'
                    ]
                    ?? ''
                ) ?>
            </h2>

            <p class="admin-muted">
                اطلاعات پایه سازمان
            </p>
        </div>

        <span class="admin-badge">
            <?= admin_h(
                $statusLabel(
                    $selectedOrganization[
                        'status'
                    ]
                    ?? ''
                )
            ) ?>
        </span>
    </div>


    <div
        class="external-directory-readonly-grid"
        id="profile-readonly-summary"
    >
        <div class="external-directory-readonly-item">
            <small>عنوان فارسی</small>
            <strong>
                <?= admin_h(
                    $selectedOrganization[
                        'title_fa'
                    ]
                    ?? '—'
                ) ?>
            </strong>
        </div>

        <div class="external-directory-readonly-item">
            <small>عنوان انگلیسی</small>
            <strong dir="ltr">
                <?= admin_h(
                    $selectedOrganization[
                        'title_en'
                    ]
                    ?: '—'
                ) ?>
            </strong>
        </div>

        <div class="external-directory-readonly-item">
            <small>عنوان کوتاه</small>
            <strong>
                <?= admin_h(
                    $selectedOrganization[
                        'short_title'
                    ]
                    ?: '—'
                ) ?>
            </strong>
        </div>

        <div class="external-directory-readonly-item">
            <small>شناسه ملی</small>
            <strong dir="ltr">
                <?= admin_h(
                    $selectedOrganization[
                        'national_id'
                    ]
                    ?: '—'
                ) ?>
            </strong>
        </div>

        <div class="external-directory-readonly-item">
            <small>شماره ثبت</small>
            <strong dir="ltr">
                <?= admin_h(
                    $selectedOrganization[
                        'registration_number'
                    ]
                    ?: '—'
                ) ?>
            </strong>
        </div>

        <div class="external-directory-readonly-item">
            <small>وب‌سایت</small>
            <strong dir="ltr">
                <?= admin_h(
                    $selectedOrganization[
                        'website_url'
                    ]
                    ?: '—'
                ) ?>
            </strong>
        </div>
    </div>

    <div class="external-directory-actions">
        <a
            class="admin-btn"
            href="<?= admin_h(
                $directoryTabUrl(
                    'profile'
                )
                . '&mode=edit-organization'
            ) ?>"
        >
            ویرایش اطلاعات سازمان
        </a>
    </div>

<?php if (
    $directoryMode
        === 'edit-organization'
): ?>
<details class="external-directory-inline-details external-directory-organization-edit external-directory-action-form" open>
        <summary>
            ویرایش اطلاعات سازمان
        </summary>

<form
        method="post"
        action="/admin/automation/external-organizations/save"
        class="admin-form"
    >
        <input
            type="hidden"
            name="_token"
            value="<?= admin_h($csrf) ?>"
        >

        <input
            type="hidden"
            name="public_reference"
            value="<?= admin_h(
                $organizationReference
            ) ?>"
        >

        <div class="admin-form-grid">
            <label class="admin-field">
                <span>
                    عنوان فارسی *
                </span>

                <input
                    name="title_fa"
                    required
                    value="<?= admin_h(
                        $selectedOrganization[
                            'title_fa'
                        ]
                        ?? ''
                    ) ?>"
                >
            </label>

            <label class="admin-field">
                <span>
                    عنوان انگلیسی
                </span>

                <input
                    name="title_en"
                    dir="ltr"
                    value="<?= admin_h(
                        $selectedOrganization[
                            'title_en'
                        ]
                        ?? ''
                    ) ?>"
                >
            </label>

            <label class="admin-field">
                <span>
                    عنوان کوتاه
                </span>

                <input
                    name="short_title"
                    value="<?= admin_h(
                        $selectedOrganization[
                            'short_title'
                        ]
                        ?? ''
                    ) ?>"
                >
            </label>

            <label class="admin-field">
                <span>
                    شناسه ملی
                </span>

                <input
                    name="national_id"
                    dir="ltr"
                    value="<?= admin_h(
                        $selectedOrganization[
                            'national_id'
                        ]
                        ?? ''
                    ) ?>"
                >
            </label>

            <label class="admin-field">
                <span>
                    شماره ثبت
                </span>

                <input
                    name="registration_number"
                    dir="ltr"
                    value="<?= admin_h(
                        $selectedOrganization[
                            'registration_number'
                        ]
                        ?? ''
                    ) ?>"
                >
            </label>

            <label class="admin-field">
                <span>
                    وب‌سایت
                </span>

                <input
                    name="website_url"
                    type="url"
                    dir="ltr"
                    value="<?= admin_h(
                        $selectedOrganization[
                            'website_url'
                        ]
                        ?? ''
                    ) ?>"
                >
            </label>

            <label class="admin-field admin-field--wide">
                <span>
                    توضیحات
                </span>

                <textarea
                    name="notes"
                    rows="3"
                ><?= admin_h(
                    $selectedOrganization[
                        'notes'
                    ]
                    ?? ''
                ) ?></textarea>
            </label>
        </div>

        <div class="admin-form-actions">
            <button
                type="submit"
                class="admin-btn admin-btn--primary"
            >
                ذخیره تغییرات
            </button>
        </div>
    </form>

    <?php if (
        (
            $selectedOrganization[
                'status'
            ]
            ?? ''
        ) === 'active'
    ): ?>
        <form
            method="post"
            action="/admin/automation/external-organizations/deactivate"
            class="admin-form"
        >
            <input
                type="hidden"
                name="_token"
                value="<?= admin_h($csrf) ?>"
            >

            <input
                type="hidden"
                name="organization_reference"
                value="<?= admin_h(
                    $organizationReference
                ) ?>"
            >

            <div class="admin-form-actions">
                <button
                    type="submit"
                    class="admin-btn"
                >
                    غیرفعال کردن سازمان
                </button>
            </div>
        </form>
    <?php endif; ?>
    </details>
<?php endif; ?>


</section>
<?php endif; ?>


<?php if ($activeDirectoryTab === 'destinations'): ?>
<section class="admin-section">
    <div class="admin-section__header">
        <div>
            <h2>
                دبیرخانه‌ها و نقاط مکاتباتی
            </h2>

            <p class="admin-muted">
                هر سازمان می‌تواند چند مقصد مستقل
                برای مکاتبه داشته باشد.
            </p>
        </div>

        <strong>
            <?= admin_h(
                $digits(
                    count($contactPoints)
                )
            ) ?>
        </strong>
    </div>


    <?php if ($contactPoints === []): ?>
        <div
            class="admin-alert external-directory-tab-empty"
        >
            هنوز مقصد مکاتباتی برای این سازمان
            ثبت نشده است.
        </div>
    <?php else: ?>
        <div
            class="external-directory-table-wrap"
            id="external-directory-destination-grid"
        >
            <table class="external-directory-table">
                <thead>
                    <tr>
                        <th>عنوان مقصد</th>
                        <th>نوع</th>
                        <th>پیش‌فرض</th>
                        <th>روش ترجیحی</th>
                        <th>مسئول پیگیری</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach (
                    $contactPoints
                    as $gridPoint
                ): ?>
                    <?php
                    $gridPointReference =
                        (string) (
                            $gridPoint[
                                'public_reference'
                            ]
                            ?? ''
                        );
                    ?>

                    <tr>
                        <td>
                            <strong>
                                <?= admin_h(
                                    $gridPoint[
                                        'title'
                                    ]
                                    ?? ''
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?= admin_h(
                                $pointKindLabels[
                                    $gridPoint[
                                        'point_kind_code'
                                    ]
                                    ?? ''
                                ]
                                ?? '—'
                            ) ?>
                        </td>

                        <td>
                            <?= (int) (
                                $gridPoint[
                                    'is_primary'
                                ]
                                ?? 0
                            ) === 1
                                ? 'بله'
                                : '—' ?>
                        </td>

                        <td>
                            <?= admin_h(
                                $dispatchLabels[
                                    $gridPoint[
                                        'preferred_dispatch_channel_code'
                                    ]
                                    ?? ''
                                ]
                                ?? 'بدون ترجیح'
                            ) ?>
                        </td>

                        <td>
                            <?= admin_h(
                                $gridPoint[
                                    'contact_person_name'
                                ]
                                ?: '—'
                            ) ?>
                        </td>

                        <td>
                            <?= admin_h(
                                $statusLabel(
                                    $gridPoint[
                                        'status'
                                    ]
                                    ?? ''
                                )
                            ) ?>
                        </td>

                        <td>
                            <div
                                class="external-directory-row-actions"
                            >
                                <a
                                    class="admin-btn"
                                    href="<?= admin_h(
                                        $directoryTabUrl(
                                            'destinations',
                                            $gridPointReference
                                        )
                                    ) ?>#destination-<?= admin_h(
                                        $gridPointReference
                                    ) ?>"
                                >
                                    مشاهده
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>


    <div
        class="external-directory-actions"
        id="create-destination-action"
    >
        <a
            class="admin-btn admin-btn--primary"
            href="<?= admin_h(
                $directoryTabUrl(
                    'destinations'
                )
                . '&mode=create-destination'
            ) ?>"
        >
            + مقصد مکاتباتی
        </a>
    </div>

    <?php if (
        $directoryMode
            === 'create-destination'
    ): ?>
<details class="external-directory-inline-details external-directory-create-destination external-directory-action-form" open>
        <summary>
            + افزودن مقصد مکاتباتی
        </summary>

<form
        method="post"
        action="/admin/automation/external-organizations/contact-points/save"
        class="admin-form"
    >
        <input
            type="hidden"
            name="_token"
            value="<?= admin_h($csrf) ?>"
        >

        <input
            type="hidden"
            name="organization_reference"
            value="<?= admin_h(
                $organizationReference
            ) ?>"
        >

        <div class="admin-form-grid">
            <label class="admin-field">
                <span>
                    عنوان *
                </span>

                <input
                    name="title"
                    required
                    placeholder="دبیرخانه مرکزی"
                >
            </label>

            <label class="admin-field">
                <span>
                    نوع مقصد
                </span>

                <select name="point_kind_code">
                    <?php foreach (
                        $pointKindLabels
                        as $code => $label
                    ): ?>
                        <option
                            value="<?= admin_h(
                                $code
                            ) ?>"
                        >
                            <?= admin_h(
                                $label
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="admin-field">
                <span>
                    مسئول پیگیری
                </span>

                <input
                    name="contact_person_name"
                >
            </label>

            <label class="admin-field">
                <span>
                    سمت مسئول
                </span>

                <input
                    name="contact_person_title"
                >
            </label>

            <label class="admin-field">
                <span>
                    ساعات پاسخگویی
                </span>

                <input
                    name="business_hours"
                >
            </label>







            <label class="admin-field">
                <span>
                    روش ارسال ترجیحی
                </span>

                <select
                    name="preferred_dispatch_channel_code"
                >
                    <option value="">
                        بدون ترجیح
                    </option>

                    <?php foreach (
                        $dispatchLabels
                        as $code => $label
                    ): ?>
                        <option
                            value="<?= admin_h(
                                $code
                            ) ?>"
                        >
                            <?= admin_h(
                                $label
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="admin-field">
                <span>
                    <input
                        type="checkbox"
                        name="is_primary"
                        value="1"
                    >
                    مقصد پیش‌فرض
                </span>
            </label>
        </div>

        <div class="admin-form-actions">
            <button
                type="submit"
                class="admin-btn admin-btn--primary"
            >
                ثبت مقصد مکاتباتی
            </button>
        </div>
    </form>
    </details>
    <?php endif; ?>

</section>
<?php endif; ?>



<?php if (in_array(
    $activeDirectoryTab,
    [
        'contacts',
        'addresses',
    ],
    true
)): ?>

<section class="admin-section">
    <div class="admin-section__header">
        <div>
            <h2>
                <?= $activeDirectoryTab === 'contacts'
                    ? 'راه‌های ارتباطی'
                    : 'نشانی‌ها' ?>
            </h2>

            <p class="admin-muted">
                مقصد مکاتباتی مورد نظر را انتخاب کنید.
            </p>
        </div>
    </div>

    <?php if ($contactPoints === []): ?>
        <div class="admin-alert">
            ابتدا یک مقصد مکاتباتی برای این
            سازمان ثبت کنید.
        </div>
    <?php else: ?>
        <form
            method="get"
            action="/admin/automation/external-organizations"
            class="external-directory-destination-selector"
        >
            <input
                type="hidden"
                name="organization"
                value="<?= admin_h(
                    $organizationReference
                ) ?>"
            >

            <input
                type="hidden"
                name="tab"
                value="<?= admin_h(
                    $activeDirectoryTab
                ) ?>"
            >

            <label class="admin-field">
                <span>
                    مقصد مکاتباتی
                </span>

                <select name="point">
                    <?php foreach (
                        $contactPoints
                        as $selectorPoint
                    ): ?>
                        <?php
                        $selectorReference =
                            (string) (
                                $selectorPoint[
                                    'public_reference'
                                ]
                                ?? ''
                            );
                        ?>

                        <option
                            value="<?= admin_h(
                                $selectorReference
                            ) ?>"
                            <?= $selectorReference
                                === $selectedDirectoryPointReference
                                    ? 'selected'
                                    : '' ?>
                        >
                            <?= admin_h(
                                $selectorPoint[
                                    'title'
                                ]
                                ?? ''
                            ) ?>

                            <?= (int) (
                                $selectorPoint[
                                    'is_primary'
                                ]
                                ?? 0
                            ) === 1
                                ? ' — پیش‌فرض'
                                : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <button
                type="submit"
                class="admin-btn admin-btn--primary"
            >
                نمایش
            </button>
        </form>
    <?php endif; ?>
</section>

<?php endif; ?>

<?php foreach ($contactPoints as $point): ?>
<?php
$pointReference =
    (string) (
        $point[
            'public_reference'
        ]
        ?? ''
    );

$methods =
    is_array($point['methods'] ?? null)
        ? $point['methods']
        : [];

$addresses =
    is_array($point['addresses'] ?? null)
        ? $point['addresses']
        : [];

$destinationValues = [
    'phone' => '',
    'extension' => '',
    'fax' => '',
    'email' => '',
];

foreach ($methods as $method) {
    if (
        (
            $method['status']
            ?? ''
        ) !== 'active'
    ) {
        continue;
    }

    $typeCode =
        (string) (
            $method[
                'contact_type_code'
            ]
            ?? ''
        );

    if (
        array_key_exists(
            $typeCode,
            $destinationValues
        )
        && $destinationValues[
            $typeCode
        ] === ''
    ) {
        $destinationValues[
            $typeCode
        ] =
            (string) (
                $method['value']
                ?? ''
            );
    }
}

$destinationAddress = null;

foreach ($addresses as $candidateAddress) {
    if (
        (
            $candidateAddress[
                'status'
            ]
            ?? ''
        ) !== 'active'
    ) {
        continue;
    }

    if (
        $destinationAddress === null
        || (int) (
            $candidateAddress[
                'is_primary'
            ]
            ?? 0
        ) === 1
    ) {
        $destinationAddress =
            $candidateAddress;
    }

    if (
        (int) (
            $candidateAddress[
                'is_primary'
            ]
            ?? 0
        ) === 1
    ) {
        break;
    }
}
?>

<section
    class="admin-section admin-users-panel"
    id="destination-<?= admin_h(
        $pointReference
    ) ?>"
    <?= (
        (
            $activeDirectoryTab
                === 'destinations'
            && $requestedPointReference
                === $pointReference
        )
        || (
            in_array(
                $activeDirectoryTab,
                [
                    'contacts',
                    'addresses',
                ],
                true
            )
            && $selectedDirectoryPointReference
                === $pointReference
        )
    )
        ? ''
        : 'hidden' ?>
>
    <div class="admin-section__header">
        <div>
            <h2>
                <?= admin_h(
                    $point['title']
                    ?? ''
                ) ?>
            </h2>

            <p class="admin-muted">
                <?= admin_h(
                    $pointKindLabels[
                        $point[
                            'point_kind_code'
                        ]
                        ?? ''
                    ]
                    ?? (
                        $point[
                            'point_kind_code'
                        ]
                        ?? ''
                    )
                ) ?>
            </p>
        </div>

        <span class="admin-badge">
            <?= admin_h(
                $statusLabel(
                    $point['status']
                    ?? ''
                )
            ) ?>
        </span>
    </div>



    <?php if (
        $activeDirectoryTab
            === 'destinations'
    ): ?>

        <div
            class="external-directory-readonly-grid"
        >
            <div class="external-directory-readonly-item">
                <small>عنوان مقصد</small>
                <strong>
                    <?= admin_h(
                        $point[
                            'title'
                        ]
                        ?? '—'
                    ) ?>
                </strong>
            </div>

            <div class="external-directory-readonly-item">
                <small>نوع مقصد</small>
                <strong>
                    <?= admin_h(
                        $pointKindLabels[
                            $point[
                                'point_kind_code'
                            ]
                            ?? ''
                        ]
                        ?? '—'
                    ) ?>
                </strong>
            </div>

            <div class="external-directory-readonly-item">
                <small>مسئول پیگیری</small>
                <strong>
                    <?= admin_h(
                        $point[
                            'contact_person_name'
                        ]
                        ?: '—'
                    ) ?>
                </strong>
            </div>

            <div class="external-directory-readonly-item">
                <small>سمت مسئول</small>
                <strong>
                    <?= admin_h(
                        $point[
                            'contact_person_title'
                        ]
                        ?: '—'
                    ) ?>
                </strong>
            </div>

            <div class="external-directory-readonly-item">
                <small>روش ترجیحی ارسال</small>
                <strong>
                    <?= admin_h(
                        $dispatchLabels[
                            $point[
                                'preferred_dispatch_channel_code'
                            ]
                            ?? ''
                        ]
                        ?? 'بدون ترجیح'
                    ) ?>
                </strong>
            </div>

            <div class="external-directory-readonly-item">
                <small>مقصد پیش‌فرض</small>
                <strong>
                    <?= (int) (
                        $point[
                            'is_primary'
                        ]
                        ?? 0
                    ) === 1
                        ? 'بله'
                        : 'خیر' ?>
                </strong>
            </div>
        </div>

        <div
            class="external-directory-actions"
            id="point-readonly-actions"
        >
            <a
                class="admin-btn"
                href="<?= admin_h(
                    $directoryTabUrl(
                        'destinations',
                        $pointReference
                    )
                    . '&mode=edit-destination'
                ) ?>#destination-<?= admin_h(
                    $pointReference
                ) ?>"
            >
                ویرایش مقصد
            </a>
        </div>

    <?php elseif (
        $activeDirectoryTab
            === 'contacts'
    ): ?>

        <div class="external-directory-actions">
            <a
                class="admin-btn admin-btn--primary"
                href="<?= admin_h(
                    $directoryTabUrl(
                        'contacts',
                        $pointReference
                    )
                    . '&mode=manage-contacts'
                ) ?>#destination-<?= admin_h(
                    $pointReference
                ) ?>"
            >
                + راه ارتباطی
            </a>
        </div>

    <?php elseif (
        $activeDirectoryTab
            === 'addresses'
    ): ?>

        <div class="external-directory-actions">
            <a
                class="admin-btn admin-btn--primary"
                href="<?= admin_h(
                    $directoryTabUrl(
                        'addresses',
                        $pointReference
                    )
                    . '&mode=manage-addresses'
                ) ?>#destination-<?= admin_h(
                    $pointReference
                ) ?>"
            >
                + نشانی
            </a>
        </div>

    <?php endif; ?>

<details
        class="external-directory-action-form"
        <?= (
            $activeDirectoryTab
                === 'destinations'
            && $directoryMode
                === 'edit-destination'
        )
            ? 'open'
            : 'hidden' ?>
    >
        <summary>
            مشخصات دبیرخانه / مقصد
        </summary>

        <form
            method="post"
            action="/admin/automation/external-organizations/contact-points/save"
            class="admin-form"
        >
            <input
                type="hidden"
                name="_token"
                value="<?= admin_h($csrf) ?>"
            >

            <input
                type="hidden"
                name="organization_reference"
                value="<?= admin_h(
                    $organizationReference
                ) ?>"
            >

            <input
                type="hidden"
                name="public_reference"
                value="<?= admin_h(
                    $pointReference
                ) ?>"
            >

            <div class="admin-form-grid">
                <label class="admin-field">
                    <span>عنوان *</span>

                    <input
                        name="title"
                        required
                        value="<?= admin_h(
                            $point['title']
                            ?? ''
                        ) ?>"
                    >
                </label>

                <label class="admin-field">
                    <span>نوع مقصد</span>

                    <select name="point_kind_code">
                        <?php foreach (
                            $pointKindLabels
                            as $code => $label
                        ): ?>
                            <option
                                value="<?= admin_h(
                                    $code
                                ) ?>"
                                <?= $selected(
                                    $point[
                                        'point_kind_code'
                                    ]
                                    ?? '',
                                    $code
                                ) ?>
                            >
                                <?= admin_h(
                                    $label
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="admin-field">
                    <span>مسئول پیگیری</span>

                    <input
                        name="contact_person_name"
                        value="<?= admin_h(
                            $point[
                                'contact_person_name'
                            ]
                            ?? ''
                        ) ?>"
                    >
                </label>

                <label class="admin-field">
                    <span>سمت مسئول</span>

                    <input
                        name="contact_person_title"
                        value="<?= admin_h(
                            $point[
                                'contact_person_title'
                            ]
                            ?? ''
                        ) ?>"
                    >
                </label>

                <label class="admin-field">
                    <span>ساعات پاسخگویی</span>

                    <input
                        name="business_hours"
                        value="<?= admin_h(
                            $point[
                                'business_hours'
                            ]
                            ?? ''
                        ) ?>"
                    >
                </label>







                <label class="admin-field">
                    <span>
                        روش ارسال ترجیحی
                    </span>

                    <select
                        name="preferred_dispatch_channel_code"
                    >
                        <option value="">
                            بدون ترجیح
                        </option>

                        <?php foreach (
                            $dispatchLabels
                            as $code => $label
                        ): ?>
                            <option
                                value="<?= admin_h(
                                    $code
                                ) ?>"
                                <?= $selected(
                                    $point[
                                        'preferred_dispatch_channel_code'
                                    ]
                                    ?? '',
                                    $code
                                ) ?>
                            >
                                <?= admin_h(
                                    $label
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="admin-field">
                    <span>
                        <input
                            type="checkbox"
                            name="is_primary"
                            value="1"
                            <?= $checked(
                                $point[
                                    'is_primary'
                                ]
                                ?? 0
                            ) ?>
                        >
                        مقصد پیش‌فرض
                    </span>
                </label>
            </div>

            <div class="admin-form-actions">
                <button
                    type="submit"
                    class="admin-btn admin-btn--primary"
                >
                    ذخیره مقصد
                </button>
            </div>
        </form>

        <?php if (
            (
                $point['status']
                ?? ''
            ) === 'active'
        ): ?>
            <form
                method="post"
                action="/admin/automation/external-organizations/contact-points/deactivate"
                class="admin-form"
            >
                <input
                    type="hidden"
                    name="_token"
                    value="<?= admin_h($csrf) ?>"
                >

                <input
                    type="hidden"
                    name="organization_reference"
                    value="<?= admin_h(
                        $organizationReference
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="contact_point_reference"
                    value="<?= admin_h(
                        $pointReference
                    ) ?>"
                >

                <div class="admin-form-actions">
                    <button
                        type="submit"
                        class="admin-btn"
                    >
                        غیرفعال کردن مقصد
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </details>


    <details
        class="external-directory-action-form"
        <?= (
            $activeDirectoryTab
                === 'contacts'
            && $directoryMode
                === 'manage-contacts'
        )
            ? 'open'
            : 'hidden' ?>
    >
        <summary>
            راه‌های ارتباطی تکمیلی
        </summary>

        <?php foreach ($methods as $method): ?>
            <form
                method="post"
                action="/admin/automation/external-organizations/contact-methods/save"
                class="admin-form external-contact-method-form" data-contact-method-form
            >
                <input
                    type="hidden"
                    name="_token"
                    value="<?= admin_h($csrf) ?>"
                >

                <input
                    type="hidden"
                    name="organization_reference"
                    value="<?= admin_h(
                        $organizationReference
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="contact_point_reference"
                    value="<?= admin_h(
                        $pointReference
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="public_reference"
                    value="<?= admin_h(
                        $method[
                            'public_reference'
                        ]
                        ?? ''
                    ) ?>"
                >

                <div class="admin-form-grid">
                    <label class="admin-field">
                        <span>نوع ارتباط</span>

                        <select
                            name="contact_type_code"
                            data-contact-type-selector
                            required
                        >
                            <?php foreach (
                                $contactTypes
                                as $type
                            ): ?>
                                <option
                                    value="<?= admin_h(
                                        $type['code']
                                    ) ?>"
                                    <?= $selected(
                                        $method[
                                            'contact_type_code'
                                        ]
                                        ?? '',
                                        $type['code']
                                    ) ?>
                                >
                                    <?= admin_h(
                                        $type['title']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <div class="external-contact-phone-group" data-contact-phone-group>
<label
                        class="admin-field"
                        data-phone-contact-field
                    >
                        <span>پیش‌شماره</span>

                        <input
                            name="area_code"
                            inputmode="numeric"
                            dir="ltr"
                            placeholder="۲۱"
                            value="<?= admin_h(
                                $digits(
                                    $method[
                                        'area_code'
                                    ]
                                    ?? ''
                                )
                            ) ?>"
                        >

                        <small class="admin-muted">
                            اختیاری؛ بدون صفر، مثال ۲۱
                        </small>
                    </label>

<label class="admin-field">
                        <span data-contact-value-label>مقدار *</span>

                        <input
                            name="value"
                            required
                            value="<?= admin_h(
                                $method['value']
                                ?? ''
                            ) ?>"
                        >
                    </label>

<label
                        class="admin-field"
                        data-phone-contact-field
                    >
                        <span>داخلی</span>

                        <input
                            name="extension"
                            inputmode="numeric"
                            dir="ltr"
                            value="<?= admin_h(
                                $digits(
                                    $method[
                                        'extension'
                                    ]
                                    ?? ''
                                )
                            ) ?>"
                        >

                        <small class="admin-muted">
                            اختیاری؛ متعلق به همین تلفن
                        </small>
                    </label>
</div>

                    <label class="admin-field">
                        <span>عنوان / توضیح</span>

                        <input
                            name="label"
                            value="<?= admin_h(
                                $method['label']
                                ?? ''
                            ) ?>"
                        >
                    </label>

                    <label class="admin-field">
                        <span>ترتیب نمایش</span>

                        <input
                            type="text"
                            min="0"
                            inputmode="numeric"
                            name="sort_order"
                            value="<?= admin_h(
                                $method[
                                    'sort_order'
                                ]
                                ?? 0
                            ) ?>"
                        >
                    </label>

                    <div class="external-contact-options-group" data-contact-options-group>
<?php foreach ([
                        'is_primary' =>
                            'راه اصلی',

                        'is_verified' =>
                            'تأییدشده',

                        'supports_dispatch' =>
                            'قابل استفاده برای ارسال',

                        'supports_followup' =>
                            'قابل استفاده برای پیگیری',
                    ] as $field => $label): ?>
                        <label class="admin-field">
                            <span>
                                <input
                                    type="checkbox"
                                    name="<?= admin_h(
                                        $field
                                    ) ?>"
                                    value="1"
                                    <?= $checked(
                                        $method[
                                            $field
                                        ]
                                        ?? 0
                                    ) ?>
                                >
                                <?= admin_h(
                                    $label
                                ) ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
</div>
                </div>

                <div class="admin-form-actions">
                    <button
                        type="submit"
                        class="admin-btn admin-btn--primary"
                    >
                        ذخیره راه ارتباطی
                    </button>
                </div>
            </form>

            <?php if (
                (
                    $method['status']
                    ?? ''
                ) === 'active'
            ): ?>
                <form
                    method="post"
                    action="/admin/automation/external-organizations/contact-methods/deactivate"
                    class="admin-form"
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= admin_h($csrf) ?>"
                    >

                    <input
                        type="hidden"
                        name="organization_reference"
                        value="<?= admin_h(
                            $organizationReference
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="contact_point_reference"
                        value="<?= admin_h(
                            $pointReference
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="method_reference"
                        value="<?= admin_h(
                            $method[
                                'public_reference'
                            ]
                            ?? ''
                        ) ?>"
                    >

                    <div class="admin-form-actions">
                        <button
                            type="submit"
                            class="admin-btn"
                        >
                            غیرفعال
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        <?php endforeach; ?>


        <form
            method="post"
            action="/admin/automation/external-organizations/contact-methods/save"
            class="admin-form external-contact-method-form" data-contact-method-form
        >
            <input
                type="hidden"
                name="_token"
                value="<?= admin_h($csrf) ?>"
            >

            <input
                type="hidden"
                name="organization_reference"
                value="<?= admin_h(
                    $organizationReference
                ) ?>"
            >

            <input
                type="hidden"
                name="contact_point_reference"
                value="<?= admin_h(
                    $pointReference
                ) ?>"
            >

            <div class="admin-form-grid">
                <label class="admin-field">
                    <span>نوع ارتباط *</span>

                    <select
                        name="contact_type_code"
                            data-contact-type-selector
                        required
                    >
                        <?php foreach (
                            $contactTypes
                            as $type
                        ): ?>
                            <option
                                value="<?= admin_h(
                                    $type['code']
                                ) ?>"
                            >
                                <?= admin_h(
                                    $type['title']
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <div class="external-contact-phone-group" data-contact-phone-group>
<label
                    class="admin-field"
                    data-phone-contact-field
                >
                    <span>پیش‌شماره</span>

                    <input
                        name="area_code"
                        inputmode="numeric"
                        dir="ltr"
                        placeholder="۲۱"
                    >

                    <small class="admin-muted">
                        اختیاری؛ بدون صفر، مثال ۲۱
                    </small>
                </label>

<label class="admin-field">
                    <span data-contact-value-label>مقدار *</span>

                    <input
                        name="value"
                        required
                    >
                </label>

<label
                    class="admin-field"
                    data-phone-contact-field
                >
                    <span>داخلی</span>

                    <input
                        name="extension"
                        inputmode="numeric"
                        dir="ltr"
                    >

                    <small class="admin-muted">
                        اختیاری؛ متعلق به همین تلفن
                    </small>
                </label>
</div>

                <label class="admin-field">
                    <span>عنوان / توضیح</span>

                    <input name="label">
                </label>

                <label class="admin-field">
                    <span>ترتیب نمایش</span>

                    <input
                        type="text"
                        min="0"
                        inputmode="numeric"
                            name="sort_order"
                        value="0"
                    >
                </label>

                <div class="external-contact-options-group" data-contact-options-group>
<label class="admin-field">
                    <span>
                        <input
                            type="checkbox"
                            name="is_primary"
                            value="1"
                        >
                        راه اصلی
                    </span>
                </label>

                <label class="admin-field">
                    <span>
                        <input
                            type="checkbox"
                            name="is_verified"
                            value="1"
                        >
                        تأییدشده
                    </span>
                </label>

                <label class="admin-field">
                    <span>
                        <input
                            type="checkbox"
                            name="supports_dispatch"
                            value="1"
                        >
                        قابل استفاده برای ارسال
                    </span>
                </label>

                <label class="admin-field">
                    <span>
                        <input
                            type="checkbox"
                            name="supports_followup"
                            value="1"
                        >
                        قابل استفاده برای پیگیری
                    </span>
                </label>
</div>
            </div>

            <div class="admin-form-actions">
                <button
                    type="submit"
                    class="admin-btn admin-btn--primary"
                >
                    افزودن راه ارتباطی
                </button>
            </div>
        </form>
    </details>


    <details
        class="external-directory-action-form"
        <?= (
            $activeDirectoryTab
                === 'addresses'
            && $directoryMode
                === 'manage-addresses'
        )
            ? 'open'
            : 'hidden' ?>
    >
        <summary>
            نشانی‌های تکمیلی
        </summary>

        <?php foreach ($addresses as $address): ?>
            <form
                method="post"
                action="/admin/automation/external-organizations/addresses/save"
                class="admin-form"
            >
                <input
                    type="hidden"
                    name="_token"
                    value="<?= admin_h($csrf) ?>"
                >

                <input
                    type="hidden"
                    name="organization_reference"
                    value="<?= admin_h(
                        $organizationReference
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="contact_point_reference"
                    value="<?= admin_h(
                        $pointReference
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="public_reference"
                    value="<?= admin_h(
                        $address[
                            'public_reference'
                        ]
                        ?? ''
                    ) ?>"
                >

                <div class="admin-form-grid">
                    <label class="admin-field">
                        <span>نوع نشانی</span>

                        <select
                            name="address_type_code"
                            required
                        >
                            <?php foreach (
                                $addressTypes
                                as $type
                            ): ?>
                                <option
                                    value="<?= admin_h(
                                        $type['code']
                                    ) ?>"
                                    <?= $selected(
                                        $address[
                                            'address_type_code'
                                        ]
                                        ?? '',
                                        $type['code']
                                    ) ?>
                                >
                                    <?= admin_h(
                                        $type['title']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="admin-field">
                        <span>محدوده / ناحیه</span>

                        <input
                            name="district"
                            value="<?= admin_h(
                                $address[
                                    'district'
                                ]
                                ?? ''
                            ) ?>"
                        >
                    </label>

                    <label class="admin-field">
                        <span>کدپستی</span>

                        <input
                            name="postal_code"
                            dir="ltr"
                            value="<?= admin_h(
                                $address[
                                    'postal_code'
                                ]
                                ?? ''
                            ) ?>"
                        >
                    </label>

                    <label class="admin-field admin-field--wide">
                        <span>نشانی *</span>

                        <textarea
                            name="address_line"
                            required
                            rows="3"
                        ><?= admin_h(
                            $address[
                                'address_line'
                            ]
                            ?? ''
                        ) ?></textarea>
                    </label>

                    <label class="admin-field">
                        <span>
                            <input
                                type="checkbox"
                                name="is_primary"
                                value="1"
                                <?= $checked(
                                    $address[
                                        'is_primary'
                                    ]
                                    ?? 0
                                ) ?>
                            >
                            نشانی اصلی
                        </span>
                    </label>

                    <label class="admin-field">
                        <span>
                            <input
                                type="checkbox"
                                name="supports_dispatch"
                                value="1"
                                <?= $checked(
                                    $address[
                                        'supports_dispatch'
                                    ]
                                    ?? 0
                                ) ?>
                            >
                            قابل استفاده برای ارسال
                        </span>
                    </label>
                </div>

                <div class="admin-form-actions">
                    <button
                        type="submit"
                        class="admin-btn admin-btn--primary"
                    >
                        ذخیره نشانی
                    </button>
                </div>
            </form>

            <?php if (
                (
                    $address['status']
                    ?? ''
                ) === 'active'
            ): ?>
                <form
                    method="post"
                    action="/admin/automation/external-organizations/addresses/deactivate"
                    class="admin-form"
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= admin_h($csrf) ?>"
                    >

                    <input
                        type="hidden"
                        name="organization_reference"
                        value="<?= admin_h(
                            $organizationReference
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="contact_point_reference"
                        value="<?= admin_h(
                            $pointReference
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="address_reference"
                        value="<?= admin_h(
                            $address[
                                'public_reference'
                            ]
                            ?? ''
                        ) ?>"
                    >

                    <div class="admin-form-actions">
                        <button
                            type="submit"
                            class="admin-btn"
                        >
                            غیرفعال
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        <?php endforeach; ?>


        <form
            method="post"
            action="/admin/automation/external-organizations/addresses/save"
            class="admin-form"
        >
            <input
                type="hidden"
                name="_token"
                value="<?= admin_h($csrf) ?>"
            >

            <input
                type="hidden"
                name="organization_reference"
                value="<?= admin_h(
                    $organizationReference
                ) ?>"
            >

            <input
                type="hidden"
                name="contact_point_reference"
                value="<?= admin_h(
                    $pointReference
                ) ?>"
            >

            <div class="admin-form-grid">
                <label class="admin-field">
                    <span>نوع نشانی *</span>

                    <select
                        name="address_type_code"
                        required
                    >
                        <?php foreach (
                            $addressTypes
                            as $type
                        ): ?>
                            <option
                                value="<?= admin_h(
                                    $type['code']
                                ) ?>"
                                <?= $selected(
                                    'correspondence',
                                    $type['code']
                                ) ?>
                            >
                                <?= admin_h(
                                    $type['title']
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="admin-field">
                    <span>محدوده / ناحیه</span>

                    <input name="district">
                </label>

                <label class="admin-field">
                    <span>کدپستی</span>

                    <input
                        name="postal_code"
                        dir="ltr"
                    >
                </label>

                <label class="admin-field admin-field--wide">
                    <span>نشانی *</span>

                    <textarea
                        name="address_line"
                        required
                        rows="3"
                    ></textarea>
                </label>

                <label class="admin-field">
                    <span>
                        <input
                            type="checkbox"
                            name="is_primary"
                            value="1"
                        >
                        نشانی اصلی
                    </span>
                </label>

                <label class="admin-field">
                    <span>
                        <input
                            type="checkbox"
                            name="supports_dispatch"
                            value="1"
                            checked
                        >
                        قابل استفاده برای ارسال
                    </span>
                </label>
            </div>

            <div class="admin-form-actions">
                <button
                    type="submit"
                    class="admin-btn admin-btn--primary"
                >
                    افزودن نشانی
                </button>
            </div>
        </form>
    </details>
</section>

<?php endforeach; ?>

<?php endif; ?>



<script data-structured-phone-ui>
(function () {
    var valueLabels = {
        phone: 'شماره تلفن *',
        mobile: 'شماره همراه *',
        fax: 'شماره فاکس *',
        email: 'نشانی ایمیل *',
        website: 'نشانی وب‌سایت *',
        system: 'شناسه / نشانی سامانه *'
    };

    var placeholders = {
        phone: '۳۳۳۳۳۳۳۳',
        mobile: '۰۹۱۲۱۲۳۴۵۶۷',
        fax: '۳۳۳۳۳۳۳۳',
        email: 'info@example.ir',
        website: 'https://example.ir',
        system: 'شناسه یا نشانی سامانه'
    };

    var directDispatchTypes = [
        'fax',
        'email',
        'system'
    ];

    var numericContactTypes = [
        'phone',
        'mobile',
        'fax'
    ];

    var persianDigits =
        '۰۱۲۳۴۵۶۷۸۹';

    var arabicDigits =
        '٠١٢٣٤٥٦٧٨٩';

    var toPersianDigits =
        function (value) {
            return String(value || '')
                .replace(
                    /[0-9]/g,
                    function (digit) {
                        return persianDigits[
                            Number(digit)
                        ];
                    }
                )
                .replace(
                    /[٠-٩]/g,
                    function (digit) {
                        var index =
                            arabicDigits.indexOf(
                                digit
                            );

                        return index >= 0
                            ? persianDigits[index]
                            : digit;
                    }
                );
        };

    var persianizeInput =
        function (input) {
            if (!input) {
                return;
            }

            var oldValue =
                input.value;

            var newValue =
                toPersianDigits(
                    oldValue
                );

            if (oldValue === newValue) {
                return;
            }

            var start =
                typeof input.selectionStart
                    === 'number'
                    ? input.selectionStart
                    : null;

            var end =
                typeof input.selectionEnd
                    === 'number'
                    ? input.selectionEnd
                    : null;

            input.value =
                newValue;

            if (
                start !== null
                && end !== null
                && typeof input.setSelectionRange
                    === 'function'
            ) {
                input.setSelectionRange(
                    start,
                    end
                );
            }
        };

    document
        .querySelectorAll(
            '[data-contact-method-form]'
        )
        .forEach(function (form) {
            var selector =
                form.querySelector(
                    '[data-contact-type-selector]'
                );

            var valueInput =
                form.querySelector(
                    '[name="value"]'
                );

            var valueLabel =
                form.querySelector(
                    '[data-contact-value-label]'
                );

            var areaCodeInput =
                form.querySelector(
                    '[name="area_code"]'
                );

            var extensionInput =
                form.querySelector(
                    '[name="extension"]'
                );

            var sortInput =
                form.querySelector(
                    '[name="sort_order"]'
                );

            var phoneFields =
                form.querySelectorAll(
                    '[data-phone-contact-field]'
                );

            var dispatchInput =
                form.querySelector(
                    '[name="supports_dispatch"]'
                );

            var dispatchField =
                dispatchInput
                    ? dispatchInput.closest(
                        '.admin-field'
                    )
                    : null;

            if (!selector) {
                return;
            }

            var isNumericType =
                function () {
                    return numericContactTypes
                        .indexOf(
                            selector.value
                        ) !== -1;
                };

            var sync =
                function () {
                    var type =
                        selector.value;

                    var isPhone =
                        type === 'phone';

                    form.classList.toggle(
                        'is-phone',
                        isPhone
                    );

                    phoneFields.forEach(
                        function (field) {
                            field.hidden =
                                !isPhone;

                            var input =
                                field.querySelector(
                                    'input'
                                );

                            if (input) {
                                input.disabled =
                                    !isPhone;
                            }
                        }
                    );

                    if (valueLabel) {
                        valueLabel.textContent =
                            valueLabels[type]
                            || 'مقدار *';
                    }

                    if (valueInput) {
                        valueInput.placeholder =
                            placeholders[type]
                            || '';

                        if (isNumericType()) {
                            valueInput.setAttribute(
                                'inputmode',
                                'numeric'
                            );

                            persianizeInput(
                                valueInput
                            );
                        } else {
                            valueInput.removeAttribute(
                                'inputmode'
                            );
                        }

                        if (
                            isNumericType()
                            || type === 'email'
                            || type === 'website'
                        ) {
                            valueInput.setAttribute(
                                'dir',
                                'ltr'
                            );
                        } else {
                            valueInput.removeAttribute(
                                'dir'
                            );
                        }
                    }

                    persianizeInput(
                        areaCodeInput
                    );

                    persianizeInput(
                        extensionInput
                    );

                    persianizeInput(
                        sortInput
                    );

                    var canDispatch =
                        directDispatchTypes
                            .indexOf(type) !== -1;

                    if (dispatchField) {
                        dispatchField.hidden =
                            false;

                        dispatchField.classList
                            .toggle(
                                'is-dispatch-unavailable',
                                !canDispatch
                            );

                        dispatchField.title =
                            canDispatch
                                ? ''
                                : 'برای این نوع راه ارتباطی، ارسال مستقیم قابل انتخاب نیست.';
                    }

                    if (dispatchInput) {
                        dispatchInput.disabled =
                            !canDispatch;

                        if (!canDispatch) {
                            dispatchInput.checked =
                                false;
                        }
                    }
                };

            [
                areaCodeInput,
                extensionInput,
                sortInput
            ].forEach(
                function (input) {
                    if (!input) {
                        return;
                    }

                    input.addEventListener(
                        'input',
                        function () {
                            persianizeInput(
                                input
                            );
                        }
                    );
                }
            );

            if (valueInput) {
                valueInput.addEventListener(
                    'input',
                    function () {
                        if (isNumericType()) {
                            persianizeInput(
                                valueInput
                            );
                        }
                    }
                );
            }

            selector.addEventListener(
                'change',
                sync
            );

            sync();
        });
})();
</script>

<?php

$content =
    ob_get_clean()
    ?: '';

require __DIR__ . '/layout.php';
