<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation\Tests;

use Ahmdrv\Navigation\Contracts\NavigationContext;
use Ahmdrv\Navigation\NavigationManager;
use Ahmdrv\Navigation\NavigationServiceProvider;
use Ahmdrv\Navigation\Support\RequestNavigationContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [NavigationServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('navigation.registry', null);
    }

    protected function navigation(): NavigationManager
    {
        return $this->app->make(NavigationManager::class);
    }

    protected function context(?Authenticatable $user = null, ?string $name = null): NavigationContext
    {
        $request = Request::create('/navigation');
        $request->setUserResolver(static fn () => $user);
        $route = new Route('GET', '/navigation', static fn () => null);
        if ($name !== null) {
            $route->name($name);
        }
        $request->setRouteResolver(static fn () => $route);

        return new RequestNavigationContext($request);
    }
}
