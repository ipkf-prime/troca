<?php
/* attachment-dynamic-policy-ui-v1 */
$attachmentPolicy =
    new \App\Services\Automation\Correspondence\CorrespondenceAttachmentPolicy();

$attachmentPolicyMaxFiles =
    $attachmentPolicy->maxFiles();

$attachmentPolicyMaxFileMb =
    $attachmentPolicy->maxFileMegabytes();

$attachmentPolicyMaxTotalMb =
    $attachmentPolicy->maxTotalMegabytes();

$attachmentPolicyAccept =
    $attachmentPolicy->acceptAttribute();

$attachmentPolicyTypeLabel =
    $attachmentPolicy->allowedTypeLabel();

$attachmentPolicyClientRules =
    $attachmentPolicy->clientRules();

$attachmentPolicyMaxFilesFa =
    $attachmentPolicy->persianNumber(
        $attachmentPolicyMaxFiles
    );

$attachmentPolicyMaxFileMbFa =
    $attachmentPolicy->persianNumber(
        $attachmentPolicyMaxFileMb
    );

$attachmentPolicyMaxTotalMbFa =
    $attachmentPolicy->persianNumber(
        $attachmentPolicyMaxTotalMb
    );
if (!function_exists('admin_h')) {
    function admin_h($value): string { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false); }
}
$detail = $detail ?? [];
$workspace = $detail['workspace'] ?? [];
$tabs = $detail['tabs'] ?? [];
$activeTab = (string) ($detail['active_tab'] ?? 'summary');
$c = $detail['correspondence'] ?? [];
$attachmentStatus = (string) ($attachmentStatus ?? '');
$registrationStatus = (string) ($registrationStatus ?? '');
$dispatchStatus = (string) ($dispatchStatus ?? '');
$canRegister = (bool) ($canRegister ?? false);
$canDispatch = (bool) ($canDispatch ?? false);
ob_start();
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb"><a href="/admin/dashboard">داشبورد</a><span>/</span><a href="/admin/automation">اتوماسیون</a><span>/</span><a href="/admin/automation/correspondences">مکاتبات</a><span>/</span><span>جزئیات</span></nav>
<?php ob_start(); ?>
<?php if($attachmentStatus==='uploaded'):?><div class="admin-alert admin-alert--success">پیوست با موفقیت ثبت شد.</div><?php elseif($attachmentStatus==='removed'):?><div class="admin-alert admin-alert--success">پیوست با موفقیت حذف شد.</div><?php elseif($attachmentStatus==='metadata_updated'):?><div class="admin-alert admin-alert--success">مشخصات پیوست با موفقیت ویرایش شد.</div><?php elseif($attachmentStatus==='attachment_limit_reached'):?><div class="admin-alert admin-alert--danger">برای هر مکاتبه حداکثر <?=admin_h($attachmentPolicyMaxFilesFa)?> پیوست قابل ثبت است.</div><?php elseif($attachmentStatus==='duplicate_attachment'):?><div class="admin-alert admin-alert--danger">این فایل قبلاً به همین مکاتبه پیوست شده است.</div><?php elseif($attachmentStatus==='attachment_infected'):?><div class="admin-alert admin-alert--danger">فایل انتخاب‌شده آلوده تشخیص داده شد و ذخیره نشد.</div><?php elseif($attachmentStatus==='attachment_scan_failed'):?><div class="admin-alert admin-alert--danger">بررسی امنیتی فایل انجام نشد؛ لطفاً دوباره تلاش کنید.</div><?php elseif($attachmentStatus==='invalid_attachment_checksum'):?><div class="admin-alert admin-alert--danger">صحت محتوای فایل قابل تأیید نیست.</div><?php elseif($attachmentStatus==='primary_attachment_exists'):?><div class="admin-alert admin-alert--danger">برای هر مکاتبه فقط یک فایل اصلی قابل ثبت است.</div><?php elseif($attachmentStatus==='invalid_attachment_role'):?><div class="admin-alert admin-alert--danger">نوع پیوست معتبر نیست.</div><?php elseif($attachmentStatus==='attachment_not_editable'):?><div class="admin-alert admin-alert--danger">ویرایش مشخصات پیوست فقط برای پیش‌نویس مجاز است.</div><?php elseif($attachmentStatus==='attachment_not_removable'):?><div class="admin-alert admin-alert--danger">حذف پیوست فقط برای پیش‌نویس مجاز است.</div><?php elseif($attachmentStatus!==''):?><div class="admin-alert admin-alert--danger">عملیات پیوست انجام نشد؛ نوع یا حجم فایل را بررسی کنید.</div><?php endif;?>

<?php if ($registrationStatus === 'registered'): ?>
<div class="admin-alert admin-alert--success">
    مکاتبه با موفقیت در دبیرخانه ثبت رسمی شد.
    <?php if (($c['official_number'] ?? '—') !== '—'): ?>
        شماره ثبت:
        <strong><?= admin_h($c['official_number']) ?></strong>
    <?php endif; ?>
</div>
<?php elseif ($registrationStatus === 'already_registered'): ?>
<div class="admin-alert admin-alert--success">
    این مکاتبه قبلاً ثبت رسمی شده است.
    <?php if (($c['official_number'] ?? '—') !== '—'): ?>
        شماره ثبت:
        <strong><?= admin_h($c['official_number']) ?></strong>
    <?php endif; ?>
</div>
<?php elseif ($registrationStatus === 'invalid_csrf'): ?>
<div class="admin-alert admin-alert--danger">
    نشست فرم معتبر نیست؛ صفحه را تازه‌سازی و دوباره تلاش کنید.
</div>
<?php elseif ($registrationStatus === 'registry_book_unavailable'): ?>
<div class="admin-alert admin-alert--danger">
    دفتر ثبت فعال و مجاز برای این نوع مکاتبه پیدا نشد.
</div>
<?php elseif ($registrationStatus === 'registry_book_ambiguous'): ?>
<div class="admin-alert admin-alert--danger">
    بیش از یک دفتر ثبت معتبر برای این مکاتبه پیدا شد؛ تنظیم دبیرخانه باید بررسی شود.
</div>
<?php elseif ($registrationStatus === 'correspondence_not_registerable'): ?>
<div class="admin-alert admin-alert--danger">
    وضعیت فعلی مکاتبه اجازه ثبت رسمی نمی‌دهد.
</div>
<?php elseif ($registrationStatus !== ''): ?>
<div class="admin-alert admin-alert--danger">
    ثبت رسمی مکاتبه انجام نشد.
    <span dir="ltr"><?= admin_h($registrationStatus) ?></span>
</div>
<?php endif; ?>

<?php if ($dispatchStatus === 'dispatch_requested'): ?>
<div class="admin-alert admin-alert--success">
    درخواست ارسال ثبت شد.
    هنوز هیچ ارسال واقعی انجام نشده است.
</div>
<?php elseif ($dispatchStatus === 'dispatch_already_requested'): ?>
<div class="admin-alert">
    برای این روش ارسال، درخواست فعال قبلی وجود دارد.
</div>
<?php elseif ($dispatchStatus === 'invalid_csrf'): ?>
<div class="admin-alert admin-alert--danger">
    نشست فرم معتبر نیست؛ صفحه را تازه‌سازی و دوباره تلاش کنید.
</div>
<?php elseif ($dispatchStatus === 'invalid_dispatch_channel'): ?>
<div class="admin-alert admin-alert--danger">
    روش ارسال انتخاب‌شده معتبر نیست.
</div>
<?php elseif ($dispatchStatus === 'official_registration_required'): ?>
<div class="admin-alert admin-alert--danger">
    نامه باید پیش از ایجاد درخواست ارسال، ثبت رسمی شده باشد.
</div>
<?php elseif ($dispatchStatus === 'dispatch_requires_outgoing'): ?>
<div class="admin-alert admin-alert--danger">
    درخواست ارسال فقط برای نامه صادره مجاز است.
</div>
<?php elseif ($dispatchStatus === 'correspondence_not_dispatchable'): ?>
<div class="admin-alert admin-alert--danger">
    وضعیت فعلی نامه اجازه ایجاد درخواست ارسال را نمی‌دهد.
</div>
<?php elseif ($dispatchStatus === 'external_directory_binding_required'): ?>
<div class="admin-alert admin-alert--danger">
    گیرنده این نامه به سازمان و مقصد معتبر در دفترچه سازمان‌های بیرونی متصل نیست.
</div>
<?php elseif ($dispatchStatus === 'external_directory_reference_invalid'): ?>
<div class="admin-alert admin-alert--danger">
    سازمان یا مقصد مکاتباتی گیرنده دیگر معتبر یا فعال نیست.
</div>
<?php elseif ($dispatchStatus === 'dispatch_destination_unavailable'): ?>
<div class="admin-alert admin-alert--danger">
    برای این مقصد، راه ارسال فعال و مجاز متناسب با روش انتخاب‌شده تعریف نشده است.
</div>
<?php elseif ($dispatchStatus === 'dispatch_source_unavailable'): ?>
<div class="admin-alert admin-alert--danger">
    فرستنده معتبر برای ایجاد درخواست ارسال پیدا نشد.
</div>
<?php elseif ($dispatchStatus === 'dispatch_target_required'): ?>
<div class="admin-alert admin-alert--danger">
    گیرنده اصلی برای ایجاد درخواست ارسال پیدا نشد.
</div>
<?php elseif ($dispatchStatus !== ''): ?>
<div class="admin-alert admin-alert--danger">
    ایجاد درخواست ارسال انجام نشد.
    <span dir="ltr"><?= admin_h($dispatchStatus) ?></span>
</div>
<?php endif; ?>

<?php if ($activeTab === 'summary'): ?>
    <section class="entity-section"><div class="admin-section__header"><div><h2>خلاصه مکاتبه</h2><p class="admin-muted">اطلاعات پایه بدون نمایش شناسه‌های فنی</p></div><div class="admin-form-actions"><?php if ($detail['editable'] ?? false): ?><a class="admin-button admin-button--soft" href="<?= admin_h($detail['edit_url']) ?>">ویرایش پیش‌نویس</a><?php endif; ?><?php if (($detail['editable'] ?? false) && $canRegister): ?><form method="post" action="/admin/automation/correspondences/<?= admin_h($c['public_reference'] ?? '') ?>/register"><input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>"><button class="admin-button" type="submit">ثبت رسمی در دبیرخانه</button></form><?php endif; ?></div></div>

<?php if (
    ($c['direction_code'] ?? '') === 'outgoing'
    && ($c['status_code'] ?? '') === 'registered'
    && $canDispatch
): ?>
<form
    class="admin-form-grid"
    method="post"
    action="/admin/automation/correspondences/<?= admin_h($c['public_reference'] ?? '') ?>/dispatch"
>
    <input
        type="hidden"
        name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>"
    >

    <label>
        <span>روش ارسال</span>

        <select
            name="channel_code"
            required
        >
            <option value="">
                انتخاب روش ارسال
            </option>
            <option value="postal">پست</option>
            <option value="courier">پیک</option>
            <option value="hand_delivery">تحویل دستی</option>
            <option value="fax">فاکس</option>
            <option value="email">رایانامه</option>
            <option value="system">سامانه</option>
        </select>
    </label>

    <div class="admin-form-grid__wide admin-muted">
        این مرحله فقط «درخواست ارسال» را ثبت می‌کند.
        وضعیت نامه و تاریخ ارسال تا اجرای واقعی و موفق ارسال تغییر نمی‌کند.
    </div>

    <div class="admin-form-actions">
        <button
            class="admin-button"
            type="submit"
        >
            ایجاد درخواست ارسال
        </button>
    </div>
</form>
<?php endif; ?>

<div class="entity-field-grid">
        <?php foreach (['شناسه عمومی' => $c['public_reference'] ?? '', 'موضوع' => $c['subject'] ?? '', 'قالب نامه' => $c['document_template'] ?? '', 'نوع/جهت' => $c['type'] ?? '', 'اولویت' => $c['priority'] ?? '', 'محرمانگی' => $c['confidentiality'] ?? '', ...((($c['direction_code'] ?? '') === 'incoming') ? ['روش دریافت' => $c['channel'] ?? '', 'شماره بیرونی' => $c['external_number'] ?? '', 'تاریخ بیرونی' => $c['external_date'] ?? ''] : []), 'شماره ثبت رسمی' => $c['official_number'] ?? '—', 'تاریخ ثبت رسمی' => $c['official_registered_at'] ?? '—', ...((($c['direction_code'] ?? '') === 'outgoing' && ($c['dispatched_at'] ?? '—') !== '—') ? ['تاریخ ارسال' => $c['dispatched_at']] : []), 'ایجاد' => $c['created_at'] ?? '', 'آخرین تغییر' => $c['updated_at'] ?? ''] as $label => $value): ?><div class="entity-field"><span><?= admin_h($label) ?></span><strong><?= admin_h($value) ?></strong></div><?php endforeach; ?>
    </div><?php if (($c['summary'] ?? '—') !== '—'): ?><p><?= admin_h($c['summary']) ?></p><?php endif; ?></section>
<?php elseif ($activeTab === 'content'): ?>
    <?php
    $richTextContent = new \App\Services\Automation\Correspondence\CorrespondenceRichTextContent();
    ?>

    <style>
        .automation-rich-content {
            direction: rtl;
            text-align: right;
            line-height: 2.1;
            overflow-wrap: anywhere;
        }

        .automation-rich-content p {
            margin: 0 0 .8rem;
        }

        .automation-rich-content h2,
        .automation-rich-content h3 {
            margin: 1.1rem 0 .6rem;
        }

        .automation-rich-content ul,
        .automation-rich-content ol {
            padding-inline-start: 1.8rem;
        }

        .automation-rich-content blockquote {
            margin: .8rem 0;
            padding-inline-start: 1rem;
            border-inline-start:
                3px solid var(--admin-border);
        }

        .automation-rich-content [data-align="right"] {
            text-align: right;
        }

        .automation-rich-content [data-align="center"] {
            text-align: center;
        }

        .automation-rich-content [data-align="left"] {
            text-align: left;
        }

        .automation-rich-content [data-align="justify"] {
            text-align: justify;
        }

        .automation-rich-content [data-indent="1"] {
            padding-inline-start: 1.5rem;
        }

        .automation-rich-content [data-indent="2"] {
            padding-inline-start: 3rem;
        }

        .automation-rich-content [data-indent="3"] {
            padding-inline-start: 4.5rem;
        }

        .automation-rich-content [data-indent="4"] {
            padding-inline-start: 6rem;
        }
    </style>

    <section class="entity-section">
        <h2>نسخه جاری</h2>

        <?php foreach (
            $detail['relations']
            ?? []
            as $relation
        ): ?>
            <p class="automation-reference-line">
                <?= admin_h(
                    $relation['line']
                ) ?>
            </p>
        <?php endforeach; ?>

        <article
            class="automation-content-box automation-rich-content"
        >
            <?= $richTextContent->renderStored(
                $c['content']
                ?? ''
            ) ?>
        </article>

        <?php
        $copies =
            array_filter(
                $detail['parties']
                ?? [],
                static fn ($party) =>
                    in_array(
                        $party[
                            'role_code'
                        ] ?? '',
                        [
                            'cc',
                            'bcc',
                        ],
                        true
                    )
            );
        ?>

        <?php if ($copies): ?>
            <div class="automation-copy-block">
                <strong>رونوشت:</strong>

                <?php foreach (
                    $copies
                    as $copy
                ): ?>
                    <span>
                        <?= admin_h(
                            $copy[
                                'display'
                            ]
                        ) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

<?php elseif ($activeTab === 'parties'): ?>
    <section class="entity-section">
        <h2>گیرندگان و رونوشت‌ها</h2>

        <?php if (($detail['parties'] ?? []) === []): ?>
            <div class="admin-empty-state">
                طرفی ثبت نشده است.
            </div>
        <?php else: ?>
            <div class="entity-card-list">
                <?php foreach ($detail['parties'] as $party): ?>
                    <article class="entity-info-card">
                        <header>
                            <strong>
                                <?= admin_h(
                                    $party['display']
                                ) ?>
                            </strong>

                            <span class="admin-pill">
                                <?= admin_h(
                                    $party['role']
                                ) ?>
                            </span>
                        </header>

                        <p>
                            <?= admin_h(
                                $party['kind']
                            ) ?>
                        </p>

                        <small>
                            <?= admin_h(
                                $party['contact']
                            ) ?>
                        </small>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php
    $dispatchMonitor =
        is_array(
            $detail[
                'dispatch_monitor'
            ] ?? null
        )
            ? $detail[
                'dispatch_monitor'
            ]
            : [
                'available' => false,
                'recipient_count' => 0,
                'dispatch_count' => 0,
                'attempt_count' => 0,
                'unassigned_dispatch_count' => 0,
                'recipients' => [],
            ];

    $dispatchRecipients =
        is_array(
            $dispatchMonitor[
                'recipients'
            ] ?? null
        )
            ? $dispatchMonitor[
                'recipients'
            ]
            : [];

    $dispatchDigits =
        static function (
            mixed $value
        ): string {
            $value =
                trim(
                    (string) (
                        $value
                        ?? ''
                    )
                );

            return $value !== ''
                ? \App\Support\AdminFormat::digits(
                    $value
                )
                : '—';
        };
    ?>

    <section
        class="entity-section"
        data-dispatch-read-model
    >
        <div class="admin-section__header">
            <div>
                <h2>رهگیری ارسال</h2>

                <p class="admin-muted">
                    وضعیت درخواست‌ها و تلاش‌های ارسال
                    برای گیرندگان اصلی.
                    این بخش فقط خواندنی است.
                </p>
            </div>

            <?php if (
                !empty(
                    $dispatchMonitor[
                        'available'
                    ]
                )
            ): ?>
                <div class="admin-form-actions">
                    <span class="admin-pill">
                        <?= admin_h(
                            \App\Support\AdminFormat::digits(
                                (string) (
                                    $dispatchMonitor[
                                        'dispatch_count'
                                    ] ?? 0
                                )
                            )
                        ) ?>
                        درخواست
                    </span>

                    <span class="admin-pill">
                        <?= admin_h(
                            \App\Support\AdminFormat::digits(
                                (string) (
                                    $dispatchMonitor[
                                        'attempt_count'
                                    ] ?? 0
                                )
                            )
                        ) ?>
                        تلاش
                    </span>
                </div>
            <?php endif; ?>
        </div>


        <?php if (
            empty(
                $dispatchMonitor[
                    'available'
                ]
            )
        ): ?>
            <div class="admin-alert admin-alert--danger">
                اطلاعات رهگیری ارسال در حال حاضر
                در دسترس نیست.
            </div>


        <?php elseif (
            $dispatchRecipients === []
        ): ?>
            <div class="admin-empty-state">
                گیرنده اصلی برای این نامه
                ثبت نشده است.
            </div>


        <?php else: ?>
            <div class="entity-card-list">
                <?php foreach (
                    $dispatchRecipients
                    as $recipient
                ): ?>
                    <?php
                    $recipientDispatches =
                        is_array(
                            $recipient[
                                'dispatches'
                            ] ?? null
                        )
                            ? $recipient[
                                'dispatches'
                            ]
                            : [];
                    ?>

                    <article class="entity-info-card">
                        <header>
                            <strong>
                                <?= admin_h(
                                    $recipient[
                                        'display'
                                    ] ?? 'گیرنده اصلی'
                                ) ?>
                            </strong>

                            <span class="admin-pill">
                                گیرنده اصلی
                            </span>
                        </header>


                        <?php if (
                            trim(
                                (string) (
                                    $recipient[
                                        'organization'
                                    ] ?? ''
                                )
                            ) !== ''
                        ): ?>
                            <p>
                                <?= admin_h(
                                    $recipient[
                                        'organization'
                                    ]
                                ) ?>
                            </p>
                        <?php endif; ?>


                        <?php if (
                            trim(
                                (string) (
                                    $recipient[
                                        'contact'
                                    ] ?? ''
                                )
                            ) !== ''
                        ): ?>
                            <small>
                                <?= admin_h(
                                    $recipient[
                                        'contact'
                                    ]
                                ) ?>
                            </small>
                        <?php endif; ?>


                        <?php if (
                            $recipientDispatches === []
                        ): ?>
                            <div class="admin-empty-state">
                                هنوز درخواست ارسالی برای این
                                گیرنده ایجاد نشده است.
                            </div>

                        <?php else: ?>
                            <?php foreach (
                                $recipientDispatches
                                as $dispatch
                            ): ?>
                                <?php
                                $attempt =
                                    is_array(
                                        $dispatch[
                                            'latest_attempt'
                                        ] ?? null
                                    )
                                        ? $dispatch[
                                            'latest_attempt'
                                        ]
                                        : null;
                                ?>

                                <div class="entity-field-grid">
                                    <div class="entity-field">
                                        <span>
                                            روش ارسال
                                        </span>

                                        <strong>
                                            <?= admin_h(
                                                $dispatch[
                                                    'channel_label'
                                                ] ?? 'نامشخص'
                                            ) ?>
                                        </strong>
                                    </div>


                                    <div class="entity-field">
                                        <span>
                                            وضعیت درخواست
                                        </span>

                                        <strong>
                                            <span class="admin-pill">
                                                <?= admin_h(
                                                    $dispatch[
                                                        'status_label'
                                                    ] ?? 'نامشخص'
                                                ) ?>
                                            </span>
                                        </strong>
                                    </div>


                                    <div class="entity-field">
                                        <span>
                                            تعداد تلاش‌ها
                                        </span>

                                        <strong>
                                            <?= admin_h(
                                                \App\Support\AdminFormat::digits(
                                                    (string) (
                                                        $dispatch[
                                                            'attempt_count'
                                                        ] ?? 0
                                                    )
                                                )
                                            ) ?>
                                        </strong>
                                    </div>


                                    <div class="entity-field">
                                        <span>
                                            آخرین تلاش
                                        </span>

                                        <strong>
                                            <?php if (
                                                $attempt !== null
                                            ): ?>
                                                <span class="admin-pill">
                                                    <?= admin_h(
                                                        $attempt[
                                                            'status_label'
                                                        ] ?? 'نامشخص'
                                                    ) ?>
                                                </span>
                                            <?php else: ?>
                                                هنوز انجام نشده است
                                            <?php endif; ?>
                                        </strong>
                                    </div>


                                    <div class="entity-field">
                                        <span>
                                            زمان درخواست
                                        </span>

                                        <strong>
                                            <?= admin_h(
                                                $dispatchDigits(
                                                    $dispatch[
                                                        'requested_at'
                                                    ] ?? null
                                                )
                                            ) ?>
                                        </strong>
                                    </div>


                                    <div class="entity-field">
                                        <span>
                                            تکمیل آخرین تلاش
                                        </span>

                                        <strong>
                                            <?= admin_h(
                                                $dispatchDigits(
                                                    $attempt !== null
                                                        ? (
                                                            $attempt[
                                                                'completed_at'
                                                            ] ?? null
                                                        )
                                                        : null
                                                )
                                            ) ?>
                                        </strong>
                                    </div>


                                    <div class="entity-field">
                                        <span>
                                            زمان ارسال
                                        </span>

                                        <strong>
                                            <?= admin_h(
                                                $dispatchDigits(
                                                    $dispatch[
                                                        'dispatched_at'
                                                    ] ?? null
                                                )
                                            ) ?>
                                        </strong>
                                    </div>


                                    <div class="entity-field">
                                        <span>
                                            کد رهگیری
                                        </span>

                                        <strong dir="ltr">
                                            <?= admin_h(
                                                trim(
                                                    (string) (
                                                        $dispatch[
                                                            'tracking_code'
                                                        ] ?? ''
                                                    )
                                                ) !== ''
                                                    ? (
                                                        $dispatch[
                                                            'tracking_code'
                                                        ]
                                                    )
                                                    : '—'
                                            ) ?>
                                        </strong>
                                    </div>
                                </div>


                                <?php if (
                                    !empty(
                                        $dispatch[
                                            'needs_review'
                                        ]
                                    )
                                ): ?>
                                    <div class="admin-alert admin-alert--danger">
                                        وضعیت نامشخص — نیازمند بررسی.
                                        تلاش مجدد خودکار برای این ارسال
                                        مجاز نیست.
                                    </div>


                                <?php elseif (
                                    !empty(
                                        $dispatch[
                                            'retryable'
                                        ]
                                    )
                                ): ?>
                                    <div class="admin-alert admin-alert--danger">
                                        آخرین تلاش ناموفق است و از نظر
                                        چرخه ارسال قابل تلاش مجدد است.
                                        در این صفحه هیچ عملیات مجددی
                                        اجرا نمی‌شود.
                                    </div>
                                <?php endif; ?>


                                <?php if (
                                    $canDispatch
                                    && $attempt !== null
                                ): ?>
                                    <?php
                                    $providerCode =
                                        trim(
                                            (string) (
                                                $attempt[
                                                    'provider_code'
                                                ] ?? ''
                                            )
                                        );

                                    $providerReference =
                                        trim(
                                            (string) (
                                                $attempt[
                                                    'provider_reference'
                                                ] ?? ''
                                            )
                                        );

                                    $failureCode =
                                        trim(
                                            (string) (
                                                $attempt[
                                                    'failure_code'
                                                ] ?? ''
                                            )
                                        );
                                    ?>

                                    <?php if (
                                        $providerCode !== ''
                                        ||
                                        $providerReference !== ''
                                        ||
                                        $failureCode !== ''
                                    ): ?>
                                        <p class="admin-muted">
                                            جزئیات فنی:

                                            <?php if (
                                                $providerCode !== ''
                                            ): ?>
                                                سرویس
                                                <code dir="ltr"><?= admin_h(
                                                    $providerCode
                                                ) ?></code>
                                            <?php endif; ?>

                                            <?php if (
                                                $providerReference !== ''
                                            ): ?>
                                                · شناسه سرویس
                                                <code dir="ltr"><?= admin_h(
                                                    $providerReference
                                                ) ?></code>
                                            <?php endif; ?>

                                            <?php if (
                                                $failureCode !== ''
                                            ): ?>
                                                · کد خطا
                                                <code dir="ltr"><?= admin_h(
                                                    $failureCode
                                                ) ?></code>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>


            <?php if (
                (int) (
                    $dispatchMonitor[
                        'unassigned_dispatch_count'
                    ] ?? 0
                ) > 0
            ): ?>
                <div class="admin-alert admin-alert--danger">
                    <?= admin_h(
                        \App\Support\AdminFormat::digits(
                            (string) (
                                $dispatchMonitor[
                                    'unassigned_dispatch_count'
                                ] ?? 0
                            )
                        )
                    ) ?>
                    درخواست ارسال بدون اتصال به گیرنده
                    شناسایی شده است و نیازمند بررسی است.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

<?php elseif ($activeTab === 'relations'): ?>
    <section class="entity-section"><h2>عطف، پیرو و نامه‌های مرتبط</h2><?php if(($detail['relations']??[])===[]):?><div class="admin-empty-state">ارتباطی با نامه‌های دیگر ثبت نشده است.</div><?php else:?><div class="entity-card-list"><?php foreach($detail['relations'] as $relation):?><article class="entity-info-card"><header><strong><?=admin_h($relation['subject'])?></strong><span class="admin-pill"><?=admin_h($relation['type'])?></span></header><p>شماره: <?=admin_h($relation['number'])?> · تاریخ: <?=admin_h($relation['date'])?></p><small><?=admin_h($relation['note'])?></small></article><?php endforeach;?></div><?php endif;?></section>
<?php elseif ($activeTab === 'attachments'): ?>
    <section class="entity-section"><div class="admin-section__header"><div><h2>پیوست‌های نامه</h2><p class="admin-muted"><?=admin_h($attachmentPolicyTypeLabel)?>؛ حداکثر <?=admin_h($attachmentPolicyMaxFileMbFa)?> مگابایت</p></div></div><?php $activeAttachmentCount=count($detail['attachments']??[]);?><?php if(($detail['editable']??false)&&$activeAttachmentCount<$attachmentPolicyMaxFiles):?><form class="admin-form-grid" method="post" enctype="multipart/form-data" action="<?=admin_h($detail['edit_url'].'/attachments')?>"><input type="hidden" name="_token" value="<?=admin_h((new \IPKF\Security\Csrf())->token())?>"><label><span>عنوان پیوست</span><input name="title" maxlength="255"></label><label><span>نوع فایل</span><select name="attachment_role_code"><option value="enclosure">پیوست</option><option value="supporting">مدرک پشتیبان</option><option value="scan">تصویر اسکن‌شده</option><option value="main">فایل اصلی</option></select></label><label class="admin-form-grid__wide"><span>انتخاب فایل</span><input type="file" name="attachment" accept="<?=admin_h($attachmentPolicyAccept)?>" required></label><div class="admin-form-actions"><button class="admin-button" type="submit">افزودن پیوست</button></div></form><?php elseif(($detail['editable']??false)&&$activeAttachmentCount>=$attachmentPolicyMaxFiles):?><div class="admin-alert admin-alert--info">حداکثر <?=admin_h($attachmentPolicyMaxFilesFa)?> پیوست برای این مکاتبه ثبت شده است.</div><?php endif;?><?php if(($detail['attachments']??[])===[]):?><div class="admin-empty-state">پیوستی ثبت نشده است.</div><?php else:?><div class="entity-card-list"><?php foreach($detail['attachments'] as $attachment):?><article class="entity-info-card"><header><strong><?=admin_h($attachment['title']==='—'?$attachment['filename']:$attachment['title'])?></strong><span class="admin-pill"><?=admin_h($attachment['role'])?></span><span class="admin-status-badge admin-status-badge--<?=admin_h($attachment['security_class'])?>"><?=admin_h($attachment['security_label'])?></span></header><p><?=admin_h($attachment['filename'])?> · <?=admin_h($attachment['size'])?></p><?php if($detail['editable']??false):?><form class="admin-form-grid" method="post" action="<?=admin_h($attachment['edit_url'])?>"><input type="hidden" name="_token" value="<?=admin_h((new \IPKF\Security\Csrf())->token())?>"><label><span>عنوان پیوست</span><input name="title" maxlength="255" value="<?=admin_h($attachment['title_raw'])?>"></label><label><span>نوع پیوست</span><select name="attachment_role_code"><option value="enclosure"<?=($attachment['role_code']==='enclosure'?' selected':'')?>>پیوست</option><option value="supporting"<?=($attachment['role_code']==='supporting'?' selected':'')?>>مدرک پشتیبان</option><option value="scan"<?=($attachment['role_code']==='scan'?' selected':'')?>>تصویر اسکن‌شده</option><option value="main"<?=($attachment['role_code']==='main'?' selected':'')?>>فایل اصلی</option></select></label><div class="admin-form-actions"><button class="admin-button admin-button--soft admin-button--compact" type="submit">ذخیره مشخصات</button></div></form><?php endif;?><div class="admin-form-actions"><a class="admin-button admin-button--soft admin-button--compact" href="<?=admin_h($attachment['url'])?>">دریافت فایل</a><?php if($detail['editable']??false):?><form method="post" action="<?=admin_h($attachment['remove_url'])?>" onsubmit="return confirm('این پیوست از پیش‌نویس حذف شود؟');"><input type="hidden" name="_token" value="<?=admin_h((new \IPKF\Security\Csrf())->token())?>"><button class="admin-button admin-button--soft admin-button--compact" type="submit">حذف پیوست</button></form><?php endif;?></div></article><?php endforeach;?></div><?php endif;?></section>
<?php elseif ($activeTab === 'versions'): ?>
    <section class="entity-section"><h2>نسخه‌ها</h2><div class="admin-users-table-wrap"><table class="admin-table"><thead><tr><th>نسخه</th><th>موضوع</th><th>یادداشت</th><th>زمان ایجاد</th></tr></thead><tbody><?php foreach ($detail['versions'] as $version): ?><tr><td><?= admin_h($version['number']) ?></td><td><?= admin_h($version['subject']) ?></td><td><?= admin_h($version['change_note']) ?></td><td><?= admin_h($version['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php else: ?>
    <section class="entity-section"><h2>تاریخچه رویداد</h2><div class="entity-card-list"><?php foreach ($detail['events'] as $event): ?><article class="entity-info-card"><header><strong><?= admin_h($event['type']) ?></strong><span><?= admin_h($event['occurred_at']) ?></span></header><p>از <?= admin_h($event['from']) ?> به <?= admin_h($event['to']) ?></p></article><?php endforeach; ?></div></section>
<?php endif; ?>
<?php $workspaceContent = ob_get_clean(); require __DIR__ . '/partials/entity-workspace.php'; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
