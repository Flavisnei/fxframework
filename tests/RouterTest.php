<?php

declare(strict_types=1);

namespace Fx\Framework\Tests;

use Fx\Framework\Foundation\Application;
use Fx\Framework\Http\ExceptionHandler;
use Fx\Framework\Http\Kernel;
use Fx\Framework\Http\Request;
use Fx\Framework\Middleware\Middleware;
use Fx\Framework\Routing\Router;
use PHPUnit\Framework\TestCase;

final class HeaderMiddleware implements Middleware
{
    public function process(Request $request, callable $next): mixed
    {
        return ['result' => $next($request), 'middleware' => true];
    }
}

final class RouterTest extends TestCase
{
    public function testMiddlewareConstructorReceivesCurrentRequest(): void
    {
        $app = new Application(__DIR__);
        $router = $app->make(Router::class);
        $router->get('/current', fn (Request $request) => $request)->middleware(RequestAwareMiddleware::class);
        foreach (['first', 'second'] as $value) {
            $request = Request::create('/current?value=' . $value);
            self::assertSame([$request, $request], $router->dispatch($request));
        }
    }

    public function testItDispatchesDynamicRoutesThroughMiddleware(): void
    {
        $app = new Application(__DIR__);
        $router = $app->make(Router::class);
        $router->get('/users/{id}', fn (string $id): string => $id)->middleware(HeaderMiddleware::class);

        $response = (new Kernel($router, new ExceptionHandler()))->handle(Request::create('/users/42'));

        self::assertSame(200, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString('{"result":"42","middleware":true}', (string) $response->getContent());
    }

    public function testItReturnsNotFoundAndMethodNotAllowed(): void
    {
        $app = new Application(__DIR__);
        $router = $app->make(Router::class);
        $router->post('/users', fn (): string => 'ok');

        self::assertSame(405, $router->dispatch(Request::create('/users', 'GET'))->getStatusCode());
        self::assertSame(404, $router->dispatch(Request::create('/missing'))->getStatusCode());
    }
}

final class RequestAwareMiddleware implements Middleware
{
    public function __construct(private Request $request) {}

    public function process(Request $request, callable $next): mixed
    {
        return [$this->request, $next($request)];
    }
}
