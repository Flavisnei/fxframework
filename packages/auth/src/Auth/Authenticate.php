<?php

declare(strict_types=1);

namespace Fx\Framework\Auth;

use Fx\Framework\Http\Request;
use Fx\Framework\Middleware\Middleware;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class Authenticate implements Middleware
{
    public function __construct(private readonly SessionGuard $guard)
    {
    }

    public function process(Request $request, callable $next): mixed
    {
        if ($this->guard->guest()) {
            return $request->expectsJson() ? new JsonResponse(['message' => 'Unauthenticated'], 401) : new Response('Unauthenticated', 401);
        }
        return $next($request);
    }
}
