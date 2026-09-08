<?php

declare(strict_types=1);

namespace Fx\Framework\Middleware;

use Fx\Framework\Http\Request;

interface Middleware
{
    public function process(Request $request, callable $next): mixed;
}
