<style>
.communication-grid {
    display: grid;
    gap: .8rem;
    grid-template-columns:
        repeat(
            auto-fit,
            minmax(min(100%, 220px), 1fr)
        );
}

.communication-card,
.communication-panel {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 14px;
    padding: clamp(.8rem, 1.5vw, 1.1rem);
}

.communication-card {
    color: inherit;
    display: block;
    min-height: 108px;
    text-decoration: none;
    cursor: pointer;
    position: relative;
    z-index: 1;
}

.communication-recipient-picker { border: 1px solid var(--admin-border); border-radius: 10px; padding: .6rem; }
.communication-recipient-search { display: grid; gap: .4rem; grid-template-columns: minmax(0, 1fr) auto; }
.communication-recipient-results { display: grid; gap: .3rem; max-height: 180px; overflow: auto; margin-top: .45rem; }
.communication-recipient-results[hidden] { display: none; }
.communication-recipient-results label { align-items: center; border: 1px solid var(--admin-border); border-radius: 8px; cursor: pointer; display: flex; gap: .55rem; padding: .42rem .58rem; }
.communication-recipient-results label:hover { background: var(--admin-primary-soft); }
.communication-recipient-results input[type="checkbox"] { flex: 0 0 17px; height: 17px; margin: 0; min-height: 0; padding: 0; width: 17px; }
.communication-recipient-results small { display: block; color: var(--admin-text-muted); margin-top: .1rem; }

.communication-card:hover {
    border-color: var(--admin-primary);
    box-shadow:
        0 8px 22px rgba(15, 80, 43, .07);
}

.communication-card h3,
.communication-panel h2,
.communication-panel h3 {
    margin: 0 0 .35rem;
}

.communication-panel__head {
    align-items: flex-start;
    display: flex;
    gap: .8rem;
    justify-content: space-between;
    margin-bottom: .8rem;
}

.communication-card p,
.communication-muted {
    color: var(--admin-text-muted);
    font-size: .88rem;
    margin-block: .2rem;
}

.communication-badge {
    background: var(--admin-primary-soft);
    border-radius: 999px;
    display: inline-flex;
    font-size: .72rem;
    font-weight: 800;
    margin-top: .5rem;
    padding: .18rem .5rem;
}

.communication-form {
    display: grid;
    gap: .7rem .9rem;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    max-width: 760px;
    margin-inline: auto;
}

.communication-form label {
    display: grid;
    gap: .28rem;
    min-width: 0;
}

.communication-form label > span {
    color: var(--admin-text-muted);
    font-size: .82rem;
    font-weight: 650;
}

.communication-form label:has(> input[type="checkbox"]) {
    align-items: center;
    column-gap: .55rem;
    grid-template-columns: auto 18px auto;
    justify-content: start;
}

.communication-form label > input[type="checkbox"] {
    height: 18px;
    margin: 0;
    min-height: 0;
    padding: 0;
    width: 18px;
}

.communication-form input,
.communication-form select,
.communication-form textarea {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 9px;
    color: inherit;
    font: inherit;
    min-height: 42px;
    padding: .55rem .7rem;
    width: 100%;
}

.communication-form input:focus,
.communication-form select:focus,
.communication-form textarea:focus {
    border-color: var(--admin-primary);
    box-shadow:
        0 0 0 3px var(--admin-primary-soft);
    outline: 0;
}

.communication-form textarea {
    min-height: 116px;
    resize: vertical;
}

.communication-form__wide {
    grid-column: 1 / -1;
}

.communication-actions {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
}

.communication-actions .admin-button {
    min-height: 40px;
}

.communication-table-wrap {
    max-width: 100%;
    overflow-x: auto;
}

.communication-table {
    border-collapse: collapse;
    min-width: 680px;
    width: 100%;
}

.communication-table th,
.communication-table td {
    border-bottom: 1px solid var(--admin-border);
    padding: .6rem;
    text-align: right;
    vertical-align: top;
}

.communication-table th {
    color: var(--admin-text-muted);
    font-size: .78rem;
    white-space: nowrap;
}

.communication-clickable-row {
    cursor: pointer;
    position: relative;
}

.communication-clickable-row:hover {
    background: var(--admin-surface-muted);
}

.communication-row-link { position: relative; z-index: 1; }

.communication-row-unread {
    font-weight: 800;
}

.communication-thread {
    display: grid;
    gap: .6rem;
}

.communication-message {
    background: var(--admin-surface-muted);
    border-radius: 10px;
    max-width: min(88%, 760px);
    padding: .65rem .75rem;
}

.communication-message.is-mine {
    border-inline-start:
        3px solid var(--admin-primary);
    margin-inline-start: auto;
}

.communication-message header {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    justify-content: space-between;
}

.message-page-shell {
    min-width: 0;
    display: grid;
    gap: .75rem;
    margin-inline: auto;
    max-width: 1040px;
}

.message-page-shell--wide { max-width: 1280px; }
.message-compose-shell { max-width: 820px; }
.communication-compact-head { padding-block: .7rem; }
.communication-filters {
    align-items: end;
    display: flex;
    flex-wrap: nowrap;
    gap: .5rem;
    margin-bottom: .65rem;
}
.communication-filters > input[type="search"] { flex: 2 1 210px; }
.communication-filters > input[type="text"] { flex: 1 1 125px; }
.communication-filters > select { flex: 1 1 112px; }
.communication-filters > .admin-button { flex: 0 0 auto; white-space: nowrap; }
.communication-filters input,
.communication-filters select {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    color: inherit;
    font: inherit;
    min-height: 34px;
    min-width: 0;
    padding: .32rem .48rem;
}

.communication-settings-shell .communication-panel { padding: .8rem; }
.communication-settings-shell .communication-form { gap: .55rem .7rem; }
.communication-settings-shell .communication-form input,
.communication-settings-shell .communication-form select { min-height: 36px; padding-block: .35rem; }

@media (max-width: 1100px) {
    .communication-filters { flex-wrap: wrap; }
    .communication-report-tools { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

@media (max-width: 680px) {
    .communication-filters { display: grid; grid-template-columns: 1fr 1fr; }
    .communication-report-tools { grid-template-columns: 1fr 1fr; }
    .communication-recipient-search { grid-template-columns: 1fr; }
}
.communication-list-meta,
.communication-pagination { align-items: center; display: flex; gap: .65rem; }
.communication-report-tools { align-items: center; display: grid; gap: .45rem; grid-template-columns: minmax(180px, 2fr) minmax(120px, 1fr) minmax(150px, 1fr) auto auto; margin-bottom: .65rem; }
.communication-report-tools input,
.communication-report-tools select { border: 1px solid var(--admin-border); border-radius: 8px; font: inherit; min-height: 34px; min-width: 0; padding: .32rem .48rem; }
.communication-list-meta { color: var(--admin-text-muted); font-size: .8rem; justify-content: space-between; margin-bottom: .5rem; }
.communication-pagination a { color: var(--admin-primary); font-weight: 750; text-decoration: none; }
.monitor-reason { position: relative; }
.monitor-reason > summary { cursor: pointer; list-style: none; }
.monitor-reason > form {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 10px;
    box-shadow: 0 12px 28px rgba(0,0,0,.12);
    display: grid;
    gap: .55rem;
    inset-inline-end: 0;
    min-width: 300px;
    padding: .75rem;
    position: absolute;
    z-index: 5;
}
.monitor-reason label { display: grid; font-size: .8rem; gap: .3rem; }
.monitor-reason input { border: 1px solid var(--admin-border); border-radius: 8px; font: inherit; padding: .5rem; }
.communication-audit > summary { cursor: pointer; font-weight: 800; }

.message-thread-head {
    align-items: center;
    display: flex;
    gap: 1rem;
    justify-content: space-between;
}

.message-thread-head__main {
    min-width: 0;
    display: grid;
    gap: .55rem;
    min-width: 0;
}

.message-back-link {
    color: var(--admin-primary);
    font-size: .8rem;
    font-weight: 750;
    text-decoration: none;
}

.message-thread-title-row {
    align-items: center;
    display: flex;
    gap: .65rem;
}

.message-thread-title-row h2,
.message-thread-title-row p {
    margin: 0;
}

.message-thread-notice {
    color: var(--admin-primary);
    margin: 0;
}

.communication-status {
    border-radius: 999px;
    flex: 0 0 auto;
    font-size: .72rem;
    font-weight: 800;
    padding: .25rem .58rem;
}

.communication-status--active {
    background: var(--admin-primary-soft);
    color: var(--admin-primary);
}

.communication-status--closed {
    background: var(--admin-surface-muted);
    color: var(--admin-text-muted);
}

.message-thread-panel {
    background: color-mix(in srgb, var(--admin-surface-muted) 55%, transparent);
    min-height: 260px;
}

.communication-thread {
    gap: .85rem;
}

.communication-message {
    border: 1px solid var(--admin-border);
    border-radius: 14px 14px 4px 14px;
    box-shadow: 0 3px 10px rgba(15, 80, 43, .04);
    max-width: min(72%, 680px);
    padding: .7rem .85rem;
}

.communication-message.is-mine {
    background: var(--admin-primary-soft);
    border-color: color-mix(in srgb, var(--admin-primary) 30%, var(--admin-border));
    margin-inline-start: auto;
}

.communication-message.is-other {
    background: var(--admin-surface);
    border-radius: 14px 14px 14px 4px;
    margin-inline-end: auto;
}

.communication-message header {
    color: var(--admin-text-muted);
    font-size: .74rem;
}

.communication-message p {
    line-height: 1.9;
    margin: .45rem 0 0;
}

.message-reply-panel {
    padding: .8rem;
}

.message-reply-form {
    display: grid;
    gap: .45rem;
}

.message-reply-form label {
    font-size: .8rem;
    font-weight: 750;
}

.message-reply-form textarea {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 10px;
    color: inherit;
    font: inherit;
    min-height: 88px;
    padding: .65rem .75rem;
    resize: vertical;
    width: 100%;
}

.message-reply-form textarea:focus {
    border-color: var(--admin-primary);
    box-shadow: 0 0 0 3px var(--admin-primary-soft);
    outline: 0;
}

.message-reply-actions {
    display: flex;
    justify-content: flex-start;
}

.message-reply-actions .admin-button {
    min-width: 132px;
}

.message-closed-note {
    color: var(--admin-text-muted);
    font-size: .86rem;
    text-align: center;
}

.communication-tabs {
    display: flex;
    gap: .4rem;
    margin-bottom: .8rem;
    max-width: 100%;
    overflow-x: auto;
    padding-bottom: .2rem;
    scrollbar-width: thin;
    white-space: nowrap;
}

.communication-tabs a {
    border: 1px solid var(--admin-border);
    border-radius: 999px;
    color: inherit;
    flex: 0 0 auto;
    padding: .38rem .68rem;
    text-decoration: none;
}

.communication-tabs a.is-active {
    background: var(--admin-primary-soft);
    border-color: var(--admin-primary);
}

@media (max-width: 760px) {
    .communication-grid,
    .communication-form {
        grid-template-columns: 1fr;
    }

    .communication-form__wide {
        grid-column: 1;
    }

    .communication-card {
        min-height: auto;
    }

    .communication-message {
        max-width: 96%;
    }

    .message-thread-head {
        align-items: stretch;
        flex-direction: column;
    }

    .message-thread-title-row {
        align-items: flex-start;
        justify-content: space-between;
    }

    .message-thread-head form .admin-button,
    .message-reply-actions .admin-button {
        width: 100%;
    }

    .communication-actions {
        align-items: stretch;
    }

    .communication-actions .admin-button {
        flex: 1 1 auto;
        justify-content: center;
    }

    .communication-filters { grid-template-columns: 1fr 1fr; }
    .communication-filters input[type="search"] { grid-column: 1 / -1; }
    .monitor-reason > form { inset-inline: auto 0; min-width: min(82vw, 300px); }
}

@media (min-width: 761px) and (max-width: 1280px) {
    .communication-filters { display: grid; grid-template-columns: repeat(4, minmax(120px, 1fr)); }
    .communication-filters input[type="search"] { grid-column: span 2; }
}

@media (max-width: 1100px) {
    .communication-report-tools { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

@media (max-width: 680px) {
    .communication-report-tools { grid-template-columns: 1fr 1fr; }
}

/* notification-provider-test-send-v061 */
body.provider-test-dialog-open {
    overflow: hidden;
}

.provider-test-dialog {
    inset: 0;
    position: fixed;
    z-index: 1200;
}

.provider-test-dialog[hidden] {
    display: none;
}

.provider-test-dialog__backdrop {
    background: rgba(15, 23, 42, .42);
    border: 0;
    inset: 0;
    padding: 0;
    position: absolute;
    width: 100%;
}

.provider-test-dialog__panel {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 16px;
    box-shadow: 0 24px 70px rgba(15, 23, 42, .22);
    left: 50%;
    max-height: calc(100vh - 2rem);
    max-width: min(92vw, 620px);
    overflow: auto;
    padding: 1rem;
    position: absolute;
    top: 50%;
    transform: translate(-50%, -50%);
    width: 100%;
}

.provider-test-dialog__head {
    align-items: flex-start;
    display: flex;
    gap: .75rem;
    justify-content: space-between;
    margin-bottom: .8rem;
}

.provider-test-dialog__head h3 {
    margin: 0;
}

.provider-test-dialog__close {
    align-items: center;
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 9px;
    color: inherit;
    cursor: pointer;
    display: inline-flex;
    flex: 0 0 36px;
    font: inherit;
    font-size: 1.35rem;
    height: 36px;
    justify-content: center;
    line-height: 1;
    padding: 0;
}

.provider-test-form {
    display: grid;
    gap: .7rem;
}

.provider-test-form label {
    display: grid;
    gap: .3rem;
}

.provider-test-form label > span {
    color: var(--admin-text-muted);
    font-size: .82rem;
    font-weight: 700;
}

.provider-test-form input,
.provider-test-form textarea {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 9px;
    color: inherit;
    font: inherit;
    min-height: 40px;
    padding: .55rem .7rem;
    width: 100%;
}

.provider-test-form textarea {
    min-height: 130px;
    resize: vertical;
}

.provider-test-form input:focus,
.provider-test-form textarea:focus {
    border-color: var(--admin-primary);
    box-shadow: 0 0 0 3px var(--admin-primary-soft);
    outline: 0;
}

.provider-test-form__actions {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    justify-content: flex-start;
}

@media (max-width: 640px) {
    .provider-test-dialog__panel {
        max-height: calc(100vh - 1rem);
        max-width: calc(100vw - 1rem);
        padding: .8rem;
    }
}

/* notification-provider-default-management-v061 */
.provider-default-form {
    display: grid;
    gap: .85rem;
}
.provider-default-intro {
    align-items: center;
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 14px;
    display: flex;
    gap: 1rem;
    justify-content: space-between;
    padding: .85rem 1rem;
}
.provider-default-intro h3 { margin: 0 0 .25rem; }
.provider-default-scope {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    display: grid;
    gap: .1rem;
    min-width: 130px;
    padding: .55rem .75rem;
    text-align: center;
}
.provider-default-scope span,
.provider-default-scope small {
    color: var(--admin-text-muted);
    font-size: .72rem;
}
.provider-default-grid {
    display: grid;
    gap: .8rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
.provider-default-card {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 14px;
    display: grid;
    gap: .7rem;
    min-width: 0;
    padding: .85rem;
}
.provider-default-card__head {
    align-items: flex-start;
    display: flex;
    gap: .6rem;
    justify-content: space-between;
}
.provider-default-card__head h3 { margin: 0; }
.provider-default-card__head > span {
    background: var(--admin-primary-soft);
    border-radius: 999px;
    direction: ltr;
    font-size: .7rem;
    font-weight: 800;
    padding: .18rem .48rem;
}
.provider-default-card label {
    display: grid;
    gap: .28rem;
}
.provider-default-card label > span {
    color: var(--admin-text-muted);
    font-size: .8rem;
    font-weight: 700;
}
.provider-default-card select {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 9px;
    color: inherit;
    font: inherit;
    min-height: 40px;
    min-width: 0;
    padding: .45rem .6rem;
    width: 100%;
}
.provider-default-card select:focus {
    border-color: var(--admin-primary);
    box-shadow: 0 0 0 3px var(--admin-primary-soft);
    outline: 0;
}
.provider-default-empty {
    background: var(--admin-surface-muted);
    border-radius: 10px;
    color: var(--admin-text-muted);
    font-size: .82rem;
    padding: .7rem;
}
.provider-default-preview {
    border-top: 1px dashed var(--admin-border);
    display: grid;
    gap: .35rem;
    padding-top: .65rem;
}
.provider-default-preview > span {
    color: var(--admin-text-muted);
    font-size: .75rem;
    font-weight: 700;
}
.provider-default-preview > strong {
    color: var(--admin-text-muted);
    font-size: .8rem;
}
.provider-default-preview ol {
    display: grid;
    gap: .3rem;
    margin: 0;
    padding-inline-start: 1.3rem;
}
.provider-default-preview li {
    font-size: .8rem;
}
.provider-default-preview li strong,
.provider-default-preview li small {
    display: block;
}
.provider-default-preview li small {
    color: var(--admin-text-muted);
}
.provider-default-actions {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: .65rem;
}
@media (max-width: 1100px) {
    .provider-default-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 720px) {
    .provider-default-intro {
        align-items: stretch;
        flex-direction: column;
    }
    .provider-default-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>

<style>
.communication-settings-shell {
    display: grid;
    gap: .8rem;
}

.communication-preference-intro {
    align-items: center;
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    display: flex;
    gap: .8rem;
    justify-content: space-between;
    margin-bottom: .8rem;
    padding: .75rem .85rem;
}

.communication-preference-intro p {
    color: var(--admin-text-muted);
    font-size: .82rem;
    margin: .2rem 0 0;
}

.communication-preference-form {
    display: grid;
    gap: .8rem;
}

.communication-preference-grid {
    display: grid;
    gap: .65rem;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
}

.communication-preference-card {
    align-items: center;
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    cursor: pointer;
    display: flex;
    gap: .75rem;
    justify-content: space-between;
    min-height: 86px;
    padding: .7rem .8rem;
    transition:
        border-color .16s ease,
        box-shadow .16s ease,
        transform .16s ease;
}

.communication-preference-card:hover {
    border-color: var(--admin-primary);
    box-shadow: 0 7px 18px rgba(15, 80, 43, .07);
    transform: translateY(-1px);
}

.communication-preference-card__main {
    display: grid;
    gap: .2rem;
    min-width: 0;
}

.communication-preference-card__main strong {
    font-size: .9rem;
}

.communication-preference-card__main small {
    color: var(--admin-text-muted);
    font-size: .75rem;
    line-height: 1.75;
}

.communication-preference-card__meta {
    color: var(--admin-text-muted);
    font-size: .68rem;
}

.communication-switch {
    flex: 0 0 auto;
    position: relative;
}

.communication-switch input {
    height: 1px;
    opacity: 0;
    position: absolute;
    width: 1px;
}

.communication-switch > span {
    background: var(--admin-border);
    border-radius: 999px;
    display: block;
    height: 26px;
    position: relative;
    transition: background .16s ease;
    width: 46px;
}

.communication-switch > span::after {
    background: #fff;
    border-radius: 50%;
    box-shadow: 0 1px 4px rgba(0, 0, 0, .22);
    content: '';
    height: 20px;
    position: absolute;
    right: 3px;
    top: 3px;
    transition: transform .16s ease;
    width: 20px;
}

.communication-switch input:checked + span {
    background: var(--admin-primary);
}

.communication-switch input:checked + span::after {
    transform: translateX(-20px);
}

.communication-switch input:focus-visible + span {
    box-shadow: 0 0 0 3px var(--admin-primary-soft);
}

@media (max-width: 760px) {
    .communication-preference-grid {
        grid-template-columns: 1fr;
    }

    .communication-preference-intro {
        align-items: flex-start;
        flex-direction: column;
    }
}


/* communication-recipient-single-line-fix */
.communication-form .communication-recipient-results label {
    align-items: center;
    display: flex;
    flex-wrap: nowrap;
}

.communication-form .communication-recipient-results label > span {
    flex: 1 1 auto;
    min-width: 0;
    white-space: nowrap;
}

.communication-form .communication-recipient-results label > span strong {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>

<style>
.provider-workspace {
    display: grid;
    gap: .75rem;
}

.provider-workspace-tabs,
.provider-editor-tabs {
    align-items: center;
    display: flex;
    gap: .4rem;
    max-width: 100%;
    overflow-x: auto;
    padding: .15rem;
    scrollbar-width: thin;
    white-space: nowrap;
}

.provider-workspace-tabs {
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 12px;
}

.provider-workspace-tab,
.provider-editor-tab {
    background: transparent;
    border: 1px solid transparent;
    border-radius: 9px;
    color: inherit;
    cursor: pointer;
    flex: 0 0 auto;
    font: inherit;
    font-size: .82rem;
    font-weight: 750;
    min-height: 38px;
    padding: .42rem .72rem;
}

.provider-workspace-tab {
    align-items: center;
    display: inline-flex;
    gap: .4rem;
}

.provider-workspace-tab > span {
    align-items: center;
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 999px;
    display: inline-flex;
    font-size: .68rem;
    justify-content: center;
    min-width: 24px;
    padding: .08rem .34rem;
}

.provider-workspace-tab.is-active,
.provider-editor-tab.is-active {
    background: var(--admin-surface);
    border-color: var(--admin-primary);
    box-shadow: 0 4px 12px rgba(15, 80, 43, .07);
    color: var(--admin-primary);
}

.provider-editor-tab:disabled {
    cursor: not-allowed;
    opacity: .48;
}

.provider-workspace-panel[hidden],
.provider-editor-panel[hidden],
.provider-dynamic-fields[hidden],
.provider-tab-empty[hidden] {
    display: none !important;
}

.provider-management-card {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    padding: .8rem;
}

.provider-management-card__head {
    align-items: flex-start;
    display: flex;
    gap: .75rem;
    justify-content: space-between;
    margin-bottom: .75rem;
}

.provider-management-card__head h3 {
    margin: 0 0 .2rem;
}

.provider-editor-card {
    overflow: clip;
    padding-bottom: 0;
}

.provider-management-form {
    display: grid;
    gap: .7rem;
    margin-inline: 0;
    max-width: none;
}

.provider-editor-tabs {
    border-bottom: 1px solid var(--admin-border);
    padding: 0 0 .55rem;
}

.provider-editor-panels {
    min-height: 250px;
}

.provider-editor-panel {
    animation: provider-panel-in .14s ease-out;
}

@keyframes provider-panel-in {
    from {
        opacity: .45;
        transform: translateY(3px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.provider-form-grid,
.provider-dynamic-grid {
    display: grid;
    gap: .65rem .8rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.provider-form-grid > label,
.provider-dynamic-grid > label {
    display: grid;
    gap: .28rem;
    min-width: 0;
}

.provider-form-grid > label > span,
.provider-dynamic-grid > label > span {
    color: var(--admin-text-muted);
    font-size: .82rem;
    font-weight: 650;
}

.provider-form-grid__wide {
    grid-column: 1 / -1;
}

.provider-management-form input,
.provider-management-form select,
.provider-management-form textarea {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 9px;
    color: inherit;
    font: inherit;
    min-height: 40px;
    padding: .48rem .64rem;
    width: 100%;
}

.provider-management-form textarea {
    min-height: 88px;
    resize: vertical;
}

.provider-management-form input:focus,
.provider-management-form select:focus,
.provider-management-form textarea:focus {
    border-color: var(--admin-primary);
    box-shadow: 0 0 0 3px var(--admin-primary-soft);
    outline: 0;
}

.provider-management-form input[type="number"]::-webkit-inner-spin-button,
.provider-management-form input[type="number"]::-webkit-outer-spin-button {
    appearance: none;
    margin: 0;
}

.provider-management-form input[type="number"] {
    appearance: textfield;
}

.provider-management-form fieldset {
    border: 0;
    margin: 0;
    min-width: 0;
    padding: 0;
}

.provider-management-form legend {
    color: inherit;
    font-size: .9rem;
    font-weight: 800;
    margin-bottom: .65rem;
    padding: 0;
}

.provider-status-field {
    align-items: center;
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 10px;
    display: grid !important;
    gap: .55rem;
    grid-template-columns: minmax(0, 1fr) auto auto;
    padding: .6rem .7rem;
}

.provider-status-field__text {
    display: grid;
    gap: .12rem;
}

.provider-status-field__text strong {
    color: inherit;
    font-size: .84rem;
}

.provider-status-field__text small,
.provider-form-grid label > small {
    color: var(--admin-text-muted);
    font-size: .7rem;
    line-height: 1.7;
}

.provider-tab-empty,
.provider-empty-state {
    background: var(--admin-surface-muted);
    border: 1px dashed var(--admin-border);
    border-radius: 10px;
    color: var(--admin-text-muted);
    padding: .85rem;
    text-align: center;
}

.provider-empty-state {
    display: grid;
    gap: .4rem;
    justify-items: center;
    min-height: 150px;
    place-content: center;
}

.provider-empty-state strong {
    color: inherit;
}

.provider-empty-state p {
    font-size: .8rem;
    margin: 0;
}

.provider-secret-input {
    align-items: stretch;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
}

.provider-secret-input input {
    border-end-end-radius: 0;
    border-start-end-radius: 0;
}

.provider-secret-toggle {
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-inline-start: 0;
    border-radius: 9px 0 0 9px;
    color: var(--admin-primary);
    cursor: pointer;
    font: inherit;
    font-size: .74rem;
    font-weight: 750;
    min-width: 64px;
    padding-inline: .55rem;
}

.provider-secret-state {
    color: var(--admin-primary);
    font-size: .72rem;
}

.provider-form-actions {
    align-items: center;
    background: color-mix(in srgb, var(--admin-surface) 94%, transparent);
    border-top: 1px solid var(--admin-border);
    bottom: 0;
    display: flex;
    flex-wrap: wrap;
    gap: .45rem;
    margin-inline: -.8rem;
    padding: .65rem .8rem;
    position: sticky;
    z-index: 3;
}

.provider-code {
    color: var(--admin-text-muted);
    display: block;
    font-size: .7rem;
    margin-top: .18rem;
}

.provider-row-actions {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
}

.provider-row-actions form {
    margin: 0;
}

.provider-accounts-table {
    min-width: 820px;
}

@media (max-width: 760px) {
    .provider-management-card__head {
        align-items: stretch;
        flex-direction: column;
    }

    .provider-form-grid,
    .provider-dynamic-grid {
        grid-template-columns: 1fr;
    }

    .provider-form-grid__wide {
        grid-column: 1;
    }

    .provider-status-field {
        grid-template-columns: minmax(0, 1fr) auto auto;
    }

    .provider-row-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .provider-row-actions .admin-button {
        justify-content: center;
        width: 100%;
    }

    .provider-form-actions {
        align-items: stretch;
    }

    .provider-form-actions .admin-button {
        flex: 1 1 100%;
        justify-content: center;
    }
}
</style>

<style>
/* notification-provider-minimal-ui-v061 */
.provider-workspace {
    gap: .55rem;
}

.provider-workspace-tabs {
    align-items: center;
    border-radius: 10px;
    gap: .2rem;
    min-height: 40px;
    padding: .2rem;
}

.provider-workspace-tab {
    border-radius: 8px;
    font-size: .78rem;
    min-height: 32px;
    padding: .28rem .62rem;
}

.provider-workspace-tab > span {
    font-size: .64rem;
    min-height: 19px;
    min-width: 19px;
    padding: 0 .28rem;
}

.provider-workspace-tab.is-active {
    box-shadow: none;
}

.provider-management-card {
    border-radius: 10px;
    padding: .68rem .75rem;
}

.provider-editor-card {
    padding-bottom: 0;
}

.provider-management-card__head {
    align-items: center;
    gap: .65rem;
    margin-bottom: .55rem;
}

.provider-management-card__head h3 {
    font-size: .94rem;
    margin-bottom: .12rem;
}

.provider-management-card__head .communication-muted {
    font-size: .76rem;
    line-height: 1.65;
    margin: 0;
}

.provider-management-card .admin-button {
    font-size: .78rem;
    min-height: 34px;
    padding: .34rem .7rem;
}

.provider-management-form {
    gap: 0;
}

.provider-editor-tabs {
    background: transparent;
    border: 0;
    border-bottom: 1px solid var(--admin-border);
    border-radius: 0;
    gap: .15rem;
    padding: 0;
}

.provider-editor-tab {
    border: 0;
    border-bottom: 2px solid transparent;
    border-radius: 0;
    font-size: .76rem;
    min-height: 34px;
    padding: .3rem .58rem;
}

.provider-editor-tab.is-active {
    background: transparent;
    border-bottom-color: var(--admin-primary);
    box-shadow: none;
}

.provider-editor-tab:focus-visible {
    background: var(--admin-primary-soft);
    outline: 0;
}

.provider-editor-panels {
    min-height: 0;
}

.provider-editor-panel {
    padding: .65rem 0 .55rem;
}

.provider-management-form fieldset {
    display: grid;
    gap: 0;
}

.provider-management-form legend {
    font-size: .84rem;
    margin: 0 0 .5rem;
}

.provider-form-grid,
.provider-dynamic-grid {
    gap: .48rem .72rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.provider-form-grid > label,
.provider-dynamic-grid > label {
    align-items: center;
    column-gap: .5rem;
    display: grid;
    grid-template-columns:
        minmax(105px, 132px)
        minmax(0, 1fr);
    min-width: 0;
    row-gap: .14rem;
}

.provider-form-grid > label > span:first-child,
.provider-dynamic-grid > label > span:first-child {
    align-self: center;
    color: var(--admin-text-muted);
    font-size: .76rem;
    font-weight: 700;
    grid-column: 1;
    grid-row: 1;
    line-height: 1.45;
    margin: 0;
}

.provider-form-grid > label > input,
.provider-form-grid > label > select,
.provider-form-grid > label > textarea,
.provider-form-grid > label > .provider-secret-input,
.provider-dynamic-grid > label > input,
.provider-dynamic-grid > label > select,
.provider-dynamic-grid > label > textarea,
.provider-dynamic-grid > label > .provider-secret-input {
    grid-column: 2;
    min-width: 0;
}

.provider-form-grid > label > small,
.provider-dynamic-grid > label > small {
    grid-column: 2;
    margin: 0;
}

.provider-form-grid > label:has(textarea),
.provider-dynamic-grid > label:has(textarea) {
    align-items: start;
}

.provider-form-grid > label:has(textarea) > span:first-child,
.provider-dynamic-grid > label:has(textarea) > span:first-child {
    padding-top: .45rem;
}

.provider-form-grid__wide {
    grid-column: 1 / -1;
}

.provider-management-form input,
.provider-management-form select,
.provider-management-form textarea {
    border-radius: 8px;
    font-size: .8rem;
    min-height: 36px;
    padding: .38rem .55rem;
}

.provider-management-form textarea {
    min-height: 64px;
}

.provider-management-form input:focus,
.provider-management-form select:focus,
.provider-management-form textarea:focus {
    box-shadow: 0 0 0 2px var(--admin-primary-soft);
}

.provider-form-grid label > small,
.provider-dynamic-grid label > small,
.provider-status-field__text small {
    font-size: .66rem;
    line-height: 1.55;
}

.provider-status-field {
    align-items: center;
    border-radius: 8px;
    column-gap: .55rem;
    display: grid !important;
    grid-template-columns: minmax(0, 1fr) auto auto !important;
    min-height: 50px;
    padding: .42rem .55rem;
}

.provider-status-field > .provider-status-field__text {
    grid-column: 1 !important;
    grid-row: 1 !important;
}

.provider-status-field__text {
    gap: .05rem;
}

.provider-status-field__text strong {
    font-size: .78rem;
}

.provider-status-field > .communication-switch {
    grid-column: 2;
}

.provider-status-field > strong:last-child {
    font-size: .74rem;
    grid-column: 3;
    min-width: 48px;
}

.provider-secret-input {
    grid-template-columns: minmax(0, 1fr) auto;
}

.provider-secret-toggle {
    border-radius: 8px 0 0 8px;
    font-size: .7rem;
    min-width: 58px;
    padding-inline: .48rem;
}

.provider-tab-empty,
.provider-empty-state {
    border-radius: 8px;
    padding: .68rem;
}

.provider-empty-state {
    gap: .3rem;
    min-height: 120px;
}

.provider-empty-state p {
    font-size: .74rem;
}

.provider-form-actions {
    background: transparent;
    bottom: auto;
    gap: .4rem;
    justify-content: flex-start;
    margin: .1rem -.75rem 0;
    padding: .55rem .75rem;
    position: static;
}

.provider-form-actions .admin-button {
    min-height: 35px;
}

.provider-row-actions {
    flex-wrap: nowrap;
    gap: .28rem;
}

.provider-row-actions .admin-button {
    white-space: nowrap;
}

.provider-accounts-table th,
.provider-accounts-table td {
    padding-block: .5rem;
}

.provider-code {
    margin-top: .08rem;
}

@media (max-width: 1100px) {
    .provider-form-grid,
    .provider-dynamic-grid {
        grid-template-columns: 1fr;
    }

    .provider-form-grid__wide {
        grid-column: 1;
    }
}

@media (max-width: 680px) {
    .provider-management-card {
        padding: .62rem;
    }

    .provider-management-card__head {
        align-items: stretch;
    }

    .provider-form-grid > label,
    .provider-dynamic-grid > label {
        grid-template-columns: 1fr;
        row-gap: .24rem;
    }

    .provider-form-grid > label > span:first-child,
    .provider-dynamic-grid > label > span:first-child,
    .provider-form-grid > label > input,
    .provider-form-grid > label > select,
    .provider-form-grid > label > textarea,
    .provider-form-grid > label > .provider-secret-input,
    .provider-form-grid > label > small,
    .provider-dynamic-grid > label > input,
    .provider-dynamic-grid > label > select,
    .provider-dynamic-grid > label > textarea,
    .provider-dynamic-grid > label > .provider-secret-input,
    .provider-dynamic-grid > label > small {
        grid-column: 1;
    }

    .provider-form-grid > label:has(textarea) > span:first-child,
    .provider-dynamic-grid > label:has(textarea) > span:first-child {
        padding-top: 0;
    }

    .provider-status-field {
        grid-template-columns: minmax(0, 1fr) auto auto !important;
    }

    .provider-form-actions {
        margin-inline: -.62rem;
        padding-inline: .62rem;
    }

    .provider-form-actions .admin-button {
        flex: 1 1 auto;
        width: auto;
    }
}
</style>

<!-- notification-provider-compact-alignment-v061:start -->
<style>
/*
 * Final compact alignment for notification-provider forms.
 * Keeps both desktop columns on the same baseline and places every
 * field label directly beside its own control.
 */
.provider-editor-panels {
    min-height: 0;
}

.provider-editor-panel {
    padding-top: .05rem;
}

.provider-form-grid,
.provider-dynamic-grid {
    align-items: start;
    gap: .5rem .85rem;
}

.provider-form-grid > label:not(.provider-status-field),
.provider-dynamic-grid > label {
    align-items: start;
    display: grid;
    gap: .18rem .48rem;
    grid-template-columns:
        minmax(120px, 145px)
        minmax(0, 1fr);
    margin: 0;
    min-width: 0;
}

.provider-form-grid > label:not(.provider-status-field) > span,
.provider-dynamic-grid > label > span {
    align-self: center;
    grid-column: 1;
    grid-row: 1;
    line-height: 1.55;
    margin: 0;
    min-width: 0;
    text-align: right;
}

.provider-form-grid > label:not(.provider-status-field) > input,
.provider-form-grid > label:not(.provider-status-field) > select,
.provider-form-grid > label:not(.provider-status-field) > textarea,
.provider-form-grid > label:not(.provider-status-field) > .provider-secret-input,
.provider-dynamic-grid > label > input,
.provider-dynamic-grid > label > select,
.provider-dynamic-grid > label > textarea,
.provider-dynamic-grid > label > .provider-secret-input {
    grid-column: 2;
    grid-row: 1;
    min-width: 0;
}

.provider-form-grid > label:not(.provider-status-field) > small,
.provider-dynamic-grid > label > small {
    grid-column: 2;
    line-height: 1.55;
    margin: 0;
    padding-top: .02rem;
}

.provider-form-grid > label:not(.provider-status-field):has(> textarea) > span,
.provider-dynamic-grid > label:has(> textarea) > span {
    align-self: start;
    padding-top: .48rem;
}

.provider-management-form input,
.provider-management-form select,
.provider-management-form textarea {
    min-height: 38px;
    padding-block: .42rem;
}

.provider-management-form textarea {
    min-height: 72px;
}

.provider-management-form legend {
    margin-bottom: .52rem;
}

.provider-editor-tabs {
    gap: .16rem;
    padding-bottom: .42rem;
}

.provider-editor-tab {
    min-height: 34px;
    padding: .32rem .58rem;
}

.provider-status-field {
    gap: .5rem;
    padding: .52rem .65rem;
}

.provider-secret-input {
    align-items: center;
    display: grid;
    gap: .45rem;
    grid-template-columns: minmax(0, 1fr) auto;
    min-width: 0;
}

.provider-secret-input input {
    border-radius: 9px;
    min-width: 0;
}

.provider-secret-toggle {
    align-items: center;
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 9px;
    display: inline-flex;
    font: inherit;
    font-size: .74rem;
    font-weight: 750;
    justify-content: center;
    min-height: 38px;
    min-width: 66px;
    padding: .4rem .62rem;
}

.provider-form-actions {
    gap: .5rem;
    padding-block: .58rem;
}

@media (max-width: 900px) {
    .provider-form-grid,
    .provider-dynamic-grid {
        grid-template-columns: 1fr;
    }

    .provider-form-grid > label:not(.provider-status-field),
    .provider-dynamic-grid > label {
        gap: .22rem;
        grid-template-columns: 1fr;
    }

    .provider-form-grid > label:not(.provider-status-field) > span,
    .provider-form-grid > label:not(.provider-status-field) > input,
    .provider-form-grid > label:not(.provider-status-field) > select,
    .provider-form-grid > label:not(.provider-status-field) > textarea,
    .provider-form-grid > label:not(.provider-status-field) > small,
    .provider-form-grid > label:not(.provider-status-field) > .provider-secret-input,
    .provider-dynamic-grid > label > span,
    .provider-dynamic-grid > label > input,
    .provider-dynamic-grid > label > select,
    .provider-dynamic-grid > label > textarea,
    .provider-dynamic-grid > label > small,
    .provider-dynamic-grid > label > .provider-secret-input {
        grid-column: 1;
        grid-row: auto;
    }

    .provider-form-grid > label:not(.provider-status-field):has(> textarea) > span,
    .provider-dynamic-grid > label:has(> textarea) > span {
        padding-top: 0;
    }
}
</style>
<!-- notification-provider-compact-alignment-v061:end -->

<style>
/* notification-delivery-report-style-v061 */
.notification-delivery-report {
    display: grid;
    gap: 1rem;
}

.notification-report-intro {
    align-items: center;
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 14px;
    display: flex;
    gap: 1rem;
    justify-content: space-between;
    padding: 1rem;
}

.notification-report-intro h3,
.notification-report-attempts h4 {
    margin: 0;
}

.notification-report-intro__count {
    background: var(--admin-primary-soft);
    border-radius: 999px;
    color: var(--admin-primary-hover);
    font-weight: 700;
    padding: .45rem .8rem;
    white-space: nowrap;
}

.notification-report-summary {
    display: grid;
    gap: .7rem;
    grid-template-columns: repeat(5, minmax(0, 1fr));
}

.notification-report-summary__card {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    display: grid;
    gap: .35rem;
    padding: .85rem;
}

.notification-report-summary__card span {
    color: var(--admin-text-muted);
    font-size: .82rem;
}

.notification-report-summary__card strong {
    font-size: 1.3rem;
}

.notification-report-filters {
    align-items: end;
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 14px;
    display: grid;
    gap: .7rem;
    grid-template-columns: 2fr repeat(7, minmax(120px, 1fr));
    padding: .85rem;
}

.notification-report-filters label {
    display: grid;
    gap: .35rem;
    min-width: 0;
}

.notification-report-filters label > span {
    color: var(--admin-text-muted);
    font-size: .78rem;
    font-weight: 700;
}

.notification-report-filters__actions {
    display: flex;
    gap: .45rem;
    grid-column: 1 / -1;
    justify-content: flex-end;
}

.notification-report-table {
    min-width: 1120px;
}

.notification-report-table td {
    vertical-align: top;
}

.notification-report-table td strong,
.notification-report-table td small {
    display: block;
}

.notification-report-table td small {
    color: var(--admin-text-muted);
    margin-top: .2rem;
}

.notification-report-reference,
.notification-report-destination {
    direction: ltr;
    unicode-bidi: isolate;
}

.notification-report-status {
    border-radius: 999px;
    display: inline-flex;
    font-size: .78rem;
    font-weight: 700;
    padding: .28rem .55rem;
}

.notification-report-status--sent,
.notification-report-status--delivered {
    background: #e8f7ef;
    color: #17643c;
}

.notification-report-status--failed,
.notification-report-status--cancelled {
    background: #fff1f1;
    color: #a33a3a;
}

.notification-report-status--pending,
.notification-report-status--queued,
.notification-report-status--processing {
    background: #fff8e7;
    color: #8a5a00;
}

.notification-report-fallback {
    color: #8a5a00 !important;
    font-weight: 700;
}

.notification-report-detail-row > td {
    background: color-mix(
        in srgb,
        var(--admin-primary-soft) 28%,
        var(--admin-surface)
    );
    padding: 1rem !important;
}

.notification-report-detail {
    display: grid;
    gap: 1rem;
}

.notification-report-detail__grid {
    display: grid;
    gap: .65rem;
    grid-template-columns: repeat(5, minmax(0, 1fr));
}

.notification-report-detail__grid > div,
.notification-report-message,
.notification-report-error {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 10px;
    display: grid;
    gap: .3rem;
    min-width: 0;
    padding: .7rem;
}

.notification-report-detail__grid span,
.notification-report-message > span,
.notification-report-error > span {
    color: var(--admin-text-muted);
    font-size: .76rem;
}

.notification-report-detail__grid strong {
    overflow-wrap: anywhere;
}

.notification-report-message p {
    line-height: 1.9;
    margin: 0;
    white-space: normal;
}

.notification-report-error {
    border-color: #ffd0d0;
}

.notification-report-error code {
    color: #a33a3a;
    direction: ltr;
    overflow-wrap: anywhere;
    unicode-bidi: isolate;
}

.notification-report-attempts {
    display: grid;
    gap: .7rem;
}

.notification-report-attempts > header {
    align-items: center;
    display: flex;
    justify-content: space-between;
}

.notification-report-attempts > header span {
    color: var(--admin-text-muted);
    font-size: .82rem;
}

.notification-report-attempt-table {
    min-width: 1080px;
}

.notification-report-metadata {
    display: grid;
    gap: .25rem;
    margin: 0;
}

.notification-report-metadata > div {
    display: grid;
    gap: .15rem;
    grid-template-columns: minmax(90px, auto) 1fr;
}

.notification-report-metadata dt {
    color: var(--admin-text-muted);
    font-size: .72rem;
}

.notification-report-metadata dd {
    margin: 0;
    overflow-wrap: anywhere;
}

.notification-report-pagination {
    align-items: center;
    display: flex;
    gap: .75rem;
    justify-content: center;
}

.notification-report-pagination a {
    background: var(--admin-primary-soft);
    border-radius: 9px;
    color: var(--admin-primary-hover);
    font-weight: 700;
    padding: .5rem .85rem;
}

.notification-report-pagination a.is-disabled {
    opacity: .45;
    pointer-events: none;
}

@media (max-width: 1280px) {
    .notification-report-summary {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .notification-report-filters {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .notification-report-filters__search {
        grid-column: span 2;
    }

    .notification-report-detail__grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 760px) {
    .notification-report-intro {
        align-items: flex-start;
        flex-direction: column;
    }

    .notification-report-summary,
    .notification-report-filters,
    .notification-report-detail__grid {
        grid-template-columns: 1fr;
    }

    .notification-report-filters__search {
        grid-column: auto;
    }

    .notification-report-filters__actions {
        justify-content: stretch;
    }

    .notification-report-filters__actions > * {
        flex: 1 1 0;
    }

    .notification-report-pagination {
        justify-content: space-between;
    }
}
</style>

<style>
/* notification-send-center-style-v061 */
.notification-send-center,
.notification-send-form {
    display: grid;
    gap: 1rem;
}

.notification-send-intro {
    align-items: center;
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 14px;
    display: flex;
    gap: 1rem;
    justify-content: space-between;
    padding: 1rem;
}

.notification-send-intro h3,
.notification-send-result h3 {
    margin: 0;
}

.notification-send-limit {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    display: grid;
    gap: .2rem;
    min-width: 180px;
    padding: .7rem .85rem;
}

.notification-send-limit span,
.notification-send-limit small {
    color: var(--admin-text-muted);
    font-size: .76rem;
}

.notification-send-result {
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 14px;
    display: grid;
    gap: .8rem;
    padding: .9rem;
}

.notification-send-result > header {
    align-items: center;
    display: flex;
    gap: 1rem;
    justify-content: space-between;
}

.notification-send-result__summary {
    display: grid;
    gap: .65rem;
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.notification-send-result__summary article {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 10px;
    display: grid;
    gap: .3rem;
    padding: .7rem;
}

.notification-send-result__summary span {
    color: var(--admin-text-muted);
    font-size: .76rem;
}

.notification-send-result__summary strong {
    font-size: 1.15rem;
}

.notification-send-result-table {
    min-width: 920px;
}

.notification-send-result-table code {
    direction: ltr;
    overflow-wrap: anywhere;
    unicode-bidi: isolate;
}

.notification-send-result-status {
    border-radius: 999px;
    display: inline-flex;
    font-size: .76rem;
    font-weight: 700;
    padding: .25rem .5rem;
}

.notification-send-result-status--sent {
    background: #e8f7ef;
    color: #17643c;
}

.notification-send-result-status--failed {
    background: #fff1f1;
    color: #a33a3a;
}

.notification-send-result-status--skipped {
    background: #fff8e7;
    color: #8a5a00;
}

.notification-send-section {
    border: 1px solid var(--admin-border);
    border-radius: 14px;
    display: grid;
    gap: .8rem;
    margin: 0;
    min-width: 0;
    padding: .9rem;
}

.notification-send-section > legend {
    font-weight: 800;
    padding-inline: .4rem;
}

.notification-send-channel-grid {
    display: grid;
    gap: .7rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.notification-send-channel {
    align-items: flex-start;
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    cursor: pointer;
    display: flex;
    gap: .65rem;
    padding: .8rem;
    transition: border-color .15s ease,
        background-color .15s ease;
}

.notification-send-channel.is-active {
    background: var(--admin-primary-soft);
    border-color: var(--admin-primary);
}

.notification-send-channel input {
    margin-top: .2rem;
}

.notification-send-channel span {
    display: grid;
    gap: .25rem;
}

.notification-send-channel small {
    color: var(--admin-text-muted);
    line-height: 1.7;
}

.notification-send-user-tools {
    align-items: end;
    display: grid;
    gap: .65rem;
    grid-template-columns: 2fr repeat(3, minmax(130px, 1fr));
}

.notification-send-user-tools label {
    display: grid;
    gap: .3rem;
}

.notification-send-user-tools label > span {
    color: var(--admin-text-muted);
    font-size: .76rem;
    font-weight: 700;
}

.notification-send-user-actions {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
}

.notification-send-user-list {
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    display: grid;
    max-height: 420px;
    overflow: auto;
}

.notification-send-user {
    align-items: center;
    border-bottom: 1px solid var(--admin-border);
    cursor: pointer;
    display: grid;
    gap: .7rem;
    grid-template-columns: auto minmax(220px, 1fr) auto;
    padding: .65rem .75rem;
}

.notification-send-user:last-child {
    border-bottom: 0;
}

.notification-send-user:hover {
    background: var(--admin-surface-muted);
}

.notification-send-user__identity {
    display: grid;
    gap: .2rem;
}

.notification-send-user__identity small {
    color: var(--admin-text-muted);
}

.notification-send-user__channels {
    display: flex;
    gap: .3rem;
}

.notification-send-user__channels small {
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 999px;
    color: var(--admin-text-muted);
    padding: .18rem .4rem;
}

.notification-send-user__channels small.is-ready {
    background: #e8f7ef;
    border-color: #c6ead7;
    color: #17643c;
}

.notification-send-manual-grid {
    display: grid;
    gap: .7rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.notification-send-manual-grid label,
.notification-send-content-grid label {
    display: grid;
    gap: .35rem;
}

.notification-send-manual-grid textarea {
    min-height: 120px;
    resize: vertical;
}

.notification-send-content-grid {
    display: grid;
    gap: .7rem;
    grid-template-columns: minmax(220px, .8fr) minmax(0, 2fr);
}

.notification-send-content-grid__body textarea {
    min-height: 170px;
    resize: vertical;
}

.notification-send-review {
    align-items: center;
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 14px;
    display: grid;
    gap: 1rem;
    grid-template-columns: minmax(180px, .7fr) minmax(0, 2fr);
    padding: .9rem;
}

.notification-send-review > div {
    display: grid;
    gap: .2rem;
}

.notification-send-review > div > span,
.notification-send-review > div > small {
    color: var(--admin-text-muted);
    font-size: .76rem;
}

.notification-send-review strong {
    font-size: 1.12rem;
}

.notification-send-review strong.is-over-limit {
    color: #a33a3a;
}

.notification-send-confirm {
    align-items: flex-start;
    display: flex;
    gap: .55rem;
}

.notification-send-confirm input {
    margin-top: .25rem;
}

.notification-send-actions {
    display: flex;
    gap: .55rem;
    justify-content: flex-end;
}

@media (max-width: 1100px) {
    .notification-send-channel-grid,
    .notification-send-manual-grid {
        grid-template-columns: 1fr;
    }

    .notification-send-user-tools {
        grid-template-columns: repeat(
            2,
            minmax(0, 1fr)
        );
    }

    .notification-send-content-grid,
    .notification-send-review {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 700px) {
    .notification-send-intro,
    .notification-send-result > header {
        align-items: stretch;
        flex-direction: column;
    }

    .notification-send-result__summary,
    .notification-send-user-tools {
        grid-template-columns: 1fr;
    }

    .notification-send-user {
        align-items: flex-start;
        grid-template-columns: auto 1fr;
    }

    .notification-send-user__channels {
        grid-column: 2;
        flex-wrap: wrap;
    }

    .notification-send-actions {
        flex-direction: column;
    }

    .notification-send-actions > * {
        width: 100%;
    }
}
</style>

<style>
/* notification-minimal-controls-v061 */
.communication-panel input[type="checkbox"],
.communication-panel input[type="radio"],
.notification-send-form input[type="checkbox"],
.notification-send-form input[type="radio"] {
    block-size: 15px !important;
    flex: 0 0 15px !important;
    inline-size: 15px !important;
    margin: 0 !important;
    max-block-size: 15px !important;
    max-inline-size: 15px !important;
    min-block-size: 15px !important;
    min-inline-size: 15px !important;
    padding: 0 !important;
}

.notification-send-step-tabs {
    display: grid;
    gap: .45rem;
    grid-template-columns: repeat(5, minmax(0, 1fr));
}

.notification-send-step-tabs button {
    align-items: center;
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 10px;
    color: var(--admin-text);
    cursor: pointer;
    display: flex;
    font: inherit;
    gap: .4rem;
    justify-content: center;
    min-height: 42px;
    padding: .5rem;
}

.notification-send-step-tabs button > span {
    align-items: center;
    background: var(--admin-surface-muted);
    border-radius: 999px;
    display: inline-flex;
    height: 22px;
    justify-content: center;
    width: 22px;
}

.notification-send-step-tabs button.is-active {
    background: var(--admin-primary-soft);
    border-color: var(--admin-primary);
    color: var(--admin-primary);
    font-weight: 800;
}

.notification-send-section[hidden],
.notification-send-review[hidden] {
    display: none !important;
}

.notification-send-type-grid {
    display: grid;
    gap: .7rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    margin-bottom: 1rem;
}

.notification-send-type {
    align-items: flex-start;
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    cursor: pointer;
    display: flex;
    gap: .65rem;
    padding: .8rem;
}

.notification-send-type.is-active {
    background: var(--admin-primary-soft);
    border-color: var(--admin-primary);
}

.notification-send-type > span {
    display: grid;
    gap: .25rem;
}

.notification-send-type small {
    color: var(--admin-text-muted);
}

.notification-send-channel.is-disabled {
    cursor: not-allowed;
    filter: grayscale(1);
    opacity: .45;
}

.notification-send-media-note {
    background: #fff8e7;
    border: 1px solid #ead7a7;
    border-radius: 10px;
    color: #785100;
    padding: .65rem;
}

.notification-send-media-foundation {
    display: grid;
    gap: .65rem;
    margin-top: .8rem;
}

.notification-send-media-foundation[hidden] {
    display: none !important;
}

.notification-send-dropzone {
    align-items: center;
    background: var(--admin-surface-muted);
    border: 1px dashed var(--admin-border);
    border-radius: 12px;
    cursor: pointer;
    display: grid;
    gap: .3rem;
    justify-items: center;
    min-height: 120px;
    padding: 1rem;
    text-align: center;
}

.notification-send-media-preview {
    display: grid;
    gap: .55rem;
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.notification-send-media-preview article {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 10px;
    display: grid;
    gap: .25rem;
    min-width: 0;
    padding: .55rem;
}

.notification-send-media-preview strong,
.notification-send-media-preview small {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

@media (max-width: 900px) {
    .notification-send-step-tabs {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .notification-send-media-preview {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 650px) {
    .notification-send-step-tabs,
    .notification-send-type-grid,
    .notification-send-media-preview {
        grid-template-columns: 1fr;
    }

    .notification-send-step-tabs button {
        justify-content: flex-start;
    }
}
</style>

<style>
/* bale-connection-management-style-v061 */
.bale-connection-management {
    display: grid;
    gap: 1rem;
}

.bale-connection-intro {
    align-items: center;
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 14px;
    display: flex;
    gap: 1rem;
    justify-content: space-between;
    padding: 1rem;
}

.bale-connection-intro h3 {
    margin: 0;
}

.bale-connection-provider {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    display: grid;
    gap: .2rem;
    min-width: 250px;
    padding: .75rem .9rem;
}

.bale-connection-provider > span,
.bale-connection-provider > small {
    color: var(--admin-text-muted);
    font-size: .76rem;
}

.bale-connection-provider__state {
    border-radius: 999px;
    font-size: .74rem;
    font-style: normal;
    margin-top: .25rem;
    padding: .25rem .5rem;
}

.bale-connection-provider__state--success {
    background: #e8f7ef;
    color: #17643c;
}

.bale-connection-provider__state--error {
    background: #fff1f1;
    color: #a33a3a;
}

.bale-connection-summary {
    display: grid;
    gap: .65rem;
    grid-template-columns: repeat(5, minmax(0, 1fr));
}

.bale-connection-summary article {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    display: grid;
    gap: .3rem;
    padding: .75rem;
}

.bale-connection-summary span {
    color: var(--admin-text-muted);
    font-size: .76rem;
}

.bale-connection-summary strong {
    font-size: 1.15rem;
}

.bale-connection-form {
    display: grid;
    gap: .8rem;
}

.bale-connection-filters {
    align-items: end;
    display: grid;
    gap: .65rem;
    grid-template-columns:
        minmax(240px, 2fr)
        repeat(4, minmax(135px, 1fr));
}

.bale-connection-filters label {
    display: grid;
    gap: .3rem;
}

.bale-connection-filters label > span {
    color: var(--admin-text-muted);
    font-size: .76rem;
    font-weight: 700;
}

.bale-connection-actions {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
}

.bale-connection-actions [data-bale-send-invitations] {
    margin-inline-start: auto;
}

.bale-connection-user-list {
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    display: grid;
    max-height: 560px;
    overflow: auto;
}

.bale-connection-user {
    align-items: center;
    border-bottom: 1px solid var(--admin-border);
    display: grid;
    gap: .7rem;
    grid-template-columns:
        auto
        minmax(210px, 1.5fr)
        minmax(100px, .7fr)
        minmax(180px, 1fr)
        minmax(115px, auto);
    padding: .7rem .8rem;
}

.bale-connection-user:last-child {
    border-bottom: 0;
}

.bale-connection-user:hover {
    background: var(--admin-surface-muted);
}

.bale-connection-user__select {
    align-items: center;
    display: flex;
}

.bale-connection-user__identity,
.bale-connection-user__mobile,
.bale-connection-user__status {
    display: grid;
    gap: .2rem;
    min-width: 0;
}

.bale-connection-user__identity small,
.bale-connection-user__mobile > span,
.bale-connection-user__status > small,
.bale-connection-user__row-actions > small {
    color: var(--admin-text-muted);
    font-size: .75rem;
}

.bale-connection-user__mobile strong {
    font-size: .78rem;
}

.bale-connection-user__mobile strong.is-ready {
    color: #17643c;
}

.bale-connection-user__mobile strong.is-missing {
    color: #a33a3a;
}

.bale-connection-status {
    border-radius: 999px;
    display: inline-flex;
    font-size: .76rem;
    font-weight: 700;
    justify-self: start;
    padding: .25rem .5rem;
}

.bale-connection-status--connected {
    background: #e8f7ef;
    color: #17643c;
}

.bale-connection-status--invited,
.bale-connection-status--waiting_confirmation {
    background: #eef4ff;
    color: #315c9a;
}

.bale-connection-status--expired,
.bale-connection-status--failed {
    background: #fff1f1;
    color: #a33a3a;
}

.bale-connection-status--disconnected,
.bale-connection-status--not_connected {
    background: var(--admin-surface-muted);
    color: var(--admin-text-muted);
}

.bale-connection-user__row-actions {
    align-items: center;
    display: flex;
    justify-content: flex-end;
}

@media (max-width: 1280px) {
    .bale-connection-summary {
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
    }

    .bale-connection-filters {
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
    }

    .bale-connection-filter-search {
        grid-column: span 2;
    }

    .bale-connection-user {
        grid-template-columns:
            auto
            minmax(200px, 1fr)
            minmax(160px, .8fr)
            minmax(110px, auto);
    }

    .bale-connection-user__mobile {
        display: none;
    }
}

@media (max-width: 760px) {
    .bale-connection-intro {
        align-items: stretch;
        flex-direction: column;
    }

    .bale-connection-provider {
        min-width: 0;
    }

    .bale-connection-summary,
    .bale-connection-filters {
        grid-template-columns: 1fr;
    }

    .bale-connection-filter-search {
        grid-column: auto;
    }

    .bale-connection-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .bale-connection-actions > * {
        margin-inline-start: 0 !important;
        width: 100%;
    }

    .bale-connection-user {
        align-items: flex-start;
        grid-template-columns: auto minmax(0, 1fr);
    }

    .bale-connection-user__status,
    .bale-connection-user__row-actions {
        grid-column: 2;
    }

    .bale-connection-user__row-actions {
        justify-content: flex-start;
    }
}
</style>

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

<style>
/* notification-multimedia-delivery-core-v061 */
.notification-send-actions [hidden] {
    display: none !important;
}

.notification-send-dropzone {
    align-items: center;
    cursor: pointer;
    display: grid;
    gap: .25rem;
    justify-items: center;
    position: relative;
    text-align: center;
}

.notification-send-dropzone input[type="file"] {
    block-size: 1px;
    clip: rect(0 0 0 0);
    clip-path: inset(50%);
    inline-size: 1px;
    overflow: hidden;
    position: absolute;
    white-space: nowrap;
}

.notification-send-dropzone__icon {
    align-items: center;
    background: var(--admin-primary-soft);
    border-radius: 999px;
    color: var(--admin-primary);
    display: inline-flex;
    font-size: 1.1rem;
    height: 32px;
    justify-content: center;
    width: 32px;
}

.notification-send-dropzone em {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 999px;
    color: var(--admin-text-muted);
    font-size: .72rem;
    font-style: normal;
    padding: .25rem .55rem;
}

.notification-send-media-limits {
    color: var(--admin-text-muted);
    font-size: .7rem;
    margin: 0;
    text-align: center;
}
</style>

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

<style>
/* notification-send-wizard-runtime-hotfix-v061 */
.notification-send-form
    > .notification-send-section[hidden],
.notification-send-form
    > .notification-send-review[hidden] {
    display: none !important;
}

/* notification-approval-ui-v062 */
.notification-approval-actions {
    align-items: flex-start;
    display: flex;
    flex-wrap: wrap;
    gap: .45rem;
}

.notification-approval-actions form {
    margin: 0;
}

.notification-approval-reject-form {
    align-items: flex-start;
    display: flex;
    flex-wrap: wrap;
    gap: .45rem;
}

.notification-approval-danger {
    background: #b42318;
    border-color: #b42318;
    color: #fff;
}

.notification-approval-danger:hover {
    background: #912018;
    border-color: #912018;
    color: #fff;
}

.notification-approval-reject-reason {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 10px;
    display: grid;
    flex-basis: 100%;
    gap: .45rem;
    min-width: min(300px, 72vw);
    padding: .55rem;
}

.notification-approval-reject-reason[hidden] {
    display: none;
}

.notification-approval-reject-reason label {
    display: grid;
    gap: .3rem;
}

.notification-approval-reject-reason input {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    color: inherit;
    font: inherit;
    min-height: 38px;
    padding: .45rem .55rem;
    width: 100%;
}

.notification-approval-reject-reason input:focus {
    border-color: #b42318;
    box-shadow: 0 0 0 3px rgba(180, 35, 24, .12);
    outline: 0;
}

.notification-approval-reject-actions {
    display: flex;
    gap: .4rem;
}

.notification-send-request-reason {
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    margin-top: .75rem;
    padding: .7rem;
}

.notification-send-request-reason label {
    display: grid;
    gap: .4rem;
}

.notification-send-request-reason label > span {
    display: grid;
    gap: .15rem;
}

.notification-send-request-reason label > span small {
    color: var(--admin-text-muted);
    font-weight: 400;
}

.notification-send-request-reason textarea {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 9px;
    color: inherit;
    font: inherit;
    min-height: 76px;
    padding: .55rem .65rem;
    resize: vertical;
    width: 100%;
}

.notification-send-request-reason textarea:focus {
    border-color: var(--admin-primary);
    box-shadow: 0 0 0 3px var(--admin-primary-soft);
    outline: 0;
}

@media (max-width: 760px) {
    .notification-approval-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .notification-approval-actions > form,
    .notification-approval-actions .admin-button {
        width: 100%;
    }

    .notification-approval-reject-reason {
        min-width: 0;
        width: 100%;
    }
}

</style>
