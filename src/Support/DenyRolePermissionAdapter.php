<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation\Support;

use Ahmdrv\Navigation\Contracts\RolePermissionAdapter;
use Illuminate\Contracts\Auth\Authenticatable;

final class DenyRolePermissionAdapter implements RolePermissionAdapter
{
    public function hasAnyRole(Authenticatable $user, array $roles): bool
    {
        return false;
    }

    public function hasAnyPermission(Authenticatable $user, array $permissions): bool
    {
        return false;
    }
}
