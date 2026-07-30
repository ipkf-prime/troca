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
