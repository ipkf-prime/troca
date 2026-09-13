<?php

if (
    !function_exists(
        'admin_h'
    )
) {
    function admin_h(
        $value
    ): string {

        return
            htmlspecialchars(
                (string) (
                    $value
                    ?? ''
                ),
                ENT_QUOTES
                |
                ENT_SUBSTITUTE,
                'UTF-8',
                false
            );
    }
}


$status =
    $status
    ?? '';


$page =
    is_array(
        $page
        ?? null
    )
        ? $page
        : [];


$identity =
    is_array(
        $page[
            'identity'
        ]
        ?? null
    )
        ?
        $page[
            'identity'
        ]
        : [];


$organizations =
    is_array(
        $page[
            'organizations'
        ]
        ?? null
    )
        ?
        $page[
            'organizations'
        ]
        : [];


$positions =
    is_array(
        $page[
            'positions'
        ]
        ?? null
    )
        ?
        $page[
            'positions'
        ]
        : [];


$memberships =
    is_array(
        $page[
            'memberships'
        ]
        ?? null
    )
        ?
        $page[
            'memberships'
        ]
        : [];


$hasPerson =
    (int) (
        $identity[
            'person_id'
        ]
        ?? 0
    ) > 0
    &&
    trim(
        (string) (
            $identity[
                'person_reference'
            ]
            ?? ''
        )
    ) !== '';


$stateLabel =
    static function (
        string $state
    ): string {

        return
            match ($state) {

                'verified'
                    =>
                        'تأییدشده',

                'rejected'
                    =>
                        'ردشده',

                default
                    =>
                        'در انتظار بررسی',
            };
    };


$stateClass =
    static function (
        string $state
    ): string {

        return
            match ($state) {

                'verified'
                    =>
                        'account-badge--success',

                'rejected'
                    =>
                        'account-badge--danger',

                default
                    =>
                        '',
            };
    };


ob_start();
?>

<style>
.affiliation-form-grid {
    display:grid;
    gap:.7rem;
    grid-template-columns:repeat(2,minmax(0,1fr));
}

.affiliation-field {
    display:grid;
    gap:.3rem;
}

.affiliation-field label {
    color:var(--admin-text-muted);
    font-size:.7rem;
    font-weight:800;
}

.affiliation-field select {
    background:var(--admin-surface);
    border:1px solid var(--admin-border);
    border-radius:.7rem;
    color:var(--admin-text);
    font:inherit;
    min-height:2.7rem;
    padding:.45rem .6rem;
    width:100%;
}

.affiliation-wide {
    grid-column:1 / -1;
}

.affiliation-check {
    align-items:center;
    display:flex;
    gap:.45rem;
    font-size:.72rem;
}

.affiliation-list {
    display:grid;
    gap:.55rem;
}

.affiliation-item {
    background:var(--admin-surface-muted);
    border:1px solid var(--admin-border);
    border-radius:.75rem;
    display:grid;
    gap:.35rem;
    padding:.7rem;
}

.affiliation-item__head {
    align-items:center;
    display:flex;
    gap:.6rem;
    justify-content:space-between;
}

.affiliation-item strong {
    font-size:.8rem;
}

.affiliation-item small {
    color:var(--admin-text-muted);
    font-size:.68rem;
    line-height:1.8;
}

@media(max-width:720px) {
    .affiliation-form-grid {
        grid-template-columns:1fr;
    }

    .affiliation-wide {
        grid-column:auto;
    }
}
</style>


<div class="account-shell">

    <?php
    require
        __DIR__
        . '/partials/account-nav.php';
    ?>


    <?php if (
        $status === 'requested'
    ): ?>

        <div class="account-notice account-notice--success">
            درخواست وابستگی سازمانی ثبت شد و در انتظار بررسی مدیر است.
        </div>

    <?php elseif (
        $status === 'already_verified'
    ): ?>

        <div class="account-notice account-notice--info">
            وابستگی شما به این سازمان قبلاً تأیید شده است.
        </div>

    <?php elseif (
        $status === 'person_required'
    ): ?>

        <div class="account-notice account-notice--danger">
            ابتدا اطلاعات هویتی حساب خود را تکمیل کنید تا شخص متناظر مشخص شود.
        </div>

    <?php elseif (
        $status === 'organization_invalid'
    ): ?>

        <div class="account-notice account-notice--danger">
            سازمان انتخاب‌شده معتبر نیست.
        </div>

    <?php elseif (
        $status === 'position_invalid'
    ): ?>

        <div class="account-notice account-notice--danger">
            پست انتخاب‌شده متعلق به سازمان انتخابی نیست یا فعال نیست.
        </div>

    <?php elseif (
        $status === 'invalid_csrf'
    ): ?>

        <div class="account-notice account-notice--danger">
            نشست فرم معتبر نیست. صفحه را دوباره بارگذاری کنید.
        </div>

    <?php elseif (
        $status === 'request_failed'
    ): ?>

        <div class="account-notice account-notice--danger">
            ثبت درخواست انجام نشد. دوباره تلاش کنید یا با مدیر سامانه تماس بگیرید.
        </div>

    <?php endif; ?>


    <section class="account-card">

        <div class="account-card__head">

            <div>
                <h2>وابستگی سازمانی من</h2>

                <p>
                    محل خود را در ساختار رسمی انتخاب کنید.
                    سمت سازمانی با نقش دسترسی سامانه یکی نیست
                    و پس از بررسی مدیر تأیید می‌شود.
                </p>
            </div>

        </div>


        <?php if (!$hasPerson): ?>

            <div class="account-notice account-notice--danger">
                برای ثبت وابستگی، حساب کاربری باید به یک «شخص» فعال متصل باشد.
            </div>

        <?php elseif (
            $organizations === []
        ): ?>

            <div class="account-notice account-notice--info">
                هنوز ساختار سازمانی فعالی برای انتخاب ثبت نشده است.
            </div>

        <?php else: ?>

            <form
                method="post"
                action="/admin/profile/affiliation"
            >

                <input
                    type="hidden"
                    name="_token"
                    value="<?= admin_h(
                        (
                            new \IPKF\Security\Csrf()
                        )->token()
                    ) ?>"
                >


                <div class="affiliation-form-grid">

                    <div class="affiliation-field affiliation-wide">

                        <label for="affiliation-organization">
                            محل در ساختار
                        </label>

                        <select
                            id="affiliation-organization"
                            name="organization_reference"
                            required
                        >

                            <option value="">
                                انتخاب سازمان / شرکت
                            </option>

                            <?php foreach (
                                $organizations
                                as $organization
                            ): ?>

                                <option
                                    value="<?= admin_h(
                                        $organization[
                                            'public_reference'
                                        ]
                                        ?? ''
                                    ) ?>"
                                >
                                    <?= admin_h(
                                        $organization[
                                            'display_path'
                                        ]
                                        ??
                                        $organization[
                                            'title'
                                        ]
                                        ?? ''
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="affiliation-field affiliation-wide">

                        <label for="affiliation-position">
                            سمت / پست سازمانی
                        </label>

                        <select
                            id="affiliation-position"
                            name="position_reference"
                            disabled
                        >

                            <option value="">
                                بدون پست مشخص / فقط عضویت
                            </option>

                            <?php foreach (
                                $positions
                                as $position
                            ): ?>

                                <option
                                    value="<?= admin_h(
                                        $position[
                                            'public_reference'
                                        ]
                                        ?? ''
                                    ) ?>"
                                    data-organization="<?= admin_h(
                                        $position[
                                            'organization_reference'
                                        ]
                                        ?? ''
                                    ) ?>"
                                >
                                    <?= admin_h(
                                        $position[
                                            'display_path'
                                        ]
                                        ??
                                        $position[
                                            'title'
                                        ]
                                        ?? ''
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <label class="affiliation-check affiliation-wide">

                        <input
                            type="checkbox"
                            name="is_primary"
                            value="1"
                        >

                        این وابستگی، وابستگی اصلی من است.

                    </label>

                </div>


                <div
                    class="account-actions"
                    style="margin-top:.8rem"
                >
                    <button type="submit">
                        ثبت درخواست بررسی
                    </button>
                </div>

            </form>

        <?php endif; ?>

    </section>


    <section class="account-card">

        <div class="account-card__head">
            <div>
                <h3>سوابق وابستگی</h3>
                <p>
                    عضویت، سمت درخواستی و وضعیت بررسی مدیر
                </p>
            </div>
        </div>


        <?php if (
            $memberships === []
        ): ?>

            <div class="account-notice account-notice--info">
                هنوز وابستگی سازمانی برای این حساب ثبت نشده است.
            </div>

        <?php else: ?>

            <div class="affiliation-list">

                <?php foreach (
                    $memberships
                    as $membership
                ): ?>

                    <?php

                    $state =
                        (string) (
                            $membership[
                                'workflow_state'
                            ]
                            ?? 'pending'
                        );

                    ?>

                    <article class="affiliation-item">

                        <div class="affiliation-item__head">

                            <strong>
                                <?= admin_h(
                                    $membership[
                                        'organization_title'
                                    ]
                                    ?? ''
                                ) ?>
                            </strong>

                            <span
                                class="account-badge <?= admin_h(
                                    $stateClass(
                                        $state
                                    )
                                ) ?>"
                            >
                                <?= admin_h(
                                    $stateLabel(
                                        $state
                                    )
                                ) ?>
                            </span>

                        </div>


                        <?php if (
                            trim(
                                (string) (
                                    $membership[
                                        'requested_position_title'
                                    ]
                                    ?? ''
                                )
                            ) !== ''
                        ): ?>

                            <small>
                                سمت درخواستی:
                                <?= admin_h(
                                    $membership[
                                        'requested_position_title'
                                    ]
                                ) ?>
                            </small>

                        <?php endif; ?>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</div>


<script>
(() => {
    const organization =
        document.getElementById(
            'affiliation-organization'
        );

    const position =
        document.getElementById(
            'affiliation-position'
        );

    if (!organization || !position) {
        return;
    }

    const allOptions =
        Array.from(
            position.options
        ).slice(1);

    const refresh = () => {

        const selected =
            organization.value;

        position.value = '';
        position.disabled =
            selected === '';

        allOptions.forEach(
            (option) => {

                const visible =
                    selected !== ''
                    &&
                    option.dataset.organization
                        === selected;

                option.hidden =
                    !visible;

                option.disabled =
                    !visible;
            }
        );
    };

    organization.addEventListener(
        'change',
        refresh
    );

    refresh();
})();
</script>

<?php

$content =
    ob_get_clean();

require
    __DIR__
    . '/layout.php';
