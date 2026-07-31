<style>
.admin-sort-link {
    align-items: center;
    color: inherit;
    display: inline-flex;
    gap: .3rem;
    text-decoration: none;
    white-space: nowrap;
}

.admin-sort-link:hover,
.admin-sort-link:focus-visible {
    color: var(--admin-primary);
}

.admin-sort-indicator {
    display: inline-flex;
    font-size: .78rem;
    justify-content: center;
    min-width: .85rem;
    opacity: .48;
}

th[aria-sort="ascending"] .admin-sort-indicator,
th[aria-sort="descending"] .admin-sort-indicator {
    opacity: 1;
}

.admin-client-sort {
    appearance: none;
    background: transparent;
    border: 0;
    color: inherit;
    cursor: pointer;
    font: inherit;
    padding: 0;
}

.work-settings-layout--minimal {
    display: grid;
    gap: .75rem;
    grid-template-columns: 13.5rem minmax(0, 1fr);
}

.work-settings-nav--minimal {
    align-content: start;
    display: grid;
    gap: .35rem;
    padding: .65rem;
}

.work-settings-nav--minimal a {
    align-items: center;
    border: 1px solid transparent;
    border-radius: .65rem;
    color: inherit;
    display: flex;
    gap: .55rem;
    justify-content: space-between;
    min-height: 2.55rem;
    padding: .45rem .6rem;
    text-decoration: none;
}

.work-settings-nav--minimal a:hover {
    background: var(--admin-surface-muted);
}

.work-settings-nav--minimal a.is-active {
    background: var(--admin-primary-soft);
    border-color: color-mix(in srgb, var(--admin-primary) 28%, transparent);
    color: var(--admin-primary-hover);
}

.work-settings-nav--minimal small {
    flex: 0 0 auto;
    font-size: .7rem;
}

.work-settings-main--minimal {
    padding: .8rem .9rem;
}

.work-settings-main--minimal .admin-section__header {
    align-items: center;
    margin-bottom: .35rem;
}

.work-settings-main--minimal .admin-section__header h2,
.work-settings-main--minimal .admin-section__header p {
    margin: 0;
}

.work-settings-main--minimal .admin-section__header p {
    font-size: .78rem;
    margin-top: .15rem;
}

.work-settings-list {
    display: grid;
}

.work-settings-row--minimal {
    border-top: 1px solid var(--admin-border);
    padding: .55rem 0;
}

.work-settings-row--minimal:first-child {
    border-top: 0;
}

.work-settings-row__meta--minimal {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
    margin-bottom: .35rem;
    min-height: 1.35rem;
}

.work-settings-row__meta--minimal code {
    color: var(--admin-text-muted);
    direction: ltr;
    font-size: .72rem;
}

.work-settings-row__meta--minimal .admin-pill {
    font-size: .65rem;
    min-height: 1.3rem;
    padding: .15rem .42rem;
}

.work-settings-form--minimal {
    align-items: end;
    display: grid;
    gap: .45rem;
    grid-template-columns:
        minmax(10rem, 1.35fr)
        minmax(8rem, .85fr)
        3.4rem
        4.6rem
        4.3rem
        auto;
}

.work-settings-form--status {
    grid-template-columns:
        minmax(10rem, 1.35fr)
        minmax(7rem, .7fr)
        3.4rem
        4.6rem
        4.3rem
        4.3rem
        auto;
}

.work-settings-form--minimal label {
    margin: 0;
    min-width: 0;
}

.work-settings-form--minimal label > span {
    display: block;
    font-size: .68rem;
    margin-bottom: .2rem;
}

.work-settings-form--minimal input:not([type="checkbox"]):not([type="color"]),
.work-settings-form--minimal select {
    min-height: 2.25rem;
    padding-block: .38rem;
}

.work-settings-form--minimal input[type="number"] {
    text-align: center;
}

.work-settings-form--minimal input[type="color"] {
    height: 2.25rem;
    min-width: 3.1rem;
    padding: .15rem;
    width: 100%;
}

.work-settings-toggle {
    align-items: center;
    display: flex;
    flex-direction: column;
    font-size: .68rem;
    gap: .18rem;
    justify-content: end;
    min-height: 2.25rem;
    white-space: nowrap;
}

.work-settings-toggle input[type="checkbox"] {
    height: 1.05rem;
    margin: 0;
    width: 1.05rem;
}

.work-settings-save {
    min-height: 2.25rem;
    padding: .35rem .6rem;
}

.work-settings-create--minimal {
    background: var(--admin-surface-muted);
    border: 1px dashed var(--admin-border);
    border-radius: .75rem;
    margin-top: .65rem;
    padding: 0 .7rem;
}

.work-settings-create--minimal > summary {
    cursor: pointer;
    font-size: .82rem;
    font-weight: 800;
    padding: .65rem 0;
}

.work-settings-create--minimal[open] {
    padding-bottom: .7rem;
}

.work-settings-create--minimal[open] > summary {
    border-bottom: 1px solid var(--admin-border);
    margin-bottom: .65rem;
}

@media (max-width: 1120px) {
    .work-settings-layout--minimal {
        grid-template-columns: 1fr;
    }

    .work-settings-nav--minimal {
        display: flex;
        overflow-x: auto;
    }

    .work-settings-nav--minimal a {
        flex: 0 0 auto;
        min-width: 10rem;
    }

    .work-settings-form--minimal,
    .work-settings-form--status {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 680px) {
    .work-settings-form--minimal,
    .work-settings-form--status {
        grid-template-columns: 1fr 1fr;
    }

    .work-settings-form--minimal > label:first-of-type {
        grid-column: 1 / -1;
    }

    .work-settings-save {
        width: 100%;
    }
}
</style>

<script data-admin-client-table-sort>
(() => {
    const fa = '۰۱۲۳۴۵۶۷۸۹';
    const ar = '٠١٢٣٤٥٦٧٨٩';

    const normalizeDigits = value => String(value ?? '')
        .replace(/[۰-۹]/g, digit => String(fa.indexOf(digit)))
        .replace(/[٠-٩]/g, digit => String(ar.indexOf(digit)));

    const comparable = (cell, type) => {
        const explicit = cell?.dataset.sortValue;
        const raw = explicit ?? cell?.textContent ?? '';
        const normalized = normalizeDigits(raw).trim();

        if (type === 'number') {
            const number = Number(normalized.replace(/[^\d.-]/g, ''));
            return Number.isFinite(number) ? number : Number.NEGATIVE_INFINITY;
        }

        if (type === 'date') {
            const timestamp = Date.parse(normalized);
            return Number.isNaN(timestamp) ? Number.NEGATIVE_INFINITY : timestamp;
        }

        return normalized.toLocaleLowerCase('fa');
    };

    document.addEventListener('click', event => {
        const button = event.target.closest('[data-client-sort-index]');
        if (!button) return;

        const table = button.closest('table[data-admin-client-sort]');
        const tbody = table?.tBodies?.[0];
        if (!table || !tbody) return;

        const index = Number(button.dataset.clientSortIndex);
        const type = button.dataset.clientSortType || 'text';
        const current = button.closest('th')?.getAttribute('aria-sort');
        const direction = current === 'ascending' ? 'descending' : 'ascending';
        const factor = direction === 'ascending' ? 1 : -1;

        table.querySelectorAll('th[aria-sort]').forEach(th => th.setAttribute('aria-sort', 'none'));
        button.closest('th')?.setAttribute('aria-sort', direction);

        const rows = Array.from(tbody.rows);
        rows.sort((a, b) => {
            const av = comparable(a.cells[index], type);
            const bv = comparable(b.cells[index], type);

            if (typeof av === 'number' && typeof bv === 'number') {
                return (av - bv) * factor;
            }

            return String(av).localeCompare(String(bv), 'fa', {numeric: true}) * factor;
        });

        rows.forEach(row => tbody.appendChild(row));
    });
})();
</script>
