<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation;

use Ahmdrv\Navigation\Data\Icon;
use Closure;

final class NavigationItem extends NavigationNode
{
    private ?Icon $iconMetadata = null;

    private ?string $routeName = null;

    /** @var array<string|int, mixed> */
    private array $routeParameters = [];

    private ?string $destination = null;

    private bool|Closure $newTab = false;

    private string|int|float|bool|Closure|null $badgeLabel = null;

    private string|Closure $badgeColor = 'primary';

    /** @var list<string> */
    private array $activePatterns = [];

    private ?Closure $activeCondition = null;

    public function icon(string|Icon $icon): self
    {
        $this->iconMetadata = is_string($icon) ? Icon::identifier($icon) : $icon;

        return $this;
    }

    /** @param array<string|int, mixed> $parameters */
    public function route(string $name, array $parameters = []): self
    {
        $this->routeName = $name;
        $this->routeParameters = $parameters;
        $this->destination = null;

        return $this;
    }

    public function url(?string $url): self
    {
        $this->destination = $url;
        $this->routeName = null;
        $this->routeParameters = [];

        return $this;
    }

    public function badge(string|int|float|bool|Closure|null $label, string|Closure $color = 'primary'): self
    {
        $this->badgeLabel = $label;
        $this->badgeColor = $color;

        return $this;
    }

    public function openUrlInNewTab(bool|Closure $condition = true): self
    {
        $this->newTab = $condition;

        return $this;
    }

    /** @param string|array<array-key, string> $patterns */
    public function activeRoute(string|array $patterns): self
    {
        $this->activePatterns = array_values((array) $patterns);

        return $this;
    }

    public function activeWhen(Closure $condition): self
    {
        $this->activeCondition = $condition;

        return $this;
    }

    public function getIcon(): ?Icon
    {
        return $this->iconMetadata;
    }

    public function getRouteName(): ?string
    {
        return $this->routeName;
    }

    /** @return array<string|int, mixed> */
    public function getRouteParameters(): array
    {
        return $this->routeParameters;
    }

    public function getUrl(): ?string
    {
        return $this->destination;
    }

    public function getNewTab(): bool|Closure
    {
        return $this->newTab;
    }

    public function getBadgeLabel(): string|int|float|bool|Closure|null
    {
        return $this->badgeLabel;
    }

    public function getBadgeColor(): string|Closure
    {
        return $this->badgeColor;
    }

    /** @return list<string> */
    public function getActivePatterns(): array
    {
        return $this->activePatterns;
    }

    public function getActiveCondition(): ?Closure
    {
        return $this->activeCondition;
    }
}
