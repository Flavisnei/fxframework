<?php

declare(strict_types=1);

namespace Fx\Framework\Middleware;

use Fx\Framework\Foundation\Application;
use Fx\Framework\Http\Request;
use InvalidArgumentException;

final class Pipeline
{
    public function __construct(private readonly Application $app)
    {
    }

    /** @param list<Middleware|class-string<Middleware>> $middleware */
    public function run(Request $request, array $middleware, callable $destination): mixed
    {
        $next = $destination;

        foreach (array_reverse($middleware) as $item) {
            $next = function (Request $request) use ($item, $next): mixed {
                $instance = is_string($item) ? $this->app->make($item) : $item;
                if (!$instance instanceof Middleware) {
                    throw new InvalidArgumentException('Middleware deve implementar ' . Middleware::class);
                }

                return $instance->process($request, $next);
            };
        }

        return $next($request);
    }
}
