<style>
/* Compact, shared presentation for IPKF Work pages. */
.work-ui-compact-hub {
    display: flex;
    align-items: center;
    min-height: 0;
    padding: .65rem .9rem;
    gap: .75rem;
    border-radius: 1rem;
}

.work-ui-compact-hub > div:not(.admin-module-hub__icon) {
    flex: 1 1 auto;
    min-width: 0;
}

.work-ui-compact-hub .admin-module-hub__icon {
    width: 2.75rem;
    height: 2.75rem;
    min-width: 2.75rem;
}

.work-ui-compact-hub h2 {
    margin: 0 0 .1rem;
    font-size: 1.12rem;
    line-height: 1.35;
}

.work-ui-compact-hub p {
    margin: 0;
    font-size: .86rem;
    line-height: 1.4;
}

.work-ui-compact-hub .admin-module-hub__back {
    flex: 0 0 auto;
    margin-inline-start: auto;
    padding: .45rem .75rem;
    white-space: nowrap;
}

.work-dashboard__toolbar {
    align-items: center;
    margin-bottom: 1rem;
}

.work-dashboard__toolbar p {
    margin: 0;
}


.work-dashboard__intro {
    margin: 0 0 1rem;
}

.work-dashboard-card-link {
    color: inherit;
    text-decoration: none;
    cursor: pointer;
}

.work-dashboard-card-link:hover {
    transform: translateY(-2px);
}

.work-dashboard-card-link:focus-visible {
    outline: 3px solid rgba(37, 99, 235, .28);
    outline-offset: 3px;
}

.work-my-toolbar {
    align-items: center;
    display: flex;
    flex-wrap: nowrap;
    gap: .55rem;
    margin: .8rem 0 1rem;
    min-width: 0;
}

.work-my-scopes,
.work-my-search {
    align-items: center;
    display: flex;
    flex-wrap: nowrap;
    gap: .45rem;
}

.work-my-scopes {
    flex: 0 0 auto;
}

.work-my-search {
    flex: 1 1 auto;
    margin: 0;
    min-width: 14rem;
}

.work-my-search input {
    flex: 1 1 auto;
    min-width: 10rem;
}

.work-my-scopes .admin-button,
.work-my-search .admin-button {
    min-height: 2.25rem;
    padding: .4rem .68rem;
    white-space: nowrap;
}

@media (max-width: 980px) {
    .work-my-toolbar {
        align-items: stretch;
        flex-wrap: wrap;
    }

    .work-my-scopes {
        max-width: 100%;
        overflow-x: auto;
        padding-bottom: .2rem;
    }

    .work-my-search {
        flex: 1 1 100%;
    }
}

.work-action-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 2.25rem;
    padding: .45rem .8rem;
    border-radius: .65rem;
    font-size: .82rem;
    font-weight: 800;
    line-height: 1;
    white-space: nowrap;
}

.work-action-button--navigate {
    background: #2563eb;
    color: #fff;
    box-shadow: 0 .35rem .8rem rgba(37, 99, 235, .18);
}

.work-action-button--disabled {
    border: 1px solid #cbd5e1;
    background: #e2e8f0;
    color: #64748b;
    box-shadow: none;
}

.work-button--danger {
    border-color: #b91c1c;
    background: #b91c1c;
    color: #fff;
}

.work-button--danger:hover {
    border-color: #991b1b;
    background: #991b1b;
}

.work-project-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .7rem;
    margin: 1rem 0 0;
}

.work-project-summary > div {
    min-width: 0;
    padding: .75rem .9rem;
    border: 1px solid rgba(21, 128, 61, .12);
    border-radius: .75rem;
    background: rgba(21, 128, 61, .035);
}

.work-project-summary span {
    display: block;
    margin-bottom: .25rem;
    font-size: .78rem;
    color: var(--admin-muted, #64748b);
}

.work-project-summary strong {
    display: block;
    overflow-wrap: anywhere;
}

.work-project-actions {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .7rem;
    margin-top: .85rem;
}

.work-project-actions .admin-action-card {
    min-height: 0;
    padding: .9rem 1rem;
}

.work-project-actions h4,
.work-project-actions p {
    margin: 0;
}

.work-project-actions p {
    margin-top: .2rem;
}

.work-project-description {
    margin-top: .9rem;
    padding-top: .85rem;
    border-top: 1px solid rgba(15, 23, 42, .08);
}

.work-project-description h3 {
    margin: 0 0 .45rem;
}

.work-compact-section {
    padding: 1rem 1.1rem;
}

.work-form-primary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .8rem;
}

.work-form-primary .work-field-wide {
    grid-column: span 2;
}

.work-form-primary label,
.work-members-inline-form label {
    margin: 0;
}

.work-form-more {
    margin-top: .9rem;
    padding: 0 .9rem .85rem;
    border: 1px solid rgba(15, 23, 42, .1);
    border-radius: .8rem;
    background: rgba(15, 23, 42, .018);
}

.work-form-more summary {
    cursor: pointer;
    padding: .8rem 0;
    font-weight: 700;
}

.work-form-more[open] summary {
    margin-bottom: .65rem;
    border-bottom: 1px solid rgba(15, 23, 42, .08);
}

.work-form-more .admin-form-grid {
    margin-top: 0;
}

.work-form-more textarea {
    min-height: 7rem;
}

.work-section-heading {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: .7rem;
}

.work-section-heading h3,
.work-section-heading p {
    margin: 0;
}

.work-members-inline-form {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(11rem, 1fr) auto;
    align-items: end;
    gap: .7rem;
}

.work-members-inline-form .admin-button {
    min-height: 2.75rem;
}

.work-member-role-form {
    display: flex;
    align-items: center;
    gap: .45rem;
    flex-wrap: nowrap;
}

.work-member-role-form select {
    min-width: 8rem;
}

.work-items-filter {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(9rem, 1fr) minmax(10rem, 1fr) auto auto;
    align-items: end;
    gap: .7rem;
    margin: .85rem 0 1rem;
}

.work-items-filter label,
.work-item-form-grid label {
    margin: 0;
}

.work-items-filter__search {
    min-width: 15rem;
}

.work-items-table td {
    vertical-align: middle;
}

.work-item-title {
    position: relative;
    min-width: 15rem;
    padding-inline-start: calc(var(--work-item-depth, 0) * 1.2rem);
}

.work-item-title strong,
.work-item-title small {
    display: block;
}

.work-item-title small {
    margin-top: .2rem;
    font-size: .72rem;
}

.work-item-form-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .8rem;
}

.work-item-field-wide {
    grid-column: span 2;
}

.work-projects-toolbar {
    align-items: flex-end;
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    justify-content: space-between;
}

.work-projects-toolbar .admin-users-search {
    flex: 1 1 46rem;
    min-width: 0;
}

.work-projects-toolbar .admin-users-search__row {
    align-items: center;
    display: flex;
    flex-wrap: nowrap;
    gap: .65rem;
}

.work-projects-toolbar .admin-users-search__row input {
    min-width: 14rem;
}

.work-project-filter-actions,
.work-projects-toolbar__meta,
.work-project-count {
    align-items: center;
    display: flex;
}

.work-project-filter-actions {
    flex: 0 0 auto;
    gap: .5rem;
}

.work-projects-toolbar__meta {
    flex: 0 0 auto;
    gap: .75rem;
}

.work-project-count {
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: .7rem;
    gap: .4rem;
    min-height: 2.5rem;
    padding: .45rem .75rem;
    white-space: nowrap;
}

.work-project-count span {
    color: var(--admin-text-muted);
    font-size: .78rem;
    font-weight: 700;
}

.work-project-count strong {
    color: var(--admin-primary);
    font-size: 1rem;
}

@media (max-width: 1100px) {
    .work-items-filter,
    .work-item-form-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .work-items-filter__search,
    .work-item-field-wide {
        grid-column: 1 / -1;
    }

    .work-projects-toolbar .admin-users-search__row {
        flex-wrap: wrap;
    }
}

@media (max-width: 760px) {
    .work-items-filter,
    .work-item-form-grid {
        grid-template-columns: 1fr;
    }

    .work-items-filter__search,
    .work-item-field-wide {
        grid-column: auto;
    }

    .work-projects-toolbar__meta {
        justify-content: space-between;
        width: 100%;
    }
}

@media (max-width: 1100px) {
    .work-project-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .work-form-primary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .work-form-primary .work-field-wide {
        grid-column: 1 / -1;
    }
}

@media (max-width: 760px) {
    .work-ui-compact-hub {
        flex-wrap: wrap;
        padding: .65rem .8rem;
        gap: .55rem .65rem;
    }

    .work-ui-compact-hub > div:not(.admin-module-hub__icon) {
        flex: 1 1 calc(100% - 3.5rem);
    }

    .work-ui-compact-hub .admin-module-hub__back {
        margin-inline-start: auto;
        padding: .42rem .7rem;
    }

    .work-project-summary,
    .work-project-actions,
    .work-form-primary,
    .work-members-inline-form {
        grid-template-columns: 1fr;
    }

    .work-form-primary .work-field-wide {
        grid-column: auto;
    }

    .work-section-heading {
        align-items: start;
        flex-direction: column;
    }
}
</style>

<script data-work-persian-digits>
(() => {
    const persianDigits = '۰۱۲۳۴۵۶۷۸۹';
    const skipTags = new Set([
        'SCRIPT',
        'STYLE',
        'TEXTAREA',
        'INPUT',
        'SELECT',
        'OPTION',
        'CODE',
        'PRE'
    ]);

    const convert = value =>
        String(value).replace(/[0-9]/g, digit => persianDigits[Number(digit)]);

    const normalize = root => {
        const walker = document.createTreeWalker(
            root,
            NodeFilter.SHOW_TEXT
        );

        const nodes = [];

        while (walker.nextNode()) {
            nodes.push(walker.currentNode);
        }

        nodes.forEach(node => {
            const parent = node.parentElement;

            if (
                !parent ||
                skipTags.has(parent.tagName) ||
                parent.closest('[data-latin-digits]')
            ) {
                return;
            }

            const value = convert(node.nodeValue ?? '');

            if (value !== node.nodeValue) {
                node.nodeValue = value;
            }
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        normalize(document.body);

        new MutationObserver(records => {
            records.forEach(record => {
                record.addedNodes.forEach(node => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        normalize(node);
                    } else if (
                        node.nodeType === Node.TEXT_NODE &&
                        node.parentElement
                    ) {
                        normalize(node.parentElement);
                    }
                });
            });
        }).observe(document.body, {
            childList: true,
            subtree: true
        });
    });
})();
</script>
