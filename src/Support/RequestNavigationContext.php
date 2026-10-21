<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation\Support;

use Ahmdrv\Navigation\Contracts\NavigationContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

final readonly class RequestNavigationContext implements NavigationContext
{
    public function __construct(private Request $currentRequest)
    {
    }

    public function user(): ?Authenticatable
    {
        return $this->currentRequest->user();
    }

    public function routeName(): ?string
    {
        $route = $this->currentRequest->route();

        return $route instanceof Route ? $route->getName() : null;
    }

    public function request(): Request
    {
        return $this->currentRequest;
    }
}
