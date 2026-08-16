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
