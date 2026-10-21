<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation\Tests\Feature;

use Ahmdrv\Navigation\Contracts\RolePermissionAdapter;
use Ahmdrv\Navigation\Facades\Navigation;
use Ahmdrv\Navigation\Tests\Fixtures\Adapter;
use Ahmdrv\Navigation\Tests\Fixtures\Invoice;
use Ahmdrv\Navigation\Tests\Fixtures\InvoicePolicy;
use Ahmdrv\Navigation\Tests\Fixtures\User;
use Ahmdrv\Navigation\Tests\TestCase;
use Illuminate\Support\Facades\Gate;

final class AuthorizationTest extends TestCase
{
    public function test_guests_see_public_items_and_guest_aware_rules_but_not_user_rules(): void
    {
        Gate::define('guest-aware', static fn (?User $user): bool => $user === null);
        Gate::define('authenticated', static fn (User $user): bool => true);
        Navigation::register([
            Navigation::item('Public'),
            Navigation::item('Guest')->can('guest-aware'),
            Navigation::item('Authenticated')->can('authenticated'),
            Navigation::item('Role')->roles('admin'),
            Navigation::item('Permission')->permissions('read'),
            Navigation::item('Callback')->authorize(static fn ($user): bool => $user !== null),
        ]);
        self::assertSame(['Public', 'Guest'], array_column(Navigation::build($this->context()), 'label'));
    }

    public function test_missing_role_permission_adapter_denies_authenticated_users(): void
    {
        Navigation::register([
            Navigation::item('Role')->roles('admin'),
            Navigation::item('Permission')->permissions('read'),
            Navigation::item('Public'),
        ]);
        self::assertSame(['Public'], array_column(Navigation::build($this->context(new User(roles: ['admin'], permissions: ['read']))), 'label'));
    }

    public function test_every_visibility_and_authorization_rule_is_cumulative(): void
    {
        $this->app->bind(RolePermissionAdapter::class, Adapter::class);
        Gate::define('read', static fn (User $user, string $resource): bool => $resource === 'invoice');
        $item = Navigation::item('Restricted')
            ->visible()->visible(static fn () => true)->hidden(false)
            ->roles(['admin', 'staff'])->permissions(['read', 'edit'])
            ->can('read', ['invoice'])->authorize(static fn ($user): bool => $user->getAuthIdentifier() === 1);
        Navigation::register([$item]);
        self::assertCount(1, Navigation::build($this->context(new User(roles: ['staff'], permissions: ['edit']))));
        self::assertSame([], Navigation::build($this->context(new User(roles: ['staff']))));
        self::assertSame([], Navigation::build($this->context(new User(permissions: ['edit']))));
        self::assertSame([], Navigation::build($this->context(new User(2, ['staff'], ['edit']))));
        $item->visible(false)->visible(true);
        self::assertSame([], Navigation::build($this->context(new User(roles: ['staff'], permissions: ['edit']))));
    }

    public function test_repeated_roles_permissions_gate_and_authorize_calls_are_cumulative(): void
    {
        $this->app->bind(RolePermissionAdapter::class, Adapter::class);
        Gate::define('yes', static fn (User $user): bool => true);
        Gate::define('no', static fn (User $user): bool => false);
        Navigation::register([
            Navigation::item('Roles')->roles('staff')->roles('admin'),
            Navigation::item('Permissions')->permissions('read')->permissions('edit'),
            Navigation::item('Gate')->can('yes')->can('no'),
            Navigation::item('Authorize')->authorize(static fn () => true)->authorize(static fn () => false),
            Navigation::item('Hidden')->hidden()->hidden(false),
            Navigation::item('Empty roles')->roles([]),
        ]);
        self::assertSame([], Navigation::build($this->context(new User(roles: ['staff'], permissions: ['read']))));
    }

    public function test_policy_and_gate_arguments_and_guest_policy_denial(): void
    {
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Navigation::register([Navigation::item('Invoices')->can('viewAny', Invoice::class)]);
        self::assertCount(1, Navigation::build($this->context(new User())));
        self::assertSame([], Navigation::build($this->context(new User(2))));
        self::assertSame([], Navigation::build($this->context()));
    }

    public function test_denied_parent_never_evaluates_children_and_empty_groups_disappear(): void
    {
        Navigation::register([
            Navigation::group('Denied')->authorize(static fn () => false)->items(static function (): array {
                self::fail('Children of denied parent must not run.');
            }),
            Navigation::item('Denied item')->visible(false)->items(static function (): array {
                self::fail('Children of hidden item must not run.');
            }),
            Navigation::group('Empty')->items([Navigation::item('Hidden')->hidden()]),
            Navigation::group('Empty nested')->items([Navigation::group('Child')->items([])]),
        ]);
        self::assertSame([], Navigation::build());
    }

    public function test_visibility_callback_uses_current_user_and_context(): void
    {
        Navigation::register([Navigation::item('User 1')->visible(
            static fn ($user, $node, $context): bool =>
            $user?->getAuthIdentifier() === 1 && $node->getLabel() === 'User 1' && $context->routeName() === 'home'
        )]);
        self::assertCount(1, Navigation::build($this->context(new User(), 'home')));
        self::assertSame([], Navigation::build($this->context(new User(2), 'home')));
    }
}
