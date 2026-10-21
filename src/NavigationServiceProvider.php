<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation;

use Ahmdrv\Navigation\Contracts\NavigationAuthorization;
use Ahmdrv\Navigation\Contracts\NavigationContext;
use Ahmdrv\Navigation\Contracts\RolePermissionAdapter;
use Ahmdrv\Navigation\Support\DefaultNavigationAuthorization;
use Ahmdrv\Navigation\Support\DenyRolePermissionAdapter;
use Ahmdrv\Navigation\Support\RequestNavigationContext;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Container\Container;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class NavigationServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('laravel-nav')->hasConfigFile('navigation');
    }

    public function packageRegistered(): void
    {
        $this->app->bindIf(RolePermissionAdapter::class, DenyRolePermissionAdapter::class);
        $this->app->bindIf(NavigationAuthorization::class, DefaultNavigationAuthorization::class);
        $this->app->bindIf(NavigationContext::class, fn (Application $app): NavigationContext => new RequestNavigationContext($app->make('request')));

        $this->app->singleton(NavigationManager::class, function (Application $app): NavigationManager {
            $path = $app->make('config')->get('navigation.registry');

            return new NavigationManager(
                static fn (): NavigationBuilder => Container::getInstance()->make(NavigationBuilder::class),
                $path,
            );
        });
    }
}
