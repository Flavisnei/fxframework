<?php

declare(strict_types=1);

namespace Fx\Framework\Routing;

use Fx\Framework\Foundation\Application;
use Fx\Framework\Http\Request;
use Illuminate\Http\Request as IlluminateRequest;
use InvalidArgumentException;
use ReflectionMethod;
use ReflectionNamedType;

final class ControllerDispatcher
{
    public function __construct(private readonly Application $app)
    {
    }

    /**
     * @param object|class-string $controller
     * @param array<string, mixed> $routeParameters Parametros nomeados da rota.
     */
    public function dispatch(
        object|string $controller,
        string $method,
        array $routeParameters = [],
        ?Request $request = null
    ): mixed {
        $request ??= Request::capture();
        $controller = is_string($controller) ? $this->app->make($controller) : $controller;

        if (!method_exists($controller, $method) || !(new ReflectionMethod($controller, $method))->isPublic()) {
            throw new InvalidArgumentException(
                sprintf('O metodo publico %s::%s nao existe.', $controller::class, $method)
            );
        }

        // A mesma instancia fica disponivel para ambos os type hints.
        $this->app->instance(Request::class, $request);
        $this->app->instance(IlluminateRequest::class, $request);
        $this->app->instance('request', $request);

        $reflection = new ReflectionMethod($controller, $method);
        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }
            $class = $type->getName();
            if ($class !== Request::class && is_subclass_of($class, Request::class)) {
                /** @var Request $formRequest */
                $formRequest = $class::createFromBase($request);
                $this->app->instance($class, $formRequest);
            }
        }

        return $this->app->call([$controller, $method], $routeParameters);
    }
}
