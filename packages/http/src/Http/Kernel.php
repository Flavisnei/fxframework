<?php

declare(strict_types=1);

namespace Fx\Framework\Http;

use Fx\Framework\Routing\Router;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

final class Kernel
{
    public function __construct(private readonly Router $router, private readonly ExceptionHandler $exceptions)
    {
    }

    public function handle(?Request $request = null): SymfonyResponse
    {
        $request ??= Request::capture();
        try {
            $result = $this->router->dispatch($request);
            return match (true) {
                $result instanceof SymfonyResponse => $result,
                is_array($result) => new JsonResponse($result),
                $result === null => new SymfonyResponse('', 204),
                default => new SymfonyResponse((string) $result),
            };
        } catch (Throwable $exception) {
            return $this->exceptions->render($exception, $request);
        }
    }
}
