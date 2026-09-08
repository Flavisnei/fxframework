<?php

declare(strict_types=1);

namespace Example;

use Fx\Framework\Support\ServiceProvider;

final class HelloProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance('hello.message', 'Modulo hello carregado.');
    }
}
