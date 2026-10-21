<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation\Tests\Unit;

use Ahmdrv\Navigation\Data\Badge;
use Ahmdrv\Navigation\Data\Icon;
use Ahmdrv\Navigation\NavigationGroup;
use Ahmdrv\Navigation\NavigationItem;
use Ahmdrv\Navigation\ResolvedNavigationItem;
use Ahmdrv\Navigation\Support\CallbackEvaluator;
use Error;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DefinitionsTest extends TestCase
{
    public function test_fluent_types_labels_and_defaults(): void
    {
        $item = new NavigationItem('Before');
        self::assertSame($item, $item->label('After')->sort(10)->url('#')->icon('home')->badge(3)->openUrlInNewTab());
        self::assertSame('After', $item->getLabel());
        self::assertSame(10, $item->getSort());
        self::assertSame(0, (new NavigationGroup('Group'))->getSort());
        self::assertSame([], (new NavigationItem('Item'))->getItems());
    }

    public function test_recursive_schema_and_no_unresolved_objects(): void
    {
        $leaf = $this->resolved();
        $root = new ResolvedNavigationItem('group', 'Group', null, null, null, null, 0, false, true, [$leaf]);
        self::assertSame([
            'kind' => 'item', 'label' => 'Leaf',
            'icon' => ['type' => 'identifier', 'value' => 'home'],
            'url' => '#', 'target' => '_blank',
            'badge' => ['label' => 5, 'color' => 'danger'],
            'sort' => 10, 'isActive' => true, 'isOpen' => true, 'children' => [],
        ], $root->toArray()['children'][0]);
        self::assertSame('Group', $root->toArray()['label']);
        self::assertSame([], json_decode(json_encode($root->toArray(), JSON_THROW_ON_ERROR), true)['children'][0]['children']);
    }

    public function test_dto_properties_are_immutable(): void
    {
        $item = $this->resolved();
        $this->expectException(Error::class);
        $item->label = 'Changed';
    }

    public function test_children_array_is_immutable(): void
    {
        $item = $this->resolved();
        $this->expectException(Error::class);
        $item->children[] = $this->resolved();
    }

    public function test_nested_children_are_immutable(): void
    {
        $root = new ResolvedNavigationItem('group', 'Group', null, null, null, null, 0, false, false, [$this->resolved()]);
        $this->expectException(Error::class);
        $root->children[0]->label = 'Changed';
    }

    public function test_badge_metadata_is_immutable(): void
    {
        $this->expectException(Error::class);
        $this->resolved()->badge->color = 'changed';
    }

    public function test_icon_metadata_is_immutable(): void
    {
        $this->expectException(Error::class);
        $this->resolved()->icon->value = 'changed';
    }

    public function test_children_detach_external_references_and_array_conversion_copies_data(): void
    {
        $child = $this->resolved();
        $children = [&$child];
        $root = new ResolvedNavigationItem('group', 'Group', null, null, null, null, 0, false, false, $children);
        $child = null;
        self::assertSame('Leaf', $root->children[0]->label);
        $array = $root->toArray();
        $array['children'][0]['label'] = 'Changed';
        self::assertSame('Leaf', $root->children[0]->label);
    }

    public function test_unresolved_children_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ResolvedNavigationItem('group', 'Group', null, null, null, null, 0, false, false, [new NavigationItem('Unresolved')]);
    }

    public function test_callbacks_accept_prefix_optional_and_variadic_arguments(): void
    {
        $evaluator = new CallbackEvaluator();
        self::assertSame(42, $evaluator->evaluate(42, []));
        self::assertSame('none', $evaluator->evaluate(static fn () => 'none', [1, 2, 3]));
        self::assertSame([1, 2, 3, 'default'], $evaluator->evaluate(static fn ($a, $b, $c, $d = 'default') => [$a, $b, $c, $d], [1, 2, 3]));
        self::assertSame([1, 2], $evaluator->evaluate(static fn (...$args) => $args, [1, 2]));
    }

    public function test_callback_unsupported_required_arguments_throw(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CallbackEvaluator())->evaluate(static fn ($a, $b) => null, [1]);
    }

    private function resolved(): ResolvedNavigationItem
    {
        return new ResolvedNavigationItem('item', 'Leaf', Icon::identifier('home'), '#', '_blank', new Badge(5, 'danger'), 10, true, true, []);
    }
}
