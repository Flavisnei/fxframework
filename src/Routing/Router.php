<?php

declare(strict_types=1);

namespace Fx\Framework\Routing;

use Closure;
use Fx\Framework\Foundation\Application;
use Fx\Framework\Http\Request;
use Fx\Framework\Middleware\Pipeline;
use Fx\Framework\Middleware\Middleware;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /** @var list<Middleware|class-string<Middleware>> */
    private array $middleware = [];

    /** Middleware aplicado a todas as rotas deste router, antes do middleware da rota.
     * @param Middleware|class-string<Middleware> $middleware
     */
    public function middleware(Middleware|string $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    public function __construct(private readonly Application $app)
    {
    }

    public function get(string $uri, mixed $action): Route { return $this->add(['GET'], $uri, $action); }
    public function post(string $uri, mixed $action): Route { return $this->add(['POST'], $uri, $action); }
    public function put(string $uri, mixed $action): Route { return $this->add(['PUT'], $uri, $action); }
    public function patch(string $uri, mixed $action): Route { return $this->add(['PATCH'], $uri, $action); }
    public function delete(string $uri, mixed $action): Route { return $this->add(['DELETE'], $uri, $action); }
    public function any(string $uri, mixed $action): Route { return $this->add(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $uri, $action); }

    /** @param class-string $controller @return list<Route> */
    public function resource(string $uri, string $controller): array
    {
        $uri = $this->normalizeUri($uri);
        return [
            $this->get($uri, [$controller, 'index']),
            $this->post($uri, [$controller, 'store']),
            $this->get($uri . '/{id}', [$controller, 'show']),
            $this->put($uri . '/{id}', [$controller, 'update']),
            $this->delete($uri . '/{id}', [$controller, 'destroy']),
        ];
    }

    /** @param list<string> $methods */
    public function add(array $methods, string $uri, mixed $action): Route
    {
        $route = new Route(array_map('strtoupper', $methods), $this->normalizeUri($uri), $action);
        $this->routes[] = $route;
        return $route;
    }

    public function dispatch(?Request $request = null): mixed
    {
        $request ??= Request::capture();
        $this->app->setRequest($request);
        $path = '/' . trim($request->getPathInfo(), '/');
        $path = $path === '/' ? '/' : rtrim($path, '/');
        $allowed = [];

        foreach ($this->routes as $route) {
            $parameters = $this->match($route->uri, $path);
            if ($parameters === null) {
                continue;
            }
            if (!in_array($request->getMethod(), $route->methods, true)) {
                array_push($allowed, ...$route->methods);
                continue;
            }

            $destination = fn (Request $request): mixed => $this->invoke($route->action, $parameters, $request);
            return (new Pipeline($this->app))->run($request, [...$this->middleware, ...$route->middlewareStack()], $destination);
        }

        return $allowed !== []
            ? new Response('Method Not Allowed', 405, ['Allow' => implode(', ', array_unique($allowed))])
            : new Response('Not Found', 404);
    }

    /** @return list<Route> */
    public function routes(): array { return $this->routes; }

    private function invoke(mixed $action, array $parameters, Request $request): mixed
    {
        if (is_array($action) && count($action) === 2) {
            return (new ControllerDispatcher($this->app))->dispatch($action[0], (string) $action[1], $parameters, $request);
        }
        if (is_string($action) && str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action, 2);
            return (new ControllerDispatcher($this->app))->dispatch($controller, $method, $parameters, $request);
        }
        if ($action instanceof Closure || is_callable($action)) {
            $this->app->setRequest($request);
            return $this->app->call($action, $parameters);
        }
        throw new InvalidArgumentException('Acao de rota invalida.');
    }

    /** @return array<string,string>|null */
    private function match(string $routeUri, string $path): ?array
    {
        $names = [];
        if ($routeUri === '/') { return $path === '/' ? [] : null; }
        $pattern = '';
        foreach (explode('/', trim($routeUri, '/')) as $segment) {
            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)(\?)?\}$/', $segment, $placeholder) === 1) {
                $names[] = $placeholder[1];
                $pattern .= isset($placeholder[2]) && $placeholder[2] === '?' ? '(?:/([^/]+))?' : '/([^/]+)';
            } else {
                $pattern .= '/' . preg_quote($segment, '#');
            }
        }
        if (preg_match('#^' . $pattern . '$#', $path, $matches) !== 1) {
            return null;
        }
        array_shift($matches);
        $matches = array_pad($matches, count($names), '');
        return array_combine($names, array_map('urldecode', $matches)) ?: [];
    }

    private function normalizeUri(string $uri): string
    {
        $uri = '/' . trim($uri, '/');
        return $uri === '/' ? '/' : rtrim($uri, '/');
    }
}
