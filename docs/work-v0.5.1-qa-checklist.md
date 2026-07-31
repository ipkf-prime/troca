# Work v0.5.1 — QA checklist

## Sortable tables

- Project columns toggle ascending/descending and preserve search/status filters.
- Work item columns toggle ascending/descending and preserve search/type/status filters.
- My Work columns preserve scope and search.
- Invalid `sort` and `dir` query values fall back to the page default.
- Recent Tasks can be sorted locally without a page reload.

## Project-scoped access

| Role | View | Edit project | Members | Create item | Edit item | Archive item | Audit |
|---|---:|---:|---:|---:|---:|---:|---:|
| Platform admin | Yes | Yes | Yes | Yes | Yes | Yes | Yes |
| Owner | Yes | Yes | Yes | Yes | Yes | Yes | Yes |
| Manager | Yes | Yes | Yes | Yes | Yes | Yes | Yes |
| Member | Yes | No | No | Yes | Assigned/created only | No | No |
| Observer | Yes | No | No | No | No | No | No |
| Public viewer | Public projects only | No | No | No | No | No | No |

## Operational flows

- Create project and verify automatic owner membership.
- Add manager, member and observer.
- Create Work → milestone → task → subtask hierarchy.
- Assign task to an active project member.
- Add comment, checklist item and private attachment.
- Complete and reopen a task.
- Verify today, overdue, unassigned and completed scopes.
- Archive a leaf item; reject archiving an item with active children.
- Archive and restore a project.
- Change a reference-data title and verify dropdowns.
- Confirm structural item types cannot be disabled.
- Confirm an in-use status cannot be disabled.

## UI

- Settings page is compact at desktop widths.
- Settings navigation becomes horizontal at tablet widths.
- Forms become one/two columns on mobile.
- Sort indicators and keyboard focus are visible.
- Persian digits remain presentation-only; technical codes stay Latin.
