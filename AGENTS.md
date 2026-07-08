# IPKF Framework

Permanent Codex instructions for this repository.

- Project name: IPKF Framework.
- Framework core must live under `/system`.
- Application code must live under `/app`.
- Namespace rules:
  - `IPKF\` maps to `system/`.
  - `App\` maps to `app/`.
- Do not recreate `app/Core`.
- Do not add Bot, CRM, ERP, Automation, or Marketplace unless explicitly requested.
- Controllers must not contain business logic.
- Services contain business logic.
- Repositories handle data access.
- Keep layered architecture clean.
- Work on feature branches.
- Do not commit directly to `main`.
- Summarize changed files after every task.
