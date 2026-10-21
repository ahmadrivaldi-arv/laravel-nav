<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation;

use Ahmdrv\Navigation\Contracts\NavigationContext;
use Closure;
use InvalidArgumentException;
use LogicException;

final class NavigationManager
{
    /** @var list<NavigationNode> */
    private array $definitions = [];

    private bool $registryLoaded = false;

    private bool $loadingRegistry = false;

    /** @param Closure(): NavigationBuilder $builderResolver */
    public function __construct(private readonly Closure $builderResolver, private readonly ?string $registryPath = null)
    {
    }

    public function item(string $label): NavigationItem
    {
        return new NavigationItem($label);
    }

    public function group(string $label): NavigationGroup
    {
        return new NavigationGroup($label);
    }

    /** @param list<NavigationNode> $definitions */
    public function register(array $definitions): void
    {
        $this->validate($definitions);
        $this->definitions = [...$this->definitions, ...$definitions];
    }

    /** @return list<ResolvedNavigationItem> */
    public function build(?NavigationContext $context = null): array
    {
        $this->loadRegistry();

        return ($this->builderResolver)()->build($this->definitions, $context);
    }

    private function loadRegistry(): void
    {
        if ($this->registryLoaded) {
            return;
        }
        if ($this->loadingRegistry) {
            throw new LogicException('Do not call build() while loading the navigation registry.');
        }
        if ($this->registryPath === null || ! is_file($this->registryPath)) {
            $this->registryLoaded = true;

            return;
        }

        $this->loadingRegistry = true;
        try {
            // Isolated scope: the file returns definitions, and cannot access manager internals.
            $definitions = (static fn (string $path): mixed => require $path)($this->registryPath);
            if (! is_array($definitions)) {
                throw new InvalidArgumentException('The navigation registry file must return a list of definitions.');
            }
            $this->register($definitions);
            $this->registryLoaded = true;
        } finally {
            $this->loadingRegistry = false;
        }
    }

    /** @param array<mixed> $definitions */
    private function validate(array $definitions): void
    {
        if (! array_is_list($definitions)) {
            throw new InvalidArgumentException('Navigation roots must be a list of definitions.');
        }
        foreach ($definitions as $definition) {
            if (! $definition instanceof NavigationNode) {
                throw new InvalidArgumentException('Navigation roots must be items or groups.');
            }
        }
    }
}
