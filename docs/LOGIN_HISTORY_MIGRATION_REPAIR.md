# Login History Migration Repair

The first login-history migration created its foreign keys inline. On an
existing deployment, a signed/unsigned difference or a non-InnoDB legacy table
could cause the complete `CREATE TABLE` statement to fail.

The repaired migration:

- reads the actual referenced column type from `information_schema`;
- creates `auth_login_history` without inline foreign keys;
- adds each foreign key only when both tables use InnoDB and the column types
  are identical;
- remains idempotent when retried after a failed migration;
- keeps login-history recording available even when an optional
  role-assignment foreign key cannot safely be created.

After deployment, rerun the protected Core migration endpoint.
