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
}

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
    max-width: 980px;
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

    .communication-actions {
        align-items: stretch;
    }

    .communication-actions .admin-button {
        flex: 1 1 auto;
        justify-content: center;
    }
}
</style>
