<?php

declare(strict_types=1);

namespace Fx\Framework\Http;

use Fx\Framework\Auth\AuthorizationException;
use Fx\Framework\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ExceptionHandler
{
    public function __construct(private readonly bool $debug = false)
    {
    }

    public function render(Throwable $exception, ?Request $request = null): Response
    {
        $status = match (true) {
            $exception instanceof ValidationException => 422,
            $exception instanceof AuthorizationException => 403,
            $exception instanceof ModelNotFoundException => 404,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };
        $message = $exception->getMessage();
        if (!$this->debug && ($status >= 500 || $exception instanceof ModelNotFoundException)) {
            $message = Response::$statusTexts[$status] ?? 'Internal Server Error';
        }
        if ($message === '') { $message = Response::$statusTexts[$status] ?? 'Error'; }
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];

        if ($request?->expectsJson() ?? false) {
            $data = ['message' => $message];
            if ($exception instanceof ValidationException) {
                $data['errors'] = $exception->errors();
            }
            if ($this->debug) {
                $data['exception'] = $exception::class;
            }
            return new JsonResponse($data, $status, $headers);
        }

        return new Response(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $status, $headers);
    }
}
