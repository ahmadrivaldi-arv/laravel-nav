<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation\Tests\Fixtures;

final class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->getAuthIdentifier() === 1;
    }
}
