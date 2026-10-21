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
