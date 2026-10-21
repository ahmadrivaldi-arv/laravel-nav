# Repository Guidelines

## Project Structure & Module Organization

This Composer library supports Laravel 10–13 on PHP 8.3+. Production code lives in `src/` under `Ahmdrv\Navigation`. Fluent definitions are `NavigationItem` and `NavigationGroup`; `NavigationManager` manages registration, and `NavigationBuilder` evaluates definitions into immutable `ResolvedNavigationItem` trees.

- `src/Contracts/`: context, authorization, and role/permission interfaces.
- `src/Support/`: callback evaluation and default adapters.
- `src/Data/` and `src/Facades/`: immutable metadata and the public facade.
- `config/navigation.php`: publishable registry configuration.
- `tests/Unit/`, `tests/Feature/`, `tests/Fixtures/`: isolated tests and minimal fixtures.
- `examples/consume.php`: application consumption example.

Keep the core focused on data. Rendering, frontend assets, layout integrations, and database-backed menu storage belong outside this package.

## Build, Test, and Development Commands

Run commands from the repository root:

- `composer install`: install dependencies and run Testbench package discovery.
- `composer prepare`: rerun package discovery after autoload or provider changes.
- `composer test`: run the PHPUnit suite.
- `composer test-coverage`: report coverage; requires a coverage driver.
- `composer format`: apply Laravel Pint formatting.
- `composer analyse`: run PHPStan/Larastan at level 6.
- `composer validate --strict`: validate Composer metadata.

There is no frontend build or application server to start.

## Coding Style & Naming Conventions

Keep `AGENTS.md` and `README.md` in English by default.

Use PSR-12, four-space indentation, `declare(strict_types=1)`, explicit types, and useful PHPDoc for collection shapes. Match filenames to PascalCase classes and use camelCase methods. Preserve the existing snake_case `test_*` naming convention. Use constructor injection and avoid application-specific namespaces or user models.

## Testing Guidelines

Use PHPUnit with Orchestra Testbench; match tooling versions to the Laravel version in the CI matrix. Add behavior tests for authorization, callback evaluation, immutable output, registry loading, and request isolation. Use minimal fixtures without a business database. No fixed coverage percentage is configured. Run the full suite and analysis before submitting changes; compatibility claims require passing the relevant matrix jobs.

## Commit & Pull Request Guidelines

Use Conventional Commits (`type: summary`): `feat: add dynamic children`, `fix: preserve stable sorting`, or `docs: clarify adapters`. Other types include `refactor`, `test`, `chore`, `ci`, `build`, `perf`, and `style`. Keep commits focused. PR descriptions should explain behavior, compatibility, and validation. Link relevant issues and update affected README examples.

## Lifecycle & Configuration

Register roots explicitly during bootstrap. Evaluate users, authorization, URLs, and badges during each build; never cache resolved user state in singleton definitions. Keep legacy dependency advisory exceptions confined to designated CI jobs and preserve the published Composer security policy.
