<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface RolePermissionAdapter
{
    /** @param list<string> $roles */
    public function hasAnyRole(Authenticatable $user, array $roles): bool;

    /** @param list<string> $permissions */
    public function hasAnyPermission(Authenticatable $user, array $permissions): bool;
}
