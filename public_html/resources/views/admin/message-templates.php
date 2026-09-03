<?php

declare(strict_types=1);

$title = 'متن‌های پیام';

$escape =
    static fn (
        mixed $value
    ): string =>
        htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES
            | ENT_SUBSTITUTE,
            'UTF-8'
        );

$digits =
    static fn (
        mixed $value
    ): string =>
        \App\Support\AdminFormat::digits(
            $value
        );

$jalaliDateTime =
    static fn (
        mixed $value
    ): string =>
        \App\Support\AdminFormat::jalaliDateTime(
            $value
        );

$page =
    is_array($page ?? null)
        ? $page
        : [];

$items =
    is_array($page['items'] ?? null)
        ? $page['items']
        : [];

$channels =
    is_array($page['channels'] ?? null)
        ? $page['channels']
        : [];

$filters =
    is_array($page['filters'] ?? null)
        ? $page['filters']
        : [];

$selected =
    is_array($page['selected'] ?? null)
        ? $page['selected']
        : null;

$draft =
    is_array($page['draft'] ?? null)
        ? $page['draft']
        : [];

$preview =
    is_array($page['preview'] ?? null)
        ? $page['preview']
        : null;

$history =
    is_array($page['history'] ?? null)
        ? $page['history']
        : [];

$audit =
    is_array($page['audit'] ?? null)
        ? $page['audit']
        : [];

$status =
    trim(
        (string) (
            $status
            ?? ''
        )
    );

$canTestSend =
    (bool) (
        $canTestSend
        ?? false
    );

$statusMessages = [
    'saved' =>
        'نسخه جدید متن با موفقیت ذخیره شد.',

    'preview_ready' =>
        'پیش‌نمایش با متغیرهای نمونه ساخته شد.',

    'preview_failed' =>
        'پیش‌نمایش ساخته نشد. متن و متغیرهای استفاده‌شده را بررسی کنید.',

    'save_failed' =>
        'ذخیره نسخه جدید انجام نشد. متن و متغیرهای استفاده‌شده را بررسی کنید.',

    'test_sent' =>
        'پیام آزمایشی با موفقیت برای درگاه ارسال شد.',

    'test_failed' =>
        'ارسال آزمایشی انجام نشد. مقصد یا تنظیمات درگاه را بررسی کنید.',

    'invalid_form' =>
        'اعتبار فرم منقضی شده است. صفحه را تازه‌سازی و دوباره تلاش کنید.',
];

$csrf =
    (
        new \IPKF\Security\Csrf()
    )->token();

$latest =
    is_array(
        $selected['latest']
        ?? null
    )
        ? $selected['latest']
        : [];

$value =
    static function (
        string $key,
        array $draft,
        array $latest
    ): string {
        if (array_key_exists(
            $key,
            $draft
        )) {
            return (string) (
                $draft[$key]
                ?? ''
            );
        }

        return (string) (
            $latest[$key]
            ?? ''
        );
    };

$currentTitle =
    $value(
        'title_template',
        $draft,
        $latest
    );

$currentBody =
    $value(
        'body_template',
        $draft,
        $latest
    );

$currentAction =
    $value(
        'action_url_template',
        $draft,
        $latest
    );

$currentActive =
    array_key_exists(
        'is_active',
        $draft
    )
        ? (
            (string) $draft[
                'is_active'
            ] === '1'
        )
        : (
            (int) (
                $latest['is_active']
                ?? 0
            ) === 1
        );

$allowedVariables =
    is_array(
        $selected[
            'allowed_variables'
        ]
        ?? null
    )
        ? $selected[
            'allowed_variables'
        ]
        : [];

$supportsSubject =
    is_array($selected)
    && (int) (
        $selected[
            'channel_supports_subject'
        ]
        ?? 0
    ) === 1;

$externalTestChannel =
    is_array($selected)
    && in_array(
        (string) (
            $selected[
                'channel_code'
            ]
            ?? ''
        ),
        [
            'sms',
            'email',
            'messenger',
            'bale',
        ],
        true
    );

ob_start();
?>

<style>
.message-template-layout {
    display: grid;
    grid-template-columns:
        minmax(270px, 0.85fr)
        minmax(0, 1.65fr);
    gap: 18px;
    align-items: start;
}

.message-template-list {
    display: grid;
    gap: 8px;
}

.message-template-item {
    display: block;
    padding: 12px 14px;
    border: 1px solid
        var(--admin-border, #dfe4ea);
    border-radius: 10px;
    text-decoration: none;
    color: inherit;
    background: var(
        --admin-surface,
        #fff
    );
}

.message-template-item:hover,
.message-template-item.is-active {
    border-color:
        var(--admin-primary, #27845b);
}

.message-template-item__meta {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
    margin-top: 7px;
    font-size: .82rem;
    opacity: .76;
}

.message-template-code {
    direction: ltr;
    unicode-bidi: plaintext;
    font-family: monospace;
    font-size: .82rem;
}

.message-template-editor {
    display: grid;
    gap: 16px;
}

.message-template-filter-form {
    grid-template-columns:
        minmax(240px, 1.5fr)
        minmax(180px, 1fr)
        minmax(160px, .8fr)
        auto;
    align-items: end;
}

.message-template-filter-form
.admin-field:last-child {
    min-width: 120px;
}

.message-template-filter-form
.admin-field:last-child
.admin-btn {
    width: 100%;
    white-space: nowrap;
}

.message-template-fields {
    display: grid;
    gap: 14px;
}

.message-template-fields textarea {
    min-height: 150px;
    resize: vertical;
}

.message-template-variables {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
}

.message-template-variable {
    direction: ltr;
    unicode-bidi: plaintext;
    display: inline-flex;
    padding: 5px 8px;
    border-radius: 999px;
    background: rgba(
        39,
        132,
        91,
        .10
    );
    font-family: monospace;
    font-size: .82rem;
}

.message-template-actions {
    display: flex;
    gap: 9px;
    flex-wrap: wrap;
}

.message-template-preview {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.message-template-history {
    width: 100%;
    border-collapse: collapse;
}

.message-template-history th,
.message-template-history td {
    padding: 9px 8px;
    border-bottom: 1px solid
        var(--admin-border, #e6e9ed);
    text-align: right;
}

@media (max-width: 1100px) {
    .message-template-filter-form {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 900px) {
    .message-template-layout {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .message-template-filter-form {
        grid-template-columns: 1fr;
    }
}
</style>

<nav
    class="admin-breadcrumb"
    aria-label="breadcrumb"
>
    <a href="/admin/dashboard">
        داشبورد
    </a>
    <span>/</span>

    <a href="/admin/communications">
        پیام‌ها و اعلان‌ها
    </a>
    <span>/</span>

    <span>
        متن‌های پیام
    </span>
</nav>

<section
    class="admin-module-hub admin-module-hub--green"
>
    <div
        class="admin-module-hub__icon"
    >
        <?= \App\Support\AdminIcon::html(
            'file-text'
        ) ?>
    </div>

    <div>
        <h2>
            مدیریت متن‌های پیام
        </h2>

        <p>
            مدیریت متمرکز متن پیامک،
            ایمیل، بله و اعلان‌های داخلی
            همراه با نسخه‌بندی و پیش‌نمایش
        </p>
    </div>
</section>

<?php if (
    $status !== ''
    && isset(
        $statusMessages[$status]
    )
): ?>
    <div class="admin-alert">
        <?= $escape(
            $statusMessages[$status]
        ) ?>
    </div>
<?php endif; ?>

<section class="admin-section">
    <div class="admin-section__header">
        <div>
            <h2>
                جستجو و فیلتر
            </h2>
        </div>
    </div>

    <form
        method="get"
        action="/admin/communications/templates"
        class="admin-form-grid message-template-filter-form"
    >
        <div class="admin-field">
            <label for="template-q">
                جستجو
            </label>

            <input
                id="template-q"
                type="search"
                name="q"
                value="<?= $escape(
                    $filters['q']
                    ?? ''
                ) ?>"
                placeholder="عنوان یا کلید متن"
            >
        </div>

        <div class="admin-field">
            <label for="template-channel-filter">
                کانال
            </label>

            <select
                id="template-channel-filter"
                name="channel"
            >
                <option value="">
                    همه کانال‌ها
                </option>

                <?php foreach (
                    $channels
                    as $channel
                ): ?>
                    <option
                        value="<?= $escape(
                            $channel['code']
                            ?? ''
                        ) ?>"
                        <?= (
                            (string) (
                                $filters['channel']
                                ?? ''
                            )
                            ===
                            (string) (
                                $channel['code']
                                ?? ''
                            )
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >
                        <?= $escape(
                            $channel['title']
                            ?? $channel['code']
                            ?? ''
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="admin-field">
            <label for="template-status-filter">
                وضعیت
            </label>

            <select
                id="template-status-filter"
                name="template_status"
            >
                <option value="">
                    همه
                </option>

                <option
                    value="active"
                    <?= (
                        ($filters['status'] ?? '')
                        === 'active'
                    )
                        ? 'selected'
                        : ''
                    ?>
                >
                    فعال
                </option>

                <option
                    value="inactive"
                    <?= (
                        ($filters['status'] ?? '')
                        === 'inactive'
                    )
                        ? 'selected'
                        : ''
                    ?>
                >
                    غیرفعال
                </option>
            </select>
        </div>

        <div class="admin-field">
            <label>&nbsp;</label>

            <button
                type="submit"
                class="admin-btn admin-btn--primary"
            >
                اعمال فیلتر
            </button>
        </div>
    </form>
</section>

<div class="message-template-layout">
    <section class="admin-section">
        <div class="admin-section__header">
            <div>
                <h2>
                    متن‌ها
                </h2>

                <p class="admin-muted">
                    <?= $escape(
                        $digits(
                            count($items)
                        )
                    ) ?>
                    مورد
                </p>
            </div>
        </div>

        <div class="message-template-list">
            <?php foreach (
                $items
                as $item
            ): ?>
                <?php
                $itemLatest =
                    is_array(
                        $item['latest']
                        ?? null
                    )
                        ? $item['latest']
                        : [];

                $itemActive =
                    (int) (
                        $itemLatest[
                            'is_active'
                        ]
                        ?? 0
                    ) === 1;

                $isSelected =
                    is_array($selected)
                    && (
                        (string) $selected['code']
                        ===
                        (string) $item['code']
                    )
                    && (
                        (string) $selected[
                            'channel_code'
                        ]
                        ===
                        (string) $item[
                            'channel_code'
                        ]
                    );

                $link =
                    '/admin/communications/templates?'
                    . http_build_query(
                        [
                            'code' =>
                                $item['code'],

                            'selected_channel' =>
                                $item[
                                    'channel_code'
                                ],

                            'locale' =>
                                $item['locale'],
                        ],
                        '',
                        '&',
                        PHP_QUERY_RFC3986
                    );
                ?>

                <a
                    class="message-template-item<?= $isSelected ? ' is-active' : '' ?>"
                    href="<?= $escape(
                        $link
                    ) ?>"
                >
                    <strong>
                        <?= $escape(
                            $item[
                                'display_title'
                            ]
                            ?? ''
                        ) ?>
                    </strong>

                    <div
                        class="message-template-code"
                    >
                        <?= $escape(
                            $item['code']
                            ?? ''
                        ) ?>
                    </div>

                    <div
                        class="message-template-item__meta"
                    >
                        <span>
                            <?= $escape(
                                $item[
                                    'channel_title'
                                ]
                                ?? $item[
                                    'channel_code'
                                ]
                                ?? ''
                            ) ?>
                        </span>

                        <span>
                            نسخه
                            <?= $escape(
                                $digits(
                                    $itemLatest[
                                        'version'
                                    ]
                                    ?? '—'
                                )
                            ) ?>
                        </span>

                        <span>
                            <?= $itemActive
                                ? 'فعال'
                                : 'غیرفعال'
                            ?>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>

            <?php if (
                $items === []
            ): ?>
                <div class="admin-empty-state">
                    متنی با این فیلتر پیدا نشد.
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="message-template-editor">
        <?php if (
            is_array($selected)
        ): ?>
            <section class="admin-section">
                <div class="admin-section__header">
                    <div>
                        <h2>
                            <?= $escape(
                                $selected[
                                    'display_title'
                                ]
                                ?? ''
                            ) ?>
                        </h2>

                        <p
                            class="admin-muted message-template-code"
                        >
                            <?= $escape(
                                $selected['code']
                                ?? ''
                            ) ?>
                        </p>
                    </div>
                </div>

                <div
                    class="message-template-item__meta"
                >
                    <span>
                        کانال:
                        <?= $escape(
                            $selected[
                                'channel_title'
                            ]
                            ?? $selected[
                                'channel_code'
                            ]
                            ?? ''
                        ) ?>
                    </span>

                    <span>
                        نسخه جاری:
                        <?= $escape(
                            $digits(
                                $latest[
                                    'version'
                                ]
                                ?? '—'
                            )
                        ) ?>
                    </span>

                    <span>
                        کلید رویداد:
                        <span
                            class="message-template-code"
                        >
                            <?= $escape(
                                $selected[
                                    'event_type'
                                ]
                                ?? ''
                            ) ?>
                        </span>
                    </span>
                </div>

                <p class="admin-muted">
                    <?= $escape(
                        $selected[
                            'description'
                        ]
                        ?? ''
                    ) ?>
                </p>

                <hr>

                <div>
                    <strong>
                        متغیرهای مجاز
                    </strong>

                    <div
                        class="message-template-variables"
                    >
                        <?php foreach (
                            $allowedVariables
                            as $variable
                        ): ?>
                            <span
                                class="message-template-variable"
                            >
                                {{<?= $escape(
                                    $variable
                                ) ?>}}
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <form
                    method="post"
                    action="/admin/communications/templates/save"
                    class="message-template-fields"
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= $escape(
                            $csrf
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="code"
                        value="<?= $escape(
                            $selected['code']
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="channel_code"
                        value="<?= $escape(
                            $selected[
                                'channel_code'
                            ]
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="locale"
                        value="<?= $escape(
                            $selected['locale']
                        ) ?>"
                    >

                    <?php if (
                        $supportsSubject
                    ): ?>
                        <div class="admin-field">
                            <label>
                                عنوان / موضوع
                            </label>

                            <input
                                type="text"
                                name="title_template"
                                maxlength="500"
                                value="<?= $escape(
                                    $currentTitle
                                ) ?>"
                            >
                        </div>
                    <?php else: ?>
                        <input
                            type="hidden"
                            name="title_template"
                            value="<?= $escape(
                                $currentTitle
                            ) ?>"
                        >
                    <?php endif; ?>

                    <div class="admin-field">
                        <label>
                            متن پیام
                        </label>

                        <textarea
                            name="body_template"
                            required
                        ><?= $escape(
                            $currentBody
                        ) ?></textarea>
                    </div>

                    <div class="admin-field">
                        <label>
                            نشانی اقدام
                        </label>

                        <input
                            type="text"
                            name="action_url_template"
                            maxlength="1000"
                            value="<?= $escape(
                                $currentAction
                            ) ?>"
                        >
                    </div>

                    <label>
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            <?= $currentActive
                                ? 'checked'
                                : ''
                            ?>
                        >
                        این نسخه فعال باشد
                    </label>

                    <div
                        class="message-template-actions"
                    >
                        <button
                            type="submit"
                            class="admin-btn admin-btn--primary"
                        >
                            ذخیره نسخه جدید
                        </button>

                        <button
                            type="submit"
                            formaction="/admin/communications/templates/preview"
                            class="admin-btn"
                        >
                            پیش‌نمایش
                        </button>
                    </div>
                </form>
            </section>

            <?php if (
                is_array($preview)
            ): ?>
                <section class="admin-section">
                    <div class="admin-section__header">
                        <h2>
                            پیش‌نمایش
                        </h2>
                    </div>

                    <?php
                    $rendered =
                        is_array(
                            $preview[
                                'rendered'
                            ]
                            ?? null
                        )
                            ? $preview[
                                'rendered'
                            ]
                            : [];
                    ?>

                    <?php if (
                        trim(
                            (string) (
                                $rendered[
                                    'title'
                                ]
                                ?? ''
                            )
                        ) !== ''
                    ): ?>
                        <h3>
                            <?= $escape(
                                $rendered[
                                    'title'
                                ]
                            ) ?>
                        </h3>
                    <?php endif; ?>

                    <div
                        class="message-template-preview"
                    ><?= $escape(
                        $rendered['body']
                        ?? ''
                    ) ?></div>

                    <?php if (
                        trim(
                            (string) (
                                $rendered[
                                    'action_url'
                                ]
                                ?? ''
                            )
                        ) !== ''
                    ): ?>
                        <p>
                            مقصد اقدام:
                            <span
                                class="message-template-code"
                            >
                                <?= $escape(
                                    $rendered[
                                        'action_url'
                                    ]
                                ) ?>
                            </span>
                        </p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="admin-section">
                <div class="admin-section__header">
                    <div>
                        <h2>
                            ارسال آزمایشی
                        </h2>
                    </div>
                </div>

                <?php if (
                    !$externalTestChannel
                ): ?>
                    <p class="admin-muted">
                        برای اعلان داخلی،
                        پیش‌نمایش بالا ملاک بررسی است.
                    </p>

                <?php elseif (
                    !$canTestSend
                ): ?>
                    <p class="admin-muted">
                        دسترسی ارسال آزمایشی
                        برای نقش فعال شما وجود ندارد.
                    </p>

                <?php else: ?>
                    <p class="admin-muted">
                        ارسال آزمایشی همیشه
                        از آخرین نسخه ذخیره‌شده
                        و فعال انجام می‌شود.
                    </p>

                    <form
                        method="post"
                        action="/admin/communications/templates/test-send"
                        class="admin-form-grid"
                    >
                        <input
                            type="hidden"
                            name="_token"
                            value="<?= $escape(
                                $csrf
                            ) ?>"
                        >

                        <input
                            type="hidden"
                            name="code"
                            value="<?= $escape(
                                $selected['code']
                            ) ?>"
                        >

                        <input
                            type="hidden"
                            name="channel_code"
                            value="<?= $escape(
                                $selected[
                                    'channel_code'
                                ]
                            ) ?>"
                        >

                        <input
                            type="hidden"
                            name="locale"
                            value="<?= $escape(
                                $selected['locale']
                            ) ?>"
                        >

                        <div class="admin-field">
                            <label>
                                مقصد آزمایشی
                            </label>

                            <input
                                type="text"
                                name="destination"
                                required
                                autocomplete="off"
                            >
                        </div>

                        <div class="admin-field">
                            <label>&nbsp;</label>

                            <button
                                type="submit"
                                class="admin-btn"
                            >
                                ارسال آزمایشی
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>

            <section class="admin-section">
                <div class="admin-section__header">
                    <h2>
                        تاریخچه نسخه‌ها
                    </h2>
                </div>

                <div class="admin-table-wrap">
                    <table
                        class="message-template-history"
                    >
                        <thead>
                            <tr>
                                <th>نسخه</th>
                                <th>وضعیت</th>
                                <th>زمان ایجاد</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach (
                                $history
                                as $row
                            ): ?>
                                <tr>
                                    <td>
                                        <?= $escape(
                                            $digits(
                                                $row[
                                                    'version'
                                                ]
                                                ?? ''
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= (
                                            (int) (
                                                $row[
                                                    'is_active'
                                                ]
                                                ?? 0
                                            ) === 1
                                        )
                                            ? 'فعال'
                                            : 'بایگانی'
                                        ?>
                                    </td>

                                    <td>
                                        <?= $escape(
                                            $jalaliDateTime(
                                                $row[
                                                    'created_at'
                                                ]
                                                ?? ''
                                            )
                                        ) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="admin-section">
                <div class="admin-section__header">
                    <h2>
                        سابقه تغییرات
                    </h2>
                </div>

                <?php if (
                    $audit === []
                ): ?>
                    <div class="admin-empty-state">
                        هنوز تغییری از طریق
                        این پنل ثبت نشده است.
                    </div>
                <?php else: ?>
                    <div class="admin-table-wrap">
                        <table
                            class="message-template-history"
                        >
                            <thead>
                                <tr>
                                    <th>نسخه قبلی</th>
                                    <th>نسخه جدید</th>
                                    <th>کاربر</th>
                                    <th>زمان</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach (
                                    $audit
                                    as $row
                                ): ?>
                                    <tr>
                                        <td>
                                            <?= $escape(
                                                $digits(
                                                    $row[
                                                        'previous_version'
                                                    ]
                                                    ?? '—'
                                                )
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= $escape(
                                                $digits(
                                                    $row[
                                                        'new_version'
                                                    ]
                                                    ?? '—'
                                                )
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= $escape(
                                                $digits(
                                                    $row[
                                                        'actor_user_id'
                                                    ]
                                                    ?? '—'
                                                )
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= $escape(
                                                $jalaliDateTime(
                                                    $row[
                                                        'created_at'
                                                    ]
                                                    ?? ''
                                                )
                                            ) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

        <?php else: ?>
            <section class="admin-section">
                <div class="admin-empty-state">
                    متنی برای مدیریت وجود ندارد.
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>

<?php

$content =
    ob_get_clean();

require __DIR__ . '/layout.php';
