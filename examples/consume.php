<?php

declare(strict_types=1);

use Ahmdrv\Navigation\Facades\Navigation;
use Ahmdrv\Navigation\ResolvedNavigationItem;

// Example controller body in the consuming Laravel application.
// The application chooses its own view or API representation.
$items = Navigation::build();

$data = array_map(
    static fn (ResolvedNavigationItem $item): array => $item->toArray(),
    $items,
);

return ['navigation' => $data];
