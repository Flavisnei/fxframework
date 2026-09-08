<?php

declare(strict_types=1);

namespace Fx\Framework\Support;

use Fx\Framework\Foundation\CoreApplication;

abstract class ServiceProvider
{
    public function __construct(protected CoreApplication $app)
    {
    }

    public function register(): void
    {
    }

}
