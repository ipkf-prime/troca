<?php

$richTextContent =
    new \App\Services\Automation\Correspondence\CorrespondenceRichTextContent();

$richFormatHint =
    (string) (
        $form[
            'content_format_code'
        ] ?? ''
    );

$richEditorHtml =
    $richTextContent->editorHtml(
        $form[
            'content'
        ] ?? '',
        $richFormatHint
    );

$richFallbackText =
    $richTextContent->editorPlainText(
        $form[
            'content'
        ] ?? '',
        $richFormatHint
    );
?>

<style>
.automation-rich-editor-shell {
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    overflow: hidden;
    background: var(--admin-surface);
}

.automation-rich-editor-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .35rem;
    padding: .55rem;
    border-bottom: 1px solid var(--admin-border);
    background: var(--admin-surface);
}

.automation-rich-editor-toolbar button {
    min-height: 2.05rem;
    min-width: 2.15rem;
    padding: .3rem .5rem;
    white-space: nowrap;
}

/* word-like-rich-editor-toolbar-v1 */
.automation-rich-editor-toolbar {
    direction: rtl;
    justify-content: flex-start;
}

.automation-rich-editor-tool {
    width: 2.35rem;
    min-width: 2.35rem !important;
    height: 2.25rem;
    min-height: 2.25rem !important;
    padding: .28rem !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: .55rem;
}

.automation-rich-editor-tool-icon {
    width: 1.18rem;
    height: 1.18rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
}

.automation-rich-editor-tool-icon svg {
    width: 100%;
    height: 100%;
    display: block;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.automation-rich-editor-tool[aria-pressed="true"],
.automation-rich-editor-tool.is-active {
    color: var(--admin-primary);
    border-color: color-mix(
        in srgb,
        var(--admin-primary) 38%,
        var(--admin-border)
    );
    background: color-mix(
        in srgb,
        var(--admin-primary) 12%,
        var(--admin-surface)
    );
    box-shadow:
        inset 0 0 0 1px
        color-mix(
            in srgb,
            var(--admin-primary) 12%,
            transparent
        );
}

.automation-rich-editor-tool:focus-visible {
    outline: 2px solid
        color-mix(
            in srgb,
            var(--admin-primary) 45%,
            transparent
        );
    outline-offset: 2px;
}

.automation-rich-editor-toolbar
.admin-button[data-rich-block] {
    min-width: auto;
    padding-inline: .7rem;
}

@media (max-width: 760px) {
    .automation-rich-editor-toolbar {
        flex-wrap: nowrap;
        overflow-x: auto;
        overscroll-behavior-inline: contain;
        scrollbar-width: thin;
    }

    .automation-rich-editor-toolbar > * {
        flex: 0 0 auto;
    }
}
.automation-rich-editor-separator {
    width: 1px;
    height: 1.55rem;
    margin: 0 .1rem;
    background: var(--admin-border);
}

.automation-rich-editor {
    min-height: 280px;
    padding: 1rem 1.15rem;
    direction: rtl;
    text-align: right;
    line-height: 2;
    outline: none;
    overflow-wrap: anywhere;
}

.automation-rich-editor:focus {
    box-shadow:
        inset 0 0 0 2px
        rgba(80, 100, 120, .08);
}

.automation-rich-editor p {
    margin: 0 0 .7rem;
}

.automation-rich-editor h2,
.automation-rich-editor h3 {
    margin: 1rem 0 .55rem;
}

.automation-rich-editor ul,
.automation-rich-editor ol {
    padding-inline-start: 1.7rem;
}

.automation-rich-editor blockquote {
    margin: .75rem 0;
    padding-inline-start: 1rem;
    border-inline-start:
        3px solid var(--admin-border);
}

.automation-rich-editor [data-align="right"] {
    text-align: right;
}

.automation-rich-editor [data-align="center"] {
    text-align: center;
}

.automation-rich-editor [data-align="left"] {
    text-align: left;
}

.automation-rich-editor [data-align="justify"] {
    text-align: justify;
}

.automation-rich-editor [data-indent="1"] {
    padding-inline-start: 1.5rem;
}

.automation-rich-editor [data-indent="2"] {
    padding-inline-start: 3rem;
}

.automation-rich-editor [data-indent="3"] {
    padding-inline-start: 4.5rem;
}

.automation-rich-editor [data-indent="4"] {
    padding-inline-start: 6rem;
}

.automation-rich-editor-footer {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: .45rem .7rem;
    border-top: 1px solid var(--admin-border);
}

.automation-rich-editor-count.is-limit {
    font-weight: 800;
}

@media (max-width: 720px) {
    .automation-rich-editor-toolbar {
        gap: .25rem;
    }

    .automation-rich-editor {
        min-height: 240px;
        padding: .8rem;
    }
}
</style>


<label class="automation-form__wide">
    <span>متن نامه</span>

    <input
        type="hidden"
        name="content_format_code"
        value="plain"
        data-rich-format
    >

    <textarea
        name="content"
        rows="10"
        maxlength="8000"
        required
        data-rich-fallback
    ><?= admin_h($richFallbackText) ?></textarea>

    <div
        class="automation-rich-editor-shell"
        data-rich-editor-shell
        hidden
    >
        <div
            class="automation-rich-editor-toolbar"
            role="toolbar"
            aria-label="ابزارهای ویرایش متن نامه"
        >
            <button
                type="button"
                class="admin-button admin-button--soft admin-button--compact"
                data-rich-command="bold"
                title="ضخیم"
            ><strong>ض</strong></button>

            <button
                type="button"
                class="admin-button admin-button--soft admin-button--compact"
                data-rich-command="italic"
                title="مورب"
            ><em>م</em></button>

            <button
                type="button"
                class="admin-button admin-button--soft admin-button--compact"
                data-rich-command="underline"
                title="زیرخط"
            ><u>ز</u></button>

            <button
                type="button"
                class="admin-button admin-button--soft admin-button--compact"
                data-rich-command="strikeThrough"
                title="خط‌خورده"
            ><s>خ</s></button>

            <span
                class="automation-rich-editor-separator"
                aria-hidden="true"
            ></span>

            <button
                type="button"
                class="admin-button admin-button--soft admin-button--compact"
                data-rich-block="p"
            >متن</button>

            <button
                type="button"
                class="admin-button admin-button--soft admin-button--compact"
                data-rich-block="h2"
            >تیتر ۱</button>

            <button
                type="button"
                class="admin-button admin-button--soft admin-button--compact"
                data-rich-block="h3"
            >تیتر ۲</button>

            <span
                class="automation-rich-editor-separator"
                aria-hidden="true"
            ></span>

            <button
                type="button"
                class="admin-button admin-button--soft admin-button--compact"
                data-rich-command="insertUnorderedList"
                title="فهرست نشانه‌ای"
            >• فهرست</button>

            <button
                type="button"
                class="admin-button admin-button--soft admin-button--compact"
                data-rich-command="insertOrderedList"
                title="فهرست شماره‌ای"
            >۱. فهرست</button>

            <span
                class="automation-rich-editor-separator"
                aria-hidden="true"
            ></span>

            <button
                type="button"
                class="admin-button admin-button--soft admin-button--compact"
                data-rich-align="right"
            >راست</button>

            <button
                type="button"
                class="admin-button admin-button--soft admin-button--compact"
                data-rich-align="center"
            >وسط</button>

            <button
                type="button"
                class="admin-button admin-button--soft admin-button--compact"
                data-rich-align="left"
            >چپ</button>

            <button
                type="button"
                class="admin-button admin-button--soft admin-button--compact"
                data-rich-align="justify"
            >دوطرفه</button>

            <span
                class="automation-rich-editor-separator"
                aria-hidden="true"
            ></span>

            <button
                type="button"
                class="admin-button admin-button--soft admin-button--compact"
                data-rich-indent="out"
            >− تورفتگی</button>

            <button
                type="button"
                class="admin-button admin-button--soft admin-button--compact"
                data-rich-indent="in"
            >+ تورفتگی</button>

            <span
                class="automation-rich-editor-separator"
                aria-hidden="true"
            ></span>

            <button
                type="button"
                class="admin-button admin-button--soft admin-button--compact"
                data-rich-link
            >پیوند</button>

            <button
                type="button"
                class="admin-button admin-button--soft admin-button--compact"
                data-rich-command="unlink"
            >حذف پیوند</button>

            <span
                class="automation-rich-editor-separator"
                aria-hidden="true"
            ></span>

            <button
                type="button"
                class="admin-button admin-button--soft admin-button--compact"
                data-rich-command="undo"
            >واگرد</button>

            <button
                type="button"
                class="admin-button admin-button--soft admin-button--compact"
                data-rich-command="redo"
            >بازانجام</button>

            <button
                type="button"
                class="admin-button admin-button--soft admin-button--compact"
                data-rich-command="removeFormat"
            >پاک‌کردن قالب</button>
        </div>

        <div
            class="automation-rich-editor"
            contenteditable="true"
            role="textbox"
            aria-multiline="true"
            aria-label="متن نامه"
            data-rich-editor
        ></div>

        <div class="automation-rich-editor-footer">
            <small class="admin-muted">
                قالب‌بندی کنترل‌شده برای مکاتبات اداری
            </small>

            <small
                class="admin-muted automation-rich-editor-count"
                data-rich-count
            >
                ۰ از ۸۰۰۰
            </small>
        </div>
    </div>

    <template data-rich-source><?= $richEditorHtml ?></template>
</label>


<script>
(() => {
    const shell =
        document.querySelector(
            '[data-rich-editor-shell]'
        );

    const editor =
        document.querySelector(
            '[data-rich-editor]'
        );

    const fallback =
        document.querySelector(
            '[data-rich-fallback]'
        );

    const source =
        document.querySelector(
            '[data-rich-source]'
        );

    const format =
        document.querySelector(
            '[data-rich-format]'
        );

    const counter =
        document.querySelector(
            '[data-rich-count]'
        );

    if (
        !shell
        || !editor
        || !fallback
        || !source
        || !format
    ) {
        return;
    }


    const maxLength = 8000;


    /*
     * word-like-rich-editor-toolbar-v1
     *
     * Keep the server-rendered Persian labels as a no-JS
     * fallback, then progressively enhance them with familiar
     * word-processor icons and accessible Persian tooltips.
     */
    const toolbarTools = [
        {
            selector: '[data-rich-command="bold"]',
            label: 'پررنگ',
            icon: '<path d="M8 4h5a4 4 0 0 1 0 8H8z"/><path d="M8 12h6a4 4 0 0 1 0 8H8z"/>',
            stateful: true,
        },
        {
            selector: '[data-rich-command="italic"]',
            label: 'مورب',
            icon: '<path d="M10 4h7"/><path d="M7 20h7"/><path d="M14 4 10 20"/>',
            stateful: true,
        },
        {
            selector: '[data-rich-command="underline"]',
            label: 'زیرخط',
            icon: '<path d="M7 4v7a5 5 0 0 0 10 0V4"/><path d="M5 21h14"/>',
            stateful: true,
        },
        {
            selector: '[data-rich-command="strikeThrough"]',
            label: 'خط‌خورده',
            icon: '<path d="M7 7c0-2 2-3 5-3s5 1 5 3"/><path d="M7 17c0 2 2 3 5 3s5-1 5-3"/><path d="M4 12h16"/>',
            stateful: true,
        },
        {
            selector: '[data-rich-command="insertUnorderedList"]',
            label: 'فهرست نشانه‌ای',
            icon: '<circle cx="5" cy="7" r="1"/><circle cx="5" cy="12" r="1"/><circle cx="5" cy="17" r="1"/><path d="M9 7h10M9 12h10M9 17h10"/>',
            stateful: true,
        },
        {
            selector: '[data-rich-command="insertOrderedList"]',
            label: 'فهرست شماره‌ای',
            icon: '<path d="M4 6h2v4M4 10h3M4 14c2-1 3 0 3 1 0 2-3 2-3 4h3"/><path d="M10 7h9M10 12h9M10 17h9"/>',
            stateful: true,
        },
        {
            selector: '[data-rich-align="right"]',
            label: 'راست‌چین',
            icon: '<path d="M5 6h14M9 10h10M5 14h14M11 18h8"/>',
            stateful: true,
        },
        {
            selector: '[data-rich-align="center"]',
            label: 'وسط‌چین',
            icon: '<path d="M5 6h14M8 10h8M5 14h14M8 18h8"/>',
            stateful: true,
        },
        {
            selector: '[data-rich-align="left"]',
            label: 'چپ‌چین',
            icon: '<path d="M5 6h14M5 10h10M5 14h14M5 18h8"/>',
            stateful: true,
        },
        {
            selector: '[data-rich-align="justify"]',
            label: 'تراز دوطرفه',
            icon: '<path d="M5 6h14M5 10h14M5 14h14M5 18h14"/>',
            stateful: true,
        },
        {
            selector: '[data-rich-indent="out"]',
            label: 'کاهش تورفتگی',
            icon: '<path d="M10 6h9M10 10h9M10 14h9M10 18h9"/><path d="m7 9-3 3 3 3"/>',
        },
        {
            selector: '[data-rich-indent="in"]',
            label: 'افزایش تورفتگی',
            icon: '<path d="M10 6h9M10 10h9M10 14h9M10 18h9"/><path d="m4 9 3 3-3 3"/>',
        },
        {
            selector: '[data-rich-link]',
            label: 'درج پیوند',
            icon: '<path d="m10 14 4-4"/><path d="M7.5 16.5 5 19a3.5 3.5 0 0 1-5-5l3-3a3.5 3.5 0 0 1 5 0"/><path d="m16.5 7.5 2.5-2.5a3.5 3.5 0 0 1 5 5l-3 3a3.5 3.5 0 0 1-5 0"/>',
        },
        {
            selector: '[data-rich-command="unlink"]',
            label: 'حذف پیوند',
            icon: '<path d="m9 15-2 2a3.5 3.5 0 0 1-5-5l3-3"/><path d="m15 9 2-2a3.5 3.5 0 0 1 5 5l-3 3"/><path d="m4 4 16 16"/>',
        },
        {
            selector: '[data-rich-command="undo"]',
            label: 'واگرد',
            icon: '<path d="m9 7-5 5 5 5"/><path d="M5 12h9a5 5 0 0 1 5 5v1"/>',
        },
        {
            selector: '[data-rich-command="redo"]',
            label: 'بازانجام',
            icon: '<path d="m15 7 5 5-5 5"/><path d="M19 12h-9a5 5 0 0 0-5 5v1"/>',
        },
        {
            selector: '[data-rich-command="removeFormat"]',
            label: 'پاک‌کردن قالب',
            icon: '<path d="m6 4 12 12"/><path d="m14 4-8 8 6 6 4-4"/><path d="M4 20h16"/>',
        },
    ];


    const decorateToolbar = () => {
        toolbarTools.forEach(
            (tool) => {
                const button =
                    shell.querySelector(
                        tool.selector
                    );

                if (!button) {
                    return;
                }

                button.classList.add(
                    'automation-rich-editor-tool'
                );

                button.dataset.richIcon =
                    'true';

                button.title =
                    tool.label;

                button.setAttribute(
                    'aria-label',
                    tool.label
                );

                if (tool.stateful) {
                    button.setAttribute(
                        'aria-pressed',
                        'false'
                    );
                }

                button.innerHTML =
                    '<span '
                    + 'class="automation-rich-editor-tool-icon" '
                    + 'aria-hidden="true">'
                    + '<svg viewBox="0 0 24 24" '
                    + 'focusable="false">'
                    + tool.icon
                    + '</svg>'
                    + '</span>';
            }
        );

        shell
            .querySelectorAll(
                '[data-rich-block]'
            )
            .forEach(
                (button) => {
                    button.setAttribute(
                        'aria-pressed',
                        'false'
                    );
                }
            );
    };


    const activeBlock = () => {
        const selection =
            window.getSelection();

        if (
            !selection
            || selection.rangeCount === 0
        ) {
            return null;
        }

        let node =
            selection.anchorNode;

        if (
            node
            && node.nodeType === Node.TEXT_NODE
        ) {
            node =
                node.parentElement;
        }

        if (
            !(node instanceof Element)
            || !editor.contains(node)
        ) {
            return null;
        }

        return node.closest(
            'p,h2,h3,blockquote,li,div'
        );
    };


    const updateToolbarState = () => {
        const selection =
            window.getSelection();

        if (
            !selection
            || selection.rangeCount === 0
            || !editor.contains(
                selection.anchorNode
            )
        ) {
            return;
        }

        shell
            .querySelectorAll(
                '[data-rich-command][aria-pressed]'
            )
            .forEach(
                (button) => {
                    let active = false;

                    try {
                        active =
                            document.queryCommandState(
                                button.dataset
                                    .richCommand
                            );
                    } catch (error) {
                        active = false;
                    }

                    button.setAttribute(
                        'aria-pressed',
                        active
                            ? 'true'
                            : 'false'
                    );
                }
            );

        const block =
            activeBlock();

        const blockTag =
            block
                ? block.tagName.toLowerCase()
                : 'p';

        shell
            .querySelectorAll(
                '[data-rich-block]'
            )
            .forEach(
                (button) => {
                    button.setAttribute(
                        'aria-pressed',
                        button.dataset
                            .richBlock
                            === blockTag
                            ? 'true'
                            : 'false'
                    );
                }
            );

        const alignment =
            block?.dataset.align
            || (
                block instanceof HTMLElement
                    ? block.style.textAlign
                    : ''
            )
            || 'right';

        shell
            .querySelectorAll(
                '[data-rich-align]'
            )
            .forEach(
                (button) => {
                    button.setAttribute(
                        'aria-pressed',
                        button.dataset
                            .richAlign
                            === alignment
                            ? 'true'
                            : 'false'
                    );
                }
            );
    };


    decorateToolbar();

    editor.addEventListener(
        'keyup',
        updateToolbarState
    );

    editor.addEventListener(
        'mouseup',
        updateToolbarState
    );

    editor.addEventListener(
        'input',
        updateToolbarState
    );

    document.addEventListener(
        'selectionchange',
        updateToolbarState
    );

    const persianDigits =
        (value) =>
            String(value).replace(
                /\d/g,
                (digit) =>
                    '۰۱۲۳۴۵۶۷۸۹'[
                        Number(digit)
                    ]
            );


    editor.innerHTML =
        source.innerHTML;

    shell.hidden = false;
    fallback.hidden = true;
    fallback.required = false;

    /*
     * maxlength remains the progressive-enhancement
     * limit for plain textarea mode. Rich HTML itself
     * may exceed 8000 bytes while its text remains
     * within the 8000-character business limit.
     */
    fallback.removeAttribute(
        'maxlength'
    );

    format.value = 'html';


    let lastGoodHtml =
        editor.innerHTML;


    const textLength =
        () =>
            (
                editor.innerText
                || editor.textContent
                || ''
            ).length;


    const sync =
        () => {
            fallback.value =
                editor.innerHTML;

            const current =
                textLength();

            if (counter) {
                counter.textContent =
                    persianDigits(
                        current
                    )
                    + ' از ۸۰۰۰';

                counter.classList.toggle(
                    'is-limit',
                    current >= maxLength
                );
            }
        };


    const validate =
        () => {
            if (
                textLength()
                <= maxLength
            ) {
                lastGoodHtml =
                    editor.innerHTML;

                sync();

                return;
            }

            editor.innerHTML =
                lastGoodHtml;

            sync();
        };


    const currentBlock =
        () => {
            const selection =
                window.getSelection();

            let node =
                selection?.anchorNode
                || null;

            if (
                node?.nodeType
                === Node.TEXT_NODE
            ) {
                node =
                    node.parentElement;
            }

            if (
                !(node instanceof Element)
            ) {
                return null;
            }

            return node.closest(
                'p,h2,h3,blockquote,li'
            );
        };


    const runCommand =
        (name, value = null) => {
            editor.focus();

            document.execCommand(
                name,
                false,
                value
            );

            validate();
        };


    shell
        .querySelectorAll(
            '[data-rich-command]'
        )
        .forEach(
            (button) => {
                button.addEventListener(
                    'mousedown',
                    (event) =>
                        event.preventDefault()
                );

                button.addEventListener(
                    'click',
                    () =>
                        runCommand(
                            button.dataset
                                .richCommand
                        )
                );
            }
        );


    shell
        .querySelectorAll(
            '[data-rich-block]'
        )
        .forEach(
            (button) => {
                button.addEventListener(
                    'mousedown',
                    (event) =>
                        event.preventDefault()
                );

                button.addEventListener(
                    'click',
                    () =>
                        runCommand(
                            'formatBlock',
                            button.dataset
                                .richBlock
                        )
                );
            }
        );


    shell
        .querySelectorAll(
            '[data-rich-align]'
        )
        .forEach(
            (button) => {
                button.addEventListener(
                    'mousedown',
                    (event) =>
                        event.preventDefault()
                );

                button.addEventListener(
                    'click',
                    () => {
                        editor.focus();

                        let block =
                            currentBlock();

                        if (!block) {
                            runCommand(
                                'formatBlock',
                                'p'
                            );

                            block =
                                currentBlock();
                        }

                        if (block) {
                            block.setAttribute(
                                'data-align',
                                button.dataset
                                    .richAlign
                            );
                        }

                        validate();
                    }
                );
            }
        );


    shell
        .querySelectorAll(
            '[data-rich-indent]'
        )
        .forEach(
            (button) => {
                button.addEventListener(
                    'mousedown',
                    (event) =>
                        event.preventDefault()
                );

                button.addEventListener(
                    'click',
                    () => {
                        editor.focus();

                        let block =
                            currentBlock();

                        if (!block) {
                            runCommand(
                                'formatBlock',
                                'p'
                            );

                            block =
                                currentBlock();
                        }

                        if (!block) {
                            return;
                        }

                        let level =
                            Number(
                                block.getAttribute(
                                    'data-indent'
                                )
                                || 0
                            );

                        level +=
                            button.dataset
                                .richIndent
                                === 'in'
                                ? 1
                                : -1;

                        level =
                            Math.max(
                                0,
                                Math.min(
                                    4,
                                    level
                                )
                            );

                        if (level === 0) {
                            block.removeAttribute(
                                'data-indent'
                            );
                        } else {
                            block.setAttribute(
                                'data-indent',
                                String(level)
                            );
                        }

                        validate();
                    }
                );
            }
        );


    const linkButton =
        shell.querySelector(
            '[data-rich-link]'
        );

    linkButton?.addEventListener(
        'mousedown',
        (event) =>
            event.preventDefault()
    );

    linkButton?.addEventListener(
        'click',
        () => {
            const href =
                window.prompt(
                    'نشانی پیوند را وارد کنید:',
                    'https://'
                );

            if (!href) {
                return;
            }

            runCommand(
                'createLink',
                href.trim()
            );
        }
    );


    editor.addEventListener(
        'input',
        () => {
            editor.removeAttribute(
                'aria-invalid'
            );

            validate();
        }
    );

    editor.addEventListener(
        'blur',
        sync
    );


    editor
        .closest('form')
        ?.addEventListener(
            'submit',
            (event) => {
                validate();
                sync();

                if (
                    textLength() === 0
                ) {
                    event.preventDefault();

                    editor.setAttribute(
                        'aria-invalid',
                        'true'
                    );

                    editor.focus();

                    return;
                }

                editor.removeAttribute(
                    'aria-invalid'
                );

                format.value =
                    'html';
            }
        );


    sync();
})();
</script>
