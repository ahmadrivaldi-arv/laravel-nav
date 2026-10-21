<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation\Tests\Feature;

use Ahmdrv\Navigation\Data\Icon;
use Ahmdrv\Navigation\Facades\Navigation;
use Ahmdrv\Navigation\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use LogicException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

final class NavigationTest extends TestCase
{
    public function test_factories_only_create_definitions_and_children_are_not_roots(): void
    {
        $child = Navigation::item('Child');
        $group = Navigation::group('Group')->items([$child]);
        Navigation::item('Unregistered');
        self::assertSame([], Navigation::build());
        Navigation::register([$group]);
        $tree = Navigation::build();
        self::assertCount(1, $tree);
        self::assertSame('group', $tree[0]->kind);
        self::assertSame('Child', $tree[0]->children[0]->label);
    }

    public function test_sorting_is_stable_at_every_level_and_does_not_mutate_definitions(): void
    {
        $first = Navigation::item('First')->sort(5);
        $second = Navigation::item('Second')->sort(5);
        $early = Navigation::item('Early')->sort(-1);
        $group = Navigation::group('Group')->items([$first, $second, $early]);
        Navigation::register([$first, $second, $group, $early]);
        $firstBuild = Navigation::build();
        self::assertSame(['Early', 'Group', 'First', 'Second'], array_column($firstBuild, 'label'));
        self::assertSame(['Early', 'First', 'Second'], array_column($firstBuild[1]->children, 'label'));
        self::assertSame([$first, $second, $early], $group->getItems());
        self::assertEquals($firstBuild, Navigation::build());
        self::assertNotSame($firstBuild[0], Navigation::build()[0]);
    }

    public function test_url_route_parameters_null_and_hash_and_last_destination_wins(): void
    {
        Route::get('invoices/{invoice}', static fn () => null)->name('invoices.show');
        Route::getRoutes()->refreshNameLookups();
        Navigation::register([
            Navigation::item('Invoice')->route('invoices.show', ['invoice' => 42, 'tab' => 'history']),
            Navigation::item('Parent'),
            Navigation::item('Hash')->url('#')->openUrlInNewTab(),
            Navigation::item('External')->route('missing')->url('https://example.com'),
            Navigation::item('Route')->url('#')->route('invoices.show', [7]),
        ]);
        $tree = Navigation::build();
        self::assertSame('http://localhost/invoices/42?tab=history', $tree[0]->url);
        self::assertNull($tree[1]->url);
        self::assertNull($tree[1]->target);
        self::assertSame('#', $tree[2]->url);
        self::assertSame('_blank', $tree[2]->target);
        self::assertSame('https://example.com', $tree[3]->url);
        self::assertSame('http://localhost/invoices/7', $tree[4]->url);
    }

    public function test_named_routes_are_resolved_at_build_after_registration(): void
    {
        Navigation::register([Navigation::item('Late')->route('late')]);
        Route::get('/late', static fn () => null)->name('late');
        Route::getRoutes()->refreshNameLookups();
        self::assertSame('http://localhost/late', Navigation::build()[0]->url);
    }

    public function test_missing_route_throws_the_laravel_exception(): void
    {
        Navigation::register([Navigation::item('Missing')->route('missing')]);
        $this->expectException(RouteNotFoundException::class);
        Navigation::build();
    }

    public function test_nested_active_and_open_state_and_multiple_wildcards(): void
    {
        $leaf = Navigation::item('Invoices')->activeRoute(['payments.*', 'invoices.*']);
        Navigation::register([Navigation::group('Finance')->items([
            Navigation::item('Billing')->items([$leaf]),
            Navigation::item('Other')->activeWhen(static fn () => false),
        ])]);
        $root = Navigation::build($this->context(name: 'invoices.show'))[0];
        self::assertFalse($root->isActive);
        self::assertTrue($root->isOpen);
        self::assertFalse($root->children[0]->isActive);
        self::assertTrue($root->children[0]->isOpen);
        self::assertTrue($root->children[0]->children[0]->isActive);
        self::assertTrue($root->children[0]->children[0]->isOpen);
        self::assertFalse($root->children[1]->isOpen);
        self::assertFalse(Navigation::build($this->context())[0]->isOpen);
    }

    public function test_named_route_defaults_to_exact_match_and_active_when_is_additive(): void
    {
        Route::get('/invoices', static fn () => null)->name('invoices.index');
        Route::getRoutes()->refreshNameLookups();
        $calls = 0;
        Navigation::register([
            Navigation::item('Exact')->route('invoices.index')->activeWhen(function () use (&$calls): bool {
                $calls++;
                return false;
            }),
            Navigation::item('Custom')->activeWhen(static fn ($user, $item, $context) => $context->request()->path() === 'navigation'),
        ]);
        self::assertTrue(Navigation::build($this->context(name: 'invoices.index'))[0]->isActive);
        self::assertSame(1, $calls);
        self::assertFalse(Navigation::build($this->context(name: 'invoices.show'))[0]->isActive);
        self::assertTrue(Navigation::build($this->context())[1]->isActive);
    }

    public function test_icons_remain_metadata_and_labels_remain_text(): void
    {
        Navigation::register([
            Navigation::item('<b>Text</b>')->icon('tabler tabler-home'),
            Navigation::item('SVG')->icon(Icon::trustedSvg('<svg><path /></svg>')),
        ]);
        $tree = Navigation::build();
        self::assertSame('<b>Text</b>', $tree[0]->label);
        self::assertSame(['type' => 'identifier', 'value' => 'tabler tabler-home'], $tree[0]->toArray()['icon']);
        self::assertSame(['type' => 'trusted_svg', 'value' => '<svg><path /></svg>'], $tree[1]->toArray()['icon']);
    }

    public function test_dynamic_children_are_evaluated_once_and_empty_groups_disappear(): void
    {
        $calls = 0;
        Navigation::register([Navigation::group('Dynamic')->items(function ($user, $item) use (&$calls): array {
            $calls++;
            self::assertSame('Dynamic', $item->getLabel());
            return [Navigation::item('Hidden')->visible(false)];
        }), Navigation::item('Empty parent')->items([])]);
        self::assertSame(['Empty parent'], array_column(Navigation::build(), 'label'));
        self::assertSame(1, $calls);
    }

    public function test_dynamic_children_and_target_receive_current_user_without_mutating_source(): void
    {
        $group = Navigation::group('Users')->items(static fn ($user): array => [
            Navigation::item('User '.$user?->getAuthIdentifier())
                ->url('/profile')->openUrlInNewTab(static fn ($user): bool => $user?->getAuthIdentifier() === 1),
        ]);
        Navigation::register([$group]);
        $first = Navigation::build($this->context(new \Ahmdrv\Navigation\Tests\Fixtures\User(1)))[0];
        $second = Navigation::build($this->context(new \Ahmdrv\Navigation\Tests\Fixtures\User(2)))[0];
        self::assertSame('User 1', $first->children[0]->label);
        self::assertSame('_blank', $first->children[0]->target);
        self::assertSame('User 2', $second->children[0]->label);
        self::assertNull($second->children[0]->target);
        self::assertInstanceOf(\Closure::class, $group->getItems());
    }

    public function test_hidden_missing_route_is_not_resolved(): void
    {
        Navigation::register([Navigation::item('Missing')->route('missing')->visible(false)]);
        self::assertSame([], Navigation::build());
    }

    public function test_children_callback_requires_a_list_of_definitions(): void
    {
        Navigation::register([Navigation::group('Invalid')->items(static fn () => null)]);
        $this->expectException(\InvalidArgumentException::class);
        Navigation::build();
    }

    public function test_cycles_are_reported(): void
    {
        $item = Navigation::item('Cycle');
        $item->items([$item]);
        Navigation::register([$item]);
        $this->expectException(LogicException::class);
        Navigation::build();
    }
}
