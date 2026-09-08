<?php

declare(strict_types=1);

namespace Fx\Framework\Routing;

use Fx\Framework\Middleware\Middleware;

final class Route
{
    /** @var list<Middleware|class-string<Middleware>> */
    private array $middleware = [];

    /** @param list<string> $methods */
    public function __construct(
        public readonly array $methods,
        public readonly string $uri,
        public readonly mixed $action,
        public readonly ?string $name = null
    ) {
    }

    /** @param Middleware|class-string<Middleware>|list<Middleware|class-string<Middleware>> $middleware */
    public function middleware(Middleware|string|array $middleware): self
    {
        array_push($this->middleware, ...($middleware instanceof Middleware || is_string($middleware) ? [$middleware] : $middleware));
        return $this;
    }

    /** @return list<Middleware|class-string<Middleware>> */
    public function middlewareStack(): array
    {
        return $this->middleware;
    }
}
