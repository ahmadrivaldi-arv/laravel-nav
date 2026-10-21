# Laravel Navigation

A Laravel navigation core with fluent definitions, authorization, and immutable navigation trees. The Composer package is `ahmdrv/laravel-nav`, with the namespace `Ahmdrv\Navigation`. It supports PHP 8.3+ and Laravel 10, 11, 12, and 13. Illuminate constraints are `^10.0 || ^11.0 || ^12.0 || ^13.0`; future major versions must be tested before they are added.

The package produces data for applications to consume. Applications handle HTML rendering, Blade, API responses, menu orientation, layouts, escaping, and sanitization. The package includes no views/components, frontend assets, view composers, database menu storage, or mandatory dependencies on Livewire, Filament, or Spatie Permission.

## Installation

Once the package is available in your Composer repository:

```bash
composer require ahmdrv/laravel-nav
php artisan vendor:publish --tag="nav-config"
```

Laravel discovers `NavigationServiceProvider` and the `Navigation` alias through package discovery. The fully qualified facade is:

```php
use Ahmdrv\Navigation\Facades\Navigation;
```

Configuration in `config/navigation.php`:

```php
return [
    'registry' => base_path('routes/navigation.php'),
];
```

Missing files are ignored. Set `registry` to `null` to disable file loading, or use a custom absolute path. Configuration is read when the manager is first resolved.

## Registration and Consumption

Register definitions in an application service provider's `boot()` method:

```php
use Ahmdrv\Navigation\Facades\Navigation;
use App\Models\Invoice;

Navigation::register([
    Navigation::group('Finance')
        ->sort(40)
        ->items([
            Navigation::item('Invoices')
                ->icon('tabler tabler-file-invoice')
                ->route('invoices.index')
                ->activeRoute('invoices.*')
                ->can('viewAny', Invoice::class),
        ]),
]);
```

`item()` and `group()` only create definitions. Only `register()` adds roots; children are never automatically registered as roots. `register()` accepts a list of definitions and returns `void`. Each call appends roots in registration order.

Alternatively, a `routes/navigation.php` file **returns a list**:

```php
<?php

declare(strict_types=1);

use Ahmdrv\Navigation\Facades\Navigation;

return [
    Navigation::item('Dashboard')->route('dashboard')->sort(-10),
    Navigation::item('Help')->url('https://example.com/help')->openUrlInNewTab(),
];
```

Do not call `register()` or `build()` inside this file. Register each definition in one place to avoid duplicates. Programmatic and file registration can be combined for different definitions.

Consume the tree in an application controller:

```php
$items = Navigation::build(); // list<ResolvedNavigationItem>
return view('dashboard', ['navigation' => $items]);
```

Or convert it to arrays for an application API Resource or response:

```php
$data = array_map(static fn ($item) => $item->toArray(), Navigation::build());
return response()->json(['navigation' => $data]);
```

Rendering remains the application's responsibility. See the [consumer example](examples/consume.php).

## Fluent Definitions

All setters return the same definition instance. Definitions are mutable while being assembled; the builder does not modify them. Labels are always text, including strings containing HTML tags. Trusted HTML labels are not supported.

| Item method | Behavior |
| --- | --- |
| `label(string)` | Replace the label text |
| `icon(string\|Icon)` | Set icon metadata; a string becomes an identifier/class |
| `route(string, array = [])` | Set a named route with Laravel route and query parameters |
| `url(?string)` | Set a literal URL; `null` is allowed and `'#'` is preserved |
| `badge(label, color = 'primary')` | Set a scalar/null/Closure label and string/Closure color |
| `openUrlInNewTab(bool\|Closure = true)` | Use target `_blank` when true, otherwise `null` |
| `sort(int)` | Set ascending sort order, default `0` |
| `items(array\|Closure)` | Set a children list or a callback returning one |
| `activeRoute(string\|array)` | Match one or more route-name patterns, including `*` |
| `activeWhen(Closure)` | Add an active-state condition |
| `visible(bool\|Closure = true)` | Require every visibility rule to be true |
| `hidden(bool\|Closure = true)` | Require every hidden rule to be false |
| `roles(string\|array)` | Require at least one matching role per call |
| `permissions(string\|array)` | Require at least one matching permission per call |
| `can(string, mixed = [])` | Check Laravel Gate/policy authorization, including class/model arguments |
| `authorize(Closure)` | Add an authorization condition |

Groups support `label`, `sort`, `items`, `visible`, `hidden`, `roles`, `permissions`, `can`, and `authorize`. Groups organize children without their own URL, badge, icon, or active condition.

Value setters (`route`, `url`, `items`, `badge`, `activeRoute`, `activeWhen`, etc.) replace previous values. `route()` and `url()` replace each other; the last destination wins. Visibility and authorization rules accumulate, including repeated calls to the same method. `roles([])` and `permissions([])` always deny access.

## Authorization and Guests

Filtering happens before evaluating children, URLs, active state, targets, or badges. Every rule category must pass: visible, hidden, roles, permissions, Gate, and authorize. Evaluation may stop as soon as a rule denies access. Denied parents neither evaluate nor expose children. Empty groups are removed after filtering; items without children remain.

Guests receive `null` as the user. They can see public items, are denied by role/permission rules, and follow Laravel Gate's behavior for callbacks/policies accepting a nullable user. Visibility and `authorize()` callbacks determine their own guest behavior.

The default `DenyRolePermissionAdapter` denies every role/permission rule, including for authenticated users. There is no automatic model-method detection or fallback that grants access. Bind an adapter in your application service provider's `register()` method:

```php
use Ahmdrv\Navigation\Contracts\RolePermissionAdapter;

$this->app->bind(RolePermissionAdapter::class, AppRolePermissionAdapter::class);
```

Adapters must implement:

```php
public function hasAnyRole(Authenticatable $user, array $roles): bool;
public function hasAnyPermission(Authenticatable $user, array $permissions): bool;
```

An example adapter for an application already using Spatie Permission:

```php
use Ahmdrv\Navigation\Contracts\RolePermissionAdapter;
use Illuminate\Contracts\Auth\Authenticatable;

final class AppRolePermissionAdapter implements RolePermissionAdapter
{
    public function hasAnyRole(Authenticatable $user, array $roles): bool
    {
        return method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles);
    }

    public function hasAnyPermission(Authenticatable $user, array $permissions): bool
    {
        return method_exists($user, 'hasAnyPermission') && $user->hasAnyPermission($permissions);
    }
}
```

The package does not install Spatie Permission. Adapters can use any role/permission implementation and must be stateless or use an appropriate request lifecycle. Replace the `NavigationAuthorization` contract to customize the complete authorization strategy. Navigation visibility does not replace authorization on application endpoints.

## Callbacks and Badges

All callbacks use `CallbackEvaluator`. Arguments are positional; there is no dependency injection based on parameter names or types.

| Callback | Available arguments, in order |
| --- | --- |
| `items`, `visible`, `hidden`, `authorize`, `activeWhen`, `openUrlInNewTab`, badge label | `?Authenticatable $user`, `NavigationNode $item`, `NavigationContext $context` |
| badge color | `string\|int\|float $label`, `?Authenticatable $user`, `NavigationItem $item`, `NavigationContext $context` |

Closures may accept zero arguments or any prefix of the supported signature. Optional parameters receive available positional arguments; optional parameters beyond the available arguments use their PHP defaults. Variadic parameters receive all remaining arguments. Requiring more arguments than the signature supports throws `InvalidArgumentException`. Callback exceptions propagate. Callbacks must be read-only and must not register or mutate definitions.

```php
Navigation::item('Inbox')->badge(
    fn ($user, $item) => $user?->unreadNotifications()->count(),
    fn ($label, $user, $item) => $label > 10 ? 'danger' : 'primary',
);
```

Badges are hidden for `null`, `false`, `''`, `0`, `0.0`, and `'0'`. Displayed labels must be strings, integers, or floats; other types, including `true`, throw `InvalidArgumentException`. Resolved colors must be strings. Colors are metadata: `danger`, `#ff5722`, and `var(--brand-color)` are preserved unchanged.

The label callback runs once per visible item occurrence in a build; the color callback runs once only when the label is displayed. Children, active-state, and target callbacks each run once when needed. Reusing a Closure across rule categories still creates separate rules. A definition deliberately placed at multiple positions is evaluated for each occurrence. Subsequent builds evaluate callbacks again; there is no automatic database caching or per-user result cache.

## URLs, Active/Open State, and Sorting

Named routes are resolved during the build, after application routes are available. Parameters use Laravel's URL generator, including model route keys and additional query parameters. A missing named route propagates `Symfony\Component\Routing\Exception\RouteNotFoundException`; missing required parameters follow Laravel's URL-generation exceptions. Hidden items do not resolve routes.

Without `activeRoute()`, a named destination defaults to an exact route-name match. Literal URLs do not become active automatically. Explicit patterns use Laravel's `Str::is`, supporting multiple patterns and wildcards. `activeWhen()` is combined with pattern matching using OR and always runs once when supplied. Without a current named route, only `activeWhen()` can activate an item.

`isActive` describes the item itself. Active descendants do **not** make a parent `isActive`. `isOpen` is true when the item is active or any descendant is active/open. Groups always have `isActive = false`, but can have `isOpen = true` through children.

Each level is sorted by `sort` ascending. The default is `0`; equal values preserve registration/list order. Source definition order is unchanged. Cycles or nesting beyond 128 levels throw `LogicException`.

## Icon Metadata

```php
use Ahmdrv\Navigation\Data\Icon;

Navigation::item('Home')->icon('tabler tabler-home');
Navigation::item('Home')->icon(Icon::identifier('home'));
Navigation::item('Logo')->icon(Icon::trustedSvg('<svg>...</svg>'));
```

Identifiers and CSS classes use `['type' => 'identifier', 'value' => '...']`. Opt-in SVG uses `['type' => 'trusted_svg', 'value' => '...']`. The core does not wrap icons in HTML, render Blade, or validate SVG. Renderers handle label/identifier escaping and markup sanitization/trust policies. Never pass unsanitized user input as trusted SVG.

## Output Contract

`build(?NavigationContext $context = null): array` returns `list<ResolvedNavigationItem>`. Without an argument, the latest context comes from Laravel's request. `NavigationContext` provides `user(): ?Authenticatable`, `routeName(): ?string`, and `request(): Request`; supply a custom implementation to `build()` or bind it in the container.

DTOs and icon/badge metadata are `final readonly`. Children are readonly arrays containing recursively readonly DTOs; source arrays are copied without reference aliases. Consumers may reorder the root array without affecting the registry. Results contain no Closures, query builders, user models, or pending authorization evaluations.

`toArray()` converts recursively using the same keys for items and groups:

```php
[
    'kind' => 'item', // 'item' | 'group'
    'label' => 'Inbox', // plain text string
    'icon' => ['type' => 'identifier', 'value' => 'mail'], // or null / trusted_svg
    'url' => 'https://example.com/inbox', // string|null
    'target' => '_blank', // '_blank'|null
    'badge' => ['label' => 3, 'color' => 'danger'], // or null
    'sort' => 0, // int
    'isActive' => true, // bool
    'isOpen' => true, // bool
    'children' => [], // list using the same schema
]
```

Groups have `null` icon/url/target/badge values. The output contains no core-generated HTML, CSS state classes, or layout keys.

## Lifecycle and Workers

`NavigationManager` is a singleton definition registry for one application/worker. Register programmatic definitions once in a provider during bootstrap, rather than in middleware/controllers on every request. The registry file loads lazily once on the first build, appending after existing programmatic roots. It must return a list; invalid results throw `InvalidArgumentException`, and loading can be retried after correction. Missing files are considered loaded; restart workers to pick up registry/configuration changes.

The builder, context, and default authorization implementation are resolved from the active container on every build. The registry stores no users, Gate results, active states, or evaluated badges. Repeated builds produce new DTOs without adding roots. There is no global result cache or Closure serialization.

Definitions retained by workers must contain static values or callbacks that read context arguments during evaluation. Do not capture `auth()->user()`, requests, user models, or query results during bootstrap. Do not bind custom contexts/adapters that capture requests as singletons. For definitions that vary by user, use `visible`, `authorize`, or `items(Closure)` instead of calling `register()` per request. The package cannot remove state deliberately captured by application callbacks.

Tests replace requests and users within the same container/process. An actual Octane server has not been exercised in local verification.

## Development and Verification

```bash
composer install
composer format
composer analyse
composer test
composer validate --strict
```

Pint uses the PSR-12 preset with strict types. PHPStan/Larastan runs at level 6 without a baseline. Tests use PHPUnit 10/11 and Orchestra Testbench 8–11 according to the Laravel version, without a business database. CI covers the test matrix, formatting, static analysis, Dependabot, and changelog updates.

Compatibility matrix:

| Laravel | PHP versions tested in CI | Testbench | PHPUnit |
| --- | --- | --- | --- |
| 10 | 8.3 | 8 | 10 |
| 11 | 8.3, 8.4 | 9 | 11 |
| 12 | 8.3, 8.4, 8.5 | 10 | 11 |
| 13 | 8.3, 8.4, 8.5 | 11 | 11 |

Each combination runs on Linux and Windows using both `prefer-lowest` and `prefer-stable` dependencies. PHP 8.1/8.2 is not supported by this package.

Local verification on PHP 8.3.21 used the following combinations. Each version passed 51 tests (137 assertions) and static analysis at level 6:

| Laravel | Testbench | PHPUnit |
| --- | --- | --- |
| 10.50.3 | 8.39.0 | 10.5.66 |
| 11.57.0 | 9.18.0 | 11.5.57 |
| 12.70.0 | 10.12.0 | 11.5.57 |
| 13.38.0 | 11.3.0 | 11.5.57 |

Laravel 10 and 11 are past upstream security maintenance. Package compatibility does not extend framework security support. Their CI jobs allow dependencies with advisories to run compatibility tests and retain audit reports. This exception applies only inside CI and is absent from the distributed Composer configuration. Laravel 12/13 jobs use Composer's default security policy. See [Laravel's support policy](https://laravel.com/docs/13.x/releases#support-policy).

## License

MIT. See [LICENSE.md](LICENSE.md).
