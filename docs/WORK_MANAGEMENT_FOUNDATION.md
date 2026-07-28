# IPKF Work Management Foundation

- Application key: `work`
- Development host: `work-dev.troca.ir`
- Dedicated database: `troca_work`
- Connection: `work.primary`

## First operational hierarchy

`Project -> Work -> Milestone -> Task -> Subtask`

The schema also includes project members, task assignees, checklists, comments,
private attachment metadata, and an append-only activity history.

Core user and organization identities are stored as stable references. No
cross-database foreign keys are introduced.

## Deployment

1. Configure `WORK_APP_URL`, `WORK_DB_*`, and add the Work host to
   `ALLOWED_APP_HOSTS`.
2. Run `migrate.php?application=work&key=...`.
3. Run `seed.php?application=work&key=...`.
4. Register/activate module key `work` in the module registry.
