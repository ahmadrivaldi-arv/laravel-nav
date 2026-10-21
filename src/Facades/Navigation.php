<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation\Facades;

use Ahmdrv\Navigation\Contracts\NavigationContext;
use Ahmdrv\Navigation\NavigationGroup;
use Ahmdrv\Navigation\NavigationItem;
use Ahmdrv\Navigation\NavigationManager;
use Ahmdrv\Navigation\NavigationNode;
use Ahmdrv\Navigation\ResolvedNavigationItem;
use Illuminate\Support\Facades\Facade;

/**
 * @method static NavigationItem item(string $label)
 * @method static NavigationGroup group(string $label)
 * @method static void register(list<NavigationNode> $definitions)
 * @method static list<ResolvedNavigationItem> build(?NavigationContext $context = null)
 *
 * @see NavigationManager
 */
final class Navigation extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NavigationManager::class;
    }
}
