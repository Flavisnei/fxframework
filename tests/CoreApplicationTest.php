<?php

declare(strict_types=1);

namespace Fx\Framework\Tests;

use Fx\Framework\Config\Repository;
use Fx\Framework\Container\Container;
use Fx\Framework\Foundation\Application;
use Fx\Framework\Foundation\CoreApplication;
use Fx\Framework\Support\ServiceProvider;
use PHPUnit\Framework\TestCase;

final class CoreTestProvider extends ServiceProvider
{
    public function register(): void { $this->app->instance('answer', 42); }
    public function boot(Repository $config): void { $config->set('boot.count', $config->get('boot.count', 0) + 1); }
}

final class CoreApplicationTest extends TestCase
{
    public function testProvidersBootOnceAndLateProvidersBootImmediately(): void
    {
        $app = new CoreApplication(__DIR__);
        $first = $app->register(CoreTestProvider::class);
        self::assertSame($first, $app->register(CoreTestProvider::class));
        self::assertSame($app, $app->registerProviders([])->boot()->boot());
        self::assertTrue($app->isBooted());
        self::assertSame(42, $app->make('answer'));
        self::assertSame(1, $app->config()->get('boot.count'));
        self::assertCount(1, $app->providers());

        $late = new CoreApplication(__DIR__);
        $late->boot();
        $late->register(CoreTestProvider::class);
        self::assertSame(1, $late->config()->get('boot.count'));
    }

    public function testConfigurationSupportsNestedValuesAndPreservesNull(): void
    {
        $config = new Repository(['mail' => ['host' => 'localhost', 'password' => null]]);
        self::assertSame('localhost', $config->get('mail.host'));
        self::assertNull($config->get('mail.password', 'fallback'));
        self::assertSame('fallback', $config->get('mail.port', 'fallback'));
        $config->set('mail.port', 2525);
        self::assertSame(['mail' => ['host' => 'localhost', 'password' => null, 'port' => 2525]], $config->all());
    }

    public function testCoreInstancesAreIsolatedAndDoNotReplaceHostContainer(): void
    {
        $host = \Illuminate\Container\Container::getInstance();
        $first = new CoreApplication('/first');
        $second = new CoreApplication('/second');
        $first->config()->set('app.name', 'First');
        self::assertNull($second->config()->get('app.name'));
        self::assertSame($host, \Illuminate\Container\Container::getInstance());
        self::assertSame($first, $first->make(CoreApplication::class));
        self::assertSame($first, $first->make(Container::class));
        self::assertSame($first->config(), $first->make('config'));
        self::assertSame($second, $second->make('app'));
        self::assertFalse($first->bound('request'));
        self::assertFalse($first->bound(\Fx\Framework\Routing\Router::class));
    }

    public function testFullApplicationRetainsOriginalContainerAndProviderApi(): void
    {
        $app = new Application(__DIR__);
        self::assertInstanceOf(Container::class, $app);
        self::assertSame($app, \Illuminate\Container\Container::getInstance());
        self::assertSame($app, $app->make(Application::class));
        $app->register(CoreTestProvider::class);
        $app->boot();
        self::assertSame(42, $app->make('answer'));
        self::assertTrue($app->bound(\Fx\Framework\Routing\Router::class));
    }
}
