<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation;

use Ahmdrv\Navigation\Data\Badge;
use Ahmdrv\Navigation\Data\Icon;
use InvalidArgumentException;

final readonly class ResolvedNavigationItem
{
    /** @var list<ResolvedNavigationItem> */
    public array $children;

    /** @param list<ResolvedNavigationItem> $children */
    public function __construct(
        public string $kind,
        public string $label,
        public ?Icon $icon,
        public ?string $url,
        public ?string $target,
        public ?Badge $badge,
        public int $sort,
        public bool $isActive,
        public bool $isOpen,
        array $children,
    ) {
        if (! in_array($kind, ['item', 'group'], true)) {
            throw new InvalidArgumentException('Invalid resolved navigation kind or children list.');
        }

        self::validateChildren($children);
        // Rebuild the array to detach any references supplied by the caller.
        $this->children = array_map(static fn (self $child): self => $child, $children);
    }

    /** @param array<mixed> $children */
    private static function validateChildren(array $children): void
    {
        if (! array_is_list($children)) {
            throw new InvalidArgumentException('Resolved children must be a list.');
        }
        foreach ($children as $child) {
            if (! $child instanceof self) {
                throw new InvalidArgumentException('Resolved children must be immutable navigation items.');
            }
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'label' => $this->label,
            'icon' => $this->icon?->toArray(),
            'url' => $this->url,
            'target' => $this->target,
            'badge' => $this->badge?->toArray(),
            'sort' => $this->sort,
            'isActive' => $this->isActive,
            'isOpen' => $this->isOpen,
            'children' => array_map(static fn (self $child): array => $child->toArray(), $this->children),
        ];
    }
}
