<?php

declare(strict_types=1);

namespace Fx\Framework\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class Response
{
    public static function html(string $content, int $status = 200): SymfonyResponse
    {
        return new SymfonyResponse($content, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(array $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status);
    }
}
