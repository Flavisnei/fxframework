<?php

declare(strict_types=1);

namespace Fx\Framework\Foundation;

use Fx\Framework\Container\Container;
use Fx\Framework\Config\Repository;
use Fx\Framework\Support\ServiceProvider;
use InvalidArgumentException;

class CoreApplication extends Container
{
    /** @var list<ServiceProvider> */
    private array $providers = [];

    private bool $booted = false;

    public function __construct(private readonly string $basePath, bool $registerAsGlobal = false)
    {
        parent::__construct($registerAsGlobal);

        $this->instance(self::class, $this);
        $this->instance(static::class, $this);
        $this->instance('app', $this);
        $this->instance('path.base', $this->basePath());
        $this->instance(Repository::class, new Repository());
        $this->alias(Repository::class, 'config');
    }

    public function basePath(string $path = ''): string
    {
        $base = rtrim($this->basePath, '/\\');
        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }

    public function config(): Repository { return $this->make(Repository::class); }

    /**
     * @param ServiceProvider|class-string<ServiceProvider> $provider
     */
    public function register(ServiceProvider|string $provider): ServiceProvider
    {
        if (is_string($provider)) {
            if (!is_subclass_of($provider, ServiceProvider::class)) {
                throw new InvalidArgumentException("{$provider} deve estender " . ServiceProvider::class);
            }
            $provider = new $provider($this);
        }

        foreach ($this->providers as $registered) {
            if ($registered::class === $provider::class) {
                return $registered;
            }
        }

        $provider->register();
        $this->providers[] = $provider;

        if ($this->booted && method_exists($provider, 'boot')) {
            $this->call([$provider, 'boot']);
        }

        return $provider;
    }

    /** @param iterable<class-string<ServiceProvider>|ServiceProvider> $providers */
    public function registerProviders(iterable $providers): static
    {
        foreach ($providers as $provider) {
            $this->register($provider);
        }

        return $this;
    }

    public function boot(): static
    {
        if ($this->booted) {
            return $this;
        }

        foreach ($this->providers as $provider) {
            if (method_exists($provider, 'boot')) {
                $this->call([$provider, 'boot']);
            }
        }

        $this->booted = true;
        return $this;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    /** @return list<ServiceProvider> */
    public function providers(): array
    {
        return $this->providers;
    }
}
