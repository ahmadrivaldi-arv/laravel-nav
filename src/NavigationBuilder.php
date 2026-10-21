<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation;

use Ahmdrv\Navigation\Contracts\NavigationAuthorization;
use Ahmdrv\Navigation\Contracts\NavigationContext;
use Ahmdrv\Navigation\Data\Badge;
use Ahmdrv\Navigation\Support\CallbackEvaluator;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final readonly class NavigationBuilder
{
    public function __construct(
        private NavigationContext $context,
        private NavigationAuthorization $authorization,
        private UrlGenerator $urls,
        private CallbackEvaluator $callbacks,
    ) {
    }

    /**
     * @param list<NavigationNode> $definitions
     * @return list<ResolvedNavigationItem>
     */
    public function build(array $definitions, ?NavigationContext $context = null): array
    {
        return $this->resolve($definitions, $context ?? $this->context, []);
    }

    /**
     * @param list<NavigationNode> $definitions
     * @param list<int> $ancestors
     * @return list<ResolvedNavigationItem>
     */
    private function resolve(array $definitions, NavigationContext $context, array $ancestors): array
    {
        $this->validateDefinitions($definitions);

        // PHP 8's stable sort preserves registration order when comparison returns zero.
        usort($definitions, static fn (NavigationNode $a, NavigationNode $b): int => $a->getSort() <=> $b->getSort());
        $resolved = [];

        foreach ($definitions as $definition) {
            if (! $this->authorization->allows($definition, $context)) {
                continue;
            }

            $id = spl_object_id($definition);
            if (in_array($id, $ancestors, true) || count($ancestors) >= 128) {
                throw new LogicException('Navigation definitions contain a cycle or exceed 128 levels.');
            }

            $arguments = [$context->user(), $definition, $context];
            $children = $this->callbacks->evaluate($definition->getItems(), $arguments);
            if (! is_array($children) || ! array_is_list($children)) {
                throw new InvalidArgumentException('Navigation children callback must return a list of definitions.');
            }
            $children = $this->resolve($children, $context, [...$ancestors, $id]);

            if ($definition instanceof NavigationGroup && $children === []) {
                continue;
            }

            $active = false;
            $url = null;
            $target = null;
            $badge = null;
            $icon = null;

            if ($definition instanceof NavigationItem) {
                $icon = $definition->getIcon();
                $url = $definition->getRouteName() !== null
                    ? $this->urls->route($definition->getRouteName(), $definition->getRouteParameters())
                    : $definition->getUrl();
                $target = $this->callbacks->evaluate($definition->getNewTab(), $arguments) ? '_blank' : null;
                $patterns = $definition->getActivePatterns();
                // A named destination is the default exact active pattern.
                if ($patterns === [] && $definition->getRouteName() !== null) {
                    $patterns = [$definition->getRouteName()];
                }
                $active = $context->routeName() !== null && Str::is($patterns, $context->routeName());
                // Always evaluate an explicitly supplied active condition once.
                $condition = $definition->getActiveCondition();
                if ($condition !== null) {
                    $active = (bool) $this->callbacks->evaluate($condition, $arguments) || $active;
                }
                $badge = $this->badge($definition, $arguments);
            }

            $open = $active;
            foreach ($children as $child) {
                $open = $open || $child->isActive || $child->isOpen;
            }

            $resolved[] = new ResolvedNavigationItem(
                kind: $definition instanceof NavigationGroup ? 'group' : 'item',
                label: $definition->getLabel(),
                icon: $icon,
                url: $url,
                target: $target,
                badge: $badge,
                sort: $definition->getSort(),
                isActive: $active,
                isOpen: $open,
                children: $children,
            );
        }

        return $resolved;
    }

    /** @param array<mixed> $definitions */
    private function validateDefinitions(array $definitions): void
    {
        if (! array_is_list($definitions)) {
            throw new InvalidArgumentException('Navigation definitions must be a list.');
        }
        foreach ($definitions as $definition) {
            if (! $definition instanceof NavigationNode) {
                throw new InvalidArgumentException('Navigation definitions must be items or groups.');
            }
        }
    }

    /** @param list<mixed> $arguments */
    private function badge(NavigationItem $item, array $arguments): ?Badge
    {
        $label = $this->callbacks->evaluate($item->getBadgeLabel(), $arguments);

        if (in_array($label, [null, false, '', 0, 0.0, '0'], true)) {
            return null;
        }

        if (! is_string($label) && ! is_int($label) && ! is_float($label)) {
            throw new InvalidArgumentException('A resolved badge label must be a string, integer, float, or an empty badge value.');
        }

        $color = $this->callbacks->evaluate($item->getBadgeColor(), [$label, ...$arguments]);
        if (! is_string($color)) {
            throw new InvalidArgumentException('A resolved badge color must be a string.');
        }

        return new Badge($label, $color);
    }
}
