# Contributing

Use PHP 8.3+ and Composer 2. Install dependencies with `composer install`.

Before submitting changes, run:

```bash
composer format
composer analyse
composer test
composer validate --strict
```

Keep the package focused on navigation data. Rendering and frontend integrations belong in separate packages or applications. Add behavior tests for meaningful core changes, especially authorization and request isolation. Tests must not require an application business database.

## Commit Messages

Use Conventional Commits with a lowercase type and a concise imperative summary:

```text
feat: add dynamic navigation children
fix: preserve stable sorting for equal priorities
docs: clarify role and permission adapters
```

Use `feat` for features, `fix` for bug fixes, and `docs` for documentation. Other types include `refactor`, `test`, `chore`, `ci`, `build`, `perf`, and `style`. Optional scopes follow the type, for example `feat(registry): add custom registry paths`. Mark breaking changes with `!` after the type/scope or a `BREAKING CHANGE:` footer. Keep messages in English and each commit focused on one change.
