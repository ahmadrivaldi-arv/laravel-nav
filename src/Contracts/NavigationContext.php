<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

interface NavigationContext
{
    public function user(): ?Authenticatable;

    public function routeName(): ?string;

    public function request(): Request;
}
