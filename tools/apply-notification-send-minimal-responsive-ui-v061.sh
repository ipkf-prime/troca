#!/usr/bin/env bash
set -euo pipefail

repo_root="${1:-/d/Documents/GitHub/troca}"
expected_branch="v0.6.1-notification-provider-management-dev"
expected_head="69de24e"

cd "$repo_root"

current_branch="$(git branch --show-current)"
current_head="$(git rev-parse --short HEAD)"

if [[ "$current_branch" != "$expected_branch" ]]; then
  printf 'Expected branch %s; current branch is %s.\n' "$expected_branch" "$current_branch" >&2
  exit 1
fi

if [[ "$current_head" != "$expected_head" ]]; then
  printf 'Expected HEAD %s; current HEAD is %s.\n' "$expected_head" "$current_head" >&2
  exit 1
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "Working tree or index is not clean. Patch stopped." >&2
  git status --short --branch >&2
  exit 1
fi

view_file="public_html/resources/views/admin/communication-settings.php"
style_file="public_html/resources/views/admin/partials/communication-style.php"
test_file="tests/NotificationSendMinimalResponsiveUiTest.php"
tool_file="tools/apply-notification-send-minimal-responsive-ui-v061.sh"

for file in "$view_file" "$style_file"; do
  [[ -f "$file" ]] || { echo "Required file not found: $file" >&2; exit 1; }
done

cleanup_on_error() {
  status=$?
  if [[ "$status" -ne 0 ]]; then
    echo
    echo "PATCH FAILED; RESTORING CLEAN TREE" >&2
    git restore --staged --worktree -- "$view_file" "$style_file" >/dev/null 2>&1 || true
    rm -f -- "$test_file" "$tool_file"
  fi
  exit "$status"
}
trap cleanup_on_error EXIT

echo
echo "=== Apply Minimal Responsive Notification Send UI ==="

VIEW_FILE="$view_file" perl <<'PERL'
use strict;
use warnings;
use utf8;
use open qw(:std :encoding(UTF-8));

my $path = $ENV{VIEW_FILE};
open my $fh, '<:encoding(UTF-8)', $path or die "Could not read $path: $!\n";
local $/;
my $text = <$fh>;
close $fh;
$text =~ s/\r\n?/\n/g;

sub replace_once {
  my ($ref, $old, $new, $label) = @_;
  my $count = () = $$ref =~ /\Q$old\E/g;
  die "Expected one anchor for $label; found $count.\n" if $count != 1;
  my $position = index($$ref, $old);
  substr($$ref, $position, length($old), $new);
  print "UPDATED: $label\n";
}

replace_once(
  \$text,
  <<'OLD_TITLES',
                    const titles = [
                        'نوع و کانال',
                        'گیرندگان',
                        'مقصدهای دستی',
                        'محتوا',
                        'بازبینی و ارسال'
                    ];
OLD_TITLES
  <<'NEW_TITLES',
                    const titles = [
                        'کانال',
                        'گیرندگان',
                        'مقصد دستی',
                        'محتوا',
                        'بازبینی'
                    ];
NEW_TITLES
  'compact send step titles'
);

my $overview = <<'OVERVIEW';
                <!-- notification-send-minimal-overview-v061 -->
                <script>
                (() => {
                    const root = document.querySelector(
                        '[data-notification-send-center]'
                    );
                    const form = root?.querySelector(
                        '[data-notification-send-form]'
                    );
                    const tabs = form?.querySelector(
                        '[data-send-step-tabs]'
                    );

                    if (
                        !root
                        || !form
                        || !tabs
                        || form.querySelector(
                            '[data-send-live-summary]'
                        )
                    ) {
                        return;
                    }

                    const summary =
                        document.createElement('section');

                    summary.className =
                        'notification-send-live-summary';
                    summary.dataset.sendLiveSummary = '';
                    summary.setAttribute(
                        'aria-live',
                        'polite'
                    );
                    summary.innerHTML = `
                        <div>
                            <span>مرحله جاری</span>
                            <strong data-send-overview-step>
                                ۱ از ۵
                            </strong>
                        </div>
                        <div>
                            <span>نوع پیام</span>
                            <strong data-send-overview-type>
                                متنی
                            </strong>
                        </div>
                        <div>
                            <span>کانال‌ها</span>
                            <strong data-send-overview-channels>
                                انتخاب نشده
                            </strong>
                        </div>
                        <div>
                            <span>گیرندگان و تحویل</span>
                            <strong data-send-overview-targets>
                                ۰ کاربر · ۰ تحویل
                            </strong>
                        </div>
                    `;

                    tabs.insertAdjacentElement(
                        'afterend',
                        summary
                    );

                    const stepView = summary.querySelector(
                        '[data-send-overview-step]'
                    );
                    const typeView = summary.querySelector(
                        '[data-send-overview-type]'
                    );
                    const channelView = summary.querySelector(
                        '[data-send-overview-channels]'
                    );
                    const targetView = summary.querySelector(
                        '[data-send-overview-targets]'
                    );
                    const digits = new Intl.NumberFormat(
                        'fa-IR'
                    );
                    const channelLabels = {
                        email: 'ایمیل',
                        sms: 'پیامک',
                        messenger: 'بله',
                    };

                    const toNumber = (value) =>
                        Number(
                            (value || '')
                                .replace(
                                    /[۰-۹]/g,
                                    (digit) =>
                                        '۰۱۲۳۴۵۶۷۸۹'
                                            .indexOf(digit)
                                )
                                .replace(/[^\d]/g, '')
                        ) || 0;

                    const refresh = () => {
                        const tabItems = Array.from(
                            tabs.querySelectorAll(
                                '[data-send-step-tab]'
                            )
                        );
                        const activeTab = tabItems.find(
                            (tab) =>
                                tab.classList.contains(
                                    'is-active'
                                )
                        ) || tabItems[0];
                        const step = Number(
                            activeTab?.dataset
                                .sendStepTab || 1
                        );
                        const stepTitle = (
                            activeTab?.textContent || ''
                        )
                            .replace(
                                /^\s*[۰-۹0-9]+\s*/u,
                                ''
                            )
                            .trim();

                        tabItems.forEach((tab) => {
                            const active =
                                tab === activeTab;
                            tab.setAttribute(
                                'aria-selected',
                                active
                                    ? 'true'
                                    : 'false'
                            );

                            if (active) {
                                tab.setAttribute(
                                    'aria-current',
                                    'step'
                                );
                            } else {
                                tab.removeAttribute(
                                    'aria-current'
                                );
                            }
                        });

                        stepView.textContent =
                            digits.format(step)
                            + ' از '
                            + digits.format(5)
                            + (
                                stepTitle !== ''
                                    ? ' · ' + stepTitle
                                    : ''
                            );

                        const messageType =
                            form.querySelector(
                                '[data-send-message-type]:checked'
                            )?.value || 'text';

                        typeView.textContent =
                            messageType === 'multimedia'
                                ? 'چندرسانه‌ای'
                                : 'متنی';

                        const selectedChannels =
                            Array.from(
                                form.querySelectorAll(
                                    '[data-send-channel]:checked'
                                )
                            ).map(
                                (channel) =>
                                    channelLabels[
                                        channel.value
                                    ] || channel.value
                            );

                        channelView.textContent =
                            selectedChannels.length > 0
                                ? selectedChannels.join('، ')
                                : 'انتخاب نشده';

                        const selectedUsers =
                            form.querySelectorAll(
                                '[data-send-user-checkbox]:checked'
                            ).length;
                        const deliveries = toNumber(
                            form.querySelector(
                                '[data-send-estimated-count]'
                            )?.textContent || '۰'
                        );

                        targetView.textContent =
                            digits.format(selectedUsers)
                            + ' کاربر · '
                            + digits.format(deliveries)
                            + ' تحویل';
                    };

                    form.addEventListener(
                        'input',
                        () => window.setTimeout(
                            refresh,
                            0
                        )
                    );
                    form.addEventListener(
                        'change',
                        () => window.setTimeout(
                            refresh,
                            0
                        )
                    );
                    tabs.addEventListener(
                        'click',
                        () => window.setTimeout(
                            refresh,
                            0
                        )
                    );

                    const observer =
                        new MutationObserver(refresh);

                    observer.observe(
                        tabs,
                        {
                            attributes: true,
                            subtree: true,
                            attributeFilter: ['class'],
                        }
                    );

                    refresh();
                })();
                </script>
OVERVIEW

replace_once(
  \$text,
  <<'OLD_SEND_END',
                </script>
            </section>

        <?php elseif ($section === 'reports'): ?>
OLD_SEND_END
  "                </script>\n"
    . $overview
    . "            </section>\n\n"
    . "        <?php elseif (\$section === 'reports'): ?>\n",
  'minimal send overview script'
);

$text =~ s/\n*\z/\n/;
open my $out, '>:encoding(UTF-8)', $path or die "Could not write $path: $!\n";
print {$out} $text;
close $out;
PERL

echo
echo "=== Add Minimal Responsive Send Styles ==="

if grep -Fq "notification-send-minimal-responsive-v061" "$style_file"; then
  echo "Minimal responsive send styles already exist." >&2
  exit 1
fi

cat >> "$style_file" <<'CSS'

<style>
/* notification-send-minimal-responsive-v061 */
.notification-send-center {
    gap: .7rem;
}

.notification-send-intro {
    align-items: flex-start;
    background: transparent;
    border: 0;
    border-bottom: 1px solid var(--admin-border);
    border-radius: 0;
    padding: 0 0 .7rem;
}

.notification-send-intro > div:first-child {
    display: grid;
    gap: .2rem;
}

.notification-send-intro h3 {
    font-size: 1.02rem;
}

.notification-send-intro p {
    font-size: .8rem;
    line-height: 1.75;
    margin: 0;
    max-width: 760px;
}

.notification-send-limit {
    align-items: center;
    border-radius: 999px;
    display: flex;
    flex-wrap: wrap;
    gap: .25rem .45rem;
    min-width: 0;
    padding: .42rem .68rem;
}

.notification-send-limit span,
.notification-send-limit small {
    font-size: .7rem;
}

.notification-send-limit strong {
    font-size: .82rem;
    white-space: nowrap;
}

.notification-send-form {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 14px;
    gap: .65rem;
    padding: .65rem;
}

.notification-send-step-tabs {
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    gap: .3rem;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    padding: .3rem;
}

.notification-send-step-tabs button {
    border-color: transparent;
    border-radius: 9px;
    font-size: .78rem;
    gap: .32rem;
    min-height: 36px;
    padding: .38rem .42rem;
}

.notification-send-step-tabs button > span {
    flex: 0 0 20px;
    height: 20px;
    width: 20px;
}

.notification-send-step-tabs button.is-active {
    background: var(--admin-surface);
    box-shadow: 0 1px 3px rgb(0 0 0 / 7%);
}

.notification-send-live-summary {
    display: grid;
    gap: .45rem;
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.notification-send-live-summary > div {
    background: color-mix(
        in srgb,
        var(--admin-surface-muted) 72%,
        var(--admin-surface)
    );
    border: 1px solid var(--admin-border);
    border-radius: 10px;
    display: grid;
    gap: .14rem;
    min-width: 0;
    padding: .48rem .58rem;
}

.notification-send-live-summary span {
    color: var(--admin-text-muted);
    font-size: .68rem;
}

.notification-send-live-summary strong {
    font-size: .76rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.notification-send-section {
    border: 0;
    border-radius: 0;
    gap: .65rem;
    padding: .15rem .1rem;
}

.notification-send-section > legend {
    block-size: 1px;
    clip: rect(0 0 0 0);
    clip-path: inset(50%);
    inline-size: 1px;
    overflow: hidden;
    padding: 0;
    position: absolute;
    white-space: nowrap;
}

.notification-send-message-types {
    display: grid;
    gap: .45rem;
}

.notification-send-message-types > h3 {
    font-size: .86rem;
    margin: 0;
}

.notification-send-type-grid {
    gap: .5rem;
    margin-bottom: 0;
}

.notification-send-type {
    align-items: center;
    border-radius: 10px;
    gap: .5rem;
    min-height: 62px;
    padding: .58rem .65rem;
}

.notification-send-type span {
    gap: .12rem;
}

.notification-send-type strong {
    font-size: .82rem;
}

.notification-send-type small {
    font-size: .7rem;
    line-height: 1.55;
}

.notification-send-channel-grid {
    gap: .5rem;
}

.notification-send-channel {
    align-items: center;
    border-radius: 10px;
    gap: .5rem;
    min-height: 64px;
    padding: .58rem .65rem;
}

.notification-send-channel span {
    gap: .1rem;
}

.notification-send-channel strong {
    font-size: .82rem;
}

.notification-send-channel small {
    display: -webkit-box;
    font-size: .7rem;
    line-height: 1.55;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.notification-send-media-note {
    border-radius: 9px;
    font-size: .75rem;
    margin: 0;
    padding: .5rem .6rem;
}

.notification-send-user-tools {
    gap: .5rem;
    grid-template-columns:
        minmax(220px, 2fr)
        repeat(3, minmax(125px, 1fr));
}

.notification-send-user-tools label {
    gap: .2rem;
}

.notification-send-user-tools label > span {
    font-size: .7rem;
}

.notification-send-user-tools input,
.notification-send-user-tools select {
    min-height: 38px;
}

.notification-send-user-actions {
    background: var(--admin-surface-muted);
    border-radius: 10px;
    gap: .4rem;
    padding: .45rem;
}

.notification-send-user-actions .admin-button {
    min-height: 34px;
    padding: .35rem .58rem;
}

.notification-send-user-actions .communication-muted {
    margin-inline-start: auto;
    white-space: nowrap;
}

.notification-send-user-list {
    border-radius: 10px;
    max-height: 360px;
}

.notification-send-user {
    gap: .55rem;
    grid-template-columns: auto minmax(180px, 1fr) auto;
    padding: .52rem .62rem;
}

.notification-send-user__identity {
    gap: .08rem;
    min-width: 0;
}

.notification-send-user__identity strong {
    font-size: .8rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.notification-send-user__identity small {
    font-size: .69rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.notification-send-user__channels {
    gap: .2rem;
}

.notification-send-user__channels small {
    font-size: .66rem;
    padding: .13rem .32rem;
}

.notification-send-manual-grid {
    gap: .55rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.notification-send-manual-grid label,
.notification-send-content-grid label {
    gap: .25rem;
}

.notification-send-manual-grid label > span,
.notification-send-content-grid label > span {
    font-size: .75rem;
    font-weight: 700;
}

.notification-send-manual-grid textarea {
    min-height: 96px;
}

.notification-send-content-grid {
    gap: .55rem;
    grid-template-columns: 1fr;
}

.notification-send-content-grid__body textarea {
    min-height: 150px;
}

.notification-send-media-foundation {
    gap: .5rem;
    margin-top: .1rem;
}

.notification-send-dropzone {
    border-radius: 10px;
    min-height: 112px;
    padding: .75rem;
}

.notification-send-review {
    border-radius: 11px;
    gap: .65rem;
    grid-template-columns:
        minmax(170px, .7fr)
        minmax(0, 1.8fr);
    padding: .65rem;
}

.notification-send-review > div {
    gap: .1rem;
}

.notification-send-review > div > span,
.notification-send-review > div > small {
    font-size: .7rem;
}

.notification-send-review strong {
    font-size: .96rem;
}

.notification-send-confirm {
    align-items: center;
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 9px;
    gap: .45rem;
    padding: .5rem .58rem;
}

.notification-send-confirm span {
    font-size: .76rem;
    line-height: 1.65;
}

.notification-send-actions {
    align-items: center;
    background: color-mix(
        in srgb,
        var(--admin-surface) 92%,
        transparent
    );
    border: 1px solid var(--admin-border);
    border-radius: 11px;
    bottom: .35rem;
    box-shadow: 0 8px 24px rgb(0 0 0 / 7%);
    gap: .4rem;
    padding: .45rem;
    position: sticky;
    z-index: 4;
}

.notification-send-actions .admin-button {
    min-height: 36px;
    padding: .38rem .7rem;
}

.notification-send-actions > a {
    margin-inline-start: auto;
}

@media (max-width: 1100px) {
    .notification-send-step-tabs {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .notification-send-live-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .notification-send-user-tools {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .notification-send-user-search {
        grid-column: 1 / -1;
    }
}

@media (max-width: 700px) {
    .notification-send-intro {
        gap: .55rem;
    }

    .notification-send-limit {
        align-self: stretch;
        border-radius: 10px;
        justify-content: center;
    }

    .notification-send-form {
        border-inline: 0;
        border-radius: 0;
        margin-inline: -.65rem;
        padding-inline: .55rem;
    }

    .notification-send-step-tabs {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .notification-send-step-tabs button:last-child {
        grid-column: 1 / -1;
    }

    .notification-send-live-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .notification-send-type-grid,
    .notification-send-channel-grid,
    .notification-send-user-tools,
    .notification-send-manual-grid,
    .notification-send-review {
        grid-template-columns: 1fr;
    }

    .notification-send-user-search {
        grid-column: auto;
    }

    .notification-send-user-actions {
        align-items: stretch;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .notification-send-user-actions .communication-muted {
        grid-column: 1 / -1;
        margin-inline-start: 0;
        text-align: center;
    }

    .notification-send-user {
        align-items: start;
        grid-template-columns: auto minmax(0, 1fr);
    }

    .notification-send-user__channels {
        grid-column: 2;
        flex-wrap: wrap;
    }

    .notification-send-user__identity small {
        white-space: normal;
    }

    .notification-send-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .notification-send-actions > a {
        grid-column: 1 / -1;
        margin-inline-start: 0;
        order: 3;
        text-align: center;
    }
}

@media (max-width: 430px) {
    .notification-send-live-summary {
        grid-template-columns: 1fr;
    }

    .notification-send-step-tabs button {
        justify-content: flex-start;
    }

    .notification-send-user-actions,
    .notification-send-actions {
        grid-template-columns: 1fr;
    }

    .notification-send-actions > a {
        grid-column: auto;
    }
}
</style>
CSS

echo "UPDATED: minimal responsive notification send styles"

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
        'notification-send-minimal-overview-v061'
    )
    && str_contains(
        $view,
        'data-send-live-summary'
    )
    && str_contains(
        $view,
        'data-send-overview-step'
    )
    && str_contains(
        $view,
        'MutationObserver'
    ),
    'Minimal send overview behavior is incomplete.'
);

$expect(
    str_contains($view, "'کانال',")
    && str_contains($view, "'مقصد دستی',")
    && str_contains($view, "'بازبینی'"),
    'Compact send step titles are incomplete.'
);

$expect(
    str_contains(
        $style,
        'notification-send-minimal-responsive-v061'
    )
    && str_contains(
        $style,
        '.notification-send-live-summary'
    )
    && str_contains(
        $style,
        'position: sticky'
    )
    && str_contains(
        $style,
        '@media (max-width: 430px)'
    ),
    'Minimal responsive send styles are incomplete.'
);

echo "Notification send minimal responsive UI checks passed.\n";
PHP

echo "ADDED: NotificationSendMinimalResponsiveUiTest.php"

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

echo
echo "=== Minimal Responsive UI Markers ==="
git grep -n -E \
  "notification-send-minimal-overview-v061|data-send-live-summary|notification-send-minimal-responsive-v061|Notification send minimal responsive UI checks passed" \
  -- \
  "$view_file" \
  "$style_file" \
  "$test_file"

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
echo "NOTIFICATION SEND MINIMAL RESPONSIVE UI ADDED AND STAGED"
echo "No commit was created."

trap - EXIT
