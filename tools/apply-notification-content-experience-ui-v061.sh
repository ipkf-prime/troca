#!/usr/bin/env bash
set -euo pipefail

repo_root="${1:-/d/Documents/GitHub/troca}"
expected_branch="v0.6.1-notification-provider-management-dev"
expected_head="d533339"

cd "$repo_root"

current_branch="$(git branch --show-current)"
current_head="$(git rev-parse --short HEAD)"

if [[ "$current_branch" != "$expected_branch" ]]; then
    printf 'Expected branch %s; current branch is %s.\n' \
        "$expected_branch" "$current_branch" >&2
    exit 1
fi

if [[ "$current_head" != "$expected_head" ]]; then
    printf 'Expected HEAD %s; current HEAD is %s.\n' \
        "$expected_head" "$current_head" >&2
    exit 1
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "Working tree or index is not clean. Patch stopped." >&2
    git status --short --branch >&2
    exit 1
fi

view_file="public_html/resources/views/admin/communication-settings.php"
style_file="public_html/resources/views/admin/partials/communication-style.php"
test_file="tests/NotificationContentExperienceUiTest.php"
tool_file="tools/apply-notification-content-experience-ui-v061.sh"

for file in "$view_file" "$style_file"; do
    if [[ ! -f "$file" ]]; then
        printf 'Required file not found: %s\n' "$file" >&2
        exit 1
    fi
done

cleanup_on_error() {
    status=$?

    if [[ "$status" -ne 0 ]]; then
        echo
        echo "PATCH FAILED; RESTORING CLEAN TREE" >&2

        git restore --staged --worktree -- \
          "$view_file" \
          "$style_file" \
          >/dev/null 2>&1 || true

        rm -f -- "$test_file" "$tool_file"
    fi

    exit "$status"
}

trap cleanup_on_error EXIT

echo
echo "=== Refine Notification Content Experience ==="

VIEW_FILE="$view_file" perl <<'PERL'
use strict;
use warnings;
use utf8;
use open qw(:std :encoding(UTF-8));

my $path = $ENV{VIEW_FILE};

open my $fh, '<:encoding(UTF-8)', $path
    or die "Could not read $path: $!\n";

local $/;
my $text = <$fh>;
close $fh;

$text =~ s/\r\n?/\n/g;

sub replace_once {
    my ($ref, $old, $new, $label) = @_;

    my $count = () = $$ref =~ /\Q$old\E/g;

    die "Expected one anchor for $label; found $count.\n"
        if $count != 1;

    my $position = index($$ref, $old);

    substr(
        $$ref,
        $position,
        length($old),
        $new
    );

    print "UPDATED: $label\n";
}

replace_once(
    \$text,
    <<'OLD_CONTENT',
                    <fieldset class="notification-send-section">
                        <legend>۴. محتوای اعلان</legend>

                        <div class="notification-send-content-grid">
                            <label>
                                <span>موضوع</span>
                                <input
                                    name="subject"
                                    maxlength="190"
                                    data-send-subject
                                >
                                <small class="communication-muted">
                                    برای ایمیل الزامی است.
                                </small>
                            </label>

                            <label class="notification-send-content-grid__body">
                                <span>متن اعلان</span>
                                <textarea
                                    name="body"
                                    maxlength="10000"
                                    required
                                    data-send-body
                                ></textarea>
                                <small class="communication-muted">
                                    حداکثر ۱۰٬۰۰۰ نویسه
                                </small>
                            </label>
                        </div>
                    </fieldset>
OLD_CONTENT
    <<'NEW_CONTENT',
                    <fieldset
                        class="notification-send-section notification-send-content-step"
                        data-send-content-step
                    >
                        <legend>۴. محتوای اعلان</legend>

                        <section class="notification-send-content-card">
                            <header class="notification-send-content-card__header">
                                <div>
                                    <h3>محتوای پیام</h3>
                                    <p>
                                        متن اصلی اعلان را بنویسید؛
                                        موضوع فقط برای ایمیل استفاده
                                        می‌شود.
                                    </p>
                                </div>
                                <span data-send-content-status>
                                    آماده‌سازی محتوا
                                </span>
                            </header>

                            <div class="notification-send-content-grid">
                                <label
                                    class="notification-send-subject-field"
                                    data-send-subject-field
                                    hidden
                                >
                                    <span>
                                        <strong>موضوع ایمیل</strong>
                                        <small>الزامی برای ایمیل</small>
                                    </span>
                                    <input
                                        name="subject"
                                        maxlength="190"
                                        data-send-subject
                                        placeholder="موضوع کوتاه و روشن بنویسید"
                                    >
                                </label>

                                <label class="notification-send-content-grid__body">
                                    <span class="notification-send-body-heading">
                                        <strong>متن اعلان</strong>
                                        <small>
                                            <b data-send-body-count>۰</b>
                                            از ۱۰٬۰۰۰ نویسه
                                        </small>
                                    </span>
                                    <textarea
                                        name="body"
                                        maxlength="10000"
                                        required
                                        data-send-body
                                        placeholder="متن اعلان را بنویسید..."
                                    ></textarea>
                                    <small class="communication-muted">
                                        متن برای همه کانال‌های انتخاب‌شده
                                        استفاده می‌شود.
                                    </small>
                                </label>
                            </div>
                        </section>
                    </fieldset>
NEW_CONTENT
    'content field composition'
);

replace_once(
    \$text,
    <<'OLD_MEDIA',
                    mediaBlock.innerHTML = `
                        <label class="notification-send-dropzone">
                            <span class="notification-send-dropzone__icon">＋</span>
                            <strong>انتخاب فایل‌های چندرسانه‌ای</strong>
                            <span>تصویر، ویدئو، صوت یا سند</span>
                            <em data-send-media-file-count>فایلی انتخاب نشده است</em>
                            <input type="file"
                                name="media_files[]" multiple
                                data-send-media-files
                                accept=".jpg,.jpeg,.png,.webp,.mp4,.mp3,.m4a,.ogg,.pdf,.docx,.xlsx,.txt">
                        </label>
                        <p class="notification-send-media-limits">
                            حداکثر ۵ فایل، هر فایل ۱۰ مگابایت و مجموع ۳۰ مگابایت
                        </p>
                        <div class="notification-send-media-preview"
                            data-send-media-preview></div>
                    `;
OLD_MEDIA
    <<'NEW_MEDIA',
                    mediaBlock.innerHTML = `
                        <header class="notification-send-media-header">
                            <div>
                                <h3>فایل‌های پیوست</h3>
                                <p>تصویر، ویدئو، صوت یا سند</p>
                            </div>
                            <span data-send-media-file-count>
                                ۰ فایل
                            </span>
                        </header>

                        <div
                            class="notification-send-dropzone"
                            data-send-dropzone
                            tabindex="0"
                            role="button"
                            aria-label="انتخاب فایل‌های چندرسانه‌ای"
                        >
                            <span class="notification-send-dropzone__icon">＋</span>
                            <div>
                                <strong>فایل را انتخاب یا اینجا رها کنید</strong>
                                <small>
                                    حداکثر ۵ فایل؛ هر فایل ۱۰ مگابایت
                                </small>
                            </div>
                            <label class="notification-send-file-trigger">
                                انتخاب فایل
                                <input type="file"
                                    name="media_files[]" multiple
                                    data-send-media-files
                                    accept=".jpg,.jpeg,.png,.webp,.mp4,.mp3,.m4a,.ogg,.pdf,.docx,.xlsx,.txt">
                            </label>
                        </div>

                        <div
                            class="notification-send-media-feedback"
                            data-send-media-feedback
                            aria-live="polite"
                        >
                            هنوز فایلی انتخاب نشده است.
                        </div>

                        <div class="notification-send-media-preview"
                            data-send-media-preview></div>

                        <p class="notification-send-media-limits">
                            مجموع فایل‌ها حداکثر ۳۰ مگابایت
                        </p>
                    `;
NEW_MEDIA
    'compact media uploader'
);

replace_once(
    \$text,
    <<'OLD_REFERENCES',
                    const mediaPreview =
                        form.querySelector(
                            '[data-send-media-preview]'
                        );

                    const refreshType = () => {
OLD_REFERENCES
    <<'NEW_REFERENCES',
                    const mediaPreview =
                        form.querySelector(
                            '[data-send-media-preview]'
                        );
                    const mediaCount =
                        form.querySelector(
                            '[data-send-media-file-count]'
                        );
                    const mediaFeedback =
                        form.querySelector(
                            '[data-send-media-feedback]'
                        );
                    const dropzone =
                        form.querySelector(
                            '[data-send-dropzone]'
                        );
                    const contentStep =
                        form.querySelector(
                            '[data-send-content-step]'
                        );
                    const subjectField =
                        form.querySelector(
                            '[data-send-subject-field]'
                        );
                    const bodyInput =
                        form.querySelector(
                            '[data-send-body]'
                        );
                    const bodyCount =
                        form.querySelector(
                            '[data-send-body-count]'
                        );
                    const contentStatus =
                        form.querySelector(
                            '[data-send-content-status]'
                        );
                    const formatBytes = (bytes) => {
                        const size = Number(bytes) || 0;

                        if (size < 1024 * 1024) {
                            return digits.format(
                                Math.max(
                                    1,
                                    Math.ceil(size / 1024)
                                )
                            ) + ' کیلوبایت';
                        }

                        return new Intl.NumberFormat(
                            'fa-IR',
                            {
                                maximumFractionDigits: 1,
                            }
                        ).format(
                            size / (1024 * 1024)
                        ) + ' مگابایت';
                    };
                    const maxFiles = 5;
                    const maxFileBytes =
                        10 * 1024 * 1024;
                    const maxTotalBytes =
                        30 * 1024 * 1024;
                    let selectedFiles = [];

                    const refreshType = () => {
NEW_REFERENCES
    'content and media UI references'
);

replace_once(
    \$text,
    <<'OLD_REFRESH_TYPE',
                    const refreshType = () => {
                        const type =
                            messageTypes.find(
                                (item) => item.checked
                            )?.value || 'text';
                        const multimedia =
                            type === 'multimedia';

                        if (sms) {
                            if (multimedia) {
                                sms.checked = false;
                            }
                            sms.disabled = multimedia;
                            sms.closest(
                                '[data-send-channel-card]'
                            )?.classList.toggle(
                                'is-disabled',
                                multimedia
                            );
                        }

                        warning.hidden = !multimedia;
                        media.hidden = !multimedia;

                        messageTypes.forEach((item) => {
                            item.closest(
                                '.notification-send-type'
                            )?.classList.toggle(
                                'is-active',
                                item.checked
                            );
                        });
                    };
OLD_REFRESH_TYPE
    <<'NEW_REFRESH_TYPE',
                    const refreshType = () => {
                        const type =
                            messageTypes.find(
                                (item) => item.checked
                            )?.value || 'text';
                        const multimedia =
                            type === 'multimedia';
                        const emailSelected =
                            channels.some(
                                (item) =>
                                    item.value === 'email'
                                    && item.checked
                            );

                        if (sms) {
                            if (multimedia) {
                                sms.checked = false;
                            }
                            sms.disabled = multimedia;
                            sms.closest(
                                '[data-send-channel-card]'
                            )?.classList.toggle(
                                'is-disabled',
                                multimedia
                            );
                        }

                        warning.hidden = !multimedia;
                        media.hidden = !multimedia;
                        contentStep?.classList.toggle(
                            'has-media',
                            multimedia
                        );

                        if (subjectField) {
                            subjectField.hidden =
                                !emailSelected;
                        }

                        if (subject) {
                            subject.required =
                                emailSelected;
                        }

                        if (contentStatus) {
                            contentStatus.textContent =
                                multimedia
                                    ? digits.format(
                                        selectedFiles.length
                                    ) + ' فایل پیوست'
                                    : 'پیام متنی';
                        }

                        messageTypes.forEach((item) => {
                            item.closest(
                                '.notification-send-type'
                            )?.classList.toggle(
                                'is-active',
                                item.checked
                            );
                        });
                    };
NEW_REFRESH_TYPE
    'conditional subject and media layout'
);

replace_once(
    \$text,
    <<'OLD_MEDIA_HANDLER',
                    mediaInput?.addEventListener(
                        'change',
                        () => {
                            mediaPreview.innerHTML = '';
                            Array.from(
                                mediaInput.files || []
                            ).forEach((file) => {
                                const item =
                                    document.createElement(
                                        'article'
                                    );
                                item.innerHTML =
                                    '<strong></strong><small></small>';
                                item.querySelector(
                                    'strong'
                                ).textContent = file.name;
                                item.querySelector(
                                    'small'
                                ).textContent =
                                    new Intl.NumberFormat(
                                        'fa-IR'
                                    ).format(
                                        Math.ceil(
                                            file.size / 1024
                                        )
                                    ) + ' کیلوبایت';
                                mediaPreview.append(item);
                            });
                        }
                    );
OLD_MEDIA_HANDLER
    <<'NEW_MEDIA_HANDLER',
                    const syncInputFiles = () => {
                        if (
                            !mediaInput
                            || typeof DataTransfer
                                === 'undefined'
                        ) {
                            return;
                        }

                        const transfer =
                            new DataTransfer();

                        selectedFiles.forEach((file) => {
                            transfer.items.add(file);
                        });

                        mediaInput.files = transfer.files;
                    };

                    const validateFiles = (files) => {
                        if (files.length > maxFiles) {
                            return 'حداکثر '
                                + digits.format(maxFiles)
                                + ' فایل مجاز است.';
                        }

                        if (files.some(
                            (file) =>
                                file.size > maxFileBytes
                        )) {
                            return 'حجم هر فایل باید حداکثر '
                                + '۱۰ مگابایت باشد.';
                        }

                        const total = files.reduce(
                            (sum, file) =>
                                sum + file.size,
                            0
                        );

                        if (total > maxTotalBytes) {
                            return 'مجموع فایل‌ها باید حداکثر '
                                + '۳۰ مگابایت باشد.';
                        }

                        return '';
                    };

                    const renderFiles = () => {
                        if (!mediaPreview) {
                            return;
                        }

                        mediaPreview.innerHTML = '';

                        selectedFiles.forEach(
                            (file, index) => {
                                const item =
                                    document.createElement(
                                        'article'
                                    );
                                const extension = (
                                    file.name
                                        .split('.')
                                        .pop()
                                    || 'فایل'
                                ).toUpperCase();

                                item.innerHTML = `
                                    <span class="notification-send-media-preview__type"></span>
                                    <span class="notification-send-media-preview__info">
                                        <strong></strong>
                                        <small></small>
                                    </span>
                                    <button
                                        type="button"
                                        aria-label="حذف فایل"
                                    >
                                        حذف
                                    </button>
                                `;

                                item.querySelector(
                                    '.notification-send-media-preview__type'
                                ).textContent = extension;
                                item.querySelector(
                                    'strong'
                                ).textContent = file.name;
                                item.querySelector(
                                    'small'
                                ).textContent =
                                    formatBytes(file.size);
                                item.querySelector(
                                    'button'
                                )?.addEventListener(
                                    'click',
                                    () => {
                                        selectedFiles.splice(
                                            index,
                                            1
                                        );
                                        syncInputFiles();
                                        renderFiles();
                                        refreshType();
                                    }
                                );

                                mediaPreview.append(item);
                            }
                        );

                        if (mediaCount) {
                            mediaCount.textContent =
                                digits.format(
                                    selectedFiles.length
                                ) + ' فایل';
                        }

                        if (mediaFeedback) {
                            const total = selectedFiles
                                .reduce(
                                    (sum, file) =>
                                        sum + file.size,
                                    0
                                );

                            mediaFeedback.textContent =
                                selectedFiles.length > 0
                                    ? digits.format(
                                        selectedFiles.length
                                    )
                                        + ' فایل با مجموع '
                                        + formatBytes(total)
                                        + ' آماده ارسال است.'
                                    : 'هنوز فایلی انتخاب نشده است.';
                            mediaFeedback.classList.toggle(
                                'is-ready',
                                selectedFiles.length > 0
                            );
                        }
                    };

                    const setFiles = (files) => {
                        const normalized =
                            Array.from(files || []);
                        const error =
                            validateFiles(normalized);

                        if (error !== '') {
                            window.alert(error);
                            selectedFiles = [];

                            if (mediaInput) {
                                mediaInput.value = '';
                            }

                            renderFiles();
                            refreshType();
                            return;
                        }

                        selectedFiles = normalized;
                        syncInputFiles();
                        renderFiles();
                        refreshType();
                    };

                    mediaInput?.addEventListener(
                        'change',
                        () => setFiles(
                            mediaInput.files || []
                        )
                    );

                    dropzone?.addEventListener(
                        'dragover',
                        (event) => {
                            event.preventDefault();
                            dropzone.classList.add(
                                'is-dragging'
                            );
                        }
                    );
                    dropzone?.addEventListener(
                        'dragleave',
                        () => dropzone.classList.remove(
                            'is-dragging'
                        )
                    );
                    dropzone?.addEventListener(
                        'drop',
                        (event) => {
                            event.preventDefault();
                            dropzone.classList.remove(
                                'is-dragging'
                            );
                            setFiles(
                                event.dataTransfer?.files
                                || []
                            );
                        }
                    );
                    dropzone?.addEventListener(
                        'keydown',
                        (event) => {
                            if (
                                event.key === 'Enter'
                                || event.key === ' '
                            ) {
                                event.preventDefault();
                                mediaInput?.click();
                            }
                        }
                    );

                    bodyInput?.addEventListener(
                        'input',
                        () => {
                            if (bodyCount) {
                                bodyCount.textContent =
                                    digits.format(
                                        bodyInput.value.length
                                    );
                            }
                        }
                    );

                    const validateContent = () => {
                        const type =
                            messageTypes.find(
                                (item) => item.checked
                            )?.value || 'text';
                        const emailSelected =
                            channels.some(
                                (item) =>
                                    item.value === 'email'
                                    && item.checked
                            );

                        if (
                            emailSelected
                            && (subject?.value || '')
                                .trim() === ''
                        ) {
                            window.alert(
                                'برای ارسال ایمیل، موضوع '
                                + 'را وارد کنید.'
                            );
                            subject?.focus();
                            return false;
                        }

                        if (
                            (bodyInput?.value || '')
                                .trim() === ''
                        ) {
                            window.alert(
                                'متن اعلان را وارد کنید.'
                            );
                            bodyInput?.focus();
                            return false;
                        }

                        if (
                            type === 'multimedia'
                            && selectedFiles.length < 1
                        ) {
                            window.alert(
                                'برای پیام چندرسانه‌ای '
                                + 'حداقل یک فایل انتخاب کنید.'
                            );
                            mediaInput?.focus();
                            return false;
                        }

                        return true;
                    };
NEW_MEDIA_HANDLER
    'file list, drag drop and content validation'
);

replace_once(
    \$text,
    <<'OLD_TAB_HANDLER',
                    Array.from(tabs.children)
                        .forEach((tab) => {
                            tab.addEventListener(
                                'click',
                                () => showStep(
                                    Number(
                                        tab.dataset.sendStepTab
                                    )
                                )
                            );
                        });
OLD_TAB_HANDLER
    <<'NEW_TAB_HANDLER',
                    Array.from(tabs.children)
                        .forEach((tab) => {
                            tab.addEventListener(
                                'click',
                                () => {
                                    const target = Number(
                                        tab.dataset.sendStepTab
                                    );

                                    if (
                                        target === 5
                                        && !validateContent()
                                    ) {
                                        return;
                                    }

                                    showStep(target);
                                }
                            );
                        });
NEW_TAB_HANDLER
    'review step content validation'
);

replace_once(
    \$text,
    <<'OLD_NEXT_HANDLER',
                    next.addEventListener(
                        'click',
                        () => showStep(step + 1)
                    );
OLD_NEXT_HANDLER
    <<'NEW_NEXT_HANDLER',
                    next.addEventListener(
                        'click',
                        () => {
                            if (
                                step === 4
                                && !validateContent()
                            ) {
                                return;
                            }

                            showStep(step + 1);
                        }
                    );

                    form.addEventListener(
                        'submit',
                        (event) => {
                            if (!validateContent()) {
                                event.preventDefault();
                                showStep(4);
                            }
                        }
                    );
NEW_NEXT_HANDLER
    'next and submit content validation'
);

replace_once(
    \$text,
    <<'OLD_TYPE_SUMMARY',
                        typeView.textContent =
                            messageType === 'multimedia'
                                ? 'چندرسانه‌ای'
                                : 'متنی';
OLD_TYPE_SUMMARY
    <<'NEW_TYPE_SUMMARY',
                        const mediaFiles =
                            form.querySelector(
                                '[data-send-media-files]'
                            )?.files?.length || 0;

                        typeView.textContent =
                            messageType === 'multimedia'
                                ? 'چندرسانه‌ای · '
                                    + digits.format(
                                        mediaFiles
                                    )
                                    + ' فایل'
                                : 'متنی';
NEW_TYPE_SUMMARY
    'live multimedia file summary'
);

replace_once(
    \$text,
    <<'OLD_FINAL_INIT',
                    form.addEventListener(
                        'change',
                        refreshType
                    );
                    refreshType();
                    showStep(1);
OLD_FINAL_INIT
    <<'NEW_FINAL_INIT',
                    form.addEventListener(
                        'change',
                        refreshType
                    );

                    renderFiles();

                    if (bodyCount && bodyInput) {
                        bodyCount.textContent =
                            digits.format(
                                bodyInput.value.length
                            );
                    }

                    refreshType();
                    showStep(1);
NEW_FINAL_INIT
    'content UI initialization'
);

$text =~ s/\n*\z/\n/;

open my $out, '>:encoding(UTF-8)', $path
    or die "Could not write $path: $!\n";

print {$out} $text;
close $out;
PERL

echo
echo "=== Add Content Experience Styles ==="

if grep -Fq \
  "notification-content-experience-v061" \
  "$style_file"
then
    echo "Content experience styles already exist." >&2
    exit 1
fi

cat >> "$style_file" <<'CSS'

<style>
/* notification-content-experience-v061 */
.notification-send-content-step {
    align-items: stretch;
    display: grid;
    gap: .65rem;
    grid-template-columns: 1fr;
}

.notification-send-content-step.has-media {
    grid-template-columns:
        minmax(0, 1.55fr)
        minmax(270px, .72fr);
}

.notification-send-content-card,
.notification-send-media-foundation {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    min-width: 0;
    padding: .72rem;
}

.notification-send-content-card {
    display: grid;
    gap: .65rem;
}

.notification-send-content-card__header,
.notification-send-media-header {
    align-items: flex-start;
    display: flex;
    gap: .65rem;
    justify-content: space-between;
}

.notification-send-content-card__header > div,
.notification-send-media-header > div {
    display: grid;
    gap: .12rem;
}

.notification-send-content-card__header h3,
.notification-send-media-header h3 {
    font-size: .88rem;
    margin: 0;
}

.notification-send-content-card__header p,
.notification-send-media-header p {
    color: var(--admin-text-muted);
    font-size: .7rem;
    line-height: 1.65;
    margin: 0;
}

.notification-send-content-card__header > span,
.notification-send-media-header > span {
    background: var(--admin-primary-soft);
    border-radius: 999px;
    color: var(--admin-primary);
    flex: 0 0 auto;
    font-size: .68rem;
    font-weight: 700;
    padding: .3rem .55rem;
}

.notification-send-content-grid {
    gap: .58rem;
}

.notification-send-subject-field,
.notification-send-content-grid__body {
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 10px;
    display: grid;
    gap: .38rem !important;
    padding: .55rem;
}

.notification-send-subject-field[hidden] {
    display: none !important;
}

.notification-send-subject-field > span,
.notification-send-body-heading {
    align-items: center;
    display: flex;
    gap: .5rem;
    justify-content: space-between;
}

.notification-send-subject-field > span strong,
.notification-send-body-heading strong {
    font-size: .76rem;
}

.notification-send-subject-field > span small,
.notification-send-body-heading small {
    color: var(--admin-text-muted);
    font-size: .67rem;
    font-weight: 400;
}

.notification-send-body-heading b {
    color: var(--admin-primary);
    font-weight: 800;
}

.notification-send-subject-field input {
    background: var(--admin-surface);
    min-height: 42px;
}

.notification-send-content-grid__body textarea {
    background: var(--admin-surface);
    line-height: 1.9;
    min-height: 148px;
    padding: .7rem;
    resize: vertical;
}

.notification-send-content-grid__body > small {
    font-size: .67rem;
}

.notification-send-media-foundation {
    align-content: start;
    display: grid;
    gap: .55rem;
    margin: 0;
}

.notification-send-media-foundation[hidden] {
    display: none !important;
}

.notification-send-dropzone {
    align-items: center;
    background: var(--admin-surface-muted);
    border: 1px dashed color-mix(
        in srgb,
        var(--admin-primary) 45%,
        var(--admin-border)
    );
    border-radius: 10px;
    cursor: default;
    display: grid;
    gap: .55rem;
    grid-template-columns:
        auto
        minmax(0, 1fr)
        auto;
    justify-items: stretch;
    min-height: 88px;
    padding: .65rem;
    text-align: start;
    transition:
        background .16s ease,
        border-color .16s ease,
        transform .16s ease;
}

.notification-send-dropzone.is-dragging {
    background: var(--admin-primary-soft);
    border-color: var(--admin-primary);
    transform: translateY(-1px);
}

.notification-send-dropzone > div {
    display: grid;
    gap: .12rem;
    min-width: 0;
}

.notification-send-dropzone > div strong {
    font-size: .75rem;
}

.notification-send-dropzone > div small {
    color: var(--admin-text-muted);
    font-size: .66rem;
}

.notification-send-file-trigger {
    align-items: center;
    background: var(--admin-primary);
    border-radius: 8px;
    color: #fff;
    cursor: pointer;
    display: inline-flex;
    font-size: .7rem;
    font-weight: 700;
    justify-content: center;
    min-height: 34px;
    padding: .35rem .6rem;
    white-space: nowrap;
}

.notification-send-file-trigger input {
    block-size: 1px;
    clip: rect(0 0 0 0);
    clip-path: inset(50%);
    inline-size: 1px;
    overflow: hidden;
    position: absolute;
    white-space: nowrap;
}

.notification-send-media-feedback {
    background: var(--admin-surface-muted);
    border-radius: 8px;
    color: var(--admin-text-muted);
    font-size: .67rem;
    line-height: 1.6;
    padding: .4rem .5rem;
}

.notification-send-media-feedback.is-ready {
    background: var(--admin-primary-soft);
    color: var(--admin-primary);
    font-weight: 700;
}

.notification-send-media-preview {
    display: grid;
    gap: .35rem;
    max-height: 184px;
    overflow-y: auto;
}

.notification-send-media-preview article {
    align-items: center;
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 9px;
    display: grid;
    gap: .42rem;
    grid-template-columns:
        auto
        minmax(0, 1fr)
        auto;
    padding: .42rem .48rem;
}

.notification-send-media-preview__type {
    align-items: center;
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 7px;
    color: var(--admin-primary);
    display: inline-flex;
    font-size: .58rem;
    font-weight: 800;
    height: 30px;
    justify-content: center;
    max-width: 44px;
    min-width: 34px;
    padding-inline: .25rem;
}

.notification-send-media-preview__info {
    display: grid;
    gap: .08rem;
    min-width: 0;
}

.notification-send-media-preview__info strong {
    font-size: .69rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.notification-send-media-preview__info small {
    color: var(--admin-text-muted);
    font-size: .62rem;
}

.notification-send-media-preview button {
    background: transparent;
    border: 0;
    border-radius: 6px;
    color: #9b3434;
    cursor: pointer;
    font: inherit;
    font-size: .65rem;
    padding: .28rem .36rem;
}

.notification-send-media-preview button:hover {
    background: #fff1f1;
}

.notification-send-media-limits {
    border-top: 1px solid var(--admin-border);
    padding-top: .42rem;
    text-align: start;
}

@media (max-width: 1050px) {
    .notification-send-content-step.has-media {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 700px) {
    .notification-send-content-card,
    .notification-send-media-foundation {
        border-radius: 10px;
        padding: .6rem;
    }

    .notification-send-content-card__header {
        align-items: stretch;
        flex-direction: column;
    }

    .notification-send-content-card__header > span {
        align-self: flex-start;
    }

    .notification-send-dropzone {
        grid-template-columns: auto minmax(0, 1fr);
    }

    .notification-send-file-trigger {
        grid-column: 1 / -1;
        width: 100%;
    }
}

@media (max-width: 430px) {
    .notification-send-subject-field > span,
    .notification-send-body-heading,
    .notification-send-media-header {
        align-items: flex-start;
        flex-direction: column;
        gap: .15rem;
    }

    .notification-send-content-grid__body textarea {
        min-height: 128px;
    }
}
</style>
CSS

echo "UPDATED: content experience responsive styles"

cat > "$test_file" <<'PHP'
<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (
    string $path
) use ($root): string {
    $content = file_get_contents(
        $root . '/' . $path
    );

    if (!is_string($content)) {
        fwrite(
            STDERR,
            "FAIL: cannot read {$path}\n"
        );
        exit(1);
    }

    return $content;
};

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$view = $read(
    'public_html/resources/views/admin/'
    . 'communication-settings.php'
);
$style = $read(
    'public_html/resources/views/admin/partials/'
    . 'communication-style.php'
);

$expect(
    str_contains(
        $view,
        'data-send-content-step'
    )
    && str_contains(
        $view,
        'data-send-subject-field'
    )
    && str_contains(
        $view,
        'data-send-body-count'
    )
    && str_contains(
        $view,
        'data-send-content-status'
    ),
    'Content composition markers are incomplete.'
);

$expect(
    str_contains(
        $view,
        'data-send-dropzone'
    )
    && str_contains(
        $view,
        'data-send-media-feedback'
    )
    && str_contains(
        $view,
        'DataTransfer'
    )
    && str_contains(
        $view,
        'validateContent'
    ),
    'Media picker experience is incomplete.'
);

$expect(
    str_contains(
        $view,
        "subjectField.hidden"
    )
    && str_contains(
        $view,
        "contentStep?.classList.toggle"
    )
    && str_contains(
        $view,
        "'چندرسانه‌ای · '"
    ),
    'Conditional content behavior is incomplete.'
);

$expect(
    str_contains(
        $style,
        'notification-content-experience-v061'
    )
    && str_contains(
        $style,
        '.notification-send-content-step.has-media'
    )
    && str_contains(
        $style,
        '.notification-send-media-preview__type'
    )
    && str_contains(
        $style,
        '@media (max-width: 430px)'
    ),
    'Content experience styles are incomplete.'
);

echo "Notification content experience UI checks passed.\n";
PHP

echo "ADDED: NotificationContentExperienceUiTest.php"

mkdir -p tools
cp -- "$0" "$tool_file"

git add -- \
  "$view_file" \
  "$style_file" \
  "$test_file" \
  "$tool_file"

echo
echo "=== Cached Validation ==="

git diff --cached --check

if command -v php >/dev/null 2>&1; then
    echo
    echo "=== PHP Validation ==="

    php -l "$view_file"
    php -l "$style_file"
    php -l "$test_file"
    php "$test_file"
else
    echo
    echo "PHP_NOT_AVAILABLE_ON_WINDOWS=SKIPPED"
fi

echo
echo "=== Content Experience Markers ==="

git grep -n -E \
  "data-send-content-step|data-send-subject-field|data-send-body-count|data-send-dropzone|data-send-media-feedback|notification-content-experience-v061|Notification content experience UI checks passed" \
  -- \
  "$view_file" \
  "$style_file" \
  "$test_file"

echo
echo "=== Backend Scope Check ==="

backend_changed="$(
  git diff --cached --name-only \
    | grep -E '^public_html/(app|routes|system)/' \
    || true
)"

if [[ -n "$backend_changed" ]]; then
    echo "BACKEND_SCOPE_CHANGED=1" >&2
    printf '%s\n' "$backend_changed" >&2
    exit 1
fi

echo "BACKEND_SCOPE_CHANGED=0"
echo "MIGRATION_REQUIRED=NO"

echo
echo "=== Unstaged Changes Check ==="

if git diff --quiet; then
    echo "UNSTAGED_CHANGES=0"
else
    echo "UNSTAGED_CHANGES=1"
    git status --short
    exit 1
fi

echo
echo "=== Cached Summary ==="

git diff --cached --stat

echo
echo "=== Final Status ==="

git status --short --branch

echo
echo "NOTIFICATION CONTENT EXPERIENCE UI ADDED AND STAGED"
echo "No commit was created."

trap - EXIT
