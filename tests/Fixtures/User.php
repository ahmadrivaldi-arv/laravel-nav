<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation\Tests\Fixtures;

use Illuminate\Auth\GenericUser;

final class User extends GenericUser
{
    /**
     * @param list<string> $roles
     * @param list<string> $permissions
     */
    public function __construct(int $id = 1, public array $roles = [], public array $permissions = [])
    {
        parent::__construct(['id' => $id]);
    }
}
