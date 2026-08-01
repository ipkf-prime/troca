<style>
.communication-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns:
        repeat(auto-fit, minmax(220px, 1fr));
}
.communication-card,
.communication-panel {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: .9rem;
    padding: 1rem;
}
.communication-card {
    color: inherit;
    display: block;
    min-height: 125px;
    text-decoration: none;
}
.communication-card:hover {
    border-color: var(--admin-primary);
}
.communication-card h3,
.communication-panel h2,
.communication-panel h3 {
    margin: 0 0 .45rem;
}
.communication-card p,
.communication-muted {
    color: var(--admin-text-muted);
}
.communication-badge {
    background: var(--admin-primary-soft);
    border-radius: 999px;
    display: inline-flex;
    font-size: .72rem;
    font-weight: 800;
    margin-top: .65rem;
    padding: .25rem .55rem;
}
.communication-form {
    display: grid;
    gap: .85rem;
}
.communication-form label {
    display: grid;
    gap: .35rem;
}
.communication-form input,
.communication-form select,
.communication-form textarea {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: .65rem;
    color: inherit;
    font: inherit;
    padding: .7rem .8rem;
    width: 100%;
}
.communication-form textarea {
    min-height: 150px;
    resize: vertical;
}
.communication-table-wrap {
    overflow-x: auto;
}
.communication-table {
    border-collapse: collapse;
    width: 100%;
}
.communication-table th,
.communication-table td {
    border-bottom: 1px solid var(--admin-border);
    padding: .7rem;
    text-align: right;
    vertical-align: top;
}
.communication-row-unread {
    font-weight: 800;
}
.communication-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .55rem;
}
.communication-thread {
    display: grid;
    gap: .7rem;
}
.communication-message {
    background: var(--admin-surface-muted);
    border-radius: .8rem;
    padding: .8rem;
}
.communication-message.is-mine {
    border-inline-start: 3px solid var(--admin-primary);
}
.communication-message header {
    display: flex;
    gap: .8rem;
    justify-content: space-between;
}
.communication-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: .45rem;
    margin-bottom: 1rem;
}
.communication-tabs a {
    border: 1px solid var(--admin-border);
    border-radius: 999px;
    color: inherit;
    padding: .45rem .75rem;
    text-decoration: none;
}
.communication-tabs a.is-active {
    background: var(--admin-primary-soft);
    border-color: var(--admin-primary);
}
</style>
