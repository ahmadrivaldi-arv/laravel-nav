<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation;

use Closure;

abstract class NavigationNode
{
    protected int $sortOrder = 0;

    /** @var list<NavigationNode>|Closure */
    protected array|Closure $childDefinitions = [];

    /** @var list<bool|Closure> */
    protected array $visibility = [];

    /** @var list<bool|Closure> */
    protected array $hidden = [];

    /** @var list<list<string>> */
    protected array $roles = [];

    /** @var list<list<string>> */
    protected array $permissions = [];

    /** @var list<array{string, mixed}> */
    protected array $abilities = [];

    /** @var list<Closure> */
    protected array $authorization = [];

    public function __construct(protected string $text)
    {
    }

    public function label(string $label): static
    {
        $this->text = $label;

        return $this;
    }

    public function sort(int $sort): static
    {
        $this->sortOrder = $sort;

        return $this;
    }

    /** @param list<NavigationNode>|Closure $items */
    public function items(array|Closure $items): static
    {
        $this->childDefinitions = $items;

        return $this;
    }

    public function visible(bool|Closure $condition = true): static
    {
        $this->visibility[] = $condition;

        return $this;
    }

    public function hidden(bool|Closure $condition = true): static
    {
        $this->hidden[] = $condition;

        return $this;
    }

    /** @param string|array<array-key, string> $roles */
    public function roles(string|array $roles): static
    {
        $this->roles[] = array_values((array) $roles);

        return $this;
    }

    /** @param string|array<array-key, string> $permissions */
    public function permissions(string|array $permissions): static
    {
        $this->permissions[] = array_values((array) $permissions);

        return $this;
    }

    public function can(string $ability, mixed $arguments = []): static
    {
        $this->abilities[] = [$ability, $arguments];

        return $this;
    }

    public function authorize(Closure $condition): static
    {
        $this->authorization[] = $condition;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->text;
    }

    public function getSort(): int
    {
        return $this->sortOrder;
    }

    /** @return list<NavigationNode>|Closure */
    public function getItems(): array|Closure
    {
        return $this->childDefinitions;
    }

    /** @return list<bool|Closure> */
    public function visibilityRules(): array
    {
        return $this->visibility;
    }

    /** @return list<bool|Closure> */
    public function hiddenRules(): array
    {
        return $this->hidden;
    }

    /** @return list<list<string>> */
    public function roleRules(): array
    {
        return $this->roles;
    }

    /** @return list<list<string>> */
    public function permissionRules(): array
    {
        return $this->permissions;
    }

    /** @return list<array{string, mixed}> */
    public function gateRules(): array
    {
        return $this->abilities;
    }

    /** @return list<Closure> */
    public function authorizationRules(): array
    {
        return $this->authorization;
    }
}
