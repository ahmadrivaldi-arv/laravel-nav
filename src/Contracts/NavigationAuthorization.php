<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation\Contracts;

use Ahmdrv\Navigation\NavigationNode;

interface NavigationAuthorization
{
    public function allows(NavigationNode $node, NavigationContext $context): bool;
}
