<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation\Tests\Feature;

use Ahmdrv\Navigation\Contracts\NavigationAuthorization;
use Ahmdrv\Navigation\Contracts\NavigationContext;
use Ahmdrv\Navigation\Contracts\RolePermissionAdapter;
use Ahmdrv\Navigation\Facades\Navigation;
use Ahmdrv\Navigation\NavigationItem;
use Ahmdrv\Navigation\NavigationManager;
use Ahmdrv\Navigation\NavigationServiceProvider;
use Ahmdrv\Navigation\Support\DenyRolePermissionAdapter;
use Ahmdrv\Navigation\Tests\Fixtures\Adapter;
use Ahmdrv\Navigation\Tests\Fixtures\User;
use Ahmdrv\Navigation\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class LifecycleTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    public function test_provider_bindings_discovery_alias_and_publishable_configuration(): void
    {
        self::assertSame($this->navigation(), $this->app->make(NavigationManager::class));
        self::assertInstanceOf(NavigationContext::class, $this->app->make(NavigationContext::class));
        self::assertInstanceOf(NavigationAuthorization::class, $this->app->make(NavigationAuthorization::class));
        self::assertInstanceOf(DenyRolePermissionAdapter::class, $this->app->make(RolePermissionAdapter::class));
        $metadata = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([NavigationServiceProvider::class], $metadata['extra']['laravel']['providers']);
        self::assertSame(Navigation::class, $metadata['extra']['laravel']['aliases']['Navigation']);
        $paths = ServiceProvider::pathsToPublish(NavigationServiceProvider::class, 'nav-config');
        self::assertContains(config_path('navigation.php'), $paths);
    }

    public function test_custom_registry_file_loads_once_and_appends_to_programmatic_roots(): void
    {
        $file = $this->registry(<<<'REGISTRY'
<?php
use Ahmdrv\Navigation\Facades\Navigation;
return [Navigation::group('File')->items([Navigation::item('Child')])];
REGISTRY);
        $this->app['config']->set('navigation.registry', $file);
        Navigation::register([Navigation::item('Programmatic')]);
        $first = Navigation::build();
        file_put_contents($file, '<?php throw new RuntimeException("Registry loaded twice");');
        self::assertEquals($first, Navigation::build());
        self::assertSame(['Programmatic', 'File'], array_column($first, 'label'));
        self::assertCount(1, $first[1]->children);
    }

    public function test_missing_and_disabled_registry_files_are_optional(): void
    {
        $this->app['config']->set('navigation.registry', sys_get_temp_dir().'/nav-missing-'.bin2hex(random_bytes(8)).'.php');
        self::assertSame([], Navigation::build());
    }

    public function test_invalid_registry_file_fails_without_marking_it_loaded(): void
    {
        $file = $this->registry('<?php return null;');
        $this->app['config']->set('navigation.registry', $file);
        try {
            Navigation::build();
            self::fail('Invalid registry should throw.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('must return a list', $exception->getMessage());
        }
        file_put_contents($file, '<?php return [new \\Ahmdrv\\Navigation\\NavigationItem("Recovered")];');
        self::assertSame('Recovered', Navigation::build()[0]->label);
    }

    public function test_root_registration_validation_is_atomic(): void
    {
        $manager = $this->navigation();
        try {
            $manager->register([new NavigationItem('Valid'), 'invalid']);
            self::fail('Expected invalid definition.');
        } catch (InvalidArgumentException) {
            self::assertSame([], $manager->build());
        }
    }

    public function test_same_manager_rebuilds_against_current_request_user_gate_badge_and_route(): void
    {
        $this->app->bind(RolePermissionAdapter::class, Adapter::class);
        Gate::define('admin', static fn (User $user): bool => $user->getAuthIdentifier() === 1);
        Navigation::register([
            Navigation::item('Profile')->badge(static fn ($user): int => $user?->getAuthIdentifier() ?? 0)->activeRoute('home'),
            Navigation::item('Admin')->can('admin')->roles('admin'),
        ]);
        $manager = $this->navigation();
        $this->replaceRequest($this->context(new User(1, ['admin']), 'home')->request());
        $first = $manager->build();
        self::assertCount(2, $first);
        self::assertSame(1, $first[0]->badge->label);
        self::assertTrue($first[0]->isActive);
        $this->replaceRequest($this->context(new User(2), 'other')->request());
        $second = $manager->build();
        self::assertCount(1, $second);
        self::assertSame(2, $second[0]->badge->label);
        self::assertFalse($second[0]->isActive);
        $this->replaceRequest($this->context()->request());
        self::assertNull($manager->build()[0]->badge);
        self::assertSame(1, $first[0]->badge->label);
        self::assertSame($manager, $this->navigation());
    }

    public function test_http_requests_consume_core_with_current_route_context(): void
    {
        Navigation::register([Navigation::item('Home')->activeRoute('home')]);
        $this->app['router']->get('/navigation-test', static fn () => array_map(static fn ($item) => $item->toArray(), Navigation::build()))->name('home');
        $this->get('/navigation-test')->assertOk()->assertJsonPath('0.isActive', true)->assertJsonPath('0.kind', 'item');
    }

    public function test_custom_context_binding_is_used_by_default_build(): void
    {
        $context = $this->context(new User(9), 'custom');
        $this->app->bind(NavigationContext::class, static fn () => $context);
        Navigation::register([Navigation::item('Custom')->activeRoute('custom')->badge(static fn ($user) => $user->getAuthIdentifier())]);
        $item = Navigation::build()[0];
        self::assertTrue($item->isActive);
        self::assertSame(9, $item->badge->label);
    }

    private function replaceRequest(Request $request): void
    {
        $resolver = $request->getUserResolver();
        $this->app->instance('request', $request);
        // Laravel's request rebind installs its auth resolver; emulate later auth middleware.
        $request->setUserResolver($resolver);
    }

    private function registry(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'laravel-nav-');
        $this->temporaryFiles[] = $path;
        file_put_contents($path, $contents);

        return $path;
    }
}
