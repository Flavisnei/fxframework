<?php

declare(strict_types=1);

namespace Fx\Framework\Tests;

use Fx\Framework\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testItCreatesHtmlResponse(): void
    {
        $response = Response::html('<h1>OK</h1>', 201);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('<h1>OK</h1>', $response->getContent());
        self::assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function testItCreatesJsonResponse(): void
    {
        $response = Response::json(['status' => 'ok'], 202);

        self::assertSame(202, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString('{"status":"ok"}', (string) $response->getContent());
    }
}
