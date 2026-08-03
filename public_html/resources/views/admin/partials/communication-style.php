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
.provider-management-layout {
    display: grid;
    gap: .85rem;
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

.provider-management-form {
    max-width: none;
}

.provider-management-form fieldset {
    border: 1px solid var(--admin-border);
    border-radius: 10px;
    margin: .1rem 0 0;
    padding: .7rem;
}

.provider-management-form fieldset[hidden] {
    display: none;
}

.provider-management-form legend {
    color: var(--admin-text-muted);
    font-size: .8rem;
    font-weight: 800;
    padding-inline: .35rem;
}

.provider-dynamic-grid {
    display: grid;
    gap: .65rem .8rem;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
}

.provider-enabled-field {
    align-self: end;
}

.provider-secret-state {
    color: var(--admin-primary);
    font-size: .72rem;
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

@media (max-width: 760px) {
    .provider-management-card__head {
        align-items: stretch;
        flex-direction: column;
    }

    .provider-dynamic-grid {
        grid-template-columns: 1fr;
    }

    .provider-row-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .provider-row-actions .admin-button {
        justify-content: center;
        width: 100%;
    }
}
</style>
