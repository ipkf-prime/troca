# Work v0.5.1 QA users

This temporary CLI script creates or resets five development-only QA accounts:

- Project owner
- Project manager
- Project member
- Project observer
- User outside the project

It also creates five dedicated QA roles and assigns only the required Work permissions. `work.project.admin` is never assigned.

## Run on the Core development host

```bash
php /home/troca/work-dev.troca.ir/scripts/create-work-qa-users.php \
  --confirm=CREATE-WORK-QA-USERS
```

Read the generated credentials:

```bash
cat /home/troca/work-dev.troca.ir/storage/private/qa/work-v0.5.1-users.txt
```

After copying the credentials:

```bash
rm -f /home/troca/work-dev.troca.ir/storage/private/qa/work-v0.5.1-users.txt
```

Each user must switch the active access role after login to the matching `QA Work - ...` role. The standard `user` role is still created automatically by the authentication system.

This script is idempotent but rerunning it resets all five passwords.
