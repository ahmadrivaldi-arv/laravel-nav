<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation\Tests\Fixtures;

use Ahmdrv\Navigation\Contracts\RolePermissionAdapter;
use Illuminate\Contracts\Auth\Authenticatable;

final class Adapter implements RolePermissionAdapter
{
    public function hasAnyRole(Authenticatable $user, array $roles): bool
    {
        return $user instanceof User && (bool) array_intersect($roles, $user->roles);
    }

    public function hasAnyPermission(Authenticatable $user, array $permissions): bool
    {
        return $user instanceof User && (bool) array_intersect($permissions, $user->permissions);
    }
}
