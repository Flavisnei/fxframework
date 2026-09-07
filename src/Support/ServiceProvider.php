<?php

declare(strict_types=1);

namespace Fx\Framework\Support;

use Fx\Framework\Foundation\Application;

abstract class ServiceProvider
{
    public function __construct(protected Application $app)
    {
    }

    public function register(): void
    {
    }

}
